<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/upload_policies.php';

final class FileUploadValidator
{
    public function validateImage(array $file, ?int $maxBytes = null): array
    {
        $allowed = adminAvatarAllowedMimeMap();
        $maxBytes = $maxBytes ?? adminAvatarMaxBytes();

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'error' => 'Aucun fichier envoye.'];
        }
        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Echec upload fichier.'];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'error' => 'Fichier invalide (max ' . adminAvatarMaxMbLabel() . ' MB).'];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'error' => 'Source upload invalide.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string) $finfo->file($tmpName));
        if (!isset($allowed[$mime])) {
            return ['ok' => false, 'error' => 'Format non autorise (' . strtolower(adminAvatarFormatsLabel()) . ').'];
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo)) {
            return ['ok' => false, 'error' => 'Image corrompue ou non valide.'];
        }

        return [
            'ok' => true,
            'mime' => $mime,
            'ext' => $allowed[$mime],
            'tmp_name' => $tmpName,
        ];
    }
}
