<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

try {
    requireAdminApi(['manager', 'admin']);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();
    if ($method === 'GET') {
        $q = trim((string) ($_GET['q'] ?? ''));
        $format = strtolower(trim((string) ($_GET['format'] ?? 'json')));

        $sql = 'SELECT c.id, c.full_name, c.email, c.phone, c.created_at,
                       COUNT(o.id) AS orders_count,
                       COALESCE(SUM(CASE WHEN o.status <> \'canceled\' THEN o.total_cents ELSE 0 END), 0) AS spent_cents
                FROM customers c
                LEFT JOIN orders o ON o.customer_id = c.id';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE c.full_name LIKE :q OR c.email LIKE :q OR c.phone LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' GROUP BY c.id, c.full_name, c.email, c.phone, c.created_at
                  ORDER BY c.id DESC
                  LIMIT 300';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $ordersCount = (int) ($row['orders_count'] ?? 0);
            $row['status'] = $ordersCount > 0 ? 'active' : 'pending';
        }
        unset($row);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="clients.csv"');
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                jsonResponse(['error' => 'CSV export unavailable'], 500);
            }
            fputcsv($out, ['id', 'nom', 'email', 'telephone', 'nombre_commandes', 'total_depense_fcfa', 'statut', 'created_at']);
            foreach ($rows as $row) {
                $spentCents = (int) ($row['spent_cents'] ?? 0);
                fputcsv($out, [
                    (int) ($row['id'] ?? 0),
                    (string) ($row['full_name'] ?? ''),
                    (string) ($row['email'] ?? ''),
                    (string) ($row['phone'] ?? ''),
                    (int) ($row['orders_count'] ?? 0),
                    (int) floor($spentCents / 100),
                    (string) ($row['status'] ?? 'pending'),
                    (string) ($row['created_at'] ?? ''),
                ]);
            }
            fclose($out);
            exit;
        }

        jsonResponse(['data' => $rows]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));
    if ($action !== 'delete_many') {
        jsonResponse(['error' => 'Unsupported action'], 400);
    }

    $idsRaw = $payload['ids'] ?? null;
    if (!is_array($idsRaw) || !$idsRaw) {
        jsonResponse(['error' => 'Invalid ids'], 422);
    }

    $ids = array_values(array_unique(array_map('intval', $idsRaw)));
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    if (!$ids) {
        jsonResponse(['error' => 'Invalid ids'], 422);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    jsonResponse(['data' => ['deleted_count' => $stmt->rowCount()]]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
