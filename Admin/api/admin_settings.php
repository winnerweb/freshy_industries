<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/controllers/SiteSettingsController.php';
require_once dirname(__DIR__) . '/controllers/SecurityController.php';
require_once dirname(__DIR__) . '/controllers/ProfileController.php';
require_once dirname(__DIR__) . '/services/SiteSettingsService.php';
require_once dirname(__DIR__) . '/services/PasswordService.php';
require_once dirname(__DIR__) . '/services/ProfileService.php';
require_once dirname(__DIR__) . '/repositories/SiteSettingsRepository.php';
require_once dirname(__DIR__) . '/repositories/AdminRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validators/PasswordValidator.php';
require_once dirname(__DIR__) . '/validators/FileUploadValidator.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $admin = requireAdminApi(['admin']);
    $pdo = db();

    $siteSettingsRepository = new SiteSettingsRepository($pdo);
    $adminRepository = new AdminRepository($pdo);
    $auditRepository = new AuditLogRepository($pdo);

    $siteSettingsService = new SiteSettingsService($pdo, $siteSettingsRepository, $auditRepository);
    $passwordService = new PasswordService($pdo, $adminRepository, $auditRepository, new PasswordValidator());
    $profileService = new ProfileService($pdo, $adminRepository, $auditRepository, new FileUploadValidator());

    $siteSettingsController = new SiteSettingsController($siteSettingsService);
    $securityController = new SecurityController($passwordService);
    $profileController = new ProfileController($profileService);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $adminId = (int) ($admin['id'] ?? 0);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    if ($method === 'GET') {
        jsonResponse([
            'data' => [
                'site_settings' => $siteSettingsController->get(),
                'profile' => $profileController->get($adminId),
            ],
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    $isMultipart = str_contains($contentType, 'multipart/form-data');

    $payload = $isMultipart ? $_POST : readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));

    // Legacy action aliases to keep older integrations compatible.
    if ($action === 'save_general' || $action === 'save_system') {
        $action = 'save_site_settings';
    } elseif ($action === 'save_profile') {
        $action = $isMultipart ? 'save_profile' : 'change_password_legacy';
    }

    if ($action === 'save_site_settings') {
        $siteSettingsController->save($payload, $adminId, $ip, $userAgent);
        jsonResponse(['data' => ['saved' => true]]);
    }

    if ($action === 'save_profile') {
        $saved = $profileController->save($adminId, $payload, $_FILES, $ip, $userAgent);
        jsonResponse(['data' => ['saved' => true, 'profile' => $saved]]);
    }

    if ($action === 'change_password' || $action === 'change_password_legacy') {
        if ($action === 'change_password_legacy') {
            $payload = [
                'current_password' => (string) ($payload['admin_password_current'] ?? ''),
                'new_password' => (string) ($payload['admin_password'] ?? ''),
                'confirm_password' => (string) ($payload['admin_password'] ?? ''),
            ];
        }
        $securityController->changePassword($payload, $adminId, $ip, $userAgent);
        jsonResponse(['data' => ['saved' => true]]);
    }

    jsonResponse(['error' => 'Unsupported action'], 400);
} catch (InvalidArgumentException $e) {
    jsonResponse(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
