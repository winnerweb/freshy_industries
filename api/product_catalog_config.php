<?php
declare(strict_types=1);

function productCatalogConfig(): array
{
    return [
        'boisson' => [
            'category_labels' => ['boisson'],
            'formats' => [
                ['label' => '25cl', 'price_cents' => 20000],
                ['label' => '33cl', 'price_cents' => 30000],
                ['label' => '50cl', 'price_cents' => 50000],
            ],
        ],
        'huile' => [
            'category_labels' => ['huile'],
            'formats' => [
                ['label' => '0.5L', 'price_cents' => 62500],
                ['label' => '1L', 'price_cents' => 125000],
            ],
        ],
        'creme' => [
            'category_labels' => ['creme', 'crème', 'creme de palme'],
            'types' => [
                'concentre' => [
                    ['label' => '450g', 'price_cents' => 130000],
                    ['label' => '900g', 'price_cents' => 250000],
                    ['label' => '1.8kg', 'price_cents' => 600000],
                ],
                'non_concentre' => [
                    ['label' => '450g', 'price_cents' => 75000],
                    ['label' => '900g', 'price_cents' => 145000],
                    ['label' => '1.8kg', 'price_cents' => 280000],
                ],
            ],
        ],
    ];
}

function productCatalogNormalize(string $value): string
{
    $value = trim(mb_strtolower($value));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = mb_strtolower($ascii);
    }
    $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '';
    return trim($value, '_');
}

function productCatalogCategoryKey(?string $slug, ?string $name): ?string
{
    $normSlug = productCatalogNormalize((string) ($slug ?? ''));
    $normName = productCatalogNormalize((string) ($name ?? ''));
    $config = productCatalogConfig();

    foreach ($config as $key => $entry) {
        if ($normSlug === $key || $normName === $key) {
            return $key;
        }
        foreach ($entry['category_labels'] as $label) {
            $normLabel = productCatalogNormalize((string) $label);
            if ($normLabel === $normSlug || $normLabel === $normName) {
                return $key;
            }
        }
    }

    // Resilient fallback for legacy encodings / slugs.
    $combined = $normSlug . ' ' . $normName;
    if (str_starts_with($normSlug, 'crem') || str_starts_with($normName, 'crem') || str_contains($combined, 'crem')) {
        return 'creme';
    }
    if (str_starts_with($normSlug, 'boiss') || str_starts_with($normName, 'boiss') || str_contains($combined, 'boiss')) {
        return 'boisson';
    }
    if (str_starts_with($normSlug, 'huil') || str_starts_with($normName, 'huil') || str_contains($combined, 'huil')) {
        return 'huile';
    }

    return null;
}

function productCatalogAllowedFormatPrice(string $categoryKey, string $variantLabel, string $creamType = ''): ?int
{
    $rows = productCatalogAllowedFormats($categoryKey, $creamType);
    if (!$rows) {
        return null;
    }

    $label = productCatalogNormalize($variantLabel);
    foreach ($rows as $row) {
        if (productCatalogNormalize((string) $row['label']) === $label) {
            return (int) $row['price_cents'];
        }
    }

    return null;
}

function productCatalogAllowedFormats(string $categoryKey, string $creamType = ''): array
{
    $config = productCatalogConfig();
    if (!isset($config[$categoryKey])) {
        return [];
    }

    if ($categoryKey === 'creme') {
        $type = productCatalogNormalize($creamType);
        if (!in_array($type, ['concentre', 'non_concentre'], true)) {
            return [];
        }
        $rows = $config[$categoryKey]['types'][$type] ?? [];
        return is_array($rows) ? $rows : [];
    }

    $rows = $config[$categoryKey]['formats'] ?? [];
    return is_array($rows) ? $rows : [];
}
