<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/articles_repository.php';

$slug = trim((string) ($_GET['article'] ?? ''));
$article = $slug !== '' ? freshyFindArticle($slug) : null;
if (!$article) {
    http_response_code(404);
    header('Location: actualite.php', true, 302);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$page_title = (string) $article['title'];
$additional_css = [];
$article_meta = [
    'title' => (string) $article['title'],
    'description' => (string) $article['excerpt'],
    'image' => $baseUrl . '/site_test' . (string) $article['image_rel'],
    'type' => 'article',
];

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

$imageUrl = $resolveMediaUrl((string) ($article['image_rel'] ?? ''));
$videoUrl = trim((string) ($article['video_url'] ?? ''));
$wordCount = str_word_count(
    (string) ($article['intro'] ?? '') . ' ' .
    (string) ($article['body_1'] ?? '') . ' ' .
    (string) ($article['body_2'] ?? '')
);
$readingMinutes = max(1, (int) ceil($wordCount / 220));
?>

<div class="container article-detail-page" style="display:block;">
    <article class="article-pro">
        <a href="actualite.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour</a>
        <h1 class="article-title" id="articleDetailTitle"><?php echo htmlspecialchars((string) $article['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="article-meta-row" id="articleDetailMeta">
            <span class="article-meta-chip"><?php echo htmlspecialchars((string) $article['date'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="article-meta-chip">Publie par <?php echo htmlspecialchars((string) $article['author'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="article-meta-chip"><?php echo $readingMinutes; ?> min de lecture</span>
        </div>

        <?php if ($imageUrl !== ''): ?>
            <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Image principale de l'article" class="article-main-image" id="articleDetailImage">
        <?php endif; ?>
        <?php if ($videoUrl !== ''): ?>
            <div class="article-main-video-wrap">
                <video controls preload="metadata" class="article-main-video">
                    <source src="<?php echo htmlspecialchars($resolveMediaUrl($videoUrl), ENT_QUOTES, 'UTF-8'); ?>">
                    Votre navigateur ne supporte pas la lecture video.
                </video>
            </div>
        <?php endif; ?>

        <div class="article-intro" id="articleDetailIntro">
            <p><?php echo htmlspecialchars((string) $article['intro'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="article-content" id="articleDetailBody">
            <p><?php echo htmlspecialchars((string) $article['body_1'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><?php echo htmlspecialchars((string) $article['body_2'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="share-section" data-share-root>
            <p>Partager sur :</p>
            <a href="#" class="share-button share-facebook" data-network="facebook" aria-label="Partager cet article sur Facebook" role="button" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i><span class="share-text">Facebook</span></a>
            <a href="#" class="share-button share-x" data-network="x" aria-label="Partager cet article sur X (Twitter)" role="button" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-x-twitter"></i><span class="share-text">Twitter</span></a>
            <a href="#" class="share-button share-whatsapp" data-network="whatsapp" aria-label="Partager cet article sur WhatsApp" role="button" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i><span class="share-text">WhatsApp</span></a>
            <a href="#" class="share-button share-linkedin" data-network="linkedin" aria-label="Partager cet article sur LinkedIn" role="button" target="_blank" rel="noopener noreferrer" style="background-color:#007bb5;"><i class="fab fa-linkedin-in"></i><span class="share-text">LinkedIn</span></a>
        </div>
    </article>
</div>

<script src="<?php echo htmlspecialchars(freshyAsset('js/article-share.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php include 'includes/footer.php'; ?>
