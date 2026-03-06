<?php
require_once __DIR__ . '/csrf.php';
$freshyCsrfToken = csrfToken();

if (!function_exists('freshyAsset')) {
    function freshyAsset(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($fullPath)) {
            return $path . '?v=' . filemtime($fullPath);
        }
        return $path;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Freshy Industries'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(freshyAsset('css/freshy_style.css')); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(freshyAsset('css/toast.css')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <?php
    // Détection de la page courante pour les liens actifs
    $current_page = basename($_SERVER['PHP_SELF']);

    if (!empty($additional_css) && is_array($additional_css)) {
        foreach ($additional_css as $css_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(freshyAsset($css_file)) . '">';
        }
    }

    // Métadonnées de partage (Open Graph + Twitter Card)
    $siteConfig = [
        'base_url'   => '', // à remplir plus tard en prod (ex: https://freshy-industries.com)
        'site_name'  => 'Freshy Industries',
        'twitter_at' => '@FreshyIndustries',
    ];
    include __DIR__ . '/social_meta.php';
    ?>

    <!-- Script de gestion des localisations -->
    <script src="<?php echo htmlspecialchars(freshyAsset('js/location-manager.js')); ?>"></script>

</head>

<body>
    <nav class="navbar">
        <div class="navbar-left" style="margin-left: -24px;">
            <a href="index.php"><img src="images/logo_freshy.webp" alt="Freshy Industries Logo" class="navbar-logo"></a>
        </div>

        <div class="navbar-center">
            <ul class="nav-links">
                <li><a href="index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Accueil</a></li>
                <li class="dropdown">
                    <a href="#" class="<?php echo in_array($current_page, ['freshy_palm_page.php', 'freshy_fruit_boosté.php']) ? 'active' : ''; ?>">Nos marques et produits <i class="fas fa-chevron-down dropdown-icon"></i></a>

                    <div class="dropdown-content">
                        <a href="freshy_palm_page.php" class="<?php echo $current_page === 'freshy_palm_page.php' ? 'active' : ''; ?>">Freshy Palm</a>
                        <a href="freshy_fruit_boosté.php" class="<?php echo $current_page === 'freshy_fruit_boosté.php' ? 'active' : ''; ?>">Freshy le Fruit Boosté</a>
                    </div>
                </li>
                <li><a href="epicerie_terroire.php" class="<?php echo $current_page === 'epicerie_terroire.php' ? 'active' : ''; ?>">Épicerie du terroir</a></li>
                <li><a href="actualite.php" class="<?php echo in_array($current_page, ['actualite.php', 'article.php'], true) ? 'active' : ''; ?>">Actualités</a></li>
                <li><a href="point_vente.php" class="<?php echo $current_page === 'point_vente.php' ? 'active' : ''; ?>">Points de Vente</a></li>
                <li><a href="contact.php" class="<?php echo $current_page === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
            </ul>
            <div class="navbar-right">
                <a href="panier.php" class="nav-icon  <?php echo $current_page === 'panier.php' ? 'active' : ''; ?>">Panier <i class="fas fa-shopping-bag"></i></a>
                <button type="button" class="nav-icon nav-icon--button" id="globalSearchToggle" aria-label="Ouvrir la recherche" aria-expanded="false" aria-controls="globalSearchPanel">
                    <i class="fas fa-search" aria-hidden="true"></i>
                </button>
                <button class="btn-quote">
                    <a href="devis.php" class="<?php echo $current_page === 'devis.php' ? 'active' : ''; ?>" style="text-decoration: none; color: white; font-size: 12px;  display: flex; align-items: center; justify-content: center;">Demander un devis</a>
                </button>
            </div>

        </div>

        <div class="menu-toggle" id="menuToggle">
            <i class="fa-solid fa-bars"></i>
        </div>
    </nav>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="close-btn" id="closeBtn">
            <i class="fas fa-times"></i>
        </button>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="index.php" class="sidebar-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>">Accueil</a></li>
                <li class="sidebar-dropdown <?php echo in_array($current_page, ['freshy_palm_page.php', 'freshy_fruit_boosté.php']) ? 'active' : ''; ?>">
                    <a href="#" class="sidebar-link dropdown-toggle <?php echo in_array($current_page, ['freshy_palm_page.php', 'freshy_fruit_boosté.php']) ? 'active' : ''; ?>">Nos marques et produits <i class="fas fa-chevron-down"></i></a>
                    <ul class="sidebar-dropdown-content" style="<?php echo in_array($current_page, ['freshy_palm_page.php', 'freshy_fruit_boosté.php']) ? 'max-height: 500px;' : ''; ?>">
                        <li><a href="freshy_palm_page.php" class="<?php echo $current_page === 'freshy_palm_page.php' ? 'active' : ''; ?>">Freshy Palm</a></li>
                        <li><a href="freshy_fruit_boosté.php" class="<?php echo $current_page === 'freshy_fruit_boosté.php' ? 'active' : ''; ?>">Freshy le Fruit Boosté</a></li>
                    </ul>
                </li>
                <li><a href="epicerie_terroire.php" class="sidebar-link <?php echo $current_page === 'epicerie_terroire.php' ? 'active' : ''; ?>">Épicerie du terroir</a></li>
                <li><a href="actualite.php" class="sidebar-link <?php echo in_array($current_page, ['actualite.php', 'article.php'], true) ? 'active' : ''; ?>">Actualités</a></li>
                <li><a href="point_vente.php" class="sidebar-link <?php echo $current_page === 'point_vente.php' ? 'active' : ''; ?>">Points de Vente</a></li>
                <li><a href="contact.php" class="sidebar-link <?php echo $current_page === 'contact.php' ? 'active' : ''; ?>">Contact</a></li>
                <li><a href="panier.php" class="sidebar-link sidebar-icon <?php echo $current_page === 'panier.php' ? 'active' : ''; ?>">Panier <i class="fas fa-shopping-bag"></i></a></li>
                <li><a href="devis.php" class="sidebar-cta <?php echo $current_page === 'devis.php' ? 'active' : ''; ?>">Demander un devis</a></li>
            </ul>
        </nav>
    </div>

    <section class="global-search-panel" id="globalSearchPanel" aria-hidden="true">
        <div class="global-search-box">
            <div class="global-search-input-wrap">
                <i class="fas fa-search" aria-hidden="true"></i>
                <input type="search" id="globalSearchInput" placeholder="Rechercher un produit, une page..." autocomplete="off" aria-label="Rechercher">
                <button type="button" id="globalSearchClose" class="global-search-close" aria-label="Fermer la recherche">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <ul class="global-search-suggestions" id="globalSearchSuggestions" role="listbox" aria-label="Suggestions de recherche"></ul>
        </div>
    </section>
