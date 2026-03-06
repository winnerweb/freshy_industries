<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/smtp.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/validation.php';

header('Content-Type: application/json; charset=utf-8');

function contactJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureContactMessagesTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            subject VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_contact_messages_email (email),
            KEY idx_contact_messages_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function enforceContactRateLimit(PDO $pdo, string $ip, string $email): void
{
    $stmtIp = $pdo->prepare(
        'SELECT COUNT(*) FROM contact_messages
         WHERE ip_address = :ip
           AND created_at >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $stmtIp->execute([':ip' => $ip]);
    $ipCount = (int) $stmtIp->fetchColumn();

    $stmtEmail = $pdo->prepare(
        'SELECT COUNT(*) FROM contact_messages
         WHERE email = :email
           AND created_at >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $stmtEmail->execute([':email' => $email]);
    $emailCount = (int) $stmtEmail->fetchColumn();

    if ($ipCount >= 5 || $emailCount >= 3) {
        throw new RuntimeException('Trop de tentatives. Réessayez dans quelques minutes.');
    }
}

function sendContactEmail(array $data, string $ip, string $userAgent): void
{
    $smtp = smtpConfig();
    if (!smtpConfigIsReady($smtp)) {
        throw new RuntimeException('Configuration SMTP incomplète.');
    }

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = (string) $smtp['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $smtp['username'];
    $mail->Password = (string) $smtp['password'];
    $mail->Port = (int) $smtp['port'];
    $mail->Timeout = (int) $smtp['timeout'];

    $enc = strtolower((string) $smtp['encryption']);
    $mail->SMTPSecure = $enc === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);
    $mail->addAddress((string) $smtp['to_email'], 'Support Freshy');
    $mail->addReplyTo($data['email'], $data['name']);
    $mail->Subject = '[Contact] ' . $data['subject'];
    $mail->isHTML(true);

    $safeName = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($data['email'], ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($data['subject'], ENT_QUOTES, 'UTF-8');
    $safeMsg = nl2br(htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8'));
    $safeWhatsapp = htmlspecialchars((string) ($data['whatsapp_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    $safeIp = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    $safeUa = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
    $safeDate = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $mail->Body = "
        <h2>Nouveau message de contact</h2>
        <p><strong>Nom:</strong> {$safeName}</p>
        <p><strong>Email:</strong> {$safeEmail}</p>
        <p><strong>WhatsApp:</strong> {$safeWhatsapp}</p>
        <p><strong>Sujet:</strong> {$safeSubject}</p>
        <p><strong>Message:</strong><br>{$safeMsg}</p>
        <hr>
        <p><strong>Date:</strong> {$safeDate}</p>
        <p><strong>IP:</strong> {$safeIp}</p>
        <p><strong>User-Agent:</strong> {$safeUa}</p>
    ";

    $mail->AltBody =
        "Nouveau message de contact\n" .
        "Nom: {$data['name']}\n" .
        "Email: {$data['email']}\n" .
        "WhatsApp: " . ($data['whatsapp_number'] ?? '') . "\n" .
        "Sujet: {$data['subject']}\n" .
        "Message:\n{$data['message']}\n" .
        "Date: {$safeDate}\n" .
        "IP: {$ip}\n" .
        "User-Agent: {$userAgent}\n";

    $mail->send();
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        contactJson(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
    }

    $validation = validateContactPayload($_POST);
    $data = $validation['data'];
    $errors = $validation['errors'];
    if ($errors !== []) {
        contactJson([
            'success' => false,
            'message' => 'Veuillez corriger les champs du formulaire.',
            'errors' => $errors,
        ], 422);
    }

    if (!csrfValidate($data['csrf_token'])) {
        contactJson(['success' => false, 'message' => 'Jeton CSRF invalide.'], 419);
    }

    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);

    $pdo = appDb();
    ensureContactMessagesTable($pdo);
    enforceContactRateLimit($pdo, $ip, $data['email']);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent)
         VALUES (:name, :email, :subject, :message, :ip_address, :user_agent)'
    );
    $stmt->execute([
        ':name' => $data['name'],
        ':email' => $data['email'],
        ':subject' => $data['subject'],
        ':message' => $data['message'] . ($data['whatsapp_number'] !== '' ? "\n\nWhatsApp: " . $data['whatsapp_number'] : ''),
        ':ip_address' => $ip,
        ':user_agent' => $userAgent,
    ]);

    try {
        sendContactEmail($data, $ip, $userAgent);
    } catch (MailException|RuntimeException $e) {
        $pdo->rollBack();
        contactJson([
            'success' => false,
            'message' => 'Envoi email impossible. Veuillez réessayer.',
            'error_code' => 'email_send_failed',
        ], 502);
    }

    $pdo->commit();
    contactJson([
        'success' => true,
        'message' => 'Votre message a été envoyé avec succès.',
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    contactJson([
        'success' => false,
        'message' => 'Erreur serveur. Veuillez réessayer plus tard.',
    ], 500);
}

