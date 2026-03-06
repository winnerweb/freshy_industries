<?php
declare(strict_types=1);

$adminPageTitle = 'Articles';
$adminActiveMenu = 'articles';
$adminExtraScripts = ['js/admin_articles_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Gestion des articles</h1>
    <button class="admin-btn admin-btn--primary" id="addArticleBtn" type="button">Ajouter article</button>
</section>

<section class="admin-table-wrap">
    <table class="admin-table">
        <thead>
            <tr>
                <th>Titre</th>
                <th>Slug</th>
                <th>Auteur</th>
                <th>Statut</th>
                <th>Date publication</th>
                <th>Media</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="articlesTableBody"></tbody>
    </table>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

