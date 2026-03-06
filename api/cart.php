<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

function buildCartSummary(array $items): array
{
    $totalQuantity = 0;
    $subtotalCents = 0;

    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 0);
        $price = (int) ($item['unit_price_cents'] ?? 0);
        $totalQuantity += $qty;
        $subtotalCents += $qty * $price;
    }

    return [
        'line_count' => count($items),
        'total_quantity' => $totalQuantity,
        'subtotal_cents' => $subtotalCents,
        'currency' => 'XOF',
    ];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'POST') {
        requireFrontendCsrf();
    }
    $sessionId = getSessionId();
    $cartId = findOrCreateCartId($sessionId);

    $pdo = db();
    $hasSaleMode = columnExists($pdo, 'cart_items', 'sale_mode');

    if ($method === 'GET') {
        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    $payload = readJsonInput();
    $action = $payload['action'] ?? '';

    if ($method === 'POST' && $action === 'add') {
        $variantId = (int) ($payload['variant_id'] ?? 0);
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        if ($variantId <= 0) {
            jsonResponse(['error' => 'Invalid variant'], 422);
        }

        $variant = fetchVariantForSale($variantId);
        if (!$variant) {
            jsonResponse(['error' => 'Variant not found'], 404);
        }

        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id');
        $stmt->execute([':cart_id' => $cartId, ':variant_id' => $variantId]);
        $existing = $stmt->fetch();

        $targetQty = $existing ? (int) $existing['quantity'] + $quantity : $quantity;
        $validationError = validateVariantSale($variant, $targetQty);
        if ($validationError === 'Variant not found') {
            jsonResponse(['error' => 'Variant not found'], 404);
        }
        if ($validationError === 'Insufficient stock') {
            jsonResponse(['error' => 'Insufficient stock'], 409);
        }

        $unitPrice = (int) $variant['price_cents'];
        if ($existing) {
            if ($hasSaleMode) {
                $update = $pdo->prepare('UPDATE cart_items SET quantity = :qty, sale_mode = :sale_mode, unit_price_cents = :price WHERE id = :id');
                $update->execute([
                    ':qty' => $targetQty,
                    ':sale_mode' => 'unit',
                    ':price' => $unitPrice,
                    ':id' => (int) $existing['id'],
                ]);
            } else {
                $update = $pdo->prepare('UPDATE cart_items SET quantity = :qty, unit_price_cents = :price WHERE id = :id');
                $update->execute([
                    ':qty' => $targetQty,
                    ':price' => $unitPrice,
                    ':id' => (int) $existing['id'],
                ]);
            }
        } else {
            if ($hasSaleMode) {
                $insert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity, sale_mode, unit_price_cents)
                     VALUES (:cart_id, :variant_id, :qty, :sale_mode, :price)'
                );
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':variant_id' => $variantId,
                    ':qty' => $quantity,
                    ':sale_mode' => 'unit',
                    ':price' => $unitPrice,
                ]);
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity, unit_price_cents)
                     VALUES (:cart_id, :variant_id, :qty, :price)'
                );
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':variant_id' => $variantId,
                    ':qty' => $quantity,
                    ':price' => $unitPrice,
                ]);
            }
        }

        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    if ($method === 'POST' && $action === 'add_carton') {
        $variantId = (int) ($payload['variant_id'] ?? 0);
        $cartonsQty = max(1, (int) ($payload['cartons_qty'] ?? 1));

        if ($variantId <= 0) {
            jsonResponse(['error' => 'Invalid variant'], 422);
        }

        $variant = fetchVariantForSale($variantId);
        if (!$variant) {
            jsonResponse(['error' => 'Variant not found'], 404);
        }

        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id');
        $stmt->execute([':cart_id' => $cartId, ':variant_id' => $variantId]);
        $existing = $stmt->fetch();

        $targetQty = $existing ? (int) $existing['quantity'] + $cartonsQty : $cartonsQty;
        $validationError = validateVariantSale($variant, $targetQty);
        if ($validationError === 'Variant not found') {
            jsonResponse(['error' => 'Variant not found'], 404);
        }
        if ($validationError === 'Insufficient stock') {
            jsonResponse(['error' => 'Insufficient stock'], 409);
        }

        $unitPrice = resolveCartonPriceCents($pdo, $variant, $targetQty);
        if ($existing) {
            if ($hasSaleMode) {
                $update = $pdo->prepare('UPDATE cart_items SET quantity = :qty, sale_mode = :sale_mode, unit_price_cents = :price WHERE id = :id');
                $update->execute([
                    ':qty' => $targetQty,
                    ':sale_mode' => 'carton',
                    ':price' => $unitPrice,
                    ':id' => (int) $existing['id'],
                ]);
            } else {
                $update = $pdo->prepare('UPDATE cart_items SET quantity = :qty, unit_price_cents = :price WHERE id = :id');
                $update->execute([
                    ':qty' => $targetQty,
                    ':price' => $unitPrice,
                    ':id' => (int) $existing['id'],
                ]);
            }
        } else {
            if ($hasSaleMode) {
                $insert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity, sale_mode, unit_price_cents)
                     VALUES (:cart_id, :variant_id, :qty, :sale_mode, :price)'
                );
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':variant_id' => $variantId,
                    ':qty' => $cartonsQty,
                    ':sale_mode' => 'carton',
                    ':price' => $unitPrice,
                ]);
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity, unit_price_cents)
                     VALUES (:cart_id, :variant_id, :qty, :price)'
                );
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':variant_id' => $variantId,
                    ':qty' => $cartonsQty,
                    ':price' => $unitPrice,
                ]);
            }
        }

        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    if ($method === 'POST' && $action === 'update') {
        $variantId = (int) ($payload['variant_id'] ?? 0);
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        if ($variantId <= 0) {
            jsonResponse(['error' => 'Invalid variant'], 422);
        }

        $variant = fetchVariantForSale($variantId);
        if (!$variant) {
            jsonResponse(['error' => 'Variant not found'], 404);
        }

        $validationError = validateVariantSale($variant, $quantity);
        if ($validationError === 'Variant not found') {
            jsonResponse(['error' => 'Variant not found'], 404);
        }
        if ($validationError === 'Insufficient stock') {
            jsonResponse(['error' => 'Insufficient stock'], 409);
        }

        $existingStmt = $pdo->prepare(
            'SELECT id' . ($hasSaleMode ? ', sale_mode' : '') . '
             FROM cart_items
             WHERE cart_id = :cart_id AND variant_id = :variant_id
             LIMIT 1'
        );
        $existingStmt->execute([':cart_id' => $cartId, ':variant_id' => $variantId]);
        $existingLine = $existingStmt->fetch();
        $saleMode = $hasSaleMode ? strtolower((string) ($existingLine['sale_mode'] ?? 'unit')) : 'unit';
        $priceCents = $saleMode === 'carton'
            ? resolveCartonPriceCents($pdo, $variant, $quantity)
            : (int) $variant['price_cents'];

        $stmt = $pdo->prepare(
            'UPDATE cart_items
             SET quantity = :qty, unit_price_cents = :price
             WHERE cart_id = :cart_id AND variant_id = :variant_id'
        );
        $stmt->execute([
            ':qty' => $quantity,
            ':price' => $priceCents,
            ':cart_id' => $cartId,
            ':variant_id' => $variantId,
        ]);

        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    if ($method === 'POST' && $action === 'remove') {
        $variantId = (int) ($payload['variant_id'] ?? 0);
        if ($variantId <= 0) {
            jsonResponse(['error' => 'Invalid variant'], 422);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM cart_items WHERE cart_id = :cart_id AND variant_id = :variant_id'
        );
        $stmt->execute([':cart_id' => $cartId, ':variant_id' => $variantId]);

        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    if ($method === 'POST' && $action === 'replace') {
        $itemsPayload = $payload['items'] ?? [];
        if (!is_array($itemsPayload)) {
            jsonResponse(['error' => 'Invalid items'], 422);
        }

        $normalized = [];
        foreach ($itemsPayload as $item) {
            $variantId = (int) ($item['variant_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));

            if ($variantId <= 0) {
                jsonResponse(['error' => 'Invalid variant'], 422);
            }

            $variant = fetchVariantForSale($variantId);
            if (!$variant) {
                jsonResponse(['error' => 'Variant not found'], 404);
            }

            $validationError = validateVariantSale($variant, $quantity);
            if ($validationError === 'Variant not found') {
                jsonResponse(['error' => 'Variant not found'], 404);
            }
            if ($validationError === 'Insufficient stock') {
                jsonResponse(['error' => 'Insufficient stock'], 409);
            }

            $normalized[] = [
                'variant_id' => $variantId,
                'quantity' => $quantity,
                'price' => (int) $variant['price_cents'],
            ];
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')
                ->execute([':cart_id' => $cartId]);

            if ($normalized) {
                $insert = $pdo->prepare(
                    'INSERT INTO cart_items (cart_id, variant_id, quantity' . ($hasSaleMode ? ', sale_mode' : '') . ', unit_price_cents)
                     VALUES (:cart_id, :variant_id, :qty' . ($hasSaleMode ? ', :sale_mode' : '') . ', :price)'
                );

                foreach ($normalized as $item) {
                    $insert->execute([
                        ':cart_id' => $cartId,
                        ':variant_id' => $item['variant_id'],
                        ':qty' => $item['quantity'],
                        ':price' => $item['price'],
                    ] + ($hasSaleMode ? [':sale_mode' => 'unit'] : []));
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $items = fetchCartItems($cartId);
        jsonResponse(['data' => $items, 'summary' => buildCartSummary($items)]);
    }

    jsonResponse(['error' => 'Unsupported request'], 400);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
