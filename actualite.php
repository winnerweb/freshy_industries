<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/articles_repository.php';

$page_title = 'Actualites';
$additional_css = [];
$articles = freshyArticles();

include 'includes/header.php';

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/actualite.php'));
$siteBasePath = rtrim((string) dirname($scriptName), '/');
if ($siteBasePath === '.' || $siteBasePath === '\\') {
    $siteBasePath = '';
}

$resolveMediaUrl = static function (string $path) use ($siteBasePath): string {
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $normalized = str_replace('\\', '/', $path);
    if ($normalized[0] !== '/') {
        $normalized = '/' . ltrim($normalized, '/');
    }
    // Legacy local path persisted in DB.
    $normalized = preg_replace('#^/site_test(?=/)#i', '', $normalized) ?? $normalized;
    if ($siteBasePath !== '' && !str_starts_with($normalized, $siteBasePath . '/')) {
        $normalized = $siteBasePath . $normalized;
    }
    return $normalized;
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
        <?php if ($articles !== []): ?>
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
        <?php else: ?>
            <div class="articles-loading-state" id="articlesLoadingState" role="status" aria-live="polite">
                <i class="fa-solid fa-spinner" aria-hidden="true"></i>
                <span class="articles-loading-state__text">Chargement en cours...</span>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($articles === []): ?>
<script>
  (function () {
    var state = document.getElementById('articlesLoadingState');
    if (!state) return;
    var icon = state.querySelector('i');
    var text = state.querySelector('.articles-loading-state__text');
    window.setTimeout(function () {
      state.classList.add('is-empty');
      if (icon) {
        icon.classList.remove('fa-spinner');
        icon.classList.add('fa-circle-info');
      }
      if (text) {
        text.textContent = 'Aucun article publie pour le moment.';
      }
    }, 6000);
  })();
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
