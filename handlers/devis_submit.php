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

function quoteJson(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ensureQuotesTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS quotes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name VARCHAR(160) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_quotes_email (email),
            KEY idx_quotes_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS quote_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            quantity INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_quote_items_quote_product (quote_id, product_id),
            KEY idx_quote_items_product (product_id),
            CONSTRAINT fk_quote_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
            CONSTRAINT fk_quote_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function enforceQuoteRateLimit(PDO $pdo, string $ip, string $email): void
{
    $stmtIp = $pdo->prepare(
        'SELECT COUNT(*) FROM quotes
         WHERE ip_address = :ip
           AND created_at >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $stmtIp->execute([':ip' => $ip]);
    $ipCount = (int) $stmtIp->fetchColumn();

    $stmtEmail = $pdo->prepare(
        'SELECT COUNT(*) FROM quotes
         WHERE email = :email
           AND created_at >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $stmtEmail->execute([':email' => $email]);
    $emailCount = (int) $stmtEmail->fetchColumn();

    if ($ipCount >= 5 || $emailCount >= 3) {
        throw new RuntimeException('Trop de tentatives. Reessayez dans quelques minutes.');
    }
}

function fetchActiveProductsByIds(PDO $pdo, array $productIds): array
{
    if ($productIds === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, name
         FROM products
         WHERE status = 'active' AND id IN ($placeholders)"
    );
    $stmt->execute($productIds);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $map[$id] = (string) ($row['name'] ?? '');
        }
    }
    return $map;
}

function sendQuoteEmail(array $quoteData, array $items, string $ip, string $userAgent): void
{
    $smtp = smtpConfig();
    if (!smtpConfigIsReady($smtp)) {
        throw new RuntimeException('Configuration SMTP incomplete.');
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
    $mail->SMTPSecure = strtolower((string) $smtp['encryption']) === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);
    $mail->addAddress((string) $smtp['to_email'], 'Support Freshy');
    $mail->addReplyTo($quoteData['email'], $quoteData['customer_name']);
    $mail->Subject = '[Devis] Nouvelle demande';
    $mail->isHTML(true);

    $itemsHtml = '';
    foreach ($items as $row) {
        $name = htmlspecialchars((string) ($row['product_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $qty = (int) ($row['quantity'] ?? 0);
        $itemsHtml .= "<li><strong>{$name}</strong> - Quantite: {$qty}</li>";
    }

    $safeName = htmlspecialchars($quoteData['customer_name'], ENT_QUOTES, 'UTF-8');
    $safePhone = htmlspecialchars($quoteData['phone'], ENT_QUOTES, 'UTF-8');
    $safeEmail = htmlspecialchars($quoteData['email'], ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($quoteData['message'], ENT_QUOTES, 'UTF-8'));
    $safeIp = htmlspecialchars($ip, ENT_QUOTES, 'UTF-8');
    $safeUa = htmlspecialchars($userAgent, ENT_QUOTES, 'UTF-8');
    $safeDate = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

    $mail->Body = "
        <h2>Nouvelle demande de devis</h2>
        <p><strong>Client:</strong> {$safeName}</p>
        <p><strong>Telephone:</strong> {$safePhone}</p>
        <p><strong>Email:</strong> {$safeEmail}</p>
        <p><strong>Adresse / Message:</strong><br>{$safeMessage}</p>
        <h3>Produits demandes</h3>
        <ul>{$itemsHtml}</ul>
        <hr>
        <p><strong>Date:</strong> {$safeDate}</p>
        <p><strong>IP:</strong> {$safeIp}</p>
        <p><strong>User-Agent:</strong> {$safeUa}</p>
    ";

    $mail->AltBody =
        "Nouvelle demande de devis\n" .
        "Client: {$quoteData['customer_name']}\n" .
        "Telephone: {$quoteData['phone']}\n" .
        "Email: {$quoteData['email']}\n" .
        "Adresse / Message: {$quoteData['message']}\n";

    $mail->send();
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        quoteJson(['success' => false, 'message' => 'Methode non autorisee.'], 405);
    }

    $validation = validateQuotePayload($_POST);
    $data = $validation['data'];
    $errors = $validation['errors'];
    if ($errors !== []) {
        quoteJson([
            'success' => false,
            'message' => 'Veuillez corriger les champs du formulaire.',
            'errors' => $errors,
        ], 422);
    }

    if (!csrfValidate((string) $data['csrf_token'])) {
        quoteJson(['success' => false, 'message' => 'Jeton CSRF invalide.'], 419);
    }

    $pdo = appDb();
    ensureQuotesTables($pdo);

    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 255);
    enforceQuoteRateLimit($pdo, $ip, (string) $data['email']);

    $products = $data['products'];
    $productIds = array_map(static fn (array $row): int => (int) $row['product_id'], $products);
    $activeProducts = fetchActiveProductsByIds($pdo, $productIds);
    foreach ($productIds as $productId) {
        if (!isset($activeProducts[$productId])) {
            quoteJson([
                'success' => false,
                'message' => 'Un produit selectionne est indisponible.',
            ], 422);
        }
    }

    $pdo->beginTransaction();

    $quoteStmt = $pdo->prepare(
        'INSERT INTO quotes (customer_name, phone, email, message, ip_address, user_agent)
         VALUES (:customer_name, :phone, :email, :message, :ip_address, :user_agent)'
    );
    $quoteStmt->execute([
        ':customer_name' => $data['customer_name'],
        ':phone' => $data['phone'],
        ':email' => $data['email'],
        ':message' => $data['message'],
        ':ip_address' => $ip,
        ':user_agent' => $userAgent,
    ]);
    $quoteId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO quote_items (quote_id, product_id, quantity)
         VALUES (:quote_id, :product_id, :quantity)'
    );
    $emailItems = [];
    foreach ($products as $row) {
        $productId = (int) $row['product_id'];
        $quantity = (int) $row['quantity'];
        $itemStmt->execute([
            ':quote_id' => $quoteId,
            ':product_id' => $productId,
            ':quantity' => $quantity,
        ]);
        $emailItems[] = [
            'product_name' => $activeProducts[$productId] ?? 'Produit',
            'quantity' => $quantity,
        ];
    }

    try {
        sendQuoteEmail($data, $emailItems, $ip, $userAgent);
    } catch (MailException|RuntimeException $e) {
        $pdo->rollBack();
        quoteJson([
            'success' => false,
            'message' => 'Envoi email impossible. Veuillez reessayer.',
            'error_code' => 'email_send_failed',
        ], 502);
    }

    $pdo->commit();
    quoteJson([
        'success' => true,
        'message' => 'Votre demande de devis a ete envoyee avec succes.',
        'data' => ['quote_id' => $quoteId],
    ], 201);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    quoteJson([
        'success' => false,
        'message' => 'Erreur serveur. Veuillez reessayer plus tard.',
    ], 500);
}

