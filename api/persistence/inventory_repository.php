<?php
declare(strict_types=1);

function inventoryRepoLockByVariantIds(PDO $pdo, array $variantIds): void
{
    $ids = array_values(array_unique(array_map('intval', $variantIds)));
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT variant_id FROM inventory WHERE variant_id IN ($placeholders) FOR UPDATE");
    $stmt->execute($ids);
}

function inventoryRepoDecrementStock(PDO $pdo, int $variantId, int $qty): void
{
    $stmt = $pdo->prepare(
        'UPDATE inventory
         SET stock_qty = GREATEST(0, stock_qty - :qty)
         WHERE variant_id = :variant_id'
    );
    $stmt->execute([
        ':qty' => max(0, $qty),
        ':variant_id' => $variantId,
    ]);

    if ($stmt->rowCount() === 0) {
        $insert = $pdo->prepare(
            'INSERT INTO inventory (variant_id, stock_qty, reserved_qty)
             VALUES (:variant_id, 0, 0)
             ON DUPLICATE KEY UPDATE variant_id = variant_id'
        );
        $insert->execute([':variant_id' => $variantId]);
    }
}
