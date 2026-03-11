<?php
declare(strict_types=1);

/**
 * Resolve legacy/mis-encoded .php URLs to canonical ASCII filenames.
 * This avoids creating one-off alias files for each historical URL.
 */

$rawPath = (string) ($_GET['path'] ?? '');
$rawPath = ltrim(str_replace('\\', '/', $rawPath), '/');
$decodedPath = rawurldecode($rawPath);

if ($decodedPath === '' || str_contains($decodedPath, "\0") || str_contains($decodedPath, '..')) {
    http_response_code(404);
    exit;
}

if (!preg_match('/\.php$/i', $decodedPath)) {
    http_response_code(404);
    exit;
}

$segments = array_values(array_filter(explode('/', $decodedPath), static fn ($segment) => $segment !== ''));
if (!$segments) {
    http_response_code(404);
    exit;
}

$normalizeSegment = static function (string $segment): string {
    $ext = '';
    $base = $segment;
    $dotPos = strrpos($segment, '.');
    if ($dotPos !== false) {
        $ext = substr($segment, $dotPos);
        $base = substr($segment, 0, $dotPos);
    }

    $base = trim($base);
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
    if (is_string($ascii) && $ascii !== '') {
        $base = $ascii;
    }

    $base = strtolower($base);
    $base = preg_replace('/[^a-z0-9_-]+/', '_', $base) ?? '';
    $base = preg_replace('/_+/', '_', $base) ?? '';
    $base = trim($base, '_');

    if ($base === '') {
        return '';
    }

    return $base . strtolower($ext);
};

$normalizedSegments = array_map($normalizeSegment, $segments);
if (in_array('', $normalizedSegments, true)) {
    http_response_code(404);
    exit;
}

$canonicalRelative = implode('/', $normalizedSegments);
$canonicalAbsolute = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $canonicalRelative);

if (!is_file($canonicalAbsolute)) {
    http_response_code(404);
    exit;
}

parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $query);
unset($query['path']);
$queryString = http_build_query($query);
$location = '/' . $canonicalRelative . ($queryString !== '' ? ('?' . $queryString) : '');

header('Location: ' . $location, true, 301);
exit;

