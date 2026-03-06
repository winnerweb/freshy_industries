<?php
declare(strict_types=1);

function paymentRepoFindWithOrderForUpdate(PDO $pdo, int $paymentId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.order_id, p.status, o.status AS order_status
         FROM payments p
         JOIN orders o ON o.id = p.order_id
         WHERE p.id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([':id' => $paymentId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function paymentRepoSetStatus(PDO $pdo, int $paymentId, string $status): void
{
    $stmt = $pdo->prepare('UPDATE payments SET status = :status WHERE id = :id');
    $stmt->execute([
        ':status' => $status,
        ':id' => $paymentId,
    ]);
}
