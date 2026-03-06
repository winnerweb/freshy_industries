<?php
declare(strict_types=1);

final class AdminRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureTables(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_profiles (
                admin_user_id BIGINT UNSIGNED NOT NULL,
                phone VARCHAR(30) NOT NULL DEFAULT '',
                bio TEXT NOT NULL,
                avatar_url VARCHAR(255) NOT NULL DEFAULT '',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (admin_user_id),
                CONSTRAINT fk_admin_profiles_admin_user
                    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS admin_security_state (
                admin_user_id BIGINT UNSIGNED NOT NULL,
                session_version INT UNSIGNED NOT NULL DEFAULT 1,
                password_changed_at DATETIME NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (admin_user_id),
                CONSTRAINT fk_admin_security_state_admin_user
                    FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function getAdminById(int $adminId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, role, password_hash, status
             FROM admin_users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $adminId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateAdminIdentity(int $adminId, string $fullName, string $email): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users
             SET full_name = :full_name, email = :email, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $adminId,
            ':full_name' => $fullName,
            ':email' => mb_strtolower($email),
        ]);
    }

    public function getProfile(int $adminId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT phone, bio, avatar_url
             FROM admin_profiles
             WHERE admin_user_id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $adminId]);
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'phone' => '',
                'bio' => '',
                'avatar_url' => '',
            ];
        }
        return [
            'phone' => (string) ($row['phone'] ?? ''),
            'bio' => (string) ($row['bio'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
        ];
    }

    public function upsertProfile(int $adminId, string $phone, string $bio, ?string $avatarUrl = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO admin_profiles (admin_user_id, phone, bio, avatar_url)
             VALUES (:id, :phone, :bio, :avatar_url)
             ON DUPLICATE KEY UPDATE
               phone = VALUES(phone),
               bio = VALUES(bio),
               avatar_url = VALUES(avatar_url),
               updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':id' => $adminId,
            ':phone' => $phone,
            ':bio' => $bio,
            ':avatar_url' => (string) ($avatarUrl ?? $this->getProfile($adminId)['avatar_url']),
        ]);
    }

    public function updatePasswordHash(int $adminId, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE admin_users
             SET password_hash = :hash, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $adminId,
            ':hash' => $hash,
        ]);
    }

    public function getOrCreateSessionVersion(int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT session_version
             FROM admin_security_state
             WHERE admin_user_id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $adminId]);
        $version = $stmt->fetchColumn();
        if ($version !== false) {
            return (int) $version;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO admin_security_state (admin_user_id, session_version)
             VALUES (:id, 1)'
        );
        $insert->execute([':id' => $adminId]);
        return 1;
    }

    public function bumpSessionVersion(int $adminId): int
    {
        $current = $this->getOrCreateSessionVersion($adminId);
        $next = $current + 1;
        $stmt = $this->pdo->prepare(
            'UPDATE admin_security_state
             SET session_version = :v, password_changed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE admin_user_id = :id'
        );
        $stmt->execute([
            ':id' => $adminId,
            ':v' => $next,
        ]);
        return $next;
    }
}
