<?php
declare(strict_types=1);

$adminPageTitle = 'Liste des clients';
$adminActiveMenu = 'clients';
$adminExtraScripts = ['js/admin_clients_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Liste des clients</h1>
    <button class="admin-btn" id="clientsExportCsvBtn" type="button">Exporter CSV</button>
</section>

<section class="admin-bulk-bar" id="clientsBulkBar" aria-hidden="true">
    <div class="admin-bulk-bar__info">
        <strong id="clientsSelectedCount">0</strong>
        <span>client(s) selectionne(s)</span>
    </div>
    <button class="admin-btn admin-btn--danger" id="clientsBulkDeleteBtn" type="button">Supprimer selection</button>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>
                    <input type="checkbox" id="clientsSelectAll" class="admin-checkbox" aria-label="Selectionner tous les clients visibles">
                </th>
                <th>Nom</th>
                <th>Email</th>
                <th>Telephone</th>
                <th>Nombre commandes</th>
                <th>Total depense</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="clientsTableBody"></tbody>
    </table>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

