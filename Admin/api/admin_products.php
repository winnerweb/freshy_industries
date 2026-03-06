<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/product_status_service.php';
require_once __DIR__ . '/product_catalog_config.php';

function slugify(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'produit';
}

function ensureUniqueProductSlug(PDO $pdo, string $baseSlug, ?int $excludeId = null): string
{
    $slug = $baseSlug;
    $suffix = 1;
    while (true) {
        $sql = 'SELECT id FROM products WHERE slug = :slug';
        $params = [':slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = $baseSlug . '-' . $suffix;
        $suffix++;
    }
}

function buildCategoryIndex(array $categories): array
{
    $index = [];
    foreach ($categories as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $slug = isset($row['slug']) ? (string) $row['slug'] : '';
        $name = isset($row['name']) ? (string) $row['name'] : '';
        $index[$id] = [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
            'catalog_key' => productCatalogCategoryKey($slug, $name),
        ];
    }
    return $index;
}

function sanitizeProductImagePath(?string $value): ?string
{
    $path = trim((string) ($value ?? ''));
    if ($path === '') {
        return null;
    }
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $path;
        }
        return null;
    }
    $path = ltrim($path, '/');
    // Allow only local uploaded product images.
    if (!preg_match('#^uploads/products/[a-zA-Z0-9/_\.\-]+$#', $path)) {
        return null;
    }
    return $path;
}

function fetchProductImagePaths(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT image_url
         FROM product_images
         WHERE product_id = :product_id'
    );
    $stmt->execute([':product_id' => $productId]);
    $rows = $stmt->fetchAll();
    return array_values(array_filter(array_map(
        static fn (array $row): string => (string) ($row['image_url'] ?? ''),
        $rows
    )));
}

function deleteImageVariantSet(string $relativePath): void
{
    $path = ltrim(trim($relativePath), '/');
    if (!preg_match('#^uploads/products/[a-zA-Z0-9/_\.\-]+$#', $path)) {
        return;
    }

    // Files are stored under project root "uploads/products".
    $baseDir = dirname(__DIR__, 2);
    $absolute = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($absolute)) {
        @unlink($absolute);
    }

    $filename = basename($path);
    if (!preg_match('/^(product_[0-9]+_)(original|large|medium|thumb)\.(webp|jpe?g|png)$/i', $filename, $m)) {
        return;
    }

    $prefix = $m[1];
    $dir = dirname($absolute);
    $suffixes = ['original', 'large', 'medium', 'thumb'];
    $extensions = ['webp', 'jpg', 'jpeg', 'png'];
    foreach ($suffixes as $suffix) {
        foreach ($extensions as $ext) {
            $candidate = $dir . DIRECTORY_SEPARATOR . $prefix . $suffix . '.' . $ext;
            if (is_file($candidate)) {
                @unlink($candidate);
            }
        }
    }
}

