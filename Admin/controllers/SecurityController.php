<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/services/PasswordService.php';

final class SecurityController
{
    public function __construct(private PasswordService $passwordService)
    {
    }

    public function changePassword(array $payload, int $adminId, string $ip, string $userAgent): void
    {
        $this->passwordService->changePassword(
            $adminId,
            (string) ($payload['current_password'] ?? ''),
            (string) ($payload['new_password'] ?? ''),
            (string) ($payload['confirm_password'] ?? ''),
            $ip,
            $userAgent
        );
    }
}
