<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand">
        <img src="<?php echo htmlspecialchars(SITE_BASE_URL . '/images/logo_freshy.webp', ENT_QUOTES, 'UTF-8'); ?>" alt="Freshy" class="admin-brand__logo">
        <div class="admin-brand__text">Freshy Admin</div>
    </div>
    <nav class="admin-nav">
        <?php foreach ($adminMenus as $menu): ?>
            <?php $active = $menu['key'] === $adminActiveMenu ? 'is-active' : ''; ?>
            <a class="admin-nav__link <?php echo $active; ?>" href="<?php echo htmlspecialchars($menu['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fa-solid <?php echo htmlspecialchars($menu['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($menu['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <a class="admin-logout" href="logout.php">
        <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
        <span>Deconnexion</span>
    </a>
</aside>
