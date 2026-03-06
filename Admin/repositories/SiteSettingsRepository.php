<?php
declare(strict_types=1);

final class SiteSettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS site_settings (
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function getGlobalSettings(): array
    {
        $defaults = [
            'site_name' => 'Freshy Industries',
            'site_url' => '',
            'site_description' => '',
            'support_email' => 'support@freshy.local',
            'payment_mode' => 'simulateur',
            'timezone' => 'Africa/Porto-Novo',
            'media_storage_driver' => 'local',
            'cloudinary_cloud_name' => '',
            'cloudinary_upload_preset' => '',
            'cloudinary_folder' => 'freshy/products',
        ];

        $stmt = $this->pdo->query('SELECT setting_key, setting_value FROM site_settings');
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $key = (string) ($row['setting_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $defaults[$key] = (string) ($row['setting_value'] ?? '');
        }

        // Backward compatibility fallback.
        $legacyStmt = $this->pdo->query(
            "SELECT setting_key, setting_value
             FROM app_settings
             WHERE setting_key IN (
               'shop_name','support_email','payment_mode','timezone',
               'media_storage_driver','cloudinary_cloud_name','cloudinary_upload_preset','cloudinary_folder'
             )"
        );
        $legacyRows = $legacyStmt->fetchAll();
        foreach ($legacyRows as $row) {
            $legacyKey = (string) ($row['setting_key'] ?? '');
            $value = (string) ($row['setting_value'] ?? '');
            if ($legacyKey === 'shop_name' && ($defaults['site_name'] ?? '') === 'Freshy Industries') {
                $defaults['site_name'] = $value;
                continue;
            }
            if ($legacyKey !== '' && ($defaults[$legacyKey] ?? '') === '') {
                $defaults[$legacyKey] = $value;
            }
        }

        return $defaults;
    }

    public function upsertSettings(array $settings): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO site_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($settings as $k => $v) {
            $stmt->execute([
                ':k' => (string) $k,
                ':v' => (string) $v,
            ]);
        }
    }

    public function syncLegacyAppSettings(array $settings): void
    {
        $legacyMap = [
            'site_name' => 'shop_name',
            'support_email' => 'support_email',
            'payment_mode' => 'payment_mode',
            'timezone' => 'timezone',
            'media_storage_driver' => 'media_storage_driver',
            'cloudinary_cloud_name' => 'cloudinary_cloud_name',
            'cloudinary_upload_preset' => 'cloudinary_upload_preset',
            'cloudinary_folder' => 'cloudinary_folder',
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO app_settings (setting_key, setting_value)
             VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
        );
        foreach ($legacyMap as $newKey => $legacyKey) {
            if (!array_key_exists($newKey, $settings)) {
                continue;
            }
            $stmt->execute([
                ':k' => $legacyKey,
                ':v' => (string) $settings[$newKey],
            ]);
        }
    }
}
