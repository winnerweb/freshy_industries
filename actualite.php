<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/articles_repository.php';

$page_title = 'Actualites';
$additional_css = [];
$articles = freshyArticles();

include 'includes/header.php';

$resolveMediaUrl = static function (string $path): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] !== '/') {
        return $path;
    }
    return ltrim($path, '/');
};
?>

<section class="freshy-palm-banner actualite-page">
    <div class="banner-right">
        <div class="palm-pattern"></div>
    </div>
</section>

<section class="articles-section actualite-page">
    <h1 class="actualite-page-title">Nos conseils pour une alimentation saine</h1>
    <div class="articles-grid actualite-page">
        <?php foreach ($articles as $article): ?>
            <?php
            $imageUrl = $resolveMediaUrl((string) ($article['image_rel'] ?? ''));
            $videoUrl = $resolveMediaUrl((string) ($article['video_url'] ?? ''));
            ?>
            <div class="article-card actualite-page">
                <?php if ($imageUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Image article">
                <?php elseif ($videoUrl !== ''): ?>
                    <video preload="metadata" muted playsinline>
                        <source src="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    </video>
                <?php endif; ?>
                <div class="article-content">
                    <p class="article-info">
                        <span><?php echo htmlspecialchars((string) $article['date'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><?php echo htmlspecialchars((string) $article['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                    <h3 class="article-detail-title"><?php echo htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p style="color:#000000;"><?php echo htmlspecialchars((string) $article['excerpt'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <a href="<?php echo htmlspecialchars('article.php?article=' . rawurlencode((string) $article['slug']), ENT_QUOTES, 'UTF-8'); ?>" class="btn-read-article">
                        Lire l'article <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
