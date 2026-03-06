<?php
declare(strict_types=1);

function csrfStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function csrfToken(): string
{
    csrfStartSession();
    $token = $_SESSION['csrf_token'] ?? null;
    if (!is_string($token) || $token === '') {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
    }
    return $token;
}

function csrfValidate(string $token): bool
{
    csrfStartSession();
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}
