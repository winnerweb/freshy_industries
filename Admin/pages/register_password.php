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

adminStartSession();
$pending = $_SESSION['admin_register_pending'] ?? null;
if (!is_array($pending)) {
    header('Location: register.php');
    exit;
}

$createdAt = (int) ($pending['created_at'] ?? 0);
if ($createdAt <= 0 || (time() - $createdAt) > 1800) {
    unset($_SESSION['admin_register_pending']);
    header('Location: register.php');
    exit;
}

$fullName = trim((string) ($pending['full_name'] ?? ''));
$email = mb_strtolower(trim((string) ($pending['email'] ?? '')));
$phone = trim((string) ($pending['phone'] ?? ''));
if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^\+?[0-9 ]{8,20}$/', $phone)) {
    unset($_SESSION['admin_register_pending']);
    header('Location: register.php');
    exit;
}

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$adminPos = strpos($scriptName, '/Admin/');
$siteBase = $adminPos !== false ? substr($scriptName, 0, $adminPos) : '';
$siteBase = rtrim($siteBase, '/');
$backgroundUrl = ($siteBase !== '' ? $siteBase : '') . '/images/banniere_vente.webp';
$logoUrl = ($siteBase !== '' ? $siteBase : '') . '/images/logo_freshy.webp';
$videoUrl = adminResolveAuthVideoUrl($siteBase);

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $token = trim((string) ($_POST['csrf_token'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (!csrfValidate($token)) {
        $error = 'Session expiree. Rechargez la page puis reessayez.';
    } elseif (mb_strlen($password) < 8) {
        $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
    } elseif (!hash_equals($password, $passwordConfirm)) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT id FROM admin_users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Cet email existe deja.';
            unset($_SESSION['admin_register_pending']);
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO admin_users (full_name, email, role, password_hash, status)
                 VALUES (:full_name, :email, :role, :password_hash, :status)'
            );
            $insert->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':role' => 'admin',
                ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ':status' => 'active',
            ]);

            unset($_SESSION['admin_register_pending']);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}
?> <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription admin - Mot de passe</title>
    <link rel="stylesheet" href="../css/admin_saas.css">
</head>
<body class="admin-password-body" style="--admin-password-bg:url('<?php echo htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8'); ?>')">
    <div class="admin-auth-media" aria-hidden="true">
        <?php if ($videoUrl !== ''): ?>
            <video class="admin-auth-video" autoplay muted loop playsinline preload="metadata">
                <source src="<?php echo htmlspecialchars($videoUrl, ENT_QUOTES, 'UTF-8'); ?>" type="video/mp4">
            </video>
        <?php endif; ?>
    </div>
    <div class="admin-password-overlay">
        <header class="admin-password-heading">
            <h1>Choisissez votre mot de passe</h1>
        </header>

        <section class="admin-password-card" aria-labelledby="adminPasswordTitle">
            <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Freshy" class="admin-password-logo">
            <h2 id="adminPasswordTitle" class="sr-only">Finaliser l'inscription admin</h2>

            <div id="adminRegisterPasswordError" class="admin-password-alert <?php echo $error !== '' ? 'is-visible' : ''; ?>" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <form method="post" action="register_password.php" id="adminRegisterPasswordForm" class="admin-password-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <label class="admin-password-field">
                    <span>Choisir un mot de passe</span>
                    <div class="admin-password-input-wrap">
                        <input
                            id="adminRegisterPassword"
                            class="admin-password-input"
                            type="password"
                            name="password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            aria-describedby="adminRegisterPasswordFieldError"
                            aria-invalid="false"
                        >
                        <button type="button" class="admin-password-toggle" data-password-toggle="adminRegisterPassword" aria-label="Afficher ou masquer le mot de passe">
                            <span aria-hidden="true">👁</span>
                        </button>
                    </div>
                    <small id="adminRegisterPasswordFieldError" class="admin-password-field-error"></small>
                </label>

                <label class="admin-password-field">
                    <span>Confirmez votre mot de passe</span>
                    <div class="admin-password-input-wrap">
                        <input
                            id="adminRegisterPasswordConfirm"
                            class="admin-password-input"
                            type="password"
                            name="password_confirm"
                            minlength="8"
                            required
                            autocomplete="new-password"
                            aria-describedby="adminRegisterPasswordConfirmFieldError"
                            aria-invalid="false"
                        >
                        <button type="button" class="admin-password-toggle" data-password-toggle="adminRegisterPasswordConfirm" aria-label="Afficher ou masquer la confirmation">
                            <span aria-hidden="true">👁</span>
                        </button>
                    </div>
                    <small id="adminRegisterPasswordConfirmFieldError" class="admin-password-field-error"></small>
                </label>


                <div class="admin-password-actions">
                    <a href="register.php" class="admin-password-back">Retour</a>
                    <button type="submit" class="admin-password-submit" id="adminRegisterPasswordSubmit">
                        <span class="admin-password-submit__label">Suivant</span>
                        <span class="admin-password-submit__spinner" aria-hidden="true"></span>
                    </button>
                </div>
            </form>
        </section>

        <section class="admin-password-steps" aria-hidden="true">
            <div class="admin-password-line">
                <span class="dot is-active"></span>
                <span class="track"></span>
                <span class="dot is-active"></span>
                <span class="track"></span>
                <span class="dot"></span>
            </div>
            <div class="admin-password-captions">
                <p><strong>Vos infos</strong><br>Noms et adresse mail</p>
                <p><strong>Choisir un mot de passe</strong><br>Choisissez un mot de passe securise</p>
                <p><strong>Connectez vous</strong><br>Profil cree avec succes</p>
            </div>
        </section>
    </div>
    <script src="../js/admin_register_password_ui.js"></script>
</body>
</html>
