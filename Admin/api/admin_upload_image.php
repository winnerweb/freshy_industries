<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__) . '/includes/media_storage.php';

function createSourceImageResource(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function saveImageResourceWebp($image, string $path): bool
{
    return function_exists('imagewebp') ? @imagewebp($image, $path, 80) : false;
}

function createCanvas(int $width, int $height, string $mime)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $canvas = imagecreatetruecolor($width, $height);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
    }
    return $canvas;
}

function saveScaledLongestEdge($source, int $srcW, int $srcH, int $maxEdge, string $mime, string $targetPath): bool
{
    $longest = max($srcW, $srcH);
    if ($longest <= $maxEdge) {
        return saveImageResourceWebp($source, $targetPath);
    }

    $ratio = $maxEdge / $longest;
    $newW = max(1, (int) floor($srcW * $ratio));
    $newH = max(1, (int) floor($srcH * $ratio));
    $canvas = createCanvas($newW, $newH, $mime);
    if ($canvas === false) {
        return false;
    }
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
    $ok = saveImageResourceWebp($canvas, $targetPath);
    imagedestroy($canvas);
    return $ok;
}

function saveSquareVariant($source, int $srcW, int $srcH, int $size, string $mime, string $targetPath): bool
{
    $cropSide = min($srcW, $srcH);
    $srcX = (int) floor(($srcW - $cropSide) / 2);
    $srcY = (int) floor(($srcH - $cropSide) / 2);
    $canvas = createCanvas($size, $size, $mime);
    if ($canvas === false) {
        return false;
    }
    imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $size, $size, $cropSide, $cropSide);
    $ok = saveImageResourceWebp($canvas, $targetPath);
    imagedestroy($canvas);
    return $ok;
}

try {
    requireAdminApi(['manager', 'admin']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        jsonResponse(['error' => 'Missing image'], 422);
    }

    $file = $_FILES['image'];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Upload failed'], 422);
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        jsonResponse(['error' => 'Invalid upload source'], 422);
    }

    $maxBytes = 2 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        jsonResponse(['error' => 'Image must be <= 2MB'], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        jsonResponse(['error' => 'Unsupported image type'], 422);
    }
    $imageSize = @getimagesize($tmpPath);
    if (!is_array($imageSize) || empty($imageSize[0]) || empty($imageSize[1])) {
        jsonResponse(['error' => 'Invalid image'], 422);
    }
    $width = (int) $imageSize[0];
    $height = (int) $imageSize[1];
    if ($width < 80 || $height < 80 || $width > 2000 || $height > 2000) {
        jsonResponse(['error' => 'Unsupported image dimensions'], 422);
    }
    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        jsonResponse(['error' => 'WebP conversion unavailable on server'], 500);
    }
    $pdo = db();

    $imageId = date('YmdHis') . random_int(1000, 9999);
    $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'freshy_uploads';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        jsonResponse(['error' => 'Cannot create temp directory'], 500);
    }

    $targetOriginal = $tempDir . DIRECTORY_SEPARATOR . 'product_' . $imageId . '_original.webp';
    $targetLarge = $tempDir . DIRECTORY_SEPARATOR . 'product_' . $imageId . '_large.webp';
    $targetMedium = $tempDir . DIRECTORY_SEPARATOR . 'product_' . $imageId . '_medium.webp';
    $targetThumb = $tempDir . DIRECTORY_SEPARATOR . 'product_' . $imageId . '_thumb.webp';

    $source = createSourceImageResource($tmpPath, $mime);
    if ($source === false) {
        jsonResponse(['error' => 'Cannot read source image'], 422);
    }
    $srcW = imagesx($source);
    $srcH = imagesy($source);
    $okOriginal = saveScaledLongestEdge($source, $srcW, $srcH, 2000, $mime, $targetOriginal);
    $okLarge = saveSquareVariant($source, $srcW, $srcH, 800, $mime, $targetLarge);
    $okMedium = saveSquareVariant($source, $srcW, $srcH, 400, $mime, $targetMedium);
    $okThumb = saveSquareVariant($source, $srcW, $srcH, 120, $mime, $targetThumb);
    imagedestroy($source);

    if (!$okOriginal || !$okLarge || !$okMedium || !$okThumb) {
        @unlink($targetOriginal);
        @unlink($targetLarge);
        @unlink($targetMedium);
        @unlink($targetThumb);
        jsonResponse(['error' => 'Cannot generate image variants'], 500);
    }

    $saved = mediaStorageSaveVariants($pdo, $imageId, [
        'original' => $targetOriginal,
        'large' => $targetLarge,
        'medium' => $targetMedium,
        'thumb' => $targetThumb,
    ]);
    $versions = $saved['versions'];
    $publicOriginal = (string) ($versions['original'] ?? '');
    $publicLarge = (string) ($versions['large'] ?? '');
    $publicMedium = (string) ($versions['medium'] ?? '');
    $publicThumb = (string) ($versions['thumb'] ?? '');
    if ($publicOriginal === '' || $publicLarge === '' || $publicMedium === '' || $publicThumb === '') {
        jsonResponse(['error' => 'Cannot persist image variants'], 500);
    }

    jsonResponse([
        'data' => [
            'image_url' => $publicMedium,
            'versions' => [
                'original' => $publicOriginal,
                'large' => $publicLarge,
                'medium' => $publicMedium,
                'thumb' => $publicThumb,
            ],
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'storage_driver' => (string) ($saved['driver'] ?? 'local'),
        ],
    ], 201);
} catch (Throwable $e) {
    error_log('[admin_upload_image] ' . $e->getMessage());
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
