<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/SiteSettingsService.php';

final class SiteSettingsController
{
    public function __construct(private SiteSettingsService $service)
    {
    }

    public function get(): array
    {
        return $this->service->getData();
    }

    public function save(array $payload, int $actorAdminId, string $ip, string $userAgent): void
    {
        $this->service->save($payload, $actorAdminId, $ip, $userAgent);
    }
}
