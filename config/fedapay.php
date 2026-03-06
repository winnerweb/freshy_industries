<?php
declare(strict_types=1);

function fedapayConfig(): array
{
    $envRaw = strtolower(trim((string) (getenv('FEDAPAY_ENV') ?: 'test')));
    $environment = in_array($envRaw, ['live', 'production', 'prod'], true) ? 'live' : 'test';
    $sdkEnvironment = $environment === 'live' ? 'live' : 'sandbox';

    return [
        'environment' => $environment,
        'sdk_environment' => $sdkEnvironment,
        'public_key' => trim((string) (getenv('FEDAPAY_PUBLIC_KEY') ?: '')),
        'secret_key' => trim((string) (getenv('FEDAPAY_SECRET_KEY') ?: '')),
        'webhook_secret' => trim((string) (getenv('FEDAPAY_WEBHOOK_SECRET') ?: '')),
        'default_currency_iso' => 'XOF',
    ];
}

function fedapayConfigIsReady(array $cfg): bool
{
    return trim((string) ($cfg['public_key'] ?? '')) !== ''
        && trim((string) ($cfg['secret_key'] ?? '')) !== '';
}

function fedapayBaseUrlFromRequest(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $proto = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '';
    }

    return $proto . '://' . $host . $basePath;
}

function fedapayProjectBaseUrlFromRequest(): string
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $proto = $https ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $projectDir = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');
    if ($projectDir === '.' || $projectDir === '/') {
        $projectDir = '';
    }
    return $proto . '://' . $host . $projectDir;
}
