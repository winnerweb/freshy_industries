<?php
declare(strict_types=1);

$adminPageTitle = 'Utilisateurs';
$adminActiveMenu = 'users';
$adminExtraScripts = ['js/admin_users_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Utilisateurs</h1>
    <button class="admin-btn admin-btn--primary" id="addUserBtn" type="button">Ajouter utilisateur</button>
</section>

<section class="admin-bulk-bar" id="usersBulkBar" aria-hidden="true">
    <div class="admin-bulk-bar__info">
        <strong id="usersSelectedCount">0</strong>
        <span>utilisateur(s) selectionne(s)</span>
    </div>
    <div class="admin-actions">
        <button class="admin-btn" id="usersBulkDisableBtn" type="button">Desactiver selection</button>
        <button class="admin-btn admin-btn--danger" id="usersBulkDeleteBtn" type="button">Supprimer selection</button>
    </div>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>
                    <input type="checkbox" id="usersSelectAll" class="admin-checkbox" aria-label="Selectionner tous les utilisateurs visibles">
                </th>
                <th>Nom</th>
                <th>Email</th>
                <th>Role</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="usersTableBody"></tbody>
    </table>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

