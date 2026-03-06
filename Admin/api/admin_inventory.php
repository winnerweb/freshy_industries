<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/product_status_service.php';

try {
    requireAdminApi(['manager', 'admin']);
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $rows = $pdo->query(
        'SELECT i.id, i.variant_id, i.stock_qty, i.reserved_qty, i.updated_at,
                p.name AS product_name, v.label AS variant_label
         FROM inventory i
         JOIN product_variants v ON v.id = i.variant_id
         JOIN products p ON p.id = v.product_id
         ORDER BY i.updated_at DESC
         LIMIT 300'
    )->fetchAll();

    foreach ($rows as &$row) {
        $stock = (int) ($row['stock_qty'] ?? 0);
        $row['stock_status'] = computeAdminStockStatus($stock);
        $row['warehouse'] = 'Principal';
    }
    unset($row);

    jsonResponse(['data' => $rows]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
