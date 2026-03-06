<?php
declare(strict_types=1);

const PRODUCT_STATUS_NEW = 'NEW';
const PRODUCT_STATUS_OUT_OF_STOCK = 'OUT_OF_STOCK';
const PRODUCT_STATUS_NONE = 'NONE';
const STOCK_STATUS_IN_STOCK = 'in-stock';
const STOCK_STATUS_LOW = 'low';
const STOCK_STATUS_OUT = 'out';
const STOCK_RUPTURE_THRESHOLD = 50;
const ADMIN_STOCK_IN_STOCK_THRESHOLD = 200;

function computeStockStatus(int $stockQty): string
{
    // Backward-compatible alias (legacy callers). Uses admin thresholds.
    return computeAdminStockStatus($stockQty);
}

function computeAdminStockStatus(int $stockQty): string
{
    if ($stockQty <= STOCK_RUPTURE_THRESHOLD) {
        return STOCK_STATUS_OUT; // <= 50
    }
    if ($stockQty <= ADMIN_STOCK_IN_STOCK_THRESHOLD) {
        return STOCK_STATUS_LOW; // 51..200
    }
    return STOCK_STATUS_IN_STOCK; // > 200
}

function computeProductStatus(int $stockQty, bool $hasPaidPurchase): string
{
    if ($stockQty <= STOCK_RUPTURE_THRESHOLD) {
        return PRODUCT_STATUS_OUT_OF_STOCK;
    }

    if (!$hasPaidPurchase) {
        return PRODUCT_STATUS_NEW;
    }

    return PRODUCT_STATUS_NONE;
}

function productHasPaidPurchase(PDO $pdo, int $productId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM order_items oi
         JOIN product_variants v ON v.id = oi.variant_id
         JOIN payments p ON p.order_id = oi.order_id
         WHERE v.product_id = :product_id
           AND p.status = :paid_status
         LIMIT 1'
    );
    $stmt->execute([
        ':product_id' => $productId,
        ':paid_status' => 'paid',
    ]);

    return (bool) $stmt->fetchColumn();
}

function getProductStatusSnapshots(PDO $pdo, array $productIds): array
{
    $normalizedIds = array_values(array_unique(array_map('intval', $productIds)));
    $normalizedIds = array_values(array_filter($normalizedIds, static fn (int $id): bool => $id > 0));
    if (!$normalizedIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id,
                COALESCE((
                    SELECT SUM(COALESCE(i.stock_qty, 0))
                    FROM product_variants v2
                    LEFT JOIN inventory i ON i.variant_id = v2.id
                    WHERE v2.product_id = p.id AND v2.is_active = 1
                ), 0) AS stock_total,
                EXISTS(
                    SELECT 1
                    FROM product_variants v3
                    JOIN order_items oi ON oi.variant_id = v3.id
                    JOIN payments pay ON pay.order_id = oi.order_id
                    WHERE v3.product_id = p.id
                      AND pay.status = 'paid'
                    LIMIT 1
                ) AS has_paid
         FROM products p
         WHERE p.id IN ($placeholders)"
    );
    $stmt->execute($normalizedIds);
    $rows = $stmt->fetchAll();

    $snapshots = [];
    foreach ($rows as $row) {
        $productId = (int) ($row['id'] ?? 0);
        if ($productId <= 0) {
            continue;
        }
        $status = computeProductStatus(
            (int) ($row['stock_total'] ?? 0),
            (int) ($row['has_paid'] ?? 0) === 1
        );
        $snapshots[$productId] = $status;
    }

    return $snapshots;
}

