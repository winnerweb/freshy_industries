<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/fedapay.php';
require_once __DIR__ . '/application/payment_application_service.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FedaPay\FedaPay;
use FedaPay\Transaction;

function redirectTo(string $url): void
{
    header('Location: ' . $url, true, 302);
    exit;
}

try {
    $cfg = fedapayConfig();
    if (!fedapayConfigIsReady($cfg)) {
        throw new RuntimeException('FedaPay configuration missing');
    }

    $orderId = max(0, (int) ($_GET['order_id'] ?? 0));
    if ($orderId <= 0) {
        throw new RuntimeException('Invalid order');
    }

    $pdo = db();
    $paymentStmt = $pdo->prepare(
        'SELECT id, provider_ref
         FROM payments
         WHERE order_id = :order_id
           AND provider = :provider
         ORDER BY id DESC
         LIMIT 1'
    );
    $paymentStmt->execute([
        ':order_id' => $orderId,
        ':provider' => 'fedapay',
    ]);
    $payment = $paymentStmt->fetch();
    if (!$payment) {
        throw new RuntimeException('Payment not found');
    }

    $providerRef = trim((string) ($payment['provider_ref'] ?? ''));
    if ($providerRef === '' || !ctype_digit($providerRef)) {
        throw new RuntimeException('Invalid provider reference');
    }

    FedaPay::setApiKey($cfg['secret_key']);
    FedaPay::setEnvironment($cfg['sdk_environment']);
    $transaction = Transaction::retrieve((int) $providerRef);
    $status = strtolower(trim((string) ($transaction->status ?? 'pending')));

    $pdo->beginTransaction();
    $result = paymentAppSettleFedapayByProviderRef($pdo, $providerRef, $status);
    if (!empty($result['error'])) {
        $pdo->rollBack();
        throw new RuntimeException((string) $result['error']);
    }
    $pdo->commit();

    $projectBase = fedapayProjectBaseUrlFromRequest();
    $localStatus = strtolower((string) (($result['data']['payment_status'] ?? 'pending')));
    if ($localStatus === 'paid') {
        redirectTo($projectBase . '/payment_success.php?order_id=' . urlencode((string) $orderId));
    }
    redirectTo($projectBase . '/payment_failed.php?order_id=' . urlencode((string) $orderId));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $projectBase = fedapayProjectBaseUrlFromRequest();
    redirectTo($projectBase . '/payment_failed.php?reason=' . urlencode('return_error'));
}

