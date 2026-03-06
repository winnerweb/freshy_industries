<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function adminResolveAuthVideoUrl(string $siteBase = ''): string
{
    $siteBase = rtrim($siteBase, '/');

    $localRelative = '/videos/admin-auth-bg.mp4';
    $localFile = dirname(__DIR__, 2) . str_replace('/', DIRECTORY_SEPARATOR, $localRelative);
    if (is_file($localFile)) {
        return ($siteBase !== '' ? $siteBase : '') . $localRelative;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT setting_value
             FROM app_settings
             WHERE setting_key = :setting_key
             LIMIT 1'
        );
        $stmt->execute([':setting_key' => 'admin_auth_video_url']);
        $row = $stmt->fetch();
        $url = trim((string) ($row['setting_value'] ?? ''));
        if ($url === '') {
            return '';
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        return $url;
    } catch (Throwable $e) {
        return '';
    }
}

