<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function freshyArticleTableExists(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'articles'");
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function freshyArticles(): array
{
    try {
        $pdo = db();
        if (freshyArticleTableExists($pdo)) {
            $rows = $pdo->query(
                "SELECT id, slug, title, author, excerpt, intro, body_1, body_2, image_url, video_url, published_at
                 FROM articles
                 WHERE status = 'published'
                 ORDER BY COALESCE(published_at, created_at) DESC, id DESC"
            )->fetchAll(PDO::FETCH_ASSOC);

            if ($rows) {
                $result = [];
                foreach ($rows as $row) {
                    $slug = trim((string) ($row['slug'] ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $publishedAt = trim((string) ($row['published_at'] ?? ''));
                    $date = 'Date non definie';
                    if ($publishedAt !== '') {
                        $dt = date_create($publishedAt);
                        if ($dt) {
                            $date = strtoupper($dt->format('d M Y'));
                        }
                    }
                    $imageUrl = trim((string) ($row['image_url'] ?? ''));
                    $result[$slug] = [
                        'id' => (int) ($row['id'] ?? 0),
                        'slug' => $slug,
                        'title' => (string) ($row['title'] ?? ''),
                        'date' => $date,
                        'author' => (string) ($row['author'] ?? 'Freshy Industries'),
                        'excerpt' => (string) ($row['excerpt'] ?? ''),
                        'intro' => (string) ($row['intro'] ?? ''),
                        'body_1' => (string) ($row['body_1'] ?? ''),
                        'body_2' => (string) ($row['body_2'] ?? ''),
                        'image_rel' => $imageUrl,
                        'video_url' => (string) ($row['video_url'] ?? ''),
                    ];
                }
                if ($result !== []) {
                    return $result;
                }
            }
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function freshyFindArticle(string $slug): ?array
{
    $articles = freshyArticles();
    return $articles[$slug] ?? null;
}
