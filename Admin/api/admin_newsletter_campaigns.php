<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/newsletter.php';

function newsletterCleanHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $html = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html) ?? '';
    $html = preg_replace('#<style\b[^>]*>(.*?)</style>#is', '', $html) ?? '';
    $html = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html) ?? '';
    $html = preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $html) ?? '';
    $html = preg_replace('/javascript\s*:/i', '', $html) ?? '';

    $allowed = '<p><br><strong><b><em><i><u><h1><h2><h3><h4><ul><ol><li><a><img><table><tbody><thead><tr><td><th><blockquote><hr><span><div>';
    return strip_tags($html, $allowed);
}

function newsletterBuildEmailHtml(array $campaign, string $email): string
{
    $subject = htmlspecialchars((string) ($campaign['subject'] ?? ''), ENT_QUOTES, 'UTF-8');
    $content = (string) ($campaign['content_html'] ?? '');
    $ctaText = trim((string) ($campaign['cta_text'] ?? ''));
    $ctaUrl = trim((string) ($campaign['cta_url'] ?? ''));
    $imageUrl = trim((string) ($campaign['image_url'] ?? ''));

    $unsubscribeUrl = newsletterSiteBaseUrl() . '/unsubscribe.php?email='
        . rawurlencode($email)
        . '&token='
        . rawurlencode(newsletterUnsubscribeToken($email));

    $ctaHtml = '';
    if ($ctaText !== '' && filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
        $safeText = htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $ctaHtml = '<p style="margin:20px 0;"><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#c24d98;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">' . $safeText . '</a></p>';
    }

    $imageHtml = '';
    if ($imageUrl !== '' && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $safeImg = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');
        $imageHtml = '<p style="margin:0 0 16px;"><img src="' . $safeImg . '" alt="" style="max-width:100%;height:auto;border-radius:10px;display:block;"></p>';
    }

    return '
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>' . $subject . '</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:Poppins,sans-serif;color:#0f172a;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:20px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;">
          <tr>
            <td style="padding:22px 24px;background:linear-gradient(120deg,#6b2f8a,#c24d98);color:#fff;">
              <h1 style="margin:0;font-size:22px;line-height:1.2;">Freshy Newsletter</h1>
              <p style="margin:6px 0 0;font-size:14px;opacity:.92;">' . $subject . '</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;">
              ' . $imageHtml . '
              <div style="font-size:15px;line-height:1.6;color:#1e293b;">' . $content . '</div>
              ' . $ctaHtml . '
            </td>
          </tr>
          <tr>
            <td style="padding:14px 24px;background:#f1f5f9;border-top:1px solid #e2e8f0;font-size:12px;color:#475569;">
              Vous recevez cet email car vous etes abonne a notre newsletter.
              <a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#6b2f8a;text-decoration:underline;">Se desinscrire</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
}

function newsletterCreateMailer(array $smtp): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = (string) $smtp['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $smtp['username'];
    $mail->Password = (string) $smtp['password'];
    $mail->Port = (int) $smtp['port'];
    $mail->Timeout = (int) ($smtp['timeout'] ?? 10);
    $mail->SMTPSecure = strtolower((string) ($smtp['encryption'] ?? 'tls')) === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);
    $mail->isHTML(true);
    return $mail;
}

function newsletterResolveValidAdminId(PDO $pdo, array $user): ?int
{
    $candidate = (int) ($user['id'] ?? 0);
    if ($candidate <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $candidate]);
    $exists = (int) ($stmt->fetchColumn() ?: 0);
    return $exists > 0 ? $exists : null;
}

