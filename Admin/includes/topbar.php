<header class="admin-topbar">
    <button class="admin-topbar__menu-btn" id="adminMenuToggle" type="button" aria-label="Ouvrir le menu">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <?php if (!$adminHideTopbarSearch): ?>
        <label class="admin-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input id="adminTopbarSearch" type="search" placeholder="Rechercher..." aria-label="Rechercher">
        </label>
    <?php endif; ?>
    <button class="admin-notif" type="button" aria-label="Notifications">
        <i class="fa-regular fa-bell" aria-hidden="true"></i>
        <span class="admin-notif__dot"></span>
    </button>
    <div class="admin-user">
        <div class="admin-user__avatar">
            <?php if (!empty($displayAvatarUrl)): ?>
                <img src="<?php echo htmlspecialchars((string) $displayAvatarUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
            <?php else: ?>
                <?php echo htmlspecialchars(adminUserInitials($displayName), ENT_QUOTES, 'UTF-8'); ?>
            <?php endif; ?>
        </div>
        <div class="admin-user__meta">
            <strong><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <small><?php echo htmlspecialchars($displayRole, ENT_QUOTES, 'UTF-8'); ?></small>
        </div>
    </div>
</header>
