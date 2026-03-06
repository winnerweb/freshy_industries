<?php
declare(strict_types=1);

// -----------------------------------------------------------
//  Configuration générale du site
// -----------------------------------------------------------

$siteConfig = [
    // URL de base du site (adapte à ton environnement de prod)
    'base_url'   => 'https://www.exemple.com', // ex: https://freshy-industries.com
    'site_name'  => 'Freshy Industries',
    'twitter_at' => '@FreshyIndustries',       // Compte Twitter/X (optionnel)
];

// Détection de l’URL courante (pour og:url et Twitter)
function getCurrentUrl(array $siteConfig): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri    = $_SERVER['REQUEST_URI'] ?? '/';

    // Si tu veux forcer la base_url, dé-commente la ligne suivante :
    // return rtrim($siteConfig['base_url'], '/') . $uri;

    return $scheme . '://' . $host . $uri;
}

// Sécurisation des sorties HTML
function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -----------------------------------------------------------
//  "Base de données" d’articles (exemple)
//  Dans un vrai site, tu vas chercher ces données dans MySQL.
// -----------------------------------------------------------

$articles = [
    'article-demo' => [
        'id'          => 'article-demo',
        'title'       => 'Boissons Freshy : le fruit dans toute sa fraîcheur',
        'description' => 'Découvrez comment Freshy sélectionne des fruits de qualité pour offrir des boissons naturelles et rafraîchissantes.',
        'image'       => '/images/articles/freshy_demo_1200x630.webp', // 1200x630 recommandé
        'type'        => 'article',
    ],

    'nouveaute-2025' => [
        'id'          => 'nouveaute-2025',
        'title'       => 'Freshy 2025 : une nouvelle gamme encore plus fruitée',
        'description' => 'Une expérience fruitée avec des recettes revisitées, des ingrédients premium et un emballage plus écoresponsable.',
        'image'       => '/images/articles/freshy_nouveaute_1200x630.webp',
        'type'        => 'article',
    ],
];

// -----------------------------------------------------------
//  Sélection de l’article courant
//  - Ici via ?article=article-demo
//  - Par défaut : article-demo
// -----------------------------------------------------------

$currentArticleId = $_GET['article'] ?? 'article-demo';

if (!isset($articles[$currentArticleId])) {
    $currentArticleId = 'article-demo';
}

$currentArticle = $articles[$currentArticleId];

// -----------------------------------------------------------
//  Validation de l’image (min 1200x630)
// -----------------------------------------------------------

function validateSocialImage(string $imagePathOrUrl): ?string
{
    // URL absolue (http/https) -> utilisation directe
    if (preg_match('#^https?://#i', $imagePathOrUrl)) {
        return $imagePathOrUrl;
    }

    // Chemin relatif au site
    $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
    $relativePath = '/' . ltrim($imagePathOrUrl, '/');
    $absolutePath = $documentRoot . $relativePath;

    if (!is_file($absolutePath)) {
        // On ne peut pas vérifier les dimensions, on renvoie tel quel
        return $relativePath;
    }

    [$width, $height] = @getimagesize($absolutePath) ?: [0, 0];

    // Dimensions recommandées : 1200x630 minimum
    if ($width >= 1200 && $height >= 630) {
        return $relativePath;
    }

    // Si trop petite, on renvoie quand même, ou on pourrait ici
    // renvoyer une image par défaut plus grande.
    return $relativePath;
}

// -----------------------------------------------------------
//  Construction des métadonnées Open Graph / Twitter Card
// -----------------------------------------------------------

$currentUrl  = getCurrentUrl($siteConfig);
$ogTitle     = $currentArticle['title'];
$ogDesc      = $currentArticle['description'];
$ogImage     = validateSocialImage($currentArticle['image']);
$ogType      = $currentArticle['type'] ?? 'article';
$siteName    = $siteConfig['site_name'];
$twitterCard = 'summary_large_image';
$twitterSite = $siteConfig['twitter_at'];

?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= esc($ogTitle) ?> - <?= esc($siteName) ?></title>

    <!-- Description classique -->
    <meta name="description" content="<?= esc($ogDesc) ?>">

    <!-- ===============================
         OPEN GRAPH (Facebook, WhatsApp, LinkedIn)
         =============================== -->
    <meta property="og:title" content="<?= esc($ogTitle) ?>">
    <meta property="og:description" content="<?= esc($ogDesc) ?>">
    <meta property="og:image" content="<?= esc($ogImage) ?>">
    <meta property="og:url" content="<?= esc($currentUrl) ?>">
    <meta property="og:type" content="<?= esc($ogType) ?>">
    <meta property="og:site_name" content="<?= esc($siteName) ?>">
    <meta property="og:locale" content="fr_FR">

    <!-- ===============================
         TWITTER CARD
         =============================== -->
    <meta name="twitter:card" content="<?= esc($twitterCard) ?>">
    <meta name="twitter:title" content="<?= esc($ogTitle) ?>">
    <meta name="twitter:description" content="<?= esc($ogDesc) ?>">
<?php if (!empty($ogImage)) : ?>
    <meta name="twitter:image" content="<?= esc($ogImage) ?>">
<?php endif; ?>
<?php if (!empty($twitterSite)) : ?>
    <meta name="twitter:site" content="<?= esc($twitterSite) ?>">
<?php endif; ?>

    <!-- ===============================
         LinkedIn
         ===============================
         LinkedIn réutilise principalement Open Graph.
         Les balises ci-dessus suffisent dans la majorité des cas.
         =============================== -->
</head>
<body>
    <main>
        <h1><?= esc($ogTitle) ?></h1>
        <p><?= esc($ogDesc) ?></p>

        <p>
            <strong>Article ID :</strong> <?= esc($currentArticle['id']) ?><br>
            <strong>URL courante :</strong> <?= esc($currentUrl) ?><br>
            <strong>Image OG utilisée :</strong> <?= esc($ogImage ?? '') ?><br>
        </p>

        <p>
            Ceci est une page de démonstration pour tester les aperçus de partage.<br>
            Change d’article avec les URL suivantes :
        </p>
        <ul>
            <li><a href="?article=article-demo">?article=article-demo</a></li>
            <li><a href="?article=nouveaute-2025">?article=nouveaute-2025</a></li>
        </ul>

        <hr>

        <h2>Liens de partage (test rapide)</h2>
<?php
        $encodedUrl   = rawurlencode($currentUrl);
        $encodedTitle = rawurlencode($ogTitle);
        $encodedDesc  = rawurlencode($ogDesc);
        $encodedMsg   = rawurlencode($ogTitle . ' - ' . $ogDesc . ' ' . $currentUrl);
?>
        <ul>
            <li>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $encodedUrl ?>"
                   target="_blank" rel="noopener">
                    Partager sur Facebook
                </a>
            </li>
            <li>
                <a href="https://twitter.com/intent/tweet?url=<?= $encodedUrl ?>&text=<?= $encodedTitle ?>"
                   target="_blank" rel="noopener">
                    Partager sur Twitter / X
                </a>
            </li>
            <li>
                <a href="https://wa.me/?text=<?= $encodedMsg ?>"
                   target="_blank" rel="noopener">
                    Partager sur WhatsApp
                </a>
            </li>
            <li>
                <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $encodedUrl ?>"
                   target="_blank" rel="noopener">
                    Partager sur LinkedIn
                </a>
            </li>
        </ul>
    </main>
</body>
</html>
