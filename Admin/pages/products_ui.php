<?php
declare(strict_types=1);

$adminPageTitle = 'Gestion des produits';
$adminActiveMenu = 'products';
$adminExtraScripts = ['js/admin_products_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Gestion des produits</h1>
    <button class="admin-btn admin-btn--primary" id="addProductBtn" type="button">Ajouter Produit</button>
</section>

<section class="admin-bulk-bar" id="productsBulkBar" aria-hidden="true">
    <div class="admin-bulk-bar__info">
        <strong id="productsSelectedCount">0</strong>
        <span>produit(s) selectionne(s)</span>
    </div>
    <button class="admin-btn admin-btn--danger" id="productsBulkDeleteBtn" type="button">Supprimer la selection</button>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>
                    <input type="checkbox" id="productsSelectAll" class="admin-checkbox" aria-label="Selectionner tous les produits visibles">
                </th>
                <th>Image principale</th>
                <th>Nom produit</th>
                <th>Categorie</th>
                <th>Prix</th>
                <th>Stock</th>
                <th>Statut stock</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="productsTableBody"></tbody>
    </table>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

