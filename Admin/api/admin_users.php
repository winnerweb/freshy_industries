<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

try {
    requireAdminApi(['admin']);
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        $currentUser = adminCurrentUser();
        $rows = $pdo->query(
            'SELECT id, full_name AS name, email, role, status, created_at
             FROM admin_users
             ORDER BY id DESC
             LIMIT 300'
        )->fetchAll();
        jsonResponse([
            'data' => $rows,
            'meta' => [
                'current_user_id' => (int) ($currentUser['id'] ?? 0),
            ],
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'create') {
        $name = trim((string) ($payload['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));
        $role = trim((string) ($payload['role'] ?? 'operator'));
        $password = (string) ($payload['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }
        if (!in_array($role, ['admin', 'manager', 'operator'], true)) {
            jsonResponse(['error' => 'Invalid role'], 422);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            jsonResponse(['error' => 'Password hashing failed'], 500);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO admin_users (full_name, email, role, password_hash, status)
             VALUES (:full_name, :email, :role, :password_hash, :status)'
        );
        try {
            $stmt->execute([
                ':full_name' => $name,
                ':email' => $email,
                ':role' => $role,
                ':password_hash' => $hash,
                ':status' => 'active',
            ]);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate') !== false) {
                jsonResponse(['error' => 'Email already exists'], 409);
            }
            throw $e;
        }

        jsonResponse(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
    }

    if ($action === 'update_status') {
        $id = (int) ($payload['id'] ?? 0);
        $status = trim((string) ($payload['status'] ?? ''));
        if ($id <= 0 || !in_array($status, ['active', 'inactive'], true)) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }

        $currentUser = adminCurrentUser();
        if ($currentUser && (int) ($currentUser['id'] ?? 0) === $id) {
            jsonResponse(['error' => 'Cannot update current account status'], 422);
        }

        $stmt = $pdo->prepare('UPDATE admin_users SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
        jsonResponse(['data' => ['id' => $id, 'status' => $status]]);
    }

    if ($action === 'update_status_many') {
        $idsRaw = $payload['ids'] ?? null;
        $status = trim((string) ($payload['status'] ?? ''));
        if (!is_array($idsRaw) || !$idsRaw || !in_array($status, ['active', 'inactive'], true)) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }

        $ids = array_values(array_unique(array_map('intval', $idsRaw)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if (!$ids) {
            jsonResponse(['error' => 'Invalid ids'], 422);
        }

        $currentUser = adminCurrentUser();
        if ($currentUser) {
            $currentId = (int) ($currentUser['id'] ?? 0);
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $currentId));
        }
        if (!$ids) {
            jsonResponse(['error' => 'No valid target user'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("UPDATE admin_users SET status = ? WHERE id IN ($placeholders)");
        $params = array_merge([$status], $ids);
        $stmt->execute($params);

        jsonResponse(['data' => ['updated_count' => $stmt->rowCount(), 'status' => $status]]);
    }

    if ($action === 'delete_many') {
        $idsRaw = $payload['ids'] ?? null;
        if (!is_array($idsRaw) || !$idsRaw) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }

        $ids = array_values(array_unique(array_map('intval', $idsRaw)));
        $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
        if (!$ids) {
            jsonResponse(['error' => 'Invalid ids'], 422);
        }

        $currentUser = adminCurrentUser();
        if ($currentUser) {
            $currentId = (int) ($currentUser['id'] ?? 0);
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $currentId));
        }
        if (!$ids) {
            jsonResponse(['error' => 'Cannot delete current account'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM admin_users WHERE id IN ($placeholders)");
        $stmt->execute($ids);

        jsonResponse(['data' => ['deleted_count' => $stmt->rowCount()]]);
    }

    jsonResponse(['error' => 'Unsupported action'], 400);
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'admin_users')) {
        jsonResponse([
            'error' => 'Table admin_users missing',
            'detail' => 'Apply database/schema.sql to create admin_users before using this endpoint.',
        ], 500);
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
