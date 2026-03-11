<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repositories/AdminRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validators/FileUploadValidator.php';

final class ProfileService
{
    public function __construct(
        private PDO $pdo,
        private AdminRepository $adminRepository,
        private AuditLogRepository $auditRepository,
        private FileUploadValidator $fileUploadValidator
    ) {
    }

    public function getProfileData(int $adminId): array
    {
        $this->adminRepository->ensureTables();
        $admin = $this->adminRepository->getAdminById($adminId);
        if (!$admin) {
            throw new RuntimeException('Administrateur introuvable.');
        }
        $profile = $this->adminRepository->getProfile($adminId);
        return [
            'full_name' => (string) ($admin['full_name'] ?? ''),
            'email' => (string) ($admin['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'bio' => (string) ($profile['bio'] ?? ''),
            'avatar_url' => (string) ($profile['avatar_url'] ?? ''),
            'role' => (string) ($admin['role'] ?? ''),
        ];
    }

    public function saveProfile(
        int $adminId,
        array $payload,
        array $files,
        string $ip,
        string $userAgent
    ): array {
        $fullName = trim((string) ($payload['full_name'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $phone = trim((string) ($payload['phone'] ?? ''));
        $bio = trim((string) ($payload['bio'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName) < 2) {
            throw new InvalidArgumentException('Nom complet invalide.');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email invalide.');
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\\-\\s]{6,30}$/', $phone)) {
            throw new InvalidArgumentException('Numero de telephone invalide.');
        }
        if (mb_strlen($bio) > 1000) {
            throw new InvalidArgumentException('Bio trop longue (max 1000 caracteres).');
        }

        $this->adminRepository->ensureTables();
        $this->auditRepository->ensureTable();

        $admin = $this->adminRepository->getAdminById($adminId);
        if (!$admin) {
            throw new RuntimeException('Administrateur introuvable.');
        }
        $beforeProfile = $this->adminRepository->getProfile($adminId);
        $before = [
            'full_name' => (string) ($admin['full_name'] ?? ''),
            'email' => (string) ($admin['email'] ?? ''),
            'phone' => (string) ($beforeProfile['phone'] ?? ''),
            'bio' => (string) ($beforeProfile['bio'] ?? ''),
            'avatar_url' => (string) ($beforeProfile['avatar_url'] ?? ''),
        ];

        $avatarUrl = (string) ($beforeProfile['avatar_url'] ?? '');
        $newAvatarPath = null;

        $avatarFile = $files['avatar'] ?? null;
        if (is_array($avatarFile) && (int) ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $validation = $this->fileUploadValidator->validateImage($avatarFile);
            if (($validation['ok'] ?? false) !== true) {
                throw new InvalidArgumentException((string) ($validation['error'] ?? 'Upload avatar invalide.'));
            }
            $newAvatarPath = $this->storeAvatarAsWebp((string) $validation['tmp_name']);
            $avatarUrl = $this->avatarPathToUrl($newAvatarPath);
        }

        $this->pdo->beginTransaction();
        try {
            $this->adminRepository->updateAdminIdentity($adminId, $fullName, $email);
            $this->adminRepository->upsertProfile($adminId, $phone, $bio, $avatarUrl);

            $after = [
                'full_name' => $fullName,
                'email' => mb_strtolower($email),
                'phone' => $phone,
                'bio' => $bio,
                'avatar_url' => $avatarUrl,
            ];
            $this->auditRepository->log(
                $adminId,
                'profile.updated',
                'admin_user',
                (string) $adminId,
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
            if ($newAvatarPath !== null && is_file($newAvatarPath)) {
                @unlink($newAvatarPath);
            }
            throw $e;
        }

        // Remove previous local avatar file after successful commit.
        $oldAvatarUrl = (string) ($before['avatar_url'] ?? '');
        if ($newAvatarPath !== null && $oldAvatarUrl !== '') {
            $oldPath = $this->avatarUrlToPath($oldAvatarUrl);
            if ($oldPath !== null && is_file($oldPath) && realpath($oldPath) !== realpath($newAvatarPath)) {
                @unlink($oldPath);
            }
        }

        return $after;
    }

    private function storeAvatarAsWebp(string $sourceTmp): string
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Extension GD requise pour traiter les avatars.');
        }

        $imageData = @file_get_contents($sourceTmp);
        if ($imageData === false) {
            throw new RuntimeException('Lecture avatar impossible.');
        }
        $resource = @imagecreatefromstring($imageData);
        if (!$resource) {
            throw new RuntimeException('Image avatar invalide.');
        }

        $srcW = imagesx($resource);
        $srcH = imagesy($resource);
        $targetSize = 320;

        $dst = imagecreatetruecolor($targetSize, $targetSize);
        if (!$dst) {
            imagedestroy($resource);
            throw new RuntimeException('Creation image avatar impossible.');
        }

        // Cover-crop to avoid letterboxing: any avatar fills its container.
        $cropSide = max(1, min($srcW, $srcH));
        $srcX = (int) floor(($srcW - $cropSide) / 2);
        $srcY = (int) floor(($srcH - $cropSide) / 2);
        imagecopyresampled($dst, $resource, 0, 0, $srcX, $srcY, $targetSize, $targetSize, $cropSide, $cropSide);

        $storageDir = dirname(__DIR__) . '/storage/profile_images';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            imagedestroy($resource);
            imagedestroy($dst);
            throw new RuntimeException('Creation dossier avatar impossible.');
        }

        $filename = 'avatar_' . bin2hex(random_bytes(16)) . '.webp';
        $absolute = $storageDir . '/' . $filename;
        $ok = imagewebp($dst, $absolute, 82);
        imagedestroy($resource);
        imagedestroy($dst);

        if (!$ok) {
            throw new RuntimeException('Ecriture avatar impossible.');
        }

        @chmod($absolute, 0644);
        return $absolute;
    }

    private function avatarPathToUrl(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);
        $needle = '/Admin/storage/profile_images/';
        $pos = strpos($normalized, $needle);
        if ($pos === false) {
            return '';
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $adminPos = strpos($scriptName, '/Admin/');
        $base = $adminPos !== false ? substr($scriptName, 0, $adminPos) : '';
        return rtrim($base, '/') . '/Admin/storage/profile_images/' . basename($absolutePath);
    }

    private function avatarUrlToPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $marker = '/Admin/storage/profile_images/';
        $pos = strpos($path, $marker);
        if ($pos === false) {
            return null;
        }
        $filename = basename($path);
        if ($filename === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return null;
        }
        return dirname(__DIR__) . '/storage/profile_images/' . $filename;
    }
}
