<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/articles_repository.php';

function searchIndexNormalize(string $value): string
{
    $value = mb_strtolower(trim($value));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = mb_strtolower($ascii);
    }
    $value = preg_replace('/\s+/u', ' ', $value) ?? '';
    return trim($value);
}

try {
    $pdo = db();
    $entries = [];
    $hasProductStatus = columnExists($pdo, 'products', 'status');
    $hasProductShortDescription = columnExists($pdo, 'products', 'short_description');

    // Dynamic products index.
    $productDescriptionSelect = $hasProductShortDescription ? 'short_description' : "NULL AS short_description";
    $productWhere = $hasProductStatus ? "WHERE status = 'active'" : '';
    $productStmt = $pdo->query(
        "SELECT id, name, $productDescriptionSelect
         FROM products
         $productWhere
         ORDER BY name ASC
         LIMIT 800"
    );
    foreach ($productStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $entries[] = [
            'label' => $name,
            'type' => 'Produit',
            'url' => 'epicerie_terroire.php?search=' . rawurlencode($name),
            'keywords' => searchIndexNormalize((string) ($row['short_description'] ?? '')),
        ];
    }

    // Dynamic articles index (published table data + fallback repository).
    foreach (freshyArticles() as $article) {
        $slug = trim((string) ($article['slug'] ?? ''));
        $title = trim((string) ($article['title'] ?? ''));
        if ($slug === '' || $title === '') {
            continue;
        }
        $entries[] = [
            'label' => $title,
            'type' => 'Actualite',
            'url' => 'article.php?article=' . rawurlencode($slug),
            'keywords' => searchIndexNormalize(
                (string) ($article['excerpt'] ?? '') . ' ' .
                (string) ($article['intro'] ?? '') . ' ' .
                (string) ($article['author'] ?? '')
            ),
        ];
    }

    jsonResponse(['data' => $entries]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
