<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';
$adminUser = requireAdminPage();

if (!defined('ADMIN_BASE_URL')) {
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pos = strpos($scriptName, '/Admin/');
    $base = $pos !== false ? substr($scriptName, 0, $pos + 6) : '/Admin';
    define('ADMIN_BASE_URL', rtrim($base, '/'));
}
if (!defined('SITE_BASE_URL')) {
    $siteBase = preg_replace('#/Admin$#', '', ADMIN_BASE_URL);
    define('SITE_BASE_URL', rtrim((string) $siteBase, '/'));
}

if (!function_exists('adminAsset')) {
    function adminAsset(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path)) {
            return $path;
        }
        $normalized = ltrim($path, '/');
        $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (is_file($fullPath)) {
            return ADMIN_BASE_URL . '/' . $normalized . '?v=' . filemtime($fullPath);
        }
        return ADMIN_BASE_URL . '/' . $normalized;
    }
}

if (!function_exists('adminUserInitials')) {
    function adminSanitizeDisplayText(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\xEF\xBF\xBD", '', $value);
        $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', '', $value) ?? '';
        return trim($value);
    }

    function adminUserInitials(string $name): string
    {
        $normalized = adminSanitizeDisplayText($name);
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
        if ($normalized === '') {
            return 'AD';
        }
        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $first = $parts[0] ?? '';
        $second = $parts[1] ?? '';
        $initials = '';
        if ($first !== '') {
            $initials .= mb_substr($first, 0, 1, 'UTF-8');
        }
        if ($second !== '') {
            $initials .= mb_substr($second, 0, 1, 'UTF-8');
        }
        if ($initials === '') {
            $initials = mb_substr($normalized, 0, 2, 'UTF-8');
        }
        return mb_strtoupper($initials, 'UTF-8');
    }
}

if (!function_exists('adminPublicUrl')) {
    function adminPublicUrl(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^(https?:)?//#i', $raw)) {
            return $raw;
        }

        $siteBase = rtrim((string) SITE_BASE_URL, '/');
        $path = str_replace('\\', '/', $raw);
        if ($path[0] !== '/') {
            return ($siteBase !== '' ? $siteBase : '') . '/' . ltrim($path, '/');
        }

        // Local env path can be stored in DB; normalize for production root.
        $path = preg_replace('#^/site_test(?=/)#i', '', $path) ?? $path;
        if ($siteBase === '') {
            return $path;
        }
        if ($path === $siteBase || str_starts_with($path, $siteBase . '/')) {
            return $path;
        }
        return $siteBase . $path;
    }
}

$adminPageTitle = $adminPageTitle ?? 'Admin';
$adminActiveMenu = $adminActiveMenu ?? 'dashboard';
$adminContent = $adminContent ?? '';
$adminExtraScripts = $adminExtraScripts ?? [];
$adminHideTopbarSearch = !empty($adminHideTopbarSearch);
$displayName = adminSanitizeDisplayText((string) ($adminUser['name'] ?? ''));
$displayRole = adminSanitizeDisplayText((string) ($adminUser['role'] ?? ''));
$displayAvatarUrl = adminUserAvatarUrl((int) ($adminUser['id'] ?? 0));

$adminMenus = [
    ['key' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'fa-chart-line', 'href' => 'dashboard.php'],
    ['key' => 'products', 'label' => 'Produits', 'icon' => 'fa-box', 'href' => 'products_ui.php'],
    ['key' => 'inventory', 'label' => 'Inventaire', 'icon' => 'fa-warehouse', 'href' => 'inventaire.php'],
    ['key' => 'orders', 'label' => 'Commande', 'icon' => 'fa-receipt', 'href' => 'order_ui.php'],
    ['key' => 'clients', 'label' => 'Clients', 'icon' => 'fa-users', 'href' => 'clients.php'],
    ['key' => 'newsletter', 'label' => 'Newsletter', 'icon' => 'fa-envelope-open-text', 'href' => 'newsletter.php'],
    ['key' => 'articles', 'label' => 'Articles', 'icon' => 'fa-newspaper', 'href' => 'articles.php'],
    ['key' => 'users', 'label' => 'Utilisateurs', 'icon' => 'fa-user-shield', 'href' => 'utilisateurs.php'],
    ['key' => 'settings', 'label' => 'Parametres', 'icon' => 'fa-gear', 'href' => 'parametres.php'],
];

require __DIR__ . '/header.php';
require __DIR__ . '/sidebar.php';
?>
<div class="admin-main">
    <?php require __DIR__ . '/topbar.php'; ?>
    <main class="admin-content">
        <?php echo $adminContent; ?>
    </main>
</div>
</div>

<button class="admin-theme-fab" id="globalAdminThemeToggle" type="button" aria-label="Activer le mode sombre">
    <i class="fa-solid fa-moon" aria-hidden="true"></i>
</button>

<div id="toast-root" class="toast-stack" aria-live="polite" aria-atomic="false"></div>

<?php require __DIR__ . '/footer.php'; ?>
