<?php
declare(strict_types=1);

final class AuditLogRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS audit_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                actor_admin_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(80) NOT NULL,
                target_type VARCHAR(80) NOT NULL,
                target_id VARCHAR(80) NOT NULL,
                before_json JSON NULL,
                after_json JSON NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_audit_logs_actor (actor_admin_id),
                KEY idx_audit_logs_event (event_type),
                KEY idx_audit_logs_created (created_at),
                CONSTRAINT fk_audit_logs_actor
                    FOREIGN KEY (actor_admin_id) REFERENCES admin_users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function log(
        int $actorAdminId,
        string $eventType,
        string $targetType,
        string $targetId,
        ?array $before,
        ?array $after,
        string $ip,
        string $userAgent
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (
               actor_admin_id, event_type, target_type, target_id,
               before_json, after_json, ip_address, user_agent
             ) VALUES (
               :actor_admin_id, :event_type, :target_type, :target_id,
               :before_json, :after_json, :ip_address, :user_agent
             )'
        );
        $stmt->execute([
            ':actor_admin_id' => $actorAdminId,
            ':event_type' => $eventType,
            ':target_type' => $targetType,
            ':target_id' => $targetId,
            ':before_json' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            ':after_json' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            ':ip_address' => substr($ip, 0, 45),
            ':user_agent' => substr($userAgent, 0, 255),
        ]);
    }
}
