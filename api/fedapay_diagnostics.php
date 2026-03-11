<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../config/fedapay.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
$cfg = fedapayConfig();

$checks = [
    'php_version' => PHP_VERSION,
    'extensions' => [
        'curl' => extension_loaded('curl'),
        'openssl' => extension_loaded('openssl'),
        'json' => extension_loaded('json'),
        'mbstring' => extension_loaded('mbstring'),
    ],
    'vendor_autoload_exists' => is_file($autoloadPath),
    'config' => [
        'environment' => (string) ($cfg['environment'] ?? ''),
        'sdk_environment' => (string) ($cfg['sdk_environment'] ?? ''),
        'public_key_set' => trim((string) ($cfg['public_key'] ?? '')) !== '',
        'secret_key_set' => trim((string) ($cfg['secret_key'] ?? '')) !== '',
        'webhook_secret_set' => trim((string) ($cfg['webhook_secret'] ?? '')) !== '',
    ],
];

try {
    $pdo = db();
    $checks['db'] = [
        'connected' => true,
        'payments_table' => tableExists($pdo, 'payments'),
        'orders_table' => tableExists($pdo, 'orders'),
        'customers_table' => tableExists($pdo, 'customers'),
    ];
} catch (Throwable $e) {
    $checks['db'] = [
        'connected' => false,
        'error' => $e->getMessage(),
    ];
}

jsonResponse(['data' => $checks]);

