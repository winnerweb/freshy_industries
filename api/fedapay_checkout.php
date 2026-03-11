<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../config/fedapay.php';
require_once __DIR__ . '/application/payment_application_service.php';

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!function_exists('fedapayCheckoutWriteDebug')) {
    function fedapayCheckoutWriteDebug(array $entry): void
    {
        try {
            $dir = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                return;
            }
            $file = $dir . '/fedapay_checkout.log';
            $payload = [
                'at' => date('c'),
                'entry' => $entry,
            ];
            @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Never break checkout flow for debug logging.
        }
    }
}

if (!is_file($autoloadPath)) {
    fedapayCheckoutWriteDebug([
        'stage' => 'sdk_bootstrap',
        'error' => 'FedaPay SDK missing',
        'detail' => 'vendor/autoload.php introuvable',
    ]);
    jsonResponse([
        'error' => 'FedaPay SDK missing',
        'detail' => 'vendor/autoload.php introuvable. Deployer le dossier vendor (composer install).',
    ], 500);
}
require_once $autoloadPath;

use FedaPay\Customer;
use FedaPay\FedaPay;
use FedaPay\Transaction;

try {
    $stage = 'request_validation';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
    requireFrontendCsrf();

    $stage = 'config_loading';
    $cfg = fedapayConfig();
    if (!fedapayConfigIsReady($cfg)) {
        $missing = [];
        if (trim((string) ($cfg['public_key'] ?? '')) === '') {
            $missing[] = 'FEDAPAY_PUBLIC_KEY';
        }
        if (trim((string) ($cfg['secret_key'] ?? '')) === '') {
            $missing[] = 'FEDAPAY_SECRET_KEY';
        }
        fedapayCheckoutWriteDebug([
            'stage' => 'config_loading',
            'error' => 'FedaPay configuration missing',
            'missing' => $missing,
        ]);
        jsonResponse([
            'error' => 'FedaPay configuration missing',
            'detail' => $missing !== [] ? ('Variables manquantes: ' . implode(', ', $missing)) : 'Configuration incomplete',
        ], 500);
    }

    $stage = 'payload_read';
    $payload = readJsonInput();
    $orderId = (int) ($payload['order_id'] ?? 0);
    if ($orderId <= 0) {
        jsonResponse(['error' => 'Invalid order'], 422);
    }

    $stage = 'db_order_lookup';
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

    $stage = 'fedapay_sdk_setup';
    FedaPay::setApiKey($cfg['secret_key']);
    FedaPay::setEnvironment($cfg['sdk_environment']);

    $stage = 'existing_payment_lookup';
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
                'error' => 'En mode test FedaPay, utilisez un numero sandbox: MTN succes 0166000001, MOOV succes 0164000001, MTN echec 66000000, MOOV echec 64000000.',
            ], 422);
        }
    }

    $stage = 'customer_prepare';
    $customerPayload = [
        'firstname' => mb_substr($firstname, 0, 60),
        'lastname' => mb_substr($lastname, 0, 80),
        'phone' => $phoneDigits,
    ];
    $email = trim((string) ($order['email'] ?? ''));
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $customerPayload['email'] = mb_strtolower($email);
    }
    $stage = 'fedapay_customer_create';
    $customer = Customer::create($customerPayload);

    $projectBaseUrl = fedapayProjectBaseUrlFromRequest();
    $callbackUrl = $projectBaseUrl . '/api/fedapay_return.php?order_id=' . urlencode((string) $orderId);

    $stage = 'fedapay_transaction_create';
    $amountFcfa = max(1, (int) floor(((int) ($order['total_cents'] ?? 0)) / 100));
    $transaction = Transaction::create([
        'description' => 'Commande ' . (string) ($order['order_number'] ?? ('#' . $orderId)),
        'amount' => $amountFcfa,
        'callback_url' => $callbackUrl,
        'currency' => ['iso' => $cfg['default_currency_iso']],
        'customer' => ['id' => (int) ($customer->id ?? 0)],
    ]);

    $stage = 'local_payment_insert';
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

    $stage = 'checkout_token_generate';
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
    $errorId = 'FPCHK-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $logPayload = [
        'error_id' => $errorId,
        'stage' => isset($stage) ? (string) $stage : 'unknown',
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
    if (isset($orderId)) {
        $logPayload['order_id'] = (int) $orderId;
    }
    fedapayCheckoutWriteDebug($logPayload);

    error_log(sprintf(
        '[%s] fedapay_checkout stage=%s type=%s message=%s',
        $errorId,
        isset($stage) ? (string) $stage : 'unknown',
        get_class($e),
        $e->getMessage()
    ));
    jsonResponse([
        'error' => 'Server error',
        'detail' => 'Paiement indisponible (ref: ' . $errorId . ', etape: ' . (isset($stage) ? (string) $stage : 'unknown') . '). ' . $e->getMessage(),
    ], 500);
}
