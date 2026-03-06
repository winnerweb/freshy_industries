<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repositories/SiteSettingsRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';

final class SiteSettingsService
{
    public function __construct(
        private PDO $pdo,
        private SiteSettingsRepository $settingsRepository,
        private AuditLogRepository $auditRepository
    ) {
    }

    public function getData(): array
    {
        $this->settingsRepository->ensureTables();
        return $this->settingsRepository->getGlobalSettings();
    }

    public function save(array $payload, int $actorAdminId, string $ip, string $userAgent): void
    {
        $existing = $this->getData();

        $siteName = trim((string) ($payload['site_name'] ?? ''));
        $siteUrl = trim((string) ($payload['site_url'] ?? ''));
        $siteDescription = trim((string) ($payload['site_description'] ?? ''));
        $supportEmail = trim((string) ($payload['support_email'] ?? ''));
        $paymentMode = trim((string) ($payload['payment_mode'] ?? ($existing['payment_mode'] ?? 'simulateur')));
        $timezone = trim((string) ($payload['timezone'] ?? ($existing['timezone'] ?? 'Africa/Porto-Novo')));
        $driver = strtolower(trim((string) ($payload['media_storage_driver'] ?? ($existing['media_storage_driver'] ?? 'local'))));
        $cloudName = trim((string) ($payload['cloudinary_cloud_name'] ?? ($existing['cloudinary_cloud_name'] ?? '')));
        $uploadPreset = trim((string) ($payload['cloudinary_upload_preset'] ?? ($existing['cloudinary_upload_preset'] ?? '')));
        $folder = trim((string) ($payload['cloudinary_folder'] ?? ($existing['cloudinary_folder'] ?? 'freshy/products')));

        if ($siteName === '' || mb_strlen($siteName) > 180) {
            throw new InvalidArgumentException('Nom de site invalide.');
        }
        if ($siteUrl !== '' && !filter_var($siteUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL du site invalide.');
        }
        if ($supportEmail === '' || !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email support invalide.');
        }
        if (!in_array($paymentMode, ['simulateur', 'psp_reel'], true)) {
            throw new InvalidArgumentException('Mode de paiement invalide.');
        }
        if ($timezone === '') {
            throw new InvalidArgumentException('Fuseau horaire invalide.');
        }
        if (!in_array($driver, ['local', 'cloudinary'], true)) {
            throw new InvalidArgumentException('Driver media invalide.');
        }
        if ($driver === 'cloudinary' && ($cloudName === '' || $uploadPreset === '')) {
            throw new InvalidArgumentException('Configuration Cloudinary incomplete.');
        }
        if ($folder === '') {
            $folder = 'freshy/products';
        }

        $this->settingsRepository->ensureTables();
        $this->auditRepository->ensureTable();

        $before = $existing;
        $after = [
            'site_name' => $siteName,
            'site_url' => $siteUrl,
            'site_description' => $siteDescription,
            'support_email' => mb_strtolower($supportEmail),
            'payment_mode' => $paymentMode,
            'timezone' => $timezone,
            'media_storage_driver' => $driver,
            'cloudinary_cloud_name' => $cloudName,
            'cloudinary_upload_preset' => $uploadPreset,
            'cloudinary_folder' => $folder,
        ];

        $this->pdo->beginTransaction();
        try {
            $this->settingsRepository->upsertSettings($after);
            $this->settingsRepository->syncLegacyAppSettings($after);
            $this->auditRepository->log(
                $actorAdminId,
                'settings.site.updated',
                'site_settings',
                'global',
                $before,
                $after,
                $ip,
                $userAgent
            );
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
