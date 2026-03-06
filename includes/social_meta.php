<?php
declare(strict_types=1);

if (!function_exists('socialMetaEsc')) {
    function socialMetaEsc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('socialMetaCurrentUrl')) {
    function socialMetaCurrentUrl(array $siteConfig): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!empty($siteConfig['base_url'])) {
            return rtrim((string) $siteConfig['base_url'], '/') . $uri;
        }
        return $scheme . '://' . $host . $uri;
    }
}

$siteConfig = (isset($siteConfig) && is_array($siteConfig)) ? $siteConfig : [
    'base_url' => '',
    'site_name' => 'Freshy Industries',
    'twitter_at' => '@FreshyIndustries',
];

$defaultTitle = isset($page_title) && is_string($page_title) && trim($page_title) !== ''
    ? trim($page_title)
    : 'Freshy Industries';
$defaultDescription = 'Freshy Industries - Produits, actualites et conseils.';
$defaultImage = '/images/logo_freshy.webp';

$meta = [];
if (isset($article_meta) && is_array($article_meta) && $article_meta !== []) {
    $meta = $article_meta;
}

$title = trim((string) ($meta['title'] ?? $defaultTitle));
$description = trim((string) ($meta['description'] ?? $defaultDescription));
$image = trim((string) ($meta['image'] ?? $defaultImage));
$type = trim((string) ($meta['type'] ?? 'website'));
$url = socialMetaCurrentUrl($siteConfig);

if ($title === '') {
    $title = $defaultTitle;
}
if ($description === '') {
    $description = $defaultDescription;
}
if ($image === '') {
    $image = $defaultImage;
}

if (!preg_match('#^https?://#i', $image)) {
    $image = '/' . ltrim($image, '/');
}
?>
<meta name="description" content="<?= socialMetaEsc($description) ?>">
<meta property="og:title" content="<?= socialMetaEsc($title) ?>">
<meta property="og:description" content="<?= socialMetaEsc($description) ?>">
<meta property="og:image" content="<?= socialMetaEsc($image) ?>">
<meta property="og:url" content="<?= socialMetaEsc($url) ?>">
<meta property="og:type" content="<?= socialMetaEsc($type !== '' ? $type : 'website') ?>">
<?php if (!empty($siteConfig['site_name'])): ?>
<meta property="og:site_name" content="<?= socialMetaEsc((string) $siteConfig['site_name']) ?>">
<?php endif; ?>
<meta property="og:locale" content="fr_FR">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= socialMetaEsc($title) ?>">
<meta name="twitter:description" content="<?= socialMetaEsc($description) ?>">
<meta name="twitter:image" content="<?= socialMetaEsc($image) ?>">
<?php if (!empty($siteConfig['twitter_at'])): ?>
<meta name="twitter:site" content="<?= socialMetaEsc((string) $siteConfig['twitter_at']) ?>">
<?php endif; ?>

