<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/ProfileService.php';

final class ProfileController
{
    public function __construct(private ProfileService $profileService)
    {
    }

    public function get(int $adminId): array
    {
        return $this->profileService->getProfileData($adminId);
    }

    public function save(int $adminId, array $payload, array $files, string $ip, string $userAgent): array
    {
        return $this->profileService->saveProfile($adminId, $payload, $files, $ip, $userAgent);
    }
}
