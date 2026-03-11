<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/admin_auth.php';
require_once dirname(__DIR__) . '/includes/admin_auth_media.php';

$currentUser = adminCurrentUser();
if ($currentUser) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$next = trim((string) ($_GET['next'] ?? $_POST['next'] ?? 'dashboard.php'));
if ($next === '' || str_contains($next, '://') || str_starts_with($next, '//')) {
    $next = 'dashboard.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    try {
        [$ok, $message] = adminLogin($email, $password);
        if ($ok) {
            header('Location: ' . $next);
            exit;
        }
        $error = $message ?? 'Login failed';
    } catch (Throwable $e) {
        $error = 'Unable to login';
    }
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$adminPos = strpos($scriptName, '/Admin/');
$siteBase = $adminPos !== false ? substr($scriptName, 0, $adminPos) : '';
$siteBase = rtrim($siteBase, '/');
$logoUrl = ($siteBase !== '' ? $siteBase : '') . '/images/logo_freshy.webp';
$backgroundUrl = ($siteBase !== '' ? $siteBase : '') . '/images/banniere_vente.webp';
$videoUrl = adminResolveAuthVideoUrl($siteBase);
$homeUrl = ($siteBase !== '' ? $siteBase : '') . '/index.php';
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="../css/admin_saas.css">
</head>
<body class="admin-auth-body" style="--admin-auth-bg:url('<?php echo htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8'); ?>')">
    <div class="admin-auth-media" aria-hidden="true">
        <?php if ($videoUrl !== ''): ?>
            <video class="admin-auth-video" autoplay muted loop playsinline preload="metadata">
                <source src="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
    </div>
    <div class="admin-auth-overlay">
        <header class="admin-auth-heading">
            <h1>Bienvenue, connectez vous ici</h1>
            <p class="admin-auth-home-link">
                <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Retour a l'accueil</a>
            </p>
        </header>

        <section class="admin-auth-card" aria-labelledby="adminLoginTitle">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Freshy" class="admin-auth-logo">
            <h2 id="adminLoginTitle" class="sr-only">Connexion admin</h2>

            <div id="adminLoginServerError" class="admin-auth-alert <?php echo $error !== '' ? 'is-visible' : ''; ?>" role="alert" aria-live="polite">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <form method="post" action="login.php" class="admin-auth-form" id="adminLoginForm" novalidate>
                <input type="hidden" name="next" value="<?php echo htmlspecialchars($next, ENT_QUOTES, 'UTF-8'); ?>">

                <label class="sr-only" for="adminLoginEmail">Email</label>
                <input
                    class="admin-auth-input admin-login-input"
                    id="adminLoginEmail"
                    type="email"
                    name="email"
                    required
                    autofocus
                    placeholder="Entrez votre mail"
                    autocomplete="email"
                    aria-describedby="adminLoginEmailError"
                    aria-invalid="false"
                >
                <small id="adminLoginEmailError" class="admin-login-field-error" aria-live="polite"></small>

                <label class="sr-only" for="adminLoginPassword">Mot de passe</label>
                <div class="admin-password-input-wrap">
                    <input
                        class="admin-auth-input admin-login-input admin-auth-input--with-toggle"
                        id="adminLoginPassword"
                        type="password"
                        name="password"
                        required
                        minlength="8"
                        placeholder="Mot de passe"
                        autocomplete="current-password"
                        aria-describedby="adminLoginPasswordError adminLoginCapsLockHint"
                        aria-invalid="false"
                    >
                    <button
                        type="button"
                        class="admin-password-toggle"
                        id="adminLoginPasswordToggle"
                        aria-label="Afficher le mot de passe"
                        aria-pressed="false"
                    >
                        <svg class="admin-password-toggle__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path class="admin-password-toggle__eye" d="M12 5C6.5 5 2 9.6 1 12c1 2.4 5.5 7 11 7s10-4.6 11-7c-1-2.4-5.5-7-11-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"></path>
                            <circle class="admin-password-toggle__pupil" cx="12" cy="12" r="2.3"></circle>
                            <path class="admin-password-toggle__slash" d="M3 4.5 20.5 22"></path>
                        </svg>
                    </button>
                </div>
                <small id="adminLoginPasswordError" class="admin-login-field-error" aria-live="polite"></small>
                <small id="adminLoginCapsLockHint" class="admin-login-capslock" aria-live="polite"></small>

                <button type="submit" class="admin-login-submit" id="adminLoginSubmitBtn">
                    <span class="admin-login-submit__label">Connexion</span>
                    <span class="admin-login-submit__spinner" aria-hidden="true"></span>
                </button>

                <p class="admin-auth-link">Avez-vous deja un compte? <a href="register.php">S'inscrire</a></p>
            </form>
        </section>

        <section class="admin-auth-steps" aria-hidden="true">
            <div class="admin-auth-line">
                <span class="dot is-active"></span>
                <span class="track"></span>
                <span class="dot is-active"></span>
                <span class="track"></span>
                <span class="dot is-active"></span>
            </div>
            <div class="admin-auth-captions">
                <p><strong>Vos infos</strong><br>Noms et adresse mail</p>
                <p><strong>Choisir un mot de passe</strong><br>Choisissez un mot de passe securise</p>
                <p><strong>Connectez vous</strong><br>Profil cree avec succes</p>
            </div>
        </section>
    </div>
    <script src="../js/admin_login_ui.js"></script>
</body>
</html>
