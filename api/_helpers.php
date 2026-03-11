<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function getSessionId(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (!isset($_SESSION['cart_session'])) {
        $_SESSION['cart_session'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['cart_session'];
}

function findOrCreateCartId(string $sessionId): int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id
         FROM carts
         WHERE session_id = :sid AND status = :status
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([':sid' => $sessionId, ':status' => 'active']);
    $cartId = $stmt->fetchColumn();
    if ($cartId) {
        return (int) $cartId;
    }

    // Preferred path: create a new active cart for the session.
    $stmt = $pdo->prepare('INSERT INTO carts (session_id, status) VALUES (:sid, :status)');
    try {
        $stmt->execute([':sid' => $sessionId, ':status' => 'active']);
        return (int) $pdo->lastInsertId();
    } catch (Throwable $e) {
        // Fallback for schemas that enforce unique session_id.
    }

    $stmt = $pdo->prepare('SELECT id FROM carts WHERE session_id = :sid ORDER BY id DESC LIMIT 1');
    $stmt->execute([':sid' => $sessionId]);
    $existingId = $stmt->fetchColumn();
    if (!$existingId) {
        throw new RuntimeException('Unable to find or create cart');
    }

    $cartId = (int) $existingId;
    $pdo->prepare('UPDATE carts SET status = :status WHERE id = :id')
        ->execute([':status' => 'active', ':id' => $cartId]);
    $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')
        ->execute([':cart_id' => $cartId]);

    return $cartId;
}

function fetchCartItems(int $cartId): array
{
    $pdo = db();
    $hasSaleMode = columnExists($pdo, 'cart_items', 'sale_mode');
    $saleModeSelect = $hasSaleMode ? 'ci.sale_mode,' : "'unit' AS sale_mode,";
    $stmt = $pdo->prepare(
        'SELECT ci.id, ci.variant_id, ci.quantity, ' . $saleModeSelect . ' ci.unit_price_cents,
                v.label AS variant_label, v.sku,
                p.id AS product_id, p.name, p.slug,
                img.image_url,
                img_decor.image_url AS decor_image_url
         FROM cart_items ci
         JOIN product_variants v ON v.id = ci.variant_id
         JOIN products p ON p.id = v.product_id
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
         WHERE ci.cart_id = :cart_id'
    );
    $stmt->execute([':cart_id' => $cartId]);
    return $stmt->fetchAll();
}

function fetchVariantForSale(int $variantId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT v.id,
                v.id AS variant_id,
                v.product_id,
                v.sku,
                v.label,
                v.price_cents,
                v.currency,
                v.is_active,
                i.id AS inventory_id,
                i.stock_qty,
                i.reserved_qty
         FROM product_variants v
         LEFT JOIN inventory i ON i.variant_id = v.id
         WHERE v.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $variantId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function validateVariantSale(array $variant, int $quantity): ?string
{
    if ((int) ($variant['is_active'] ?? 0) !== 1) {
        return 'Variant not found';
    }

    $saleMode = strtolower((string) ($variant['sale_mode'] ?? 'unit'));
    $hasInventory = array_key_exists('stock_qty', $variant) && $variant['stock_qty'] !== null;
    if ($hasInventory) {
        $availableUnits = (int) $variant['stock_qty'] - (int) ($variant['reserved_qty'] ?? 0);
        $available = $availableUnits;
        if ($saleMode === 'carton' && array_key_exists('stock_cartons', $variant) && $variant['stock_cartons'] !== null) {
            $available = (int) $variant['stock_cartons'];
        }
        if ($quantity > $available) {
            return 'Insufficient stock';
        }
    }

    return null;
}

function tableExists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    $key = strtolower($tableName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table
         LIMIT 1'
    );
    $stmt->execute([':table' => $tableName]);
    $cache[$key] = (bool) $stmt->fetchColumn();
    return $cache[$key];
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    $key = strtolower($tableName . '::' . $columnName);
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column
         LIMIT 1'
    );
    $stmt->execute([':table' => $tableName, ':column' => $columnName]);
    $cache[$key] = (bool) $stmt->fetchColumn();
    return $cache[$key];
}

