<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$logFile = dirname(__DIR__) . '/storage/logs/fedapay_checkout.log';
if (!is_file($logFile)) {
    jsonResponse([
        'data' => [
            'exists' => false,
            'message' => 'Aucun log checkout pour le moment.',
        ],
    ]);
}

$max = max(1, min(50, (int) ($_GET['limit'] ?? 10)));
$lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (!is_array($lines)) {
    jsonResponse([
        'data' => [
            'exists' => true,
            'message' => 'Lecture du log impossible.',
        ],
    ], 500);
}

$slice = array_slice($lines, -$max);
$entries = [];
foreach ($slice as $line) {
    $decoded = json_decode($line, true);
    if (is_array($decoded)) {
        $entries[] = $decoded;
    }
}

jsonResponse([
    'data' => [
        'exists' => true,
        'count' => count($entries),
        'entries' => $entries,
    ],
]);

