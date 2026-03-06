<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../config/fedapay.php';
require_once __DIR__ . '/application/payment_application_service.php';
require_once __DIR__ . '/../vendor/autoload.php';

use FedaPay\FedaPay;
use FedaPay\Transaction;

function fedapayExtractTransactionId(array $payload): ?string
{
    $candidates = [
        $payload['id'] ?? null,
        $payload['transaction_id'] ?? null,
        $payload['data']['id'] ?? null,
        $payload['data']['transaction_id'] ?? null,
        $payload['data']['transaction']['id'] ?? null,
        $payload['event']['data']['id'] ?? null,
        $payload['event']['data']['transaction_id'] ?? null,
        $payload['event']['data']['transaction']['id'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        $value = trim((string) ($candidate ?? ''));
        if ($value !== '' && ctype_digit($value)) {
            return $value;
        }
    }

    return null;
}

function fedapayWebhookSignatureIsValid(string $rawBody, string $secret): bool
{
    if ($secret === '') {
        return true;
    }

    $headerCandidates = [
        $_SERVER['HTTP_X_FEDAPAY_SIGNATURE'] ?? '',
        $_SERVER['HTTP_X_SIGNATURE'] ?? '',
        $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '',
    ];
    $received = '';
    foreach ($headerCandidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate !== '') {
            $received = $candidate;
            break;
        }
    }
    if ($received === '') {
        return false;
    }

    $expected = hash_hmac('sha256', $rawBody, $secret);
    return hash_equals($expected, $received);
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $cfg = fedapayConfig();
    if (!fedapayConfigIsReady($cfg)) {
        jsonResponse(['error' => 'FedaPay configuration missing'], 500);
    }

    $rawBody = file_get_contents('php://input');
    if (!is_string($rawBody) || trim($rawBody) === '') {
        jsonResponse(['error' => 'Invalid payload'], 422);
    }
    if (!fedapayWebhookSignatureIsValid($rawBody, (string) ($cfg['webhook_secret'] ?? ''))) {
        jsonResponse(['error' => 'Invalid webhook signature'], 401);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        jsonResponse(['error' => 'Invalid JSON payload'], 422);
    }

    $transactionId = fedapayExtractTransactionId($payload);
    if ($transactionId === null) {
        jsonResponse(['error' => 'Transaction ID not found'], 422);
    }

    FedaPay::setApiKey($cfg['secret_key']);
    FedaPay::setEnvironment($cfg['sdk_environment']);
    $transaction = Transaction::retrieve((int) $transactionId);
    $status = strtolower(trim((string) ($transaction->status ?? 'pending')));

    $pdo = db();
    $pdo->beginTransaction();
    $result = paymentAppSettleFedapayByProviderRef($pdo, $transactionId, $status);
    if (!empty($result['error'])) {
        $pdo->rollBack();
        $statusCode = (int) ($result['status'] ?? 400);
        jsonResponse(['error' => $result['error']], $statusCode);
    }
    $pdo->commit();

    jsonResponse([
        'data' => [
            'transaction_id' => $transactionId,
            'transaction_status' => $status,
            'settlement' => $result['data'] ?? null,
        ],
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}

