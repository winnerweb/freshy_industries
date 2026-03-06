<?php
declare(strict_types=1);

$adminPageTitle = 'Gestion des commandes';
$adminActiveMenu = 'orders';
$adminExtraScripts = ['js/admin_orders_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Gestion des commandes</h1>
</section>

<section class="admin-toolbar">
    <select class="admin-select" id="ordersStatusFilter">
        <option value="">Tous les statuts</option>
        <option value="pending">pending</option>
        <option value="paid">paid</option>
        <option value="processing">processing</option>
        <option value="shipped">shipped</option>
        <option value="delivered">delivered</option>
        <option value="canceled">canceled</option>
    </select>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Numero commande</th>
                <th>Client</th>
                <th>Date</th>
                <th>Montant</th>
                <th>Statut</th>
                <th>Mode de paiement</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="ordersTableBody"></tbody>
    </table>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

