<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth_media.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';

$currentUser = adminCurrentUser();
if ($currentUser) {
    header('Location: dashboard.php');
    exit;
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$adminPos = strpos($scriptName, '/Admin/');
$siteBase = $adminPos !== false ? substr($scriptName, 0, $adminPos) : '';
$siteBase = rtrim($siteBase, '/');
$backgroundUrl = ($siteBase !== '' ? $siteBase : '') . '/images/banniere_vente.webp';
$logoUrl = ($siteBase !== '' ? $siteBase : '') . '/images/logo_freshy.webp';
$videoUrl = adminResolveAuthVideoUrl($siteBase);

$pdo = db();
$error = '';
$success = '';
$values = [
    'full_name' => '',
    'email' => '',
    'phone' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    $values['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $values['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $values['phone'] = trim((string) ($_POST['phone'] ?? ''));

    if (!csrfValidate($token)) {
        $error = 'Session expiree. Rechargez la page puis reessayez.';
    } elseif ($values['full_name'] === '' || $values['email'] === '' || $values['phone'] === '') {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (!preg_match('/^\+?[0-9 ]{8,20}$/', $values['phone'])) {
        $error = 'Numero de telephone invalide.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $values['email']]);
        if ($stmt->fetch()) {
            $error = 'Cet email existe deja.';
        } else {
            adminStartSession();
            $_SESSION['admin_register_pending'] = [
                'full_name' => $values['full_name'],
                'email' => $values['email'],
                'phone' => $values['phone'],
                'created_at' => time(),
            ];
            header('Location: register_password.php');
            exit;
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription admin</title>
    <link rel="stylesheet" href="../css/admin_saas.css">
</head>
<body class="admin-register-body" style="--admin-register-bg:url('<?php echo htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8'); ?>')">
    <div class="admin-auth-media" aria-hidden="true">
        <?php if ($videoUrl !== ''): ?>
            <video class="admin-auth-video" autoplay muted loop playsinline preload="metadata">
                <source src="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
    </div>
    <div class="admin-register-overlay">
        <div class="admin-register-heading">
            <h1>Bienvenue, veuillez creer votre compte admin</h1>
        </div>

        <section class="admin-register-card" aria-labelledby="adminRegisterTitle">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Freshy" class="admin-register-logo">
            <h2 id="adminRegisterTitle" class="sr-only">Creer un compte admin</h2>

            <div class="admin-register-alert <?php echo $error !== '' ? 'is-visible' : ''; ?>" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="admin-register-success <?php echo $success !== '' ? 'is-visible' : ''; ?>"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>

            <form method="post" action="register.php" id="adminRegisterForm" class="admin-register-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="admin-register-step is-active" data-step="1">
                    <input type="text" name="full_name" placeholder="Nom de l'admin" required value="<?php echo htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="email" name="email" placeholder="Adresse mail" required value="<?php echo htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="tel" name="phone" placeholder="Numero de telephone" required value="<?php echo htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="admin-register-btn" id="adminRegisterNextBtn">Suivant</button>
                    <p class="admin-register-auth-link">Avez-vous deja un compte? <a href="login.php">Se connecter</a></p>
                </div>
            </form>
        </section>

        <div class="admin-register-steps" aria-hidden="true">
            <div class="admin-register-line">
                <span class="dot is-active"></span>
                <span class="track"></span>
                <span class="dot"></span>
                <span class="track"></span>
                <span class="dot"></span>
            </div>
            <div class="admin-register-captions">
                <p><strong>Vos infos</strong><br>Noms et adresse mail</p>
                <p><strong>Choisir un mot de passe</strong><br>Choisissez un mot de passe securise</p>
                <p><strong>Connectez vous</strong><br>Profil cree avec succes</p>
            </div>
        </div>
    </div>
    <script src="../js/admin_register_ui.js"></script>
</body>
</html>
