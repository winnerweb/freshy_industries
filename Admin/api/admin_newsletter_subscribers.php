<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once dirname(__DIR__, 2) . '/includes/newsletter.php';

try {
    requireAdminApi(['manager', 'admin']);
    $pdo = db();
    newsletterEnsureTables($pdo);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $format = trim((string) ($_GET['format'] ?? 'json'));
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 12)));

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = 'email LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="newsletter_subscribers.csv"');
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id', 'email', 'status', 'created_at']);
            $stmt = $pdo->prepare(
                'SELECT id, email, status, created_at
                 FROM newsletter_subscribers' . $whereSql . '
                 ORDER BY created_at DESC, id DESC'
            );
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [
                    (int) ($row['id'] ?? 0),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    (string) ($row['created_at'] ?? ''),
                ]);
            }
            fclose($out);
            exit;
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_subscribers' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = 'SELECT id, email, status, created_at, updated_at
                FROM newsletter_subscribers' . $whereSql . '
                ORDER BY created_at DESC, id DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));
    $ids = $payload['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

    if ($action === 'delete_many') {
        if ($ids === []) {
            jsonResponse(['error' => 'Aucune selection.'], 422);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        jsonResponse(['data' => ['deleted_count' => $stmt->rowCount()]]);
    }

    if ($action === 'update_status_many') {
        $status = trim((string) ($payload['status'] ?? ''));
        if ($ids === [] || !in_array($status, ['active', 'unsubscribed'], true)) {
            jsonResponse(['error' => 'Payload invalide.'], 422);
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$status], $ids);
        $stmt = $pdo->prepare("UPDATE newsletter_subscribers SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
        $stmt->execute($params);
        jsonResponse(['data' => ['updated_count' => $stmt->rowCount(), 'status' => $status]]);
    }

    jsonResponse(['error' => 'Action non supportee'], 400);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}

