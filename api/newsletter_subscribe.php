<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/newsletter.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

requireFrontendCsrf();

$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
$payload = str_contains($contentType, 'application/json') ? readJsonInput() : $_POST;

$email = newsletterNormalizeEmail((string) ($payload['email'] ?? ''));
if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['error' => 'Adresse email invalide.'], 422);
}

$honeypot = trim((string) ($payload['website'] ?? ''));
if ($honeypot !== '') {
    jsonResponse(['error' => 'Requete invalide.'], 422);
}

try {
    $pdo = db();
    newsletterEnsureTables($pdo);

    $rateStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM newsletter_subscribers
         WHERE created_at >= (NOW() - INTERVAL 10 MINUTE)'
    );
    $rateStmt->execute();
    $recentGlobal = (int) $rateStmt->fetchColumn();
    if ($recentGlobal > 500) {
        jsonResponse(['error' => 'Service temporairement indisponible.'], 429);
    }

    $subscriberStmt = $pdo->prepare(
        'SELECT id, status FROM newsletter_subscribers WHERE email = :email LIMIT 1'
    );
    $subscriberStmt->execute([':email' => $email]);
    $row = $subscriberStmt->fetch();

    if ($row) {
        $id = (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        if ($status === 'active') {
            jsonResponse(['error' => 'Cet email est deja inscrit a la newsletter.'], 409);
        }
        $reactivateStmt = $pdo->prepare(
            "UPDATE newsletter_subscribers
             SET status = 'active', updated_at = NOW()
             WHERE id = :id"
        );
        $reactivateStmt->execute([':id' => $id]);
        jsonResponse(['data' => ['subscriber_id' => $id, 'reactivated' => true]], 200);
    }

    $insertStmt = $pdo->prepare(
        "INSERT INTO newsletter_subscribers (email, status, created_at, updated_at)
         VALUES (:email, 'active', NOW(), NOW())"
    );
    $insertStmt->execute([':email' => $email]);

    jsonResponse(['data' => ['subscriber_id' => (int) $pdo->lastInsertId()]], 201);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Erreur serveur newsletter.'], 500);
}
