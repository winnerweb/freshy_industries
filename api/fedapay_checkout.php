<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../config/fedapay.php';
require_once __DIR__ . '/application/payment_application_service.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FedaPay\Customer;
use FedaPay\FedaPay;
use FedaPay\Transaction;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    requireFrontendCsrf();

    $cfg = fedapayConfig();
    if (!fedapayConfigIsReady($cfg)) {
        jsonResponse(['error' => 'FedaPay configuration missing'], 500);
    }

    $payload = readJsonInput();
    $orderId = (int) ($payload['order_id'] ?? 0);
    if ($orderId <= 0) {
        jsonResponse(['error' => 'Invalid order'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT o.id, o.order_number, o.status, o.total_cents, o.currency,
                c.full_name, c.email, c.phone
         FROM orders o
         LEFT JOIN customers c ON c.id = o.customer_id
         WHERE o.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        jsonResponse(['error' => 'Order not found'], 404);
    }
    if ((string) ($order['status'] ?? '') !== 'pending') {
        jsonResponse(['error' => 'Order is not payable'], 409);
    }

    FedaPay::setApiKey($cfg['secret_key']);
    FedaPay::setEnvironment($cfg['sdk_environment']);

    $existingStmt = $pdo->prepare(
        'SELECT id, provider_ref, status
         FROM payments
         WHERE order_id = :order_id
           AND provider = :provider
         ORDER BY id DESC
         LIMIT 1'
    );
    $existingStmt->execute([
        ':order_id' => $orderId,
        ':provider' => 'fedapay',
    ]);
    $existingPayment = $existingStmt->fetch();
    if ($existingPayment) {
        $existingRef = trim((string) ($existingPayment['provider_ref'] ?? ''));
        if ($existingRef !== '' && ctype_digit($existingRef)) {
            $existingTransaction = Transaction::retrieve((int) $existingRef);
            $existingStatus = strtolower(trim((string) ($existingTransaction->status ?? 'pending')));

            $pdo->beginTransaction();
            $settlement = paymentAppSettleFedapayByProviderRef($pdo, $existingRef, $existingStatus);
            if (empty($settlement['error'])) {
                $pdo->commit();
                $localPaymentStatus = (string) ($settlement['data']['payment_status'] ?? 'pending');
                if ($localPaymentStatus === 'paid') {
                    jsonResponse(['error' => 'Order already paid'], 409);
                }
            } else {
                $pdo->rollBack();
            }

            if ($existingStatus === 'pending') {
                $tokenObject = $existingTransaction->generateToken();
                $checkoutUrl = trim((string) ($tokenObject->url ?? ''));
                if ($checkoutUrl !== '') {
                    jsonResponse([
                        'data' => [
                            'order_id' => $orderId,
                            'payment_id' => (int) ($existingPayment['id'] ?? 0),
                            'provider' => 'fedapay',
                            'provider_ref' => $existingRef,
                            'checkout_url' => $checkoutUrl,
                            'environment' => $cfg['environment'],
                            'reused' => true,
                        ],
                    ]);
                }
            }
        }
    }

    $fullName = trim((string) ($order['full_name'] ?? 'Client'));
    $parts = preg_split('/\s+/', $fullName) ?: ['Client'];
    $firstname = trim((string) ($parts[0] ?? 'Client'));
    $lastname = trim((string) implode(' ', array_slice($parts, 1)));
    if ($lastname === '') {
        $lastname = 'Freshy';
    }
    $phoneDigits = preg_replace('/\D+/', '', (string) ($order['phone'] ?? '')) ?: '';
    if (str_starts_with($phoneDigits, '229') && strlen($phoneDigits) === 11) {
        $phoneDigits = substr($phoneDigits, 3);
    }
    if (($cfg['environment'] ?? 'test') === 'test') {
        $sandboxAllowed = ['0166000001', '64000001', '66000000', '64000000'];
        if (!in_array($phoneDigits, $sandboxAllowed, true)) {
            jsonResponse([
                'error' => 'En mode test FedaPay, utilisez un numero sandbox: MTN succes 66000001, MOOV succes 64000001, MTN echec 66000000, MOOV echec 64000000.',
            ], 422);
        }
    }

    $customerPayload = [
        'firstname' => mb_substr($firstname, 0, 60),
        'lastname' => mb_substr($lastname, 0, 80),
        'phone' => $phoneDigits,
    ];
    $email = trim((string) ($order['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $customerPayload['email'] = mb_strtolower($email);
    }
    $customer = Customer::create($customerPayload);

    $projectBaseUrl = fedapayProjectBaseUrlFromRequest();
    $callbackUrl = $projectBaseUrl . '/api/fedapay_return.php?order_id=' . urlencode((string) $orderId);

    $amountFcfa = max(1, (int) floor(((int) ($order['total_cents'] ?? 0)) / 100));
    $transaction = Transaction::create([
        'description' => 'Commande ' . (string) ($order['order_number'] ?? ('#' . $orderId)),
        'amount' => $amountFcfa,
        'callback_url' => $callbackUrl,
        'currency' => ['iso' => $cfg['default_currency_iso']],
        'customer' => ['id' => (int) ($customer->id ?? 0)],
    ]);

    $providerRef = (string) ($transaction->id ?? '');
    if ($providerRef === '') {
        jsonResponse(['error' => 'Failed to initialize FedaPay transaction'], 502);
    }

    $pdo->beginTransaction();
    $paymentStmt = $pdo->prepare(
        'INSERT INTO payments (order_id, provider, provider_ref, status, amount_cents, currency)
         VALUES (:order_id, :provider, :provider_ref, :status, :amount_cents, :currency)'
    );
    $paymentStmt->execute([
        ':order_id' => $orderId,
        ':provider' => 'fedapay',
        ':provider_ref' => $providerRef,
        ':status' => 'pending',
        ':amount_cents' => (int) ($order['total_cents'] ?? 0),
        ':currency' => (string) ($order['currency'] ?? 'XOF'),
    ]);
    $paymentId = (int) $pdo->lastInsertId();
    $pdo->commit();

    $tokenObject = $transaction->generateToken();
    $checkoutUrl = trim((string) ($tokenObject->url ?? ''));
    if ($checkoutUrl === '') {
        jsonResponse(['error' => 'Failed to initialize checkout URL'], 502);
    }

    jsonResponse([
        'data' => [
            'order_id' => $orderId,
            'payment_id' => $paymentId,
            'provider' => 'fedapay',
            'provider_ref' => $providerRef,
            'checkout_url' => $checkoutUrl,
            'environment' => $cfg['environment'],
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
