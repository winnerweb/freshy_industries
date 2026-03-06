<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function newsletterEnsureTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_newsletter_subscribers_email (email),
            KEY idx_newsletter_subscribers_status (status),
            KEY idx_newsletter_subscribers_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_campaigns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            subject VARCHAR(200) NOT NULL,
            content_html MEDIUMTEXT NOT NULL,
            cta_text VARCHAR(120) NULL,
            cta_url VARCHAR(500) NULL,
            image_url VARCHAR(500) NULL,
            status ENUM('draft','sending','sent','failed') NOT NULL DEFAULT 'draft',
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_newsletter_campaigns_status (status),
            KEY idx_newsletter_campaigns_created_at (created_at),
            CONSTRAINT fk_newsletter_campaigns_admin_user
                FOREIGN KEY (created_by) REFERENCES admin_users(id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS newsletter_campaign_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            subscriber_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            status ENUM('sent','failed') NOT NULL,
            error_message VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_newsletter_campaign_log_once (campaign_id, subscriber_id),
            KEY idx_newsletter_campaign_logs_campaign (campaign_id),
            KEY idx_newsletter_campaign_logs_status (status),
            CONSTRAINT fk_newsletter_campaign_logs_campaign
                FOREIGN KEY (campaign_id) REFERENCES newsletter_campaigns(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_newsletter_campaign_logs_subscriber
                FOREIGN KEY (subscriber_id) REFERENCES newsletter_subscribers(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function newsletterNormalizeEmail(string $email): string
{
    return mb_strtolower(trim($email));
}

function newsletterUnsubscribeSecret(): string
{
    $secret = getenv('NEWSLETTER_UNSUBSCRIBE_SECRET')
        ?: getenv('APP_KEY')
        ?: getenv('SMTP_PASS')
        ?: 'local-newsletter-secret';
    return hash('sha256', (string) $secret);
}

function newsletterUnsubscribeToken(string $email): string
{
    $normalized = newsletterNormalizeEmail($email);
    return hash_hmac('sha256', $normalized, newsletterUnsubscribeSecret());
}

function newsletterTokenIsValid(string $email, string $token): bool
{
    if ($token === '') {
        return false;
    }
    $expected = newsletterUnsubscribeToken($email);
    return hash_equals($expected, $token);
}

function newsletterSiteBaseUrl(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $https = (string) ($_SERVER['HTTPS'] ?? '');
    $scheme = ($https !== '' && strtolower($https) !== 'off') ? 'https' : 'http';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    $candidates = ['/Admin/api/', '/api/', '/handlers/', '/includes/'];
    $basePath = '';
    foreach ($candidates as $needle) {
        $pos = strpos($script, $needle);
        if ($pos !== false) {
            $basePath = substr($script, 0, $pos);
            break;
        }
    }

    return rtrim($scheme . '://' . $host . rtrim((string) $basePath, '/'), '/');
}

