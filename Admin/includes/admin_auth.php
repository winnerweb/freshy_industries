<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function adminEnsureSecurityStateTable(PDO $pdo): void
{
    $pdo->exec(
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

function adminGetOrCreateSessionVersion(PDO $pdo, int $adminId): int
{
    adminEnsureSecurityStateTable($pdo);
    $stmt = $pdo->prepare(
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

    $insert = $pdo->prepare('INSERT INTO admin_security_state (admin_user_id, session_version) VALUES (:id, 1)');
    $insert->execute([':id' => $adminId]);
    return 1;
}

function adminStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function adminCurrentUser(): ?array
{
    adminStartSession();
    $user = $_SESSION['admin_user'] ?? null;
    if (!is_array($user)) {
        return null;
    }

    $id = (int) ($user['id'] ?? 0);
    $name = trim((string) ($user['name'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    $role = trim((string) ($user['role'] ?? ''));
    if ($id <= 0 || $name === '' || $email === '' || $role === '') {
        return null;
    }

    // Re-hydrate from DB to avoid stale/corrupted session display values.
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT id, full_name, email, role, status
             FROM admin_users
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row && (string) ($row['status'] ?? 'inactive') === 'active') {
            $sessionVersion = (int) ($_SESSION['admin_session_version'] ?? 0);
            $dbVersion = adminGetOrCreateSessionVersion($pdo, (int) $row['id']);
            if ($sessionVersion > 0 && $dbVersion !== $sessionVersion) {
                adminLogout();
                return null;
            }
            $freshName = trim((string) ($row['full_name'] ?? ''));
            $freshEmail = trim((string) ($row['email'] ?? ''));
            $freshRole = trim((string) ($row['role'] ?? ''));
            if ($freshName !== '' && $freshEmail !== '' && $freshRole !== '') {
                $_SESSION['admin_user'] = [
                    'id' => (int) $row['id'],
                    'name' => $freshName,
                    'email' => $freshEmail,
                    'role' => $freshRole,
                ];
                return [
                    'id' => (int) $row['id'],
                    'name' => $freshName,
                    'email' => $freshEmail,
                    'role' => $freshRole,
                ];
            }
        }
    } catch (Throwable $e) {
        // Keep session fallback below.
    }

    return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'role' => $role,
    ];
}

function adminUserHasRole(string $userRole, array $allowedRoles): bool
{
    $rank = [
        'operator' => 1,
        'manager' => 2,
        'admin' => 3,
    ];
    $userRank = $rank[$userRole] ?? 0;
    if ($userRank <= 0) {
        return false;
    }

    $minRank = null;
    foreach ($allowedRoles as $role) {
        $value = $rank[(string) $role] ?? null;
        if ($value === null) {
            continue;
        }
        $minRank = $minRank === null ? $value : min($minRank, $value);
    }
    if ($minRank === null) {
        return false;
    }

    return $userRank >= $minRank;
}

function adminUserAvatarUrl(int $adminId): string
{
    if ($adminId <= 0) {
        return '';
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT avatar_url
             FROM admin_profiles
             WHERE admin_user_id = :id
             LIMIT 1'
        );
        $stmt->execute([':id' => $adminId]);
        $url = trim((string) $stmt->fetchColumn());
        return $url;
    } catch (Throwable $e) {
        return '';
    }
}

function adminLogin(string $email, string $password): array
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return [false, 'Invalid credentials'];
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, role, password_hash, status
         FROM admin_users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute([':email' => mb_strtolower(trim($email))]);
    $row = $stmt->fetch();
    if (!$row || (string) ($row['status'] ?? 'inactive') !== 'active') {
        return [false, 'Invalid credentials'];
    }

    $hash = (string) ($row['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return [false, 'Invalid credentials'];
    }

    adminStartSession();
    session_regenerate_id(true);
    $_SESSION['admin_user'] = [
        'id' => (int) $row['id'],
        'name' => (string) $row['full_name'],
        'email' => (string) $row['email'],
        'role' => (string) $row['role'],
    ];
    $_SESSION['admin_session_version'] = adminGetOrCreateSessionVersion($pdo, (int) $row['id']);
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));

    return [true, null];
}

function adminLogout(): void
{
    adminStartSession();
    unset($_SESSION['admin_user']);
    unset($_SESSION['admin_csrf_token']);
    unset($_SESSION['admin_session_version']);
    session_regenerate_id(true);
}

function adminCsrfToken(): string
{
    adminStartSession();
    $token = $_SESSION['admin_csrf_token'] ?? null;
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['admin_csrf_token'] = $token;
    }
    return $token;
}

function adminValidateCsrfToken(string $token): bool
{
    adminStartSession();
    $sessionToken = $_SESSION['admin_csrf_token'] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

function requireAdminPage(array $allowedRoles = ['operator', 'manager', 'admin']): array
{
    $user = adminCurrentUser();
    if (!$user) {
        $next = rawurlencode((string) ($_SERVER['REQUEST_URI'] ?? 'dashboard.php'));
        header('Location: login.php?next=' . $next);
        exit;
    }
    if (!adminUserHasRole((string) $user['role'], $allowedRoles)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $user;
}
