<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function mediaStorageSettings(PDO $pdo): array
{
    $keys = [
        'media_storage_driver',
        'cloudinary_cloud_name',
        'cloudinary_upload_preset',
        'cloudinary_folder',
    ];
    $in = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_settings WHERE setting_key IN ($in)");
    $stmt->execute($keys);
    $rows = $stmt->fetchAll();

    $settings = [
        'media_storage_driver' => 'local',
        'cloudinary_cloud_name' => '',
        'cloudinary_upload_preset' => '',
        'cloudinary_folder' => 'freshy/products',
    ];
    foreach ($rows as $row) {
        $k = (string) ($row['setting_key'] ?? '');
        $v = trim((string) ($row['setting_value'] ?? ''));
        if ($k !== '') {
            $settings[$k] = $v;
        }
    }
    $driver = strtolower((string) ($settings['media_storage_driver'] ?? 'local'));
    $settings['media_storage_driver'] = in_array($driver, ['local', 'cloudinary'], true) ? $driver : 'local';
    return $settings;
}

/**
 * @param array<string,string> $variantFiles absolute temp files keyed by variant name (original/large/medium/thumb)
 * @return array{driver:string,versions:array<string,string>}
 */
function mediaStorageSaveVariants(PDO $pdo, string $imageId, array $variantFiles): array
{
    $settings = mediaStorageSettings($pdo);
    if ($settings['media_storage_driver'] === 'cloudinary') {
        try {
            return mediaStorageSaveCloudinary($settings, $imageId, $variantFiles);
        } catch (Throwable $e) {
            // Robust fallback in case cloud credentials/network are invalid.
            return mediaStorageSaveLocal($imageId, $variantFiles);
        }
    }
    return mediaStorageSaveLocal($imageId, $variantFiles);
}

/**
 * @param array<string,string> $variantFiles
 * @return array{driver:string,versions:array<string,string>}
 */
function mediaStorageSaveLocal(string $imageId, array $variantFiles): array
{
    $uploadDir = dirname(__DIR__, 2) . '/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Cannot create upload directory');
    }

    $versions = [];
    foreach ($variantFiles as $variant => $tempPath) {
        $filename = 'product_' . $imageId . '_' . $variant . '.webp';
        $target = $uploadDir . '/' . $filename;
        if (!@rename($tempPath, $target)) {
            if (!@copy($tempPath, $target)) {
                throw new RuntimeException('Cannot move generated image variant');
            }
            @unlink($tempPath);
        }
        @chmod($target, 0644);
        $versions[$variant] = 'uploads/products/' . $filename;
    }

    return [
        'driver' => 'local',
        'versions' => $versions,
    ];
}

/**
 * @param array<string,string> $settings
 * @param array<string,string> $variantFiles
 * @return array{driver:string,versions:array<string,string>}
 */
function mediaStorageSaveCloudinary(array $settings, string $imageId, array $variantFiles): array
{
    $cloudName = trim((string) ($settings['cloudinary_cloud_name'] ?? ''));
    $uploadPreset = trim((string) ($settings['cloudinary_upload_preset'] ?? ''));
    $folder = trim((string) ($settings['cloudinary_folder'] ?? 'freshy/products'));
    if ($cloudName === '' || $uploadPreset === '') {
        throw new RuntimeException('Cloudinary settings missing');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL extension is required for cloud storage');
    }

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/image/upload';
    $versions = [];

    foreach ($variantFiles as $variant => $tempPath) {
        $publicId = $folder . '/product_' . $imageId . '_' . $variant;
        $postFields = [
            'upload_preset' => $uploadPreset,
            'public_id' => $publicId,
            'overwrite' => 'true',
            'resource_type' => 'image',
            'file' => curl_file_create($tempPath, 'image/webp', basename($tempPath)),
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('Cloud upload failed: ' . ($err !== '' ? $err : 'HTTP ' . $code));
        }
        $json = json_decode((string) $raw, true);
        $url = trim((string) ($json['secure_url'] ?? $json['url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException('Cloud upload response missing URL');
        }
        $versions[$variant] = $url;
    }

    // Cleanup temporary files after successful cloud upload.
    foreach ($variantFiles as $tempPath) {
        @unlink($tempPath);
    }

    return [
        'driver' => 'cloudinary',
        'versions' => $versions,
    ];
}

