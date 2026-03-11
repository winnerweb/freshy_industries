<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

function ensureArticlesTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS articles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(180) NOT NULL,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(140) NOT NULL DEFAULT 'Freshy Industries',
            excerpt TEXT NOT NULL,
            intro TEXT NOT NULL,
            body_1 TEXT NOT NULL,
            body_2 TEXT NOT NULL,
            image_url VARCHAR(500) NULL,
            video_url VARCHAR(500) NULL,
            status ENUM('draft','published') NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_articles_slug (slug),
            KEY idx_articles_status_published_at (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function articleLocalMediaPath(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return null;
    }

    if (preg_match('#^https?://#i', $url)) {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $url = $path;
    }

    $url = str_replace('\\', '/', $url);
    $url = ltrim($url, '/');
    if (!str_starts_with($url, 'uploads/articles/')) {
        return null;
    }

    $absolute = dirname(__DIR__, 2) . '/' . $url;
    $real = realpath($absolute);
    $base = realpath(dirname(__DIR__, 2) . '/uploads/articles');
    if ($real === false || $base === false) {
        return null;
    }
    if (!str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $base) . '/')) {
        return null;
    }
    if (!is_file($real)) {
        return null;
    }
    return $real;
}

function cleanupOrphanArticleMedia(PDO $pdo, string $mediaUrl): void
{
    $mediaUrl = trim($mediaUrl);
    if ($mediaUrl === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM articles
         WHERE image_url = :url OR video_url = :url'
    );
    $stmt->execute([':url' => $mediaUrl]);
    $stillUsed = (int) $stmt->fetchColumn();
    if ($stillUsed > 0) {
        return;
    }

    $localPath = articleLocalMediaPath($mediaUrl);
    if ($localPath !== null) {
        @unlink($localPath);
    }
}

function sanitizeSlug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9\-]+/', '-', $value) ?? '';
    $value = preg_replace('/-+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return substr($value, 0, 180);
}

try {
    $user = requireAdminApi(['manager', 'admin']);
    $pdo = db();
    ensureArticlesTable($pdo);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT id, slug, title, author, excerpt, intro, body_1, body_2, image_url, video_url, status, published_at, created_at
             FROM articles
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT 300"
        )->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse(['data' => $rows]);
    }

    if ($method !== 'POST') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $payload = readJsonInput();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($action === 'delete') {
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'ID article invalide'], 422);
        }
        $mediaStmt = $pdo->prepare('SELECT image_url, video_url FROM articles WHERE id = :id LIMIT 1');
        $mediaStmt->execute([':id' => $id]);
        $mediaRow = $mediaStmt->fetch(PDO::FETCH_ASSOC);
        if (!$mediaRow) {
            jsonResponse(['error' => 'Article introuvable'], 404);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM articles WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() <= 0) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Article introuvable'], 404);
        }

        cleanupOrphanArticleMedia($pdo, (string) ($mediaRow['image_url'] ?? ''));
        cleanupOrphanArticleMedia($pdo, (string) ($mediaRow['video_url'] ?? ''));
        $pdo->commit();

        jsonResponse(['data' => ['deleted' => true]]);
    }

    if (!in_array($action, ['create', 'update'], true)) {
        jsonResponse(['error' => 'Action non supportee'], 400);
    }

    $id = (int) ($payload['id'] ?? 0);
    $title = trim((string) ($payload['title'] ?? ''));
    $slug = sanitizeSlug((string) ($payload['slug'] ?? ''));
    if ($slug === '' && $title !== '') {
        $slug = sanitizeSlug($title);
    }
    $author = trim((string) ($payload['author'] ?? 'Freshy Industries'));
    $excerpt = trim((string) ($payload['excerpt'] ?? ''));
    $intro = trim((string) ($payload['intro'] ?? ''));
    $body1 = trim((string) ($payload['body_1'] ?? ''));
    $body2 = trim((string) ($payload['body_2'] ?? ''));
    $imageUrl = trim((string) ($payload['image_url'] ?? ''));
    $videoUrl = trim((string) ($payload['video_url'] ?? ''));
    $status = trim((string) ($payload['status'] ?? 'draft'));
    $publishedAt = trim((string) ($payload['published_at'] ?? ''));

    if ($title === '' || $slug === '' || $excerpt === '' || $intro === '' || $body1 === '') {
        jsonResponse(['error' => 'Champs obligatoires manquants'], 422);
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        jsonResponse(['error' => 'Statut invalide'], 422);
    }
    if ($imageUrl !== '' && !preg_match('#^(https?://|/|uploads/)#i', $imageUrl)) {
        jsonResponse(['error' => 'Image URL invalide'], 422);
    }
    if ($videoUrl !== '' && !preg_match('#^(https?://|/|uploads/)#i', $videoUrl)) {
        jsonResponse(['error' => 'Video URL invalide'], 422);
    }
    if ($publishedAt !== '' && strtotime($publishedAt) === false) {
        jsonResponse(['error' => 'Date publication invalide'], 422);
    }

    $dupStmt = $pdo->prepare('SELECT id FROM articles WHERE slug = :slug LIMIT 1');
    $dupStmt->execute([':slug' => $slug]);
    $dup = $dupStmt->fetchColumn();
    if ($dup && (int) $dup !== $id) {
        jsonResponse(['error' => 'Slug deja utilise'], 409);
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare(
            "INSERT INTO articles
            (slug, title, author, excerpt, intro, body_1, body_2, image_url, video_url, status, published_at, created_by, created_at, updated_at)
             VALUES
            (:slug, :title, :author, :excerpt, :intro, :body_1, :body_2, :image_url, :video_url, :status, :published_at, :created_by, NOW(), NOW())"
        );
        $stmt->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':author' => $author,
            ':excerpt' => $excerpt,
            ':intro' => $intro,
            ':body_1' => $body1,
            ':body_2' => $body2,
            ':image_url' => ($imageUrl !== '' ? $imageUrl : null),
            ':video_url' => ($videoUrl !== '' ? $videoUrl : null),
            ':status' => $status,
            ':published_at' => ($publishedAt !== '' ? date('Y-m-d H:i:s', strtotime($publishedAt)) : null),
            ':created_by' => (int) ($user['id'] ?? 0) ?: null,
        ]);
        jsonResponse(['data' => ['id' => (int) $pdo->lastInsertId()]], 201);
    }

    if ($id <= 0) {
        jsonResponse(['error' => 'ID article invalide'], 422);
    }

    $stmt = $pdo->prepare(
        "UPDATE articles SET
          slug = :slug,
          title = :title,
          author = :author,
          excerpt = :excerpt,
          intro = :intro,
          body_1 = :body_1,
          body_2 = :body_2,
          image_url = :image_url,
          video_url = :video_url,
          status = :status,
          published_at = :published_at,
          updated_at = NOW()
         WHERE id = :id"
    );
    $stmt->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':author' => $author,
        ':excerpt' => $excerpt,
        ':intro' => $intro,
        ':body_1' => $body1,
        ':body_2' => $body2,
        ':image_url' => ($imageUrl !== '' ? $imageUrl : null),
        ':video_url' => ($videoUrl !== '' ? $videoUrl : null),
        ':status' => $status,
        ':published_at' => ($publishedAt !== '' ? date('Y-m-d H:i:s', strtotime($publishedAt)) : null),
        ':id' => $id,
    ]);

    jsonResponse(['data' => ['id' => $id]]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
