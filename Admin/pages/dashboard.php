<?php
declare(strict_types=1);

$adminPageTitle = 'Tableau de bord';
$adminActiveMenu = 'dashboard';
$adminExtraScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
    'js/admin_dashboard_live.js',
];

ob_start();
?>
<section class="admin-page-head admin-page-head--dashboard">
    <div>
        <h1>Tableau de bord</h1>
        <p class="admin-page-subtitle">Pilotage business temps reel, filtres globaux et widgets personnalisables.</p>
    </div>
    <div class="admin-actions">
        <button class="admin-btn" type="button" id="dashboardManageWidgetsBtn">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i> Widgets
        </button>
        <button class="admin-btn admin-btn--primary" type="button" id="dashboardRefreshBtn">
            <i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Actualiser
        </button>
    </div>
</section>

<section class="admin-dashboard-filters admin-panel">
    <div class="admin-dashboard-filter">
        <label for="dashboardPeriodSelect">Periode</label>
        <select id="dashboardPeriodSelect" class="admin-select">
            <option value="week">Semaine</option>
            <option value="month" selected>Mois</option>
            <option value="year">Annee</option>
        </select>
    </div>
    <div class="admin-dashboard-filter">
        <label for="dashboardAutoRefreshSelect">Auto-refresh</label>
        <select id="dashboardAutoRefreshSelect" class="admin-select">
            <option value="15">15s</option>
            <option value="30">30s</option>
            <option value="45" selected>45s</option>
            <option value="60">60s</option>
            <option value="120">2 min</option>
        </select>
    </div>
    <button class="admin-btn" type="button" id="dashboardThemeToggle">
        <i class="fa-solid fa-moon" aria-hidden="true"></i> Theme
    </button>
</section>

<section class="admin-card-grid" id="dashboardStatsGrid">
    <article class="admin-stat-card" data-widget-id="stat_ventes" draggable="true">
        <div class="admin-widget-head">
            <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget ventes">
                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
            </button>
            <h3>Ventes</h3>
        </div>
        <strong id="statVentes">0</strong>
        <small id="statVentesDelta" class="admin-kpi-delta">--</small>
    </article>
    <article class="admin-stat-card" data-widget-id="stat_revenus" draggable="true">
        <div class="admin-widget-head">
            <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget revenus">
                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
            </button>
            <h3>Revenus</h3>
        </div>
        <strong id="statRevenus">0 Fcfa</strong>
        <small id="statRevenusDelta" class="admin-kpi-delta">--</small>
    </article>
    <article class="admin-stat-card" data-widget-id="stat_commandes" draggable="true">
        <div class="admin-widget-head">
            <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget commandes">
                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
            </button>
            <h3>Commandes</h3>
        </div>
        <strong id="statCommandes">0</strong>
        <small id="statCommandesDelta" class="admin-kpi-delta">--</small>
    </article>
    <article class="admin-stat-card" data-widget-id="stat_clients" draggable="true">
        <div class="admin-widget-head">
            <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget clients">
                <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
            </button>
            <h3>Clients</h3>
        </div>
        <strong id="statClients">0</strong>
        <small id="statClientsDelta" class="admin-kpi-delta">--</small>
    </article>
</section>

<section class="admin-dashboard-board" id="dashboardWidgetBoard">
    <article class="admin-panel admin-widget-card" data-widget-id="sales_chart" draggable="true">
        <div class="admin-panel-head">
            <div class="admin-widget-head">
                <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget graphique">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </button>
                <h2>Ventes et commandes des regimes</h2>
            </div>
            <button class="admin-chart-period" type="button" id="adminChartPeriodBtn" aria-label="Periode mensuelle" aria-haspopup="menu" aria-expanded="false">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <span id="adminChartPeriodLabel">/mois</span>
                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="admin-chart-period-menu" id="adminChartPeriodMenu" role="menu" aria-label="Choisir la periode">
                <button type="button" role="menuitemradio" data-period="week" aria-checked="false">Semaine</button>
                <button type="button" role="menuitemradio" data-period="month" aria-checked="true">Mois</button>
                <button type="button" role="menuitemradio" data-period="year" aria-checked="false">Annee</button>
            </div>
        </div>
        <button class="admin-chart-next" type="button" aria-label="Suivant">
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        <div class="admin-chart-wrap"><canvas id="salesChart"></canvas></div>
    </article>
    <article class="admin-panel admin-widget-card" data-widget-id="business_alerts" draggable="true">
        <div class="admin-panel-head">
            <div class="admin-widget-head">
                <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget alertes">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </button>
                <h2>Alertes business</h2>
            </div>
            <button class="admin-btn admin-btn--chip" type="button" data-refresh-widget="business_alerts">Refresh</button>
        </div>
        <ul class="admin-alert-list" id="dashboardAlerts"></ul>
    </article>
    <article class="admin-panel admin-widget-card" data-widget-id="recent_orders" draggable="true">
        <div class="admin-panel-head">
            <div class="admin-widget-head">
                <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget commandes recentes">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </button>
                <h2>Commandes recentes</h2>
            </div>
            <button class="admin-btn admin-btn--chip" type="button" data-refresh-widget="recent_orders">Refresh</button>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Commande</th><th>Client</th><th>Montant</th><th>Statut</th></tr></thead>
                <tbody id="dashboardRecentOrders"></tbody>
            </table>
        </div>
    </article>
    <article class="admin-panel admin-widget-card" data-widget-id="activity_feed" draggable="true">
        <div class="admin-panel-head">
            <div class="admin-widget-head">
                <button class="admin-drag-handle" type="button" data-drag-handle aria-label="Deplacer widget activite">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </button>
                <h2>Activite recente</h2>
            </div>
            <button class="admin-btn admin-btn--chip" type="button" data-refresh-widget="activity_feed">Refresh</button>
        </div>
        <ul class="admin-activity-list" id="dashboardActivity"></ul>
    </article>
</section>
<?php
$adminContent = (string) ob_get_clean();
require dirname(__DIR__) . '/includes/admin_shell.php';
