<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/product_status_service.php';

try {
    requireAdminApi(['manager', 'admin']);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        $status = trim((string) ($_GET['status'] ?? ''));
        $allowedStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'canceled'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            jsonResponse(['error' => 'Invalid status filter'], 422);
        }

        $sql = 'SELECT o.id, o.order_number, o.status, o.total_cents, o.currency, o.created_at,
                       c.full_name AS customer_name, c.phone AS customer_phone, c.email AS customer_email,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count
                FROM orders o
                LEFT JOIN customers c ON c.id = o.customer_id';
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE o.status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY o.id DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        jsonResponse(['data' => $rows]);
    }

    if ($method === 'POST') {
        $payload = readJsonInput();
        $action = trim((string) ($payload['action'] ?? ''));

        if ($action !== 'update_status') {
            jsonResponse(['error' => 'Unsupported action'], 400);
        }

        $orderId = (int) ($payload['order_id'] ?? 0);
        $newStatus = trim((string) ($payload['status'] ?? ''));
        $allowedStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'canceled'];

        if ($orderId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }

        $checkStmt = $pdo->prepare('SELECT id, status FROM orders WHERE id = :id LIMIT 1');
        $checkStmt->execute([':id' => $orderId]);
        $existing = $checkStmt->fetch();
        if (!$existing) {
            jsonResponse(['error' => 'Order not found'], 404);
        }

        $update = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $update->execute([
            ':status' => $newStatus,
            ':id' => $orderId,
        ]);

        // Keep payments aligned when manually setting paid/canceled in admin.
        if ($newStatus === 'paid') {
            $payStmt = $pdo->prepare(
                'UPDATE payments
                 SET status = :status
                 WHERE order_id = :order_id AND status IN (\'pending\', \'authorized\')'
            );
            $payStmt->execute([
                ':status' => 'paid',
                ':order_id' => $orderId,
            ]);

            // Apply stock deduction only on first transition to paid.
            if ((string) ($existing['status'] ?? '') !== 'paid') {
                applyInventoryDeductionForPaidOrder($pdo, $orderId);
                clearNewFlagForPaidOrder($pdo, $orderId);
            }
        }

        if ($newStatus === 'canceled') {
            $payStmt = $pdo->prepare(
                'UPDATE payments
                 SET status = :status
                 WHERE order_id = :order_id AND status = \'pending\''
            );
            $payStmt->execute([
                ':status' => 'failed',
                ':order_id' => $orderId,
            ]);
        }

        jsonResponse([
            'data' => [
                'order_id' => $orderId,
                'status' => $newStatus,
            ],
        ]);
    }

    jsonResponse(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