function recordProductStatusTransitions(PDO $pdo, array $before, array $after, string $eventName, array $payload = []): int
{
    $event = trim($eventName);
    if ($event === '') {
        $event = 'unknown_event';
    }

    $count = 0;
    $insert = $pdo->prepare(
        'INSERT INTO product_status_audit (product_id, from_status, to_status, event_name, event_payload)
         VALUES (:product_id, :from_status, :to_status, :event_name, :event_payload)'
    );

    foreach ($after as $productId => $toStatus) {
        $id = (int) $productId;
        if ($id <= 0) {
            continue;
        }

        $fromStatus = (string) ($before[$id] ?? PRODUCT_STATUS_NONE);
        $toStatus = (string) $toStatus;
        if ($fromStatus === $toStatus) {
            continue;
        }

        try {
            $insert->execute([
                ':product_id' => $id,
                ':from_status' => $fromStatus,
                ':to_status' => $toStatus,
                ':event_name' => $event,
                ':event_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        } catch (Throwable $e) {
            // Non-blocking in production: status calculation must never fail due to audit table issues.
            error_log('[product_status] audit_insert_failed product_id=' . $id . ' reason=' . $e->getMessage());
        }
    }

    if ($count > 0) {
        error_log('[product_status] transitions event=' . $event . ' count=' . $count);
    }

    return $count;
}

function clearNewFlagForPaidOrder(PDO $pdo, int $orderId): int
{
    if ($orderId <= 0) {
        return 0;
    }

    $idsStmt = $pdo->prepare(
        'SELECT DISTINCT p.id
         FROM products p
         JOIN product_variants v ON v.product_id = p.id
         JOIN order_items oi ON oi.variant_id = v.id
         WHERE oi.order_id = :order_id'
    );
    $idsStmt->execute([':order_id' => $orderId]);
    $productIds = array_map('intval', array_column($idsStmt->fetchAll(), 'id'));
    if (!$productIds) {
        return 0;
    }

    $before = getProductStatusSnapshots($pdo, $productIds);

    $stmt = $pdo->prepare(
        'UPDATE products p
         JOIN product_variants v ON v.product_id = p.id
         JOIN order_items oi ON oi.variant_id = v.id
         SET p.is_new = 0
         WHERE oi.order_id = :order_id
           AND p.is_new = 1'
    );
    $stmt->execute([':order_id' => $orderId]);
    $affected = $stmt->rowCount();

    $after = getProductStatusSnapshots($pdo, $productIds);
    recordProductStatusTransitions($pdo, $before, $after, 'payment_confirmed_event', [
        'order_id' => $orderId,
        'updated_new_flags' => $affected,
    ]);

    if ($affected > 0) {
        error_log('[product_status] order_paid clear_new_flag order_id=' . $orderId . ' affected=' . $affected);
    }

    return $affected;
}

function applyInventoryDeductionForPaidOrder(PDO $pdo, int $orderId): array
{
    if ($orderId <= 0) {
        return ['variants' => 0, 'units' => 0];
    }

    $stmt = $pdo->prepare(
        'SELECT oi.variant_id, SUM(oi.quantity) AS qty
         FROM order_items oi
         WHERE oi.order_id = :order_id
         GROUP BY oi.variant_id'
    );
    $stmt->execute([':order_id' => $orderId]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return ['variants' => 0, 'units' => 0];
    }

    $variantIds = array_map(static fn (array $row): int => (int) ($row['variant_id'] ?? 0), $rows);
    $variantIds = array_values(array_filter($variantIds, static fn (int $id): bool => $id > 0));
    if ($variantIds) {
        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $lockStmt = $pdo->prepare("SELECT variant_id FROM inventory WHERE variant_id IN ($placeholders) FOR UPDATE");
        $lockStmt->execute($variantIds);
    }

    $updateStmt = $pdo->prepare(
        'UPDATE inventory
         SET stock_qty = GREATEST(0, stock_qty - :qty)
         WHERE variant_id = :variant_id'
    );
    $insertStmt = $pdo->prepare(
        'INSERT INTO inventory (variant_id, stock_qty, reserved_qty)
         VALUES (:variant_id, 0, 0)
         ON DUPLICATE KEY UPDATE variant_id = variant_id'
    );

    $variants = 0;
    $units = 0;
    foreach ($rows as $row) {
        $variantId = (int) ($row['variant_id'] ?? 0);
        $qty = max(0, (int) ($row['qty'] ?? 0));
        if ($variantId <= 0 || $qty <= 0) {
            continue;
        }
        $updateStmt->execute([':qty' => $qty, ':variant_id' => $variantId]);
        if ($updateStmt->rowCount() === 0) {
            $insertStmt->execute([':variant_id' => $variantId]);
        }
        $variants++;
        $units += $qty;
    }

    if ($variants > 0) {
        error_log('[inventory] paid_order_deduction order_id=' . $orderId . ' variants=' . $variants . ' units=' . $units);
    }

    return ['variants' => $variants, 'units' => $units];
}
