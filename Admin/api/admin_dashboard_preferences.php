<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

function dashboardDefaultLayout(): array
{
    return [
        'refresh_sec' => 45,
        'widgets_order' => ['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'],
        'dashboard_widgets_order' => ['sales_chart', 'business_alerts', 'recent_orders', 'activity_feed'],
        'widgets_visible' => [
            'stat_ventes' => true,
            'stat_revenus' => true,
            'stat_commandes' => true,
            'stat_clients' => true,
            'sales_chart' => true,
            'business_alerts' => true,
            'recent_orders' => true,
            'activity_feed' => true,
        ],
    ];
}

try {
    $user = requireAdminApi(['operator', 'manager', 'admin']);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $pdo = db();
    $adminUserId = (int) ($user['id'] ?? 0);
    if ($adminUserId <= 0) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    if ($method === 'GET') {
        $stmt = $pdo->prepare(
            'SELECT layout_json, theme, updated_at
             FROM user_dashboard_preferences
             WHERE admin_user_id = :admin_user_id
             LIMIT 1'
        );
        $stmt->execute([':admin_user_id' => $adminUserId]);
        $row = $stmt->fetch();

        $layout = dashboardDefaultLayout();
        $theme = 'light';
        if ($row) {
            $decoded = json_decode((string) ($row['layout_json'] ?? '{}'), true);
            if (is_array($decoded)) {
                $layout = array_replace_recursive($layout, $decoded);
            }
            $storedTheme = (string) ($row['theme'] ?? 'light');
            $theme = in_array($storedTheme, ['light', 'dark'], true) ? $storedTheme : 'light';
        }

        jsonResponse([
            'data' => [
                'layout' => $layout,
                'theme' => $theme,
            ],
        ]);
    }

    if ($method === 'POST') {
        $payload = readJsonInput();
        $layoutInput = $payload['layout'] ?? [];
        $themeInput = (string) ($payload['theme'] ?? 'light');
        $theme = in_array($themeInput, ['light', 'dark'], true) ? $themeInput : 'light';

        $layout = dashboardDefaultLayout();
        if (is_array($layoutInput)) {
            $layout = array_replace_recursive($layout, $layoutInput);
        }

        $layout['refresh_sec'] = max(15, min(300, (int) ($layout['refresh_sec'] ?? 45)));

        $allowedWidgets = [
            'stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients',
            'sales_chart', 'business_alerts', 'recent_orders', 'activity_feed',
        ];

        $visible = [];
        $inputVisible = is_array($layout['widgets_visible'] ?? null) ? $layout['widgets_visible'] : [];
        foreach ($allowedWidgets as $widgetId) {
            $visible[$widgetId] = (bool) ($inputVisible[$widgetId] ?? true);
        }
        $layout['widgets_visible'] = $visible;

        $inputOrder = is_array($layout['widgets_order'] ?? null) ? $layout['widgets_order'] : [];
        $order = [];
        foreach ($inputOrder as $widgetId) {
            $widgetId = (string) $widgetId;
            if (in_array($widgetId, ['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'], true) && !in_array($widgetId, $order, true)) {
                $order[] = $widgetId;
            }
        }
        foreach (['stat_ventes', 'stat_revenus', 'stat_commandes', 'stat_clients'] as $fallback) {
            if (!in_array($fallback, $order, true)) {
                $order[] = $fallback;
            }
        }
        $layout['widgets_order'] = $order;

        $inputBoardOrder = is_array($layout['dashboard_widgets_order'] ?? null) ? $layout['dashboard_widgets_order'] : [];
        $boardOrder = [];
        foreach ($inputBoardOrder as $widgetId) {
            $widgetId = (string) $widgetId;
            if (in_array($widgetId, ['sales_chart', 'business_alerts', 'recent_orders', 'activity_feed'], true) && !in_array($widgetId, $boardOrder, true)) {
                $boardOrder[] = $widgetId;
            }
        }
        foreach (['sales_chart', 'business_alerts', 'recent_orders', 'activity_feed'] as $fallback) {
            if (!in_array($fallback, $boardOrder, true)) {
                $boardOrder[] = $fallback;
            }
        }
        $layout['dashboard_widgets_order'] = $boardOrder;

        $stmt = $pdo->prepare(
            'INSERT INTO user_dashboard_preferences (admin_user_id, layout_json, theme)
             VALUES (:admin_user_id, :layout_json, :theme)
             ON DUPLICATE KEY UPDATE
                layout_json = VALUES(layout_json),
                theme = VALUES(theme),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':admin_user_id' => $adminUserId,
            ':layout_json' => json_encode($layout, JSON_UNESCAPED_UNICODE),
            ':theme' => $theme,
        ]);

        jsonResponse([
            'data' => [
                'layout' => $layout,
                'theme' => $theme,
            ],
        ]);
    }

    jsonResponse(['error' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'user_dashboard_preferences')) {
        jsonResponse([
            'error' => 'Table user_dashboard_preferences missing',
            'detail' => 'Apply database/schema.sql to create user_dashboard_preferences before using this endpoint.',
        ], 500);
    }
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}
