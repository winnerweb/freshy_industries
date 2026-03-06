<?php
declare(strict_types=1);

function orderRepoFindByIdForUpdate(PDO $pdo, int $orderId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, status, total_cents, currency
         FROM orders
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([':id' => $orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function orderRepoSetStatus(PDO $pdo, int $orderId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':id' => $orderId,
    ]);
}

function orderRepoGroupedItemsByVariant(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare(
        'SELECT variant_id, SUM(quantity) AS qty
         FROM order_items
         WHERE order_id = :order_id
         GROUP BY variant_id'
    );
    $stmt->execute([':order_id' => $orderId]);
    return $stmt->fetchAll();
}
