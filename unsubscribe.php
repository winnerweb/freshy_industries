<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/newsletter.php';

$email = newsletterNormalizeEmail((string) ($_GET['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$title = 'Desinscription newsletter';
$message = 'Lien invalide ou expire.';
$ok = false;

if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && newsletterTokenIsValid($email, $token)) {
    try {
        $pdo = db();
        newsletterEnsureTables($pdo);
        $stmt = $pdo->prepare(
            "UPDATE newsletter_subscribers
             SET status = 'unsubscribed', updated_at = NOW()
             WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);

        $ok = true;
        $message = 'Vous avez ete desinscrit de la newsletter.';
    } catch (Throwable $e) {
        $message = 'Erreur serveur. Veuillez reessayer plus tard.';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
  <style>
    body { font-family: 'Poppins', sans-serif; margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
    .box { width: min(540px, 92vw); background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 10px 28px rgba(15,23,42,.12); }
    h1 { margin: 0 0 10px; font-size: 22px; }
    p { margin: 0; color: #334155; }
    .ok { color: #166534; }
    .ko { color: #b91c1c; }
  </style>
</head>
<body>
  <main class="box">
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="<?php echo $ok ? 'ok' : 'ko'; ?>">
      <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </p>
  </main>
</body>
</html>
