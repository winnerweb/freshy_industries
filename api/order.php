<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

$pdo = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    requireFrontendCsrf();

    $payload = readJsonInput();
    $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
    $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];

    $fullName = trim((string) ($customer['full_name'] ?? ''));
    $phoneRaw = trim((string) ($customer['phone'] ?? ''));
    $email = trim((string) ($customer['email'] ?? ''));

    $country = trim((string) ($address['country'] ?? ''));
    $city = trim((string) ($address['city'] ?? ''));
    $neighborhood = trim((string) ($address['neighborhood'] ?? ''));
    $recipient = trim((string) ($address['recipient_name'] ?? $fullName));

    if ($fullName === '' || $phoneRaw === '' || $country === '' || $city === '' || $recipient === '') {
        jsonResponse(['error' => 'Missing required fields'], 422);
    }

    $phone = preg_replace('/\D+/', '', $phoneRaw) ?: '';
    if (strlen($phone) < 8 || strlen($phone) > 20) {
        jsonResponse(['error' => 'Invalid phone number'], 422);
    }

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        jsonResponse(['error' => 'Invalid email address'], 422);
    }

    $sessionId = getSessionId();
    $cartId = findOrCreateCartId($sessionId);

    $pdo = db();
    $items = fetchCartItems($cartId);
    if (!$items) {
        jsonResponse(['error' => 'Cart is empty'], 422);
    }

    $subtotal = 0;
    $normalizedItems = [];
    foreach ($items as $item) {
        $variantId = (int) $item['variant_id'];
        $quantity = (int) $item['quantity'];

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

        $unitPrice = (int) $variant['price_cents'];
        $subtotal += $unitPrice * $quantity;
        $normalizedItems[] = [
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPrice,
        ];
    }

    $shipping = 0;
    $total = $subtotal + $shipping;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'INSERT INTO customers (full_name, phone, email)
         VALUES (:full_name, :phone, :email)'
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':phone' => $phone,
        ':email' => $email !== '' ? $email : null,
    ]);
    $customerId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare(
        'INSERT INTO addresses (customer_id, country, city, neighborhood, recipient_name, phone, email)
         VALUES (:customer_id, :country, :city, :neighborhood, :recipient_name, :phone, :email)'
    );
    $stmt->execute([
        ':customer_id' => $customerId,
        ':country' => $country,
        ':city' => $city,
        ':neighborhood' => $neighborhood !== '' ? $neighborhood : null,
        ':recipient_name' => $recipient,
        ':phone' => $phone,
        ':email' => $email !== '' ? $email : null,
    ]);
    $addressId = (int) $pdo->lastInsertId();

    $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $stmt = $pdo->prepare(
        'INSERT INTO orders (order_number, customer_id, address_id, status, subtotal_cents, shipping_cents, total_cents, currency)
         VALUES (:order_number, :customer_id, :address_id, :status, :subtotal, :shipping, :total, :currency)'
    );
    $stmt->execute([
        ':order_number' => $orderNumber,
        ':customer_id' => $customerId,
        ':address_id' => $addressId,
        ':status' => 'pending',
        ':subtotal' => $subtotal,
        ':shipping' => $shipping,
        ':total' => $total,
        ':currency' => 'XOF',
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, variant_id, quantity, unit_price_cents)
         VALUES (:order_id, :variant_id, :quantity, :unit_price)'
    );
    foreach ($normalizedItems as $item) {
        $itemStmt->execute([
            ':order_id' => $orderId,
            ':variant_id' => $item['variant_id'],
            ':quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price_cents'],
        ]);
    }

    $pdo->prepare('UPDATE carts SET status = :status WHERE id = :id')
        ->execute([':status' => 'converted', ':id' => $cartId]);
    $pdo->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id')
        ->execute([':cart_id' => $cartId]);

    $pdo->commit();

    jsonResponse([
        'data' => [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'subtotal_cents' => $subtotal,
            'shipping_cents' => $shipping,
            'total_cents' => $total,
            'currency' => 'XOF',
        ],
    ], 201);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
