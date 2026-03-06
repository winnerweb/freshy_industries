<?php
declare(strict_types=1);

$adminPageTitle = 'Gestion de l\'inventaire';
$adminActiveMenu = 'inventory';
$adminExtraScripts = ['js/admin_inventory_live.js?v=inventory_pagination_20260222_1'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Gestion de l'inventaire</h1>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Produit</th>
                <th>Quantite</th>
                <th>Entrepot</th>
                <th>Statut stock</th>
                <th>Date mise a jour</th>
            </tr>
        </thead>
        <tbody id="inventoryTableBody"></tbody>
    </table>
</section>

<section class="admin-pagination" id="inventoryPagination" aria-live="polite" hidden>
    <button class="admin-btn admin-btn--chip" id="inventoryPrevBtn" type="button">
        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        Precedent
    </button>
    <div class="admin-pagination__meta" id="inventoryPageMeta">Page 1 / 1</div>
    <button class="admin-btn admin-btn--chip" id="inventoryNextBtn" type="button">
        Suivant
        <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
    </button>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

