<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function freshyArticleFallbackData(): array
{
    return [
        'sauce-graine-sans-salir-cuisine' => [
            'id' => 0,
            'slug' => 'sauce-graine-sans-salir-cuisine',
            'title' => 'Comment faire la sauce graine sans salir toute la cuisine?',
            'date' => '07 AVR 2025',
            'author' => 'Freshy Palm',
            'excerpt' => 'Decouvrez nos astuces simples pour une sauce graine savoureuse, propre et rapide a preparer.',
            'intro' => 'Cet eclairage analyse les bonnes pratiques pour cuisiner proprement, gagner du temps et mieux organiser votre plan de travail.',
            'body_1' => 'Commencez par preparer tous les ingredients avant la cuisson. Cette methode reduit les deplacements inutiles et limite les projections.',
            'body_2' => 'Utilisez des ustensiles adaptes, une casserole profonde et un feu maitrise pour conserver un environnement de cuisine propre.',
            'image_rel' => '/images/image_actualite.webp',
            'video_url' => null,
        ],
        'huile-rouge-qualite-et-conservation' => [
            'id' => 0,
            'slug' => 'huile-rouge-qualite-et-conservation',
            'title' => 'Huile rouge: bien choisir et conserver pour une meilleure qualite',
            'date' => '15 AVR 2025',
            'author' => 'Freshy Palm',
            'excerpt' => 'Les criteres cles pour identifier une huile rouge de qualite et prolonger sa conservation en toute securite.',
            'intro' => 'Une bonne huile rouge doit garder sa couleur, son odeur et sa stabilite. Voici les points de controle essentiels.',
            'body_1' => 'Verifiez la clarte, le conditionnement et la provenance. Evitez la chaleur et la lumiere directe pour limiter l oxydation.',
            'body_2' => 'Stockez dans un recipient propre, ferme et sec. Refermez immediatement apres usage pour proteger les aromes.',
            'image_rel' => '/images/image_actualite.webp',
            'video_url' => null,
        ],
        'boisson-fruitee-hydratation-intelligente' => [
            'id' => 0,
            'slug' => 'boisson-fruitee-hydratation-intelligente',
            'title' => 'Boisson fruitee: conseils pratiques pour une hydratation intelligente',
            'date' => '22 AVR 2025',
            'author' => 'Freshy Industries',
            'excerpt' => 'Quand et comment consommer une boisson fruitee pour optimiser energie, hydratation et recuperation.',
            'intro' => 'Adapter la boisson au moment de la journee aide a mieux gerer l energie et la concentration.',
            'body_1' => 'Le matin, privilegiez les formats legers. Avant effort, preferez une hydration fractionnee en petites prises.',
            'body_2' => 'Apres effort, combinez une bonne hydration avec une alimentation equilibree pour soutenir la recuperation.',
            'image_rel' => '/images/image_actualite.webp',
            'video_url' => null,
        ],
        'creme-noix-de-palm-usage-culinaire' => [
            'id' => 0,
            'slug' => 'creme-noix-de-palm-usage-culinaire',
            'title' => 'Creme de noix de palm: usages culinaires et dosage ideal',
            'date' => '01 MAI 2025',
            'author' => 'Freshy Palm',
            'excerpt' => 'Comment doser correctement la creme de noix de palm selon les recettes et le nombre de portions.',
            'intro' => 'Le bon dosage evite le gaspillage, stabilise le gout et assure une texture constante dans vos preparations.',
            'body_1' => 'Commencez avec une base moderee, puis ajustez progressivement selon la consistance recherchee.',
            'body_2' => 'Pour les grandes portions, faites une dilution controlee afin de conserver l equilibre aromatique.',
            'image_rel' => '/images/image_actualite.webp',
            'video_url' => null,
        ],
    ];
}

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
        // fallback
    }

    return freshyArticleFallbackData();
}

function freshyFindArticle(string $slug): ?array
{
    $articles = freshyArticles();
    return $articles[$slug] ?? null;
}