function generateVariantSku(int $productId): string
{
    return 'SKU-' . $productId . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function fcfaToCents($value): int
{
    $numeric = is_numeric($value) ? (float) $value : 0.0;
    if ($numeric <= 0) {
        return 0;
    }
    return (int) round($numeric * 100);
}

function normalizeVariantLabel(string $value): string
{
    return productCatalogNormalize($value);
}

try {
    requireAdminApi(['manager', 'admin']);
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $categories = $pdo->query(
        'SELECT id, name, slug
         FROM categories
         WHERE is_active = 1
         ORDER BY name ASC'
    )->fetchAll();
    $categoriesById = buildCategoryIndex($categories);

    if ($method === 'GET') {
        $products = $pdo->query(
            'SELECT p.id, p.name, p.slug, p.short_description, p.status, p.is_new, p.created_at,
                    p.category_id, c.name AS category_name,
                    v.id AS variant_id, v.label AS variant_label, v.price_cents, v.is_active AS variant_active,
                    (
                        SELECT MIN(vp.price_cents)
                        FROM product_variants vp
                        WHERE vp.product_id = p.id AND vp.is_active = 1
                    ) AS min_price_cents,
                    (
                        SELECT COALESCE(SUM(COALESCE(i2.stock_qty, 0)), 0)
                        FROM product_variants v3
                        LEFT JOIN inventory i2 ON i2.variant_id = v3.id
                        WHERE v3.product_id = p.id AND v3.is_active = 1
                    ) AS stock_total,
                    EXISTS(
                        SELECT 1
                        FROM product_variants v4
                        JOIN order_items oi ON oi.variant_id = v4.id
                        JOIN payments pay ON pay.order_id = oi.order_id
                        WHERE v4.product_id = p.id
                          AND pay.status = \'paid\'
                        LIMIT 1
                    ) AS has_paid_purchase,
                    COALESCE(i.stock_qty, 0) AS stock_qty,
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
             LEFT JOIN product_variants v ON v.id = (
                SELECT v2.id
                FROM product_variants v2
                WHERE v2.product_id = p.id
                ORDER BY v2.id ASC
                LIMIT 1
             )
             LEFT JOIN inventory i ON i.variant_id = v.id
             ORDER BY p.id DESC
             LIMIT 300'
        )->fetchAll();

        foreach ($products as &$product) {
            $stockTotal = (int) ($product['stock_total'] ?? 0);
            $product['stock_status'] = computeAdminStockStatus($stockTotal);
            $product['product_status'] = computeProductStatus($stockTotal, (int) ($product['has_paid_purchase'] ?? 0) === 1);
            // Keep API backward-compatible: expose stock_qty as the same aggregate used for status.
            $product['stock_qty'] = $stockTotal;
            $minPriceCents = (int) ($product['min_price_cents'] ?? 0);
            if ($minPriceCents > 0) {
                $product['price_cents'] = $minPriceCents;
            }
            $product['variants'] = [];
        }
        unset($product);

        $productIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $products
        )));
        $productIds = array_values(array_filter($productIds, static fn (int $id): bool => $id > 0));
        if ($productIds) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $variantStmt = $pdo->prepare(
                "SELECT v.id, v.product_id, v.label, v.price_cents, v.is_active, COALESCE(i.stock_qty, 0) AS stock_qty
                 FROM product_variants v
                 LEFT JOIN inventory i ON i.variant_id = v.id
                 WHERE v.product_id IN ($placeholders)
                 ORDER BY v.product_id ASC, v.id ASC"
            );
            $variantStmt->execute($productIds);
            $variantsByProduct = [];
            foreach ($variantStmt->fetchAll() as $variantRow) {
                $pid = (int) ($variantRow['product_id'] ?? 0);
                if ($pid <= 0) {
                    continue;
                }
                $variantsByProduct[$pid][] = [
                    'id' => (int) ($variantRow['id'] ?? 0),
                    'label' => (string) ($variantRow['label'] ?? ''),
                    'price_cents' => (int) ($variantRow['price_cents'] ?? 0),
                    'is_active' => (int) ($variantRow['is_active'] ?? 0) === 1,
                    'stock_qty' => (int) ($variantRow['stock_qty'] ?? 0),
                ];
            }
            foreach ($products as &$product) {
                $pid = (int) ($product['id'] ?? 0);
                $product['variants'] = $variantsByProduct[$pid] ?? [];
            }
            unset($product);
        }

        jsonResponse([
            'data' => $products,
            'meta' => [
                'categories' => $categories,
                'catalog_config' => productCatalogConfig(),
            ],
        ]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'create') {
        $name = trim((string) ($payload['name'] ?? ''));
        $categoryIdRaw = $payload['category_id'] ?? null;
        $categoryId = is_numeric($categoryIdRaw) ? (int) $categoryIdRaw : null;
        $status = trim((string) ($payload['status'] ?? 'active'));
        // New admin-created product starts lifecycle as NEW by default.
        $isNew = 1;
        $shortDescription = trim((string) ($payload['short_description'] ?? ''));
        $variantLabel = trim((string) ($payload['variant_label'] ?? ''));
        $creamType = trim((string) ($payload['cream_type'] ?? ''));
        $allFormats = !empty($payload['all_formats']);
        $priceFcfa = $payload['price_fcfa'] ?? 0;
        $stockQty = max(0, (int) ($payload['stock_qty'] ?? 0));
        $primaryImageUrl = sanitizeProductImagePath($payload['primary_image_url'] ?? null);
        $decorImageUrl = sanitizeProductImagePath($payload['decor_image_url'] ?? null);

        if ($name === '' || $categoryId === null || !isset($categoriesById[$categoryId])) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }
        if ($primaryImageUrl === null) {
            jsonResponse(['error' => 'Primary image is required'], 422);
        }
        if (!in_array($status, ['active', 'inactive', 'draft'], true)) {
            jsonResponse(['error' => 'Invalid status'], 422);
        }

        $categoryCatalogKey = (string) ($categoriesById[$categoryId]['catalog_key'] ?? '');
        if ($categoryCatalogKey === '') {
            jsonResponse(['error' => 'Unsupported category'], 422);
        }
        $allowedRows = [];
        $manualPriceCents = 0;
        if ($allFormats) {
            $allowedRows = productCatalogAllowedFormats($categoryCatalogKey, $creamType);
            if (!$allowedRows) {
                jsonResponse(['error' => 'Invalid format configuration for selected category'], 422);
            }
        } else {
            $manualPriceCents = fcfaToCents($priceFcfa);
            if ($variantLabel === '' || $manualPriceCents <= 0) {
                jsonResponse(['error' => 'Format ou prix manuel invalide'], 422);
            }
            $resolvedPriceCents = productCatalogAllowedFormatPrice($categoryCatalogKey, $variantLabel, $creamType);
            if ($resolvedPriceCents === null) {
                jsonResponse(['error' => 'Format invalide pour la categorie selectionnee'], 422);
            }
            $allowedRows = [
                ['label' => $variantLabel, 'price_cents' => $manualPriceCents],
            ];
        }

        $pdo->beginTransaction();
        $slug = ensureUniqueProductSlug($pdo, slugify($name));

        $productStmt = $pdo->prepare(
            'INSERT INTO products (category_id, name, slug, short_description, status, is_new)
             VALUES (:category_id, :name, :slug, :short_description, :status, :is_new)'
        );
        $productStmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':short_description' => $shortDescription !== '' ? $shortDescription : null,
            ':status' => $status,
            ':is_new' => $isNew,
        ]);
        $productId = (int) $pdo->lastInsertId();

        $variantStmt = $pdo->prepare(
            'INSERT INTO product_variants (product_id, sku, label, price_cents, currency, is_active)
             VALUES (:product_id, :sku, :label, :price_cents, :currency, :is_active)'
        );
        $inventoryStmt = $pdo->prepare(
            'INSERT INTO inventory (variant_id, stock_qty, reserved_qty)
             VALUES (:variant_id, :stock_qty, 0)'
        );
        $createdVariantIds = [];
        foreach ($allowedRows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $price = (int) ($row['price_cents'] ?? 0);
            if ($label === '' || $price <= 0) {
                continue;
            }
            $variantStmt->execute([
                ':product_id' => $productId,
                ':sku' => generateVariantSku($productId),
                ':label' => $label,
                ':price_cents' => $price,
                ':currency' => 'XOF',
                ':is_active' => 1,
            ]);
            $variantId = (int) $pdo->lastInsertId();
            $createdVariantIds[] = $variantId;
            $inventoryStmt->execute([
                ':variant_id' => $variantId,
                ':stock_qty' => $stockQty,
            ]);
        }
        if (!$createdVariantIds) {
            jsonResponse(['error' => 'No variant could be created'], 422);
        }

        $imageStmt = $pdo->prepare(
            'INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
             VALUES (:product_id, :image_url, :is_primary, :sort_order)'
        );
        $imageStmt->execute([
            ':product_id' => $productId,
            ':image_url' => $primaryImageUrl,
            ':is_primary' => 1,
            ':sort_order' => 0,
        ]);
        if ($decorImageUrl !== null) {
            $imageStmt->execute([
                ':product_id' => $productId,
                ':image_url' => $decorImageUrl,
                ':is_primary' => 0,
                ':sort_order' => 1,
            ]);
        }

        $pdo->commit();

        $afterStatuses = getProductStatusSnapshots($pdo, [$productId]);
        recordProductStatusTransitions($pdo, [], $afterStatuses, 'admin_product_create', [
            'product_id' => $productId,
            'variant_id' => $createdVariantIds[0] ?? null,
        ]);

        jsonResponse(['data' => ['product_id' => $productId, 'variant_ids' => $createdVariantIds]], 201);
    }

    if ($action === 'update') {
        $productId = (int) ($payload['product_id'] ?? 0);
        if ($productId <= 0) {
            jsonResponse(['error' => 'Invalid product'], 422);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $categoryIdRaw = $payload['category_id'] ?? null;
        $categoryId = is_numeric($categoryIdRaw) ? (int) $categoryIdRaw : null;
        $status = trim((string) ($payload['status'] ?? 'active'));
        $isNew = !empty($payload['is_new']) ? 1 : 0;
        $shortDescription = trim((string) ($payload['short_description'] ?? ''));
        $variantId = (int) ($payload['variant_id'] ?? 0);
        $variantLabel = trim((string) ($payload['variant_label'] ?? ''));
        $creamType = trim((string) ($payload['cream_type'] ?? ''));
        $allFormats = !empty($payload['all_formats']);
        $priceFcfa = $payload['price_fcfa'] ?? 0;
        $stockQty = max(0, (int) ($payload['stock_qty'] ?? 0));
        $primaryImageUrl = sanitizeProductImagePath($payload['primary_image_url'] ?? null);
        $decorImageUrl = sanitizeProductImagePath($payload['decor_image_url'] ?? null);

        if ($name === '' || !in_array($status, ['active', 'inactive', 'draft'], true) || $categoryId === null || !isset($categoriesById[$categoryId])) {
            jsonResponse(['error' => 'Invalid payload'], 422);
        }

        $hasPaidPurchase = productHasPaidPurchase($pdo, $productId);
        if ($hasPaidPurchase) {
            $isNew = 0;
        }

        $categoryCatalogKey = (string) ($categoriesById[$categoryId]['catalog_key'] ?? '');
        if ($categoryCatalogKey === '') {
            jsonResponse(['error' => 'Unsupported category'], 422);
        }
        $allowedRows = productCatalogAllowedFormats($categoryCatalogKey, $creamType);
        if (!$allowedRows) {
            jsonResponse(['error' => 'Invalid format configuration for selected category'], 422);
        }
        if (!$allFormats) {
            $resolvedPriceCents = productCatalogAllowedFormatPrice($categoryCatalogKey, $variantLabel, $creamType);
            if ($resolvedPriceCents === null) {
                jsonResponse(['error' => 'Invalid format for selected category'], 422);
            }
            $manualPriceCents = fcfaToCents($priceFcfa);
            $allowedRows = [
                ['label' => $variantLabel, 'price_cents' => $manualPriceCents > 0 ? $manualPriceCents : $resolvedPriceCents],
            ];
        }

        $beforeStatuses = getProductStatusSnapshots($pdo, [$productId]);
        $oldImagePaths = fetchProductImagePaths($pdo, $productId);

        $pdo->beginTransaction();
        $slug = ensureUniqueProductSlug($pdo, slugify($name), $productId);

        $productStmt = $pdo->prepare(
            'UPDATE products
             SET category_id = :category_id, name = :name, slug = :slug, short_description = :short_description,
                 status = :status, is_new = :is_new
             WHERE id = :id'
        );
        $productStmt->execute([
            ':category_id' => $categoryId,
            ':name' => $name,
            ':slug' => $slug,
            ':short_description' => $shortDescription !== '' ? $shortDescription : null,
            ':status' => $status,
            ':is_new' => $isNew,
            ':id' => $productId,
        ]);

        $inventoryStmt = $pdo->prepare(
            'INSERT INTO inventory (variant_id, stock_qty, reserved_qty)
             VALUES (:variant_id, :stock_qty, 0)
             ON DUPLICATE KEY UPDATE stock_qty = VALUES(stock_qty)'
        );

        $existingStmt = $pdo->prepare(
            'SELECT id, label
             FROM product_variants
             WHERE product_id = :product_id
             ORDER BY id ASC'
        );
        $existingStmt->execute([':product_id' => $productId]);
        $existingRows = $existingStmt->fetchAll();
        $existingById = [];
        $existingByLabel = [];
        foreach ($existingRows as $row) {
            $eid = (int) ($row['id'] ?? 0);
            $labelNorm = normalizeVariantLabel((string) ($row['label'] ?? ''));
            if ($eid > 0) {
                $existingById[$eid] = $labelNorm;
                if ($labelNorm !== '' && !isset($existingByLabel[$labelNorm])) {
                    $existingByLabel[$labelNorm] = $eid;
                }
            }
        }

        if ($allFormats) {
            $allowedByLabel = [];
            foreach ($allowedRows as $row) {
                $label = trim((string) ($row['label'] ?? ''));
                $price = (int) ($row['price_cents'] ?? 0);
                $norm = normalizeVariantLabel($label);
                if ($norm === '' || $price <= 0) {
                    continue;
                }
                $allowedByLabel[$norm] = ['label' => $label, 'price_cents' => $price];
            }

            $updateVariantStmt = $pdo->prepare(
                'UPDATE product_variants
                 SET label = :label, price_cents = :price_cents, is_active = 1
                 WHERE id = :id AND product_id = :product_id'
            );
            $insertVariantStmt = $pdo->prepare(
                'INSERT INTO product_variants (product_id, sku, label, price_cents, currency, is_active)
                 VALUES (:product_id, :sku, :label, :price_cents, :currency, :is_active)'
            );
            $deactivateStmt = $pdo->prepare(
                'UPDATE product_variants
                 SET is_active = 0
                 WHERE id = :id AND product_id = :product_id'
            );

            foreach ($allowedByLabel as $labelNorm => $row) {
                $existingVariantId = (int) ($existingByLabel[$labelNorm] ?? 0);
                if ($existingVariantId > 0) {
                    $updateVariantStmt->execute([
                        ':label' => $row['label'],
                        ':price_cents' => (int) $row['price_cents'],
                        ':id' => $existingVariantId,
                        ':product_id' => $productId,
                    ]);
                    $targetVariantId = $existingVariantId;
                } else {
                    $insertVariantStmt->execute([
                        ':product_id' => $productId,
                        ':sku' => generateVariantSku($productId),
                        ':label' => $row['label'],
                        ':price_cents' => (int) $row['price_cents'],
                        ':currency' => 'XOF',
                        ':is_active' => 1,
                    ]);
                    $targetVariantId = (int) $pdo->lastInsertId();
                }
                $inventoryStmt->execute([
                    ':variant_id' => $targetVariantId,
                    ':stock_qty' => $stockQty,
                ]);
            }

            foreach ($existingById as $eid => $labelNorm) {
                if (!isset($allowedByLabel[$labelNorm])) {
                    $deactivateStmt->execute([
                        ':id' => $eid,
                        ':product_id' => $productId,
                    ]);
                }
            }
        } else {
            // Manual mode: update the selected existing variant only.
            if (!isset($existingById[$variantId])) {
                jsonResponse(['error' => 'Variant not found for manual update'], 422);
            }
            $targetVariantId = $variantId;
            $variantUpdatePriceStmt = $pdo->prepare(
                'UPDATE product_variants
                 SET price_cents = :price_cents, is_active = 1
                 WHERE id = :id AND product_id = :product_id'
            );
            $variantUpdatePriceStmt->execute([
                ':price_cents' => $manualPriceCents,
                ':id' => $targetVariantId,
                ':product_id' => $productId,
            ]);
            $inventoryStmt->execute([
                ':variant_id' => $targetVariantId,
                ':stock_qty' => $stockQty,
            ]);
        }

        if ($primaryImageUrl !== null) {
            $existingPrimaryStmt = $pdo->prepare(
                'SELECT id
                 FROM product_images
                 WHERE product_id = :product_id AND is_primary = 1
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $existingPrimaryStmt->execute([':product_id' => $productId]);
            $existingPrimaryId = (int) ($existingPrimaryStmt->fetchColumn() ?: 0);

            if ($existingPrimaryId > 0) {
                $upd = $pdo->prepare(
                    'UPDATE product_images
                     SET image_url = :image_url, sort_order = 0
                     WHERE id = :id'
                );
                $upd->execute([
                    ':id' => $existingPrimaryId,
                    ':image_url' => $primaryImageUrl,
                ]);
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
                     VALUES (:product_id, :image_url, 1, 0)'
                );
                $ins->execute([
                    ':product_id' => $productId,
                    ':image_url' => $primaryImageUrl,
                ]);
            }
        }

        if ($decorImageUrl !== null) {
            $existingDecorStmt = $pdo->prepare(
                'SELECT id
                 FROM product_images
                 WHERE product_id = :product_id AND is_primary = 0
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1'
            );
            $existingDecorStmt->execute([':product_id' => $productId]);
            $existingDecorId = (int) ($existingDecorStmt->fetchColumn() ?: 0);

            if ($existingDecorId > 0) {
                $updDecor = $pdo->prepare(
                    'UPDATE product_images
                     SET image_url = :image_url, sort_order = 1
                     WHERE id = :id'
                );
                $updDecor->execute([
                    ':id' => $existingDecorId,
                    ':image_url' => $decorImageUrl,
                ]);
            } else {
                $insDecor = $pdo->prepare(
                    'INSERT INTO product_images (product_id, image_url, is_primary, sort_order)
                     VALUES (:product_id, :image_url, 0, 1)'
                );
                $insDecor->execute([
                    ':product_id' => $productId,
                    ':image_url' => $decorImageUrl,
                ]);
            }
        }

        $pdo->commit();

        if ($primaryImageUrl !== null || $decorImageUrl !== null) {
            $newImagePaths = fetchProductImagePaths($pdo, $productId);
            $newMap = array_fill_keys($newImagePaths, true);
            foreach ($oldImagePaths as $oldPath) {
                if (!isset($newMap[$oldPath])) {
                    deleteImageVariantSet($oldPath);
                }
            }
        }

        $afterStatuses = getProductStatusSnapshots($pdo, [$productId]);
        recordProductStatusTransitions($pdo, $beforeStatuses, $afterStatuses, 'admin_product_update', [
            'product_id' => $productId,
            'variant_id' => $variantId > 0 ? $variantId : null,
        ]);

        jsonResponse(['data' => ['product_id' => $productId]]);
    }

    if ($action === 'archive') {
        $productId = (int) ($payload['product_id'] ?? 0);
        if ($productId <= 0) {
            jsonResponse(['error' => 'Invalid product'], 422);
        }

        $stmt = $pdo->prepare('UPDATE products SET status = :status WHERE id = :id');
        $stmt->execute([
            ':status' => 'inactive',
            ':id' => $productId,
        ]);
        jsonResponse(['data' => ['product_id' => $productId, 'status' => 'inactive']]);
    }

    if ($action === 'delete') {
        $productId = (int) ($payload['product_id'] ?? 0);
        if ($productId <= 0) {
            jsonResponse(['error' => 'Invalid product'], 422);
        }

        $existsStmt = $pdo->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $existsStmt->execute([':id' => $productId]);
        if (!$existsStmt->fetchColumn()) {
            jsonResponse(['error' => 'Product not found'], 404);
        }
        $imagePathsToDelete = fetchProductImagePaths($pdo, $productId);

        try {
            $deleteStmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $deleteStmt->execute([':id' => $productId]);
        } catch (Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'foreign key constraint fails')) {
                jsonResponse(['error' => 'Impossible de supprimer ce produit : il est lie a des commandes existantes.'], 409);
            }
            throw $e;
        }

        foreach ($imagePathsToDelete as $path) {
            deleteImageVariantSet($path);
        }

        jsonResponse(['data' => ['product_id' => $productId, 'deleted' => true]]);
    }

    if ($action === 'delete_many') {
        $idsRaw = $payload['product_ids'] ?? null;
        if (!is_array($idsRaw) || !$idsRaw) {
            jsonResponse(['error' => 'Invalid product_ids'], 422);
        }

        $productIds = array_values(array_unique(array_map('intval', $idsRaw)));
        $productIds = array_values(array_filter($productIds, static fn (int $id): bool => $id > 0));
        if (!$productIds) {
            jsonResponse(['error' => 'Invalid product_ids'], 422);
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $existsStmt = $pdo->prepare("SELECT id FROM products WHERE id IN ($placeholders)");
        $existsStmt->execute($productIds);
        $existing = array_map('intval', array_column($existsStmt->fetchAll(), 'id'));
        if (!$existing) {
            jsonResponse(['error' => 'No products found'], 404);
        }

        $imagePathsByProduct = [];
        $placeholdersExisting = implode(',', array_fill(0, count($existing), '?'));
        $imgStmt = $pdo->prepare(
            "SELECT product_id, image_url
             FROM product_images
             WHERE product_id IN ($placeholdersExisting)"
        );
        $imgStmt->execute($existing);
        foreach ($imgStmt->fetchAll() as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $image = (string) ($row['image_url'] ?? '');
            if ($pid > 0 && $image !== '') {
                $imagePathsByProduct[$pid][] = $image;
            }
        }

        $pdo->beginTransaction();
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholdersExisting)");
            $deleteStmt->execute($existing);
            $deletedCount = $deleteStmt->rowCount();
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains(strtolower($e->getMessage()), 'foreign key constraint fails')) {
                jsonResponse(['error' => 'Impossible de supprimer un ou plusieurs produits : ils sont lies a des commandes existantes.'], 409);
            }
            throw $e;
        }

        foreach ($imagePathsByProduct as $images) {
            foreach ($images as $path) {
                deleteImageVariantSet($path);
            }
        }

        jsonResponse([
            'data' => [
                'deleted_count' => $deletedCount,
                'product_ids' => $existing,
            ],
        ]);
    }

    jsonResponse(['error' => 'Unsupported action'], 400);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
