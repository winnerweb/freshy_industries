<?php
declare(strict_types=1);

function productRepoFetchActiveProducts(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT p.id, p.name, p.slug, p.short_description, p.status, p.is_new,
                c.name AS category_name, c.slug AS category_slug,
                img.image_url,
                img_decor.image_url AS decor_image_url
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN product_images img ON img.id = (
            SELECT pi1.id
            FROM product_images pi1
            WHERE pi1.product_id = p.id AND pi1.is_primary = 1
            ORDER BY pi1.id ASC
            LIMIT 1
         )
         LEFT JOIN product_images img_decor ON img_decor.id = (
            SELECT pi2.id
            FROM product_images pi2
            WHERE pi2.product_id = p.id AND pi2.is_primary = 0
            ORDER BY pi2.sort_order ASC, pi2.id ASC
            LIMIT 1
         )
         WHERE p.status = "active"
           AND (c.id IS NULL OR c.is_active = 1)
         ORDER BY p.id DESC'
    );
    return $stmt->fetchAll();
}

function productRepoFetchActiveProductsByIds(PDO $pdo, array $productIds): array
{
    $ids = array_values(array_unique(array_map('intval', $productIds)));
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.slug, p.short_description, p.status, p.is_new,
                c.name AS category_name, c.slug AS category_slug,
                img.image_url,
                img_decor.image_url AS decor_image_url
         FROM products p
         LEFT JOIN categories c ON c.id = p.category_id
         LEFT JOIN product_images img ON img.id = (
            SELECT pi1.id
            FROM product_images pi1
            WHERE pi1.product_id = p.id AND pi1.is_primary = 1
            ORDER BY pi1.id ASC
            LIMIT 1
         )
         LEFT JOIN product_images img_decor ON img_decor.id = (
            SELECT pi2.id
            FROM product_images pi2
            WHERE pi2.product_id = p.id AND pi2.is_primary = 0
            ORDER BY pi2.sort_order ASC, pi2.id ASC
            LIMIT 1
         )
         WHERE p.status = 'active'
           AND (c.id IS NULL OR c.is_active = 1)
           AND p.id IN ($placeholders)"
    );
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

function productRepoFetchActiveVariantsByProductIds(PDO $pdo, array $productIds): array
{
    if (!$productIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT v.id, v.product_id, v.sku, v.label, v.price_cents, v.currency, v.is_active,
                COALESCE(i.stock_qty, 0) AS stock_qty
         FROM product_variants v
         LEFT JOIN inventory i ON i.variant_id = v.id
         WHERE v.product_id IN ($placeholders)
           AND v.is_active = 1"
    );
    $stmt->execute($productIds);
    return $stmt->fetchAll();
}

function productRepoFetchPaidProductIds(PDO $pdo, array $productIds): array
{
    if (!$productIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT v.product_id
         FROM order_items oi
         JOIN product_variants v ON v.id = oi.variant_id
         JOIN payments pay ON pay.order_id = oi.order_id
         WHERE pay.status = 'paid'
           AND v.product_id IN ($placeholders)"
    );
    $stmt->execute($productIds);
    return array_map('intval', array_column($stmt->fetchAll(), 'product_id'));
}