try {
    $user = requireAdminApi(['manager', 'admin']);
    $pdo = db();
    newsletterEnsureTables($pdo);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $campaigns = $pdo->query(
            'SELECT c.id, c.subject, c.status, c.created_at, c.sent_at,
                    COALESCE(ls.sent_count, 0) AS sent_count,
                    COALESCE(ls.failed_count, 0) AS failed_count
             FROM newsletter_campaigns c
             LEFT JOIN (
                SELECT campaign_id,
                       SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) AS sent_count,
                       SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count
                FROM newsletter_campaign_logs
                GROUP BY campaign_id
             ) ls ON ls.campaign_id = c.id
             ORDER BY c.created_at DESC, c.id DESC
             LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['data' => $campaigns]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'create') {
        $subject = trim((string) ($payload['subject'] ?? ''));
        $contentHtml = newsletterCleanHtml((string) ($payload['content_html'] ?? ''));
        $ctaText = trim((string) ($payload['cta_text'] ?? ''));
        $ctaUrl = trim((string) ($payload['cta_url'] ?? ''));
        $imageUrl = trim((string) ($payload['image_url'] ?? ''));

        if ($subject === '' || strlen($subject) > 200) {
            jsonResponse(['error' => 'Sujet invalide.'], 422);
        }
        if ($contentHtml === '' || strlen($contentHtml) > 50000) {
            jsonResponse(['error' => 'Contenu invalide.'], 422);
        }
        if ($ctaText !== '' && strlen($ctaText) > 120) {
            jsonResponse(['error' => 'Texte CTA trop long.'], 422);
        }
        if ($ctaUrl !== '' && !filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'Lien CTA invalide.'], 422);
        }
        if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            jsonResponse(['error' => 'URL image invalide.'], 422);
        }

        $createdBy = newsletterResolveValidAdminId($pdo, $user);
        $stmt = $pdo->prepare(
            "INSERT INTO newsletter_campaigns
              (subject, content_html, cta_text, cta_url, image_url, status, created_by, created_at)
             VALUES
              (:subject, :content_html, :cta_text, :cta_url, :image_url, 'draft', :created_by, NOW())"
        );
        $stmt->execute([
            ':subject' => $subject,
            ':content_html' => $contentHtml,
            ':cta_text' => ($ctaText !== '' ? $ctaText : null),
            ':cta_url' => ($ctaUrl !== '' ? $ctaUrl : null),
            ':image_url' => ($imageUrl !== '' ? $imageUrl : null),
            ':created_by' => $createdBy,
        ]);
        $campaignId = (int) $pdo->lastInsertId();
        jsonResponse([
            'data' => [
                'campaign_id' => $campaignId,
                'campaign' => [
                    'id' => $campaignId,
                    'subject' => $subject,
                    'status' => 'draft',
                    'sent_count' => 0,
                    'failed_count' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'sent_at' => null,
                ],
            ],
        ], 201);
    }

    if ($action === 'send') {
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            jsonResponse(['error' => 'Dependances email indisponibles (vendor/autoload.php manquant).'], 500);
        }
        require_once $autoloadPath;
        require_once dirname(__DIR__, 2) . '/config/smtp.php';

        $campaignId = (int) ($payload['campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            jsonResponse(['error' => 'Campagne invalide.'], 422);
        }

        $campaignStmt = $pdo->prepare(
            'SELECT id, subject, content_html, cta_text, cta_url, image_url, status
             FROM newsletter_campaigns
             WHERE id = :id
             LIMIT 1'
        );
        $campaignStmt->execute([':id' => $campaignId]);
        $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
        if (!$campaign) {
            jsonResponse(['error' => 'Campagne introuvable.'], 404);
        }
        $status = (string) ($campaign['status'] ?? 'draft');
        if ($status === 'sending') {
            jsonResponse(['error' => 'Campagne deja en cours d envoi.'], 409);
        }
        if ($status === 'sent') {
            jsonResponse(['error' => 'Campagne deja envoyee.'], 409);
        }

        $smtp = smtpConfig();
        if (!smtpConfigIsReady($smtp)) {
            jsonResponse(['error' => 'Configuration SMTP incomplete.'], 422);
        }

        $pdo->prepare("UPDATE newsletter_campaigns SET status = 'sending' WHERE id = :id")->execute([':id' => $campaignId]);

        $subscribers = $pdo->query(
            "SELECT id, email
             FROM newsletter_subscribers
             WHERE status = 'active'
             ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$subscribers) {
            $pdo->prepare("UPDATE newsletter_campaigns SET status = 'failed' WHERE id = :id")->execute([':id' => $campaignId]);
            jsonResponse(['error' => 'Aucun abonne actif.'], 422);
        }

        $insertLog = $pdo->prepare(
            "INSERT INTO newsletter_campaign_logs
               (campaign_id, subscriber_id, email, status, error_message, created_at)
             VALUES
               (:campaign_id, :subscriber_id, :email, :status, :error_message, NOW())
             ON DUPLICATE KEY UPDATE
               status = VALUES(status),
               error_message = VALUES(error_message),
               created_at = NOW()"
        );

        $mail = newsletterCreateMailer($smtp);
        $sentCount = 0;
        $failedCount = 0;
        foreach ($subscribers as $subscriber) {
            $subscriberId = (int) ($subscriber['id'] ?? 0);
            $email = newsletterNormalizeEmail((string) ($subscriber['email'] ?? ''));
            if ($subscriberId <= 0 || $email === '') {
                continue;
            }

            try {
                $mail->clearAllRecipients();
                $mail->clearReplyTos();
                $mail->addAddress($email);
                $mail->Subject = (string) $campaign['subject'];
                $mail->Body = newsletterBuildEmailHtml($campaign, $email);
                $mail->AltBody = trim(strip_tags((string) $campaign['content_html']));
                $mail->send();

                $insertLog->execute([
                    ':campaign_id' => $campaignId,
                    ':subscriber_id' => $subscriberId,
                    ':email' => $email,
                    ':status' => 'sent',
                    ':error_message' => null,
                ]);
                $sentCount++;
            } catch (MailException|Throwable $e) {
                $insertLog->execute([
                    ':campaign_id' => $campaignId,
                    ':subscriber_id' => $subscriberId,
                    ':email' => $email,
                    ':status' => 'failed',
                    ':error_message' => substr($e->getMessage(), 0, 250),
                ]);
                $failedCount++;
            }
        }

        $finalStatus = $sentCount > 0 ? 'sent' : 'failed';
        $pdo->prepare(
            "UPDATE newsletter_campaigns
             SET status = :status, sent_at = NOW()
             WHERE id = :id"
        )->execute([
            ':status' => $finalStatus,
            ':id' => $campaignId,
        ]);

        jsonResponse([
            'data' => [
                'campaign_id' => $campaignId,
                'status' => $finalStatus,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
            ],
        ]);
    }

    jsonResponse(['error' => 'Action non supportee'], 400);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
