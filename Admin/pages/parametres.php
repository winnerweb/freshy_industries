<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/upload_policies.php';

$adminPageTitle = 'Parametres';
$adminActiveMenu = 'settings';
$adminExtraScripts = ['js/admin_settings_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Parametres</h1>
</section>

<section class="settings-grid">
    <article class="settings-card settings-card--site">
        <header class="settings-card__head">
            <div>
                <h2>Configuration globale</h2>
                <p class="settings-card__desc">Parametres principaux du site et du systeme.</p>
            </div>
        </header>
        <form id="settingsSiteForm" class="settings-form" novalidate>
            <input type="hidden" name="action" value="save_site_settings">

            <div class="admin-form-group">
                <label for="settingSiteName">Nom du site</label>
                <input class="admin-input" id="settingSiteName" name="site_name" required maxlength="180" placeholder="Freshy Industries">
            </div>
            <div class="admin-form-group">
                <label for="settingSupportEmail">Email support</label>
                <input class="admin-input" id="settingSupportEmail" name="support_email" type="email" required placeholder="support@site.com">
            </div>
            <div class="admin-form-group">
                <label for="settingSiteUrl">URL du site</label>
                <input class="admin-input" id="settingSiteUrl" name="site_url" type="url" placeholder="https://example.com">
            </div>
            <div class="admin-form-group">
                <label for="settingTimezone">Fuseau horaire</label>
                <select class="admin-select" id="settingTimezone" name="timezone">
                    <option value="Africa/Porto-Novo">Africa/Porto-Novo</option>
                    <option value="UTC">UTC</option>
                </select>
            </div>
            <div class="admin-form-group admin-form-group--full">
                <label for="settingSiteDescription">Description du site</label>
                <textarea class="admin-input" id="settingSiteDescription" name="site_description" rows="3" placeholder="Description courte du site"></textarea>
            </div>

            <div class="settings-actions">
                <button class="admin-btn admin-btn--primary" id="settingsSiteSubmit" type="submit">Enregistrer les parametres</button>
            </div>
        </form>
    </article>

    <article class="settings-card settings-card--security">
        <header class="settings-card__head">
            <div>
                <h2>Securite compte admin</h2>
                <p class="settings-card__desc">Changement mot de passe avec invalidation des sessions.</p>
            </div>
        </header>

        <ul class="settings-security-list">
            <li>Minimum 8 caracteres</li>
            <li>Majuscule, minuscule, chiffre et caractere special</li>
            <li>Ancien mot de passe requis</li>
        </ul>

        <form id="settingsSecurityForm" class="settings-form" novalidate>
            <input type="hidden" name="action" value="change_password">
            <div class="admin-form-group admin-form-group--full">
                <label for="settingCurrentPassword">Ancien mot de passe</label>
                <input class="admin-input" id="settingCurrentPassword" name="current_password" type="password" required autocomplete="current-password">
            </div>
            <div class="admin-form-group admin-form-group--full">
                <label for="settingNewPassword">Nouveau mot de passe</label>
                <input class="admin-input" id="settingNewPassword" name="new_password" type="password" required autocomplete="new-password">
            </div>
            <div class="admin-form-group admin-form-group--full">
                <label for="settingConfirmPassword">Confirmer le mot de passe</label>
                <input class="admin-input" id="settingConfirmPassword" name="confirm_password" type="password" required autocomplete="new-password">
            </div>
            <div class="settings-actions">
                <button class="admin-btn" id="settingsSecuritySubmit" type="submit">Mettre a jour le mot de passe</button>
            </div>
        </form>
    </article>

    <article class="settings-card settings-card--profile">
        <header class="settings-card__head">
            <div>
                <h2>Profil administrateur</h2>
                <p class="settings-card__desc">Informations personnelles et avatar.</p>
            </div>
        </header>

        <form id="settingsProfileForm" class="settings-form" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="save_profile">

            <div class="settings-avatar-block">
                <div class="settings-avatar" id="settingsAvatarPreview" aria-label="Avatar">
                    <span id="settingsAvatarFallback">AD</span>
                </div>
                <div class="settings-avatar-actions">
                    <input class="admin-input" id="settingAvatar" name="avatar" type="file" accept="<?php echo htmlspecialchars(adminAvatarAcceptAttribute(), ENT_QUOTES, 'UTF-8'); ?>">
                    <small>Formats: <?php echo htmlspecialchars(adminAvatarFormatsLabel(), ENT_QUOTES, 'UTF-8'); ?>, max <?php echo htmlspecialchars(adminAvatarMaxMbLabel(), ENT_QUOTES, 'UTF-8'); ?> MB</small>
                </div>
            </div>

            <div class="admin-form-group">
                <label for="settingFullName">Nom complet</label>
                <input class="admin-input" id="settingFullName" name="full_name" required>
            </div>
            <div class="admin-form-group">
                <label for="settingAdminEmail">Email</label>
                <input class="admin-input" id="settingAdminEmail" name="email" type="email" required>
            </div>
            <div class="admin-form-group">
                <label for="settingPhone">Telephone</label>
                <input class="admin-input" id="settingPhone" name="phone" placeholder="+229 ...">
            </div>
            <div class="admin-form-group admin-form-group--full">
                <label for="settingBio">Bio</label>
                <textarea class="admin-input" id="settingBio" name="bio" rows="4" maxlength="1000"></textarea>
            </div>
            <div class="settings-actions">
                <button class="admin-btn admin-btn--primary" id="settingsProfileSubmit" type="submit">Enregistrer le profil</button>
            </div>
        </form>
    </article>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

