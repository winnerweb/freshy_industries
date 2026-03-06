<?php
declare(strict_types=1);

$adminPageTitle = 'Newsletter';
$adminActiveMenu = 'newsletter';
$adminExtraScripts = ['js/admin_newsletter_live.js'];

ob_start();
?>
<section class="admin-page-head">
    <h1>Newsletter</h1>
</section>

<section class="admin-tabs" id="newsletterTabs" role="tablist" aria-label="Sections newsletter">
    <button class="admin-tab is-active" type="button" data-tab-target="newsletterSubscribersPanel" role="tab" aria-selected="true">
        Voir abonnes
    </button>
    <button class="admin-tab" type="button" data-tab-target="newsletterCreatePanel" role="tab" aria-selected="false">
        Creer campagne
    </button>
    <button class="admin-tab" type="button" data-tab-target="newsletterHistoryPanel" role="tab" aria-selected="false">
        Historique campagnes
    </button>
</section>

<section class="admin-tab-panel is-active" id="newsletterSubscribersPanel" role="tabpanel">
    <section class="admin-bulk-bar" id="newsletterBulkBar" aria-hidden="true">
        <div class="admin-bulk-bar__info">
            <strong id="newsletterSelectedCount">0</strong>
            <span>abonne(s) selectionne(s)</span>
        </div>
        <div class="admin-actions">
            <button class="admin-btn" id="newsletterBulkUnsubscribeBtn" type="button">Desinscrire selection</button>
            <button class="admin-btn admin-btn--danger" id="newsletterBulkDeleteBtn" type="button">Supprimer selection</button>
            <button class="admin-btn" id="newsletterExportCsvBtn" type="button">Exporter CSV</button>
        </div>
    </section>

    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="newsletterSelectAll" class="admin-checkbox" aria-label="Selectionner tous les abonnes"></th>
                    <th>Email</th>
                    <th>Statut</th>
                    <th>Date inscription</th>
                </tr>
            </thead>
            <tbody id="newsletterSubscribersBody"></tbody>
        </table>
    </section>

    <div class="admin-pagination" id="newsletterPagination">
        <button class="admin-btn" type="button" id="newsletterPrevPageBtn">Precedent</button>
        <span id="newsletterPageLabel">Page 1 / 1</span>
        <button class="admin-btn" type="button" id="newsletterNextPageBtn">Suivant</button>
    </div>
</section>

<section class="admin-tab-panel" id="newsletterCreatePanel" role="tabpanel" hidden>
    <form id="newsletterCampaignForm" class="admin-form-grid" novalidate>
        <div class="admin-form-group admin-form-group--full">
            <label for="newsletterSubject">Sujet</label>
            <input class="admin-input" id="newsletterSubject" name="subject" type="text" maxlength="200" required>
        </div>
        <div class="admin-form-group admin-form-group--full">
            <label for="newsletterContent">Contenu HTML</label>
            <textarea class="admin-textarea" id="newsletterContent" name="content_html" rows="10" required></textarea>
        </div>
        <div class="admin-form-group">
            <label for="newsletterCtaText">Texte CTA</label>
            <input class="admin-input" id="newsletterCtaText" name="cta_text" type="text" maxlength="120">
        </div>
        <div class="admin-form-group">
            <label for="newsletterCtaUrl">Lien CTA</label>
            <input class="admin-input" id="newsletterCtaUrl" name="cta_url" type="url" placeholder="https://...">
        </div>
        <div class="admin-form-group admin-form-group--full">
            <label for="newsletterImageUrl">Image (URL)</label>
            <input class="admin-input" id="newsletterImageUrl" name="image_url" type="url" placeholder="https://...">
        </div>
        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit" id="newsletterSaveCampaignBtn">Enregistrer campagne</button>
        </div>
    </form>
</section>

<section class="admin-tab-panel" id="newsletterHistoryPanel" role="tabpanel" hidden>
    <section class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Sujet</th>
                    <th>Statut</th>
                    <th>Envoyes</th>
                    <th>Echecs</th>
                    <th>Cree le</th>
                    <th>Envoye le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="newsletterCampaignsBody"></tbody>
        </table>
    </section>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';

