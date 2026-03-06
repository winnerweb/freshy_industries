<?php
declare(strict_types=1);

require_once __DIR__ . '/../persistence/payment_repository.php';
require_once __DIR__ . '/../persistence/order_repository.php';
require_once __DIR__ . '/../persistence/inventory_repository.php';
require_once __DIR__ . '/../product_status_service.php';

function paymentAppApplyPaidOrderStockMutation(PDO $pdo, int $orderId): array
{
    $groupedItems = orderRepoGroupedItemsByVariant($pdo, $orderId);
    $variantIds = array_map(static fn (array $row): int => (int) ($row['variant_id'] ?? 0), $groupedItems);
    inventoryRepoLockByVariantIds($pdo, $variantIds);

    $variants = 0;
    $units = 0;
    foreach ($groupedItems as $row) {
        $variantId = (int) ($row['variant_id'] ?? 0);
        $qty = max(0, (int) ($row['qty'] ?? 0));
        if ($variantId <= 0 || $qty <= 0) {
            continue;
        }
        inventoryRepoDecrementStock($pdo, $variantId, $qty);
        $variants++;
        $units += $qty;
    }

    clearNewFlagForPaidOrder($pdo, $orderId);
    return ['variants' => $variants, 'units' => $units];
}

function paymentAppSettleSimulatorPayment(PDO $pdo, int $paymentId, string $outcome): array
{
    $row = paymentRepoFindWithOrderForUpdate($pdo, $paymentId);
    if (!$row) {
        return ['error' => 'Payment not found', 'status' => 404];
    }

    $currentPaymentStatus = (string) ($row['status'] ?? '');
    if (in_array($currentPaymentStatus, ['paid', 'failed'], true)) {
        return [
            'data' => [
                'payment_id' => (int) $row['id'],
                'order_id' => (int) $row['order_id'],
                'payment_status' => $currentPaymentStatus,
                'order_status' => (string) ($row['order_status'] ?? 'pending'),
                'inventory' => ['variants' => 0, 'units' => 0],
                'idempotent' => true,
            ],
            'status' => 200,
        ];
    }

    $newPaymentStatus = $outcome === 'success' ? 'paid' : 'failed';
    $newOrderStatus = $outcome === 'success' ? 'paid' : 'pending';
    paymentRepoSetStatus($pdo, (int) $row['id'], $newPaymentStatus);
    orderRepoSetStatus($pdo, (int) $row['order_id'], $newOrderStatus);

    $inventory = ['variants' => 0, 'units' => 0];
    if ($newPaymentStatus === 'paid') {
        $inventory = paymentAppApplyPaidOrderStockMutation($pdo, (int) $row['order_id']);
    }

    return [
        'data' => [
            'payment_id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'payment_status' => $newPaymentStatus,
            'order_status' => $newOrderStatus,
            'inventory' => $inventory,
            'idempotent' => false,
        ],
        'status' => 200,
    ];
}

function paymentAppMapFedapayStatusToLocal(string $fedapayStatus): string
{
    $status = strtolower(trim($fedapayStatus));
    $paidStatuses = [
        'approved',
        'transferred',
        'refunded',
        'approved_partially_refunded',
        'transferred_partially_refunded',
        'paid',
    ];
    if (in_array($status, $paidStatuses, true)) {
        return 'paid';
    }

    $failedStatuses = ['declined', 'canceled', 'cancelled', 'failed', 'expired'];
    if (in_array($status, $failedStatuses, true)) {
        return 'failed';
    }

    return 'pending';
}

function paymentAppSettleFedapayByProviderRef(PDO $pdo, string $providerRef, string $fedapayStatus): array
{
    $ref = trim($providerRef);
    if ($ref === '') {
        return ['error' => 'Missing provider ref', 'status' => 422];
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.order_id, p.status, o.status AS order_status
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         WHERE p.provider = :provider AND p.provider_ref = :provider_ref
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([
        ':provider' => 'fedapay',
        ':provider_ref' => $ref,
    ]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['error' => 'Payment not found', 'status' => 404];
    }

    $targetPaymentStatus = paymentAppMapFedapayStatusToLocal($fedapayStatus);
    $currentPaymentStatus = (string) ($row['status'] ?? 'pending');
    if ($targetPaymentStatus === $currentPaymentStatus) {
        return [
            'data' => [
                'payment_id' => (int) $row['id'],
                'order_id' => (int) $row['order_id'],
                'payment_status' => $currentPaymentStatus,
                'order_status' => (string) ($row['order_status'] ?? 'pending'),
                'inventory' => ['variants' => 0, 'units' => 0],
                'idempotent' => true,
            ],
            'status' => 200,
        ];
    }

    $orderStatus = $targetPaymentStatus === 'paid' ? 'paid' : 'pending';
    paymentRepoSetStatus($pdo, (int) $row['id'], $targetPaymentStatus);
    orderRepoSetStatus($pdo, (int) $row['order_id'], $orderStatus);

    $inventory = ['variants' => 0, 'units' => 0];
    if ($targetPaymentStatus === 'paid' && $currentPaymentStatus !== 'paid') {
        $inventory = applyInventoryDeductionForPaidOrder($pdo, (int) $row['order_id']);
        clearNewFlagForPaidOrder($pdo, (int) $row['order_id']);
    }

    return [
        'data' => [
            'payment_id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'payment_status' => $targetPaymentStatus,
            'order_status' => $orderStatus,
            'inventory' => $inventory,
            'idempotent' => false,
        ],
        'status' => 200,
    ];
}
