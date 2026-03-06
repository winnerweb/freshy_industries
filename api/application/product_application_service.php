<?php
declare(strict_types=1);

require_once __DIR__ . '/../persistence/product_repository.php';
require_once __DIR__ . '/../product_status_service.php';

function productAppBuildCatalogPayload(PDO $pdo): array
{
    $products = productRepoFetchActiveProducts($pdo);
    if (!$products) {
        return [];
    }

    $productIds = array_map(static fn (array $p): int => (int) $p['id'], $products);
    $variants = productRepoFetchActiveVariantsByProductIds($pdo, $productIds);
    $paidProductIds = productRepoFetchPaidProductIds($pdo, $productIds);
    $paidProductMap = array_fill_keys($paidProductIds, true);

    $variantsByProduct = [];
    foreach ($variants as $variant) {
        $productId = (int) $variant['product_id'];
        $stockQty = (int) ($variant['stock_qty'] ?? 0);
        $variantsByProduct[$productId][] = [
            'id' => (int) $variant['id'],
            'product_id' => $productId,
            'sku' => $variant['sku'],
            'label' => $variant['label'],
            'price_cents' => (int) $variant['price_cents'],
            'currency' => $variant['currency'],
            'is_active' => (bool) $variant['is_active'],
            'stock_qty' => $stockQty,
            'in_stock' => $stockQty > 0,
        ];
    }

    return array_map(static function (array $product) use ($variantsByProduct, $paidProductMap): array {
        $id = (int) $product['id'];
        $productVariants = $variantsByProduct[$id] ?? [];
        $stockTotal = 0;
        foreach ($productVariants as $variant) {
            $stockTotal += (int) ($variant['stock_qty'] ?? 0);
        }

        $productStatus = computeProductStatus($stockTotal, !empty($paidProductMap[$id]));
        $stockStatus = computeStockStatus($stockTotal);

        return [
            'id' => $id,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'short_description' => $product['short_description'],
            'status' => $product['status'],
            'is_new' => (bool) $product['is_new'],
            'in_stock' => $stockTotal > 0,
            'product_status' => $productStatus,
            'stock_status' => $stockStatus,
            'category' => [
                'name' => $product['category_name'],
                'slug' => $product['category_slug'],
            ],
            'image' => $product['image_url'],
            'decor_image' => $product['decor_image_url'],
            'variants' => $productVariants,
        ];
    }, $products);
}

function productAppBuildCatalogPayloadByProductIds(PDO $pdo, array $productIds): array
{
    $ids = array_values(array_unique(array_map('intval', $productIds)));
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    if (!$ids) {
        return [];
    }

    $products = productRepoFetchActiveProductsByIds($pdo, $ids);
    if (!$products) {
        return [];
    }

    $variants = productRepoFetchActiveVariantsByProductIds($pdo, $ids);
    $paidProductIds = productRepoFetchPaidProductIds($pdo, $ids);
    $paidProductMap = array_fill_keys($paidProductIds, true);

    $variantsByProduct = [];
    foreach ($variants as $variant) {
        $productId = (int) $variant['product_id'];
        $stockQty = (int) ($variant['stock_qty'] ?? 0);
        $saleMode = strtolower((string) ($variant['sale_mode'] ?? 'unit'));
        $stockCartons = (int) ($variant['stock_cartons'] ?? 0);
        $effectiveStock = $saleMode === 'carton' ? $stockCartons : $stockQty;
        $variantsByProduct[$productId][] = [
            'id' => (int) $variant['id'],
            'product_id' => $productId,
            'sku' => $variant['sku'],
            'label' => $variant['label'],
            'contenance' => $variant['contenance'] ?? null,
            'price_cents' => (int) $variant['price_cents'],
            'currency' => $variant['currency'],
            'is_active' => (bool) $variant['is_active'],
            'sale_mode' => $saleMode,
            'stock_qty' => $stockQty,
            'stock_cartons' => $stockCartons,
            'in_stock' => $effectiveStock > 0,
        ];
    }

    $payload = array_map(static function (array $product) use ($variantsByProduct, $paidProductMap): array {
        $id = (int) $product['id'];
        $productVariants = $variantsByProduct[$id] ?? [];
        $stockTotal = 0;
        foreach ($productVariants as $variant) {
            if (($variant['sale_mode'] ?? 'unit') === 'carton') {
                $stockTotal += (int) ($variant['stock_cartons'] ?? 0);
            } else {
                $stockTotal += (int) ($variant['stock_qty'] ?? 0);
            }
        }

        $productStatus = computeProductStatus($stockTotal, !empty($paidProductMap[$id]));
        $stockStatus = computeStockStatus($stockTotal);

        return [
            'id' => $id,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'short_description' => $product['short_description'],
            'status' => $product['status'],
            'is_new' => (bool) $product['is_new'],
            'in_stock' => $stockTotal > 0,
            'product_status' => $productStatus,
            'stock_status' => $stockStatus,
            'category' => [
                'name' => $product['category_name'],
                'slug' => $product['category_slug'],
            ],
            'image' => $product['image_url'],
            'decor_image' => $product['decor_image_url'],
            'variants' => $productVariants,
        ];
    }, $products);

    $orderMap = array_flip($ids);
    usort($payload, static function (array $a, array $b) use ($orderMap): int {
        $pa = $orderMap[(int) ($a['id'] ?? 0)] ?? PHP_INT_MAX;
        $pb = $orderMap[(int) ($b['id'] ?? 0)] ?? PHP_INT_MAX;
        return $pa <=> $pb;
    });

    return $payload;
}
