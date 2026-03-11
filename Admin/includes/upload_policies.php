<?php
declare(strict_types=1);

if (!function_exists('adminAvatarAllowedMimeMap')) {
    function adminAvatarAllowedMimeMap(): array
    {
        $default = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $raw = trim((string) (getenv('ADMIN_AVATAR_ALLOWED_MIMES') ?: ''));
        if ($raw === '') {
            return $default;
        }

        $result = [];
        $parts = preg_split('/\s*,\s*/', strtolower($raw)) ?: [];
        foreach ($parts as $mime) {
            if ($mime === '' || !isset($default[$mime])) {
                continue;
            }
            $result[$mime] = $default[$mime];
        }

        return $result !== [] ? $result : $default;
    }
}

if (!function_exists('adminAvatarMaxBytes')) {
    function adminAvatarMaxBytes(): int
    {
        $rawMb = trim((string) (getenv('ADMIN_AVATAR_MAX_MB') ?: ''));
        $mb = is_numeric($rawMb) ? (int) $rawMb : 10;
        $mb = max(1, min(30, $mb));
        return $mb * 1024 * 1024;
    }
}

if (!function_exists('adminAvatarMaxMbLabel')) {
    function adminAvatarMaxMbLabel(): string
    {
        $mb = (int) floor(adminAvatarMaxBytes() / (1024 * 1024));
        return (string) $mb;
    }
}

if (!function_exists('adminAvatarAcceptAttribute')) {
    function adminAvatarAcceptAttribute(): string
    {
        return implode(',', array_keys(adminAvatarAllowedMimeMap()));
    }
}

if (!function_exists('adminAvatarFormatsLabel')) {
    function adminAvatarFormatsLabel(): string
    {
        $exts = array_values(array_unique(array_map(static fn (string $ext): string => strtoupper($ext), array_values(adminAvatarAllowedMimeMap()))));
        // Keep "JPEG" explicitly visible for admin clarity.
        if (in_array('JPG', $exts, true) && !in_array('JPEG', $exts, true)) {
            $exts[] = 'JPEG';
        }
        return implode('/', $exts);
    }
}

