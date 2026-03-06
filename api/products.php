<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/application/product_application_service.php';

try {
    $pdo = db();
    $minimal = (string) ($_GET['minimal'] ?? '') === '1';
    if ($minimal) {
        $stmt = $pdo->query(
            "SELECT id, name
             FROM products
             WHERE status = 'active'
             ORDER BY name ASC"
        );
        jsonResponse(['data' => $stmt->fetchAll()]);
    }
    $products = productAppBuildCatalogPayload($pdo);
    jsonResponse(['data' => $products]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
