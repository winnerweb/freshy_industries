<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

function ensureAdminNotificationStateTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_notification_state (
            admin_user_id BIGINT UNSIGNED NOT NULL,
            last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (admin_user_id),
            CONSTRAINT fk_admin_notification_state_admin_user
              FOREIGN KEY (admin_user_id) REFERENCES admin_users(id)
              ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function adminNotificationCurrentState(PDO $pdo, int $adminUserId): string
{
    ensureAdminNotificationStateTable($pdo);
    $stmt = $pdo->prepare(
        'SELECT last_seen_at
         FROM admin_notification_state
         WHERE admin_user_id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $adminUserId]);
    $seenAt = (string) ($stmt->fetchColumn() ?: '');
    if ($seenAt !== '') {
        return $seenAt;
    }

    $pdo->prepare(
        'INSERT INTO admin_notification_state (admin_user_id, last_seen_at)
         VALUES (:id, NOW())'
    )->execute([':id' => $adminUserId]);

    return date('Y-m-d H:i:s');
}

function adminNotificationMarkRead(PDO $pdo, int $adminUserId): void
{
    ensureAdminNotificationStateTable($pdo);
    $pdo->prepare(
        'INSERT INTO admin_notification_state (admin_user_id, last_seen_at)
         VALUES (:id, NOW())
         ON DUPLICATE KEY UPDATE last_seen_at = NOW()'
    )->execute([':id' => $adminUserId]);
}

function fetchUnreadOrderNotifications(PDO $pdo, string $since, int $limit = 30): array
{
    if (!tableExists($pdo, 'orders')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, order_number, status, total_cents, created_at
         FROM orders
         WHERE created_at > :since
         ORDER BY created_at DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':since', $since, PDO::PARAM_STR);
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    foreach ($rows as $row) {
        $notifications[] = [
            'id' => 'order_' . (int) ($row['id'] ?? 0),
            'type' => 'order',
            'title' => 'Nouvelle commande',
            'message' => sprintf(
                'Commande %s (%s) - %s FCFA',
                (string) ($row['order_number'] ?? '#'),
                (string) ($row['status'] ?? 'pending'),
                number_format((int) floor(((int) ($row['total_cents'] ?? 0)) / 100), 0, ',', ' ')
            ),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'target_url' => 'order_ui.php',
        ];
    }
    return $notifications;
}

function fetchUnreadNewsletterNotifications(PDO $pdo, string $since, int $limit = 30): array
{
    if (!tableExists($pdo, 'newsletter_subscribers')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, email, created_at
         FROM newsletter_subscribers
         WHERE created_at > :since
         ORDER BY created_at DESC
         LIMIT :lim'
    );
    $stmt->bindValue(':since', $since, PDO::PARAM_STR);
    $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];
    foreach ($rows as $row) {
        $notifications[] = [
            'id' => 'newsletter_' . (int) ($row['id'] ?? 0),
            'type' => 'newsletter',
            'title' => 'Nouvel abonne newsletter',
            'message' => (string) ($row['email'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'target_url' => 'newsletter.php',
        ];
    }
    return $notifications;
}

try {
    $user = requireAdminApi(['manager', 'admin']);
    $adminUserId = (int) ($user['id'] ?? 0);
    if ($adminUserId <= 0) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }

    $pdo = db();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'POST') {
        $payload = readJsonInput();
        $action = trim((string) ($payload['action'] ?? ''));
        if ($action !== 'mark_read') {
            jsonResponse(['error' => 'Action non supportee'], 400);
        }
        adminNotificationMarkRead($pdo, $adminUserId);
        jsonResponse(['data' => ['ok' => true, 'marked_read_at' => date('Y-m-d H:i:s')]]);
    }

    if ($method !== 'GET') {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    $since = adminNotificationCurrentState($pdo, $adminUserId);
    $orders = fetchUnreadOrderNotifications($pdo, $since, 40);
    $newsletter = fetchUnreadNewsletterNotifications($pdo, $since, 40);
    $items = array_merge($orders, $newsletter);
    usort($items, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });

    jsonResponse([
        'data' => [
            'count' => count($items),
            'items' => $items,
            'sources' => [
                'orders' => count($orders),
                'newsletter' => count($newsletter),
            ],
            'since' => $since,
        ],
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
}

