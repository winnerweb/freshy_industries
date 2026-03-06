<?php
declare(strict_types=1);

function smtpResolveSupportEmailFromSettings(): string
{
    static $cached = null;
    if (is_string($cached)) {
        return $cached;
    }

    try {
        if (!function_exists('db')) {
            $cached = '';
            return $cached;
        }
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT setting_value
             FROM site_settings
             WHERE setting_key = :k
             LIMIT 1'
        );
        $stmt->execute([':k' => 'support_email']);
        $value = trim((string) $stmt->fetchColumn());
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $cached = mb_strtolower($value);
            return $cached;
        }

        // Backward compatibility with legacy settings table.
        $legacy = $pdo->prepare(
            'SELECT setting_value
             FROM app_settings
             WHERE setting_key = :k
             LIMIT 1'
        );
        $legacy->execute([':k' => 'support_email']);
        $legacyValue = trim((string) $legacy->fetchColumn());
        if ($legacyValue !== '' && filter_var($legacyValue, FILTER_VALIDATE_EMAIL)) {
            $cached = mb_strtolower($legacyValue);
            return $cached;
        }
    } catch (Throwable $e) {
        // Ignore DB read failure and keep env fallback.
    }

    $cached = '';
    return $cached;
}

function smtpConfig(): array
{
    $supportEmail = smtpResolveSupportEmailFromSettings();
    if ($supportEmail === '') {
        $supportEmail = getenv('CONTACT_TO_EMAIL') ?: '';
    }

    return [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => (int) (getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASS') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // tls|ssl
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: '',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'Freshy Industries',
        'to_email' => $supportEmail,
        'timeout' => (int) (getenv('SMTP_TIMEOUT') ?: 10),
    ];
}

function smtpConfigIsReady(array $cfg): bool
{
    $required = ['host', 'port', 'username', 'password', 'from_email', 'to_email'];
    foreach ($required as $key) {
        if (!isset($cfg[$key]) || trim((string) $cfg[$key]) === '') {
            return false;
        }
    }
    return true;
}
