<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/repositories/AdminRepository.php';
require_once dirname(__DIR__) . '/repositories/AuditLogRepository.php';
require_once dirname(__DIR__) . '/validators/PasswordValidator.php';

final class PasswordService
{
    public function __construct(
        private PDO $pdo,
        private AdminRepository $adminRepository,
        private AuditLogRepository $auditRepository,
        private PasswordValidator $passwordValidator
    ) {
    }

    public function changePassword(
        int $adminId,
        string $currentPassword,
        string $newPassword,
        string $confirmPassword,
        string $ip,
        string $userAgent
    ): void {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new InvalidArgumentException('Tous les champs mot de passe sont obligatoires.');
        }
        if ($newPassword !== $confirmPassword) {
            throw new InvalidArgumentException('La confirmation du mot de passe est invalide.');
        }

        $this->adminRepository->ensureTables();
        $this->auditRepository->ensureTable();

        $admin = $this->adminRepository->getAdminById($adminId);
        if (!$admin) {
            throw new RuntimeException('Administrateur introuvable.');
        }

        $hash = (string) ($admin['password_hash'] ?? '');
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            throw new InvalidArgumentException('Ancien mot de passe incorrect.');
        }

        $passwordError = $this->passwordValidator->validate($newPassword);
        if ($passwordError !== null) {
            throw new InvalidArgumentException($passwordError);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHash === false) {
            throw new RuntimeException('Impossible de securiser le mot de passe.');
        }

        $this->pdo->beginTransaction();
        try {
            $this->adminRepository->updatePasswordHash($adminId, $newHash);
            $nextVersion = $this->adminRepository->bumpSessionVersion($adminId);
            $this->auditRepository->log(
                $adminId,
                'security.password.changed',
                'admin_user',
                (string) $adminId,
                ['session_version' => $nextVersion - 1],
                ['session_version' => $nextVersion],
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
