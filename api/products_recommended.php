<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/application/product_application_service.php';

function recommendedGetSessionId(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return (string) ($_SESSION['cart_session'] ?? '');
}

function recommendedFetchActiveCartId(PDO $pdo, string $sessionId): int
{
    if ($sessionId === '') {
        return 0;
    }
    $stmt = $pdo->prepare(
        "SELECT id
         FROM carts
         WHERE session_id = :sid
           AND status = 'active'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':sid' => $sessionId]);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function recommendedFetchCartContext(PDO $pdo, int $cartId): array
{
    if ($cartId <= 0) {
        return ['product_ids' => [], 'category_ids' => []];
    }

    $stmt = $pdo->prepare(
        'SELECT DISTINCT p.id AS product_id, p.category_id
         FROM cart_items ci
         JOIN product_variants v ON v.id = ci.variant_id
         JOIN products p ON p.id = v.product_id
         WHERE ci.cart_id = :cart_id'
    );
    $stmt->execute([':cart_id' => $cartId]);
    $rows = $stmt->fetchAll();

    $productIds = [];
    $categoryIds = [];
    foreach ($rows as $row) {
        $pid = (int) ($row['product_id'] ?? 0);
        $cid = (int) ($row['category_id'] ?? 0);
        if ($pid > 0) {
            $productIds[] = $pid;
        }
        if ($cid > 0) {
            $categoryIds[] = $cid;
        }
    }

    return [
        'product_ids' => array_values(array_unique($productIds)),
        'category_ids' => array_values(array_unique($categoryIds)),
    ];
}

function recommendedFetchProductIds(PDO $pdo, array $categoryIds, array $excludeProductIds, int $limit): array
{
    $params = [];
    $where = [
        "p.status = 'active'",
        "EXISTS (
            SELECT 1
            FROM product_variants v
            LEFT JOIN inventory i ON i.variant_id = v.id
            WHERE v.product_id = p.id
              AND v.is_active = 1
              AND COALESCE(i.stock_qty, 0) > 0
        )",
    ];

    if ($categoryIds) {
        $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
        $where[] = "p.category_id IN ($placeholders)";
        $params = array_merge($params, $categoryIds);
    }

    if ($excludeProductIds) {
        $placeholders = implode(',', array_fill(0, count($excludeProductIds), '?'));
        $where[] = "p.id NOT IN ($placeholders)";
        $params = array_merge($params, $excludeProductIds);
    }

    $sql = 'SELECT p.id
            FROM products p
            LEFT JOIN (
                SELECT v.product_id, SUM(oi.quantity) AS sold_qty
                FROM order_items oi
                JOIN product_variants v ON v.id = oi.variant_id
                GROUP BY v.product_id
            ) pop ON pop.product_id = p.id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY COALESCE(pop.sold_qty, 0) DESC, p.created_at DESC
            LIMIT ' . max(1, $limit);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

try {
    $pdo = db();
    $limit = max(1, min(6, (int) ($_GET['limit'] ?? 6)));

    $sessionId = recommendedGetSessionId();
    $cartId = recommendedFetchActiveCartId($pdo, $sessionId);
    $context = recommendedFetchCartContext($pdo, $cartId);

    $productIds = [];
    if (!empty($context['category_ids'])) {
        $productIds = recommendedFetchProductIds(
            $pdo,
            $context['category_ids'],
            $context['product_ids'],
            $limit
        );
    }

    if (!$productIds) {
        $productIds = recommendedFetchProductIds(
            $pdo,
            [],
            $context['product_ids'],
            $limit
        );
    }

    if (!$productIds) {
        jsonResponse(['data' => []]);
    }

    $products = productAppBuildCatalogPayloadByProductIds($pdo, $productIds);
    jsonResponse(['data' => $products]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}

