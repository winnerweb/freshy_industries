<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

try {
    requireAdminApi(['manager', 'admin']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    if (!isset($_FILES['media']) || !is_array($_FILES['media'])) {
        jsonResponse(['error' => 'Fichier media manquant'], 422);
    }

    $file = $_FILES['media'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Upload media echoue'], 422);
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        jsonResponse(['error' => 'Source upload invalide'], 422);
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        jsonResponse(['error' => 'Fichier vide'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $imageAllowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $videoAllowed = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
    ];

    $mediaType = '';
    $ext = '';
    if (isset($imageAllowed[$mime])) {
        $mediaType = 'image';
        $ext = $imageAllowed[$mime];
        if ($size > 5 * 1024 * 1024) {
            jsonResponse(['error' => 'Image trop lourde (max 5MB)'], 422);
        }
    } elseif (isset($videoAllowed[$mime])) {
        $mediaType = 'video';
        $ext = $videoAllowed[$mime];
        if ($size > 30 * 1024 * 1024) {
            jsonResponse(['error' => 'Video trop lourde (max 30MB)'], 422);
        }
    } else {
        jsonResponse(['error' => 'Type media non supporte'], 422);
    }

    $uploadDir = dirname(__DIR__, 2) . '/uploads/articles';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        jsonResponse(['error' => 'Creation dossier upload impossible'], 500);
    }

    $fileId = date('YmdHis') . random_int(1000, 9999);
    $filename = 'article_' . $fileId . '.' . $ext;
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $target)) {
        jsonResponse(['error' => 'Enregistrement media impossible'], 500);
    }
    @chmod($target, 0644);

    $publicUrl = 'uploads/articles/' . $filename;
    jsonResponse([
        'data' => [
            'media_type' => $mediaType,
            'media_url' => $publicUrl,
            'mime' => $mime,
            'size' => $size,
        ],
    ], 201);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}

