<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
jsonResponse(['data' => ['csrf_token' => csrfToken()]]);