function resolveCartonPriceCents(PDO $pdo, array $variant, int $cartonsQty): int
{
    $defaultPrice = max(0, (int) ($variant['price_cents'] ?? 0));
    if ($cartonsQty <= 0) {
        return $defaultPrice;
    }

    $variantId = (int) ($variant['id'] ?? 0);
    if ($variantId <= 0) {
        return $defaultPrice;
    }

    // 1) Category-level tiers (applies to all variants in category).
    if (tableExists($pdo, 'carton_category_pricing_tiers')) {
        if ($variantId > 0) {
            $categoryStmt = $pdo->prepare(
                'SELECT p.category_id
                 FROM product_variants v
                 JOIN products p ON p.id = v.product_id
                 WHERE v.id = :variant_id
                 LIMIT 1'
            );
            try {
                $categoryStmt->execute([':variant_id' => $variantId]);
                $categoryId = (int) ($categoryStmt->fetchColumn() ?: 0);
                if ($categoryId > 0) {
                    $tierStmt = $pdo->prepare(
                        'SELECT price_per_carton_cents
                         FROM carton_category_pricing_tiers
                         WHERE category_id = :category_id
                           AND min_cartons <= :qty_min
                           AND (max_cartons IS NULL OR max_cartons = 0 OR max_cartons >= :qty_max)
                         ORDER BY min_cartons DESC
                         LIMIT 1'
                    );
                    $tierStmt->execute([
                        ':category_id' => $categoryId,
                        ':qty_min' => $cartonsQty,
                        ':qty_max' => $cartonsQty,
                    ]);
                    $categoryPrice = (int) ($tierStmt->fetchColumn() ?: 0);
                    if ($categoryPrice > 0) {
                        return $categoryPrice;
                    }
                }
            } catch (Throwable $e) {
                // Keep safe fallback to default variant price.
            }
        }
    }

    // 2) Variant-level tiers, if configured.
    if (tableExists($pdo, 'pricing_tiers')) {
        $stmt = $pdo->prepare(
            'SELECT *
             FROM pricing_tiers
             WHERE variant_id = :variant_id
               AND min_cartons <= :qty_min
               AND (max_cartons IS NULL OR max_cartons = 0 OR max_cartons >= :qty_max)
             ORDER BY min_cartons DESC
             LIMIT 1'
        );
        try {
            $stmt->execute([
                ':variant_id' => $variantId,
                ':qty_min' => $cartonsQty,
                ':qty_max' => $cartonsQty,
            ]);
            $row = $stmt->fetch();
            if ($row) {
                $keys = ['price_per_carton_cents', 'prix_par_carton', 'price_cents'];
                foreach ($keys as $key) {
                    if (isset($row[$key]) && (int) $row[$key] > 0) {
                        return (int) $row[$key];
                    }
                }
            }
        } catch (Throwable $e) {
            // Ignore and keep default fallback.
        }
    }

    return $defaultPrice;
}

function requireAdminApi(array $allowedRoles = ['operator', 'manager', 'admin']): array
{
    $adminAuthPath = __DIR__ . '/../Admin/includes/admin_auth.php';
    if (!is_file($adminAuthPath)) {
        jsonResponse(['error' => 'Admin auth module missing'], 500);
    }
    require_once $adminAuthPath;

    $user = adminCurrentUser();
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
    if (!adminUserHasRole((string) $user['role'], $allowedRoles)) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (!adminValidateCsrfToken($token)) {
            jsonResponse(['error' => 'Invalid CSRF token'], 419);
        }
    }

    return $user;
}

function requireFrontendCsrf(): void
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $token = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!csrfValidate($token)) {
        jsonResponse(['error' => 'Invalid CSRF token'], 419);
    }
}
