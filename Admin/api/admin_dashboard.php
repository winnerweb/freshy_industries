<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

try {
    requireAdminApi(['operator', 'manager', 'admin']);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $pdo = db();
    $period = strtolower(trim((string) ($_GET['period'] ?? 'month')));
    if (!in_array($period, ['week', 'month', 'year'], true)) {
        $period = 'month';
    }

    $periodWindow = [
        // week: day labels (Lun, Mar, Mer...)
        'week' => ['interval' => '7 DAY', 'group_fmt' => '%Y-%m-%d', 'points' => 7],
        // month: month labels like design reference (Jan..)
        'month' => ['interval' => '10 MONTH', 'group_fmt' => '%Y-%m', 'points' => 10],
        // year: year labels like design reference (2019..2024)
        'year' => ['interval' => '6 YEAR', 'group_fmt' => '%Y', 'points' => 6],
    ][$period];

    $totalOrders = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $totalClients = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    $totalSales = (int) $pdo->query(
        'SELECT COUNT(*) FROM orders WHERE status IN (\'paid\', \'processing\', \'shipped\', \'delivered\')'
    )->fetchColumn();
    $totalRevenue = (int) $pdo->query(
        'SELECT COALESCE(SUM(total_cents), 0) FROM orders WHERE status IN (\'paid\', \'processing\', \'shipped\', \'delivered\')'
    )->fetchColumn();

    $seriesSql = sprintf(
        'SELECT DATE_FORMAT(created_at, %s) AS bucket,
                COALESCE(SUM(CASE WHEN status IN (\'pending\', \'processing\', \'shipped\', \'delivered\', \'paid\') THEN total_cents ELSE 0 END), 0) AS achats_cents,
                COALESCE(SUM(CASE WHEN status IN (\'paid\', \'processing\', \'shipped\', \'delivered\') THEN total_cents ELSE 0 END), 0) AS ventes_cents
         FROM orders
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s)
         GROUP BY DATE_FORMAT(created_at, %s)
         ORDER BY bucket ASC',
        $pdo->quote($periodWindow['group_fmt']),
        $periodWindow['interval'],
        $pdo->quote($periodWindow['group_fmt'])
    );
    $seriesStmt = $pdo->query($seriesSql);
    $seriesRows = $seriesStmt->fetchAll();
    $seriesMapAchats = [];
    $seriesMapVentes = [];
    foreach ($seriesRows as $row) {
        $bucket = (string) ($row['bucket'] ?? '');
        $seriesMapAchats[$bucket] = (int) floor(((int) ($row['achats_cents'] ?? 0)) / 100);
        $seriesMapVentes[$bucket] = (int) floor(((int) ($row['ventes_cents'] ?? 0)) / 100);
    }

    $labels = [];
    $valuesAchats = [];
    $valuesVentes = [];
    if ($period === 'year') {
        $date = (new DateTimeImmutable('first day of january this year'))->modify('-5 year');
        for ($i = 0; $i < $periodWindow['points']; $i++) {
            $bucket = $date->format('Y');
            $labels[] = $bucket;
            $valuesAchats[] = $seriesMapAchats[$bucket] ?? 0;
            $valuesVentes[] = $seriesMapVentes[$bucket] ?? 0;
            $date = $date->modify('+1 year');
        }
    } elseif ($period === 'week') {
        $dayNames = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];
        $date = new DateTimeImmutable('monday this week');
        for ($i = 0; $i < $periodWindow['points']; $i++) {
            $current = $date->modify('+' . $i . ' day');
            $bucket = $current->format('Y-m-d');
            $labels[] = $dayNames[(int) $current->format('N')] ?? $current->format('d/m');
            $valuesAchats[] = $seriesMapAchats[$bucket] ?? 0;
            $valuesVentes[] = $seriesMapVentes[$bucket] ?? 0;
        }
    } else {
        $monthNames = [
            '01' => 'Jan',
            '02' => 'Fev',
            '03' => 'Mar',
            '04' => 'Avr',
            '05' => 'Mai',
            '06' => 'Jui',
            '07' => 'Jul',
            '08' => 'Aou',
            '09' => 'Sep',
            '10' => 'Oct',
            '11' => 'Nov',
            '12' => 'Dec',
        ];
        $date = new DateTimeImmutable('first day of -9 month');
        for ($i = 0; $i < $periodWindow['points']; $i++) {
            $bucket = $date->format('Y-m');
            $labels[] = $monthNames[$date->format('m')] ?? $date->format('m');
            $valuesAchats[] = $seriesMapAchats[$bucket] ?? 0;
            $valuesVentes[] = $seriesMapVentes[$bucket] ?? 0;
            $date = $date->modify('+1 month');
        }
    }

    $recent = $pdo->query(
        'SELECT o.id, o.order_number, o.status, o.total_cents,
                COALESCE(c.full_name, \'Client\') AS customer_name
         FROM orders o
         LEFT JOIN customers c ON c.id = o.customer_id
         ORDER BY o.id DESC
         LIMIT 8'
    )->fetchAll();

    $lowStockCount = (int) $pdo->query(
        'SELECT COUNT(*)
         FROM inventory i
         WHERE i.stock_qty > 50
           AND i.stock_qty <= 200'
    )->fetchColumn();

    $pendingCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM orders WHERE status = 'pending'"
    )->fetchColumn();

    $failedPayments = (int) $pdo->query(
        "SELECT COUNT(*) FROM payments WHERE status = 'failed'"
    )->fetchColumn();

    $alerts = [];
    if ($lowStockCount > 0) {
        $alerts[] = [
            'severity' => 'warning',
            'title' => 'Stock faible',
            'detail' => $lowStockCount . ' variante(s) sous le seuil.',
            'action_url' => 'inventaire.php',
        ];
    }
    if ($pendingCount > 0) {
        $alerts[] = [
            'severity' => 'info',
            'title' => 'Commandes en attente',
            'detail' => $pendingCount . ' commande(s) pending a traiter.',
            'action_url' => 'order_ui.php',
        ];
    }
    if ($failedPayments > 0) {
        $alerts[] = [
            'severity' => 'danger',
            'title' => 'Paiements echoues',
            'detail' => $failedPayments . ' paiement(s) en echec.',
            'action_url' => 'order_ui.php',
        ];
    }

    $activity = $pdo->query(
        "SELECT event_type, description, created_at
         FROM (
            SELECT 'order' AS event_type,
                   CONCAT('Commande ', o.order_number, ' (', o.status, ')') AS description,
                   o.created_at AS created_at
            FROM orders o
            UNION ALL
            SELECT 'product' AS event_type,
                   CONCAT('Produit mis a jour: ', p.name) AS description,
                   p.updated_at AS created_at
            FROM products p
         ) t
         ORDER BY created_at DESC
         LIMIT 12"
    )->fetchAll();

    jsonResponse([
        'data' => [
            'stats' => [
                'ventes' => $totalSales,
                'revenus' => $totalRevenue,
                'commandes' => $totalOrders,
                'clients' => $totalClients,
            ],
            'stats_delta' => [
                'ventes' => 0,
                'revenus' => 0,
                'commandes' => 0,
                'clients' => 0,
            ],
            'chart' => [
                'labels' => $labels,
                'series' => $valuesVentes,
                'series_achats' => $valuesAchats,
                'series_ventes' => $valuesVentes,
            ],
            'recent_orders' => $recent,
            'alerts' => $alerts,
            'activity' => $activity,
            'meta' => [
                'period' => $period,
            ],
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
