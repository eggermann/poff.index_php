<?php
/**
 * Shared CMS utilities: JSON responses, config helpers, HTTP helpers.
 */

require_once __DIR__ . '/../project-root.php';
require_once __DIR__ . '/../edit-mode.php';

const CMS_HTTP_TIMEOUT_SECONDS = 300;

function cmsJsonResponse(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function cmsReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function cmsIsVirtualLayoutPath(string $relativePath): bool
{
    $trimmed = trim($relativePath, "/\\");
    return $trimmed === '.layout' || str_ends_with($trimmed, '/.layout');
}

function cmsIsHiddenSystemEntry(string $entry): bool
{
    return in_array($entry, [
        'poff.config.json',
        '.poff-auth.php',
        'auth.config.php',
        '.works',
        '.layout',
        '.DS_Store',
        'Thumbs.db',
        '.git',
        '.idea',
        'node_modules',
        '.edit.allow',
        'edit.allow',
        '.edit.not-allow',
        'edit.not-allow',
    ], true);
}

function cmsVirtualLayoutSubjectPath(string $relativePath): string
{
    $trimmed = trim($relativePath, "/\\");
    if ($trimmed === '.layout') {
        return '';
    }
    if (str_ends_with($trimmed, '/.layout')) {
        return substr($trimmed, 0, -strlen('/.layout'));
    }

    return $trimmed;
}

function cmsResolvePhysicalTarget(string $rootDir, string $relativePath): ?array
{
    $trimmed = trim($relativePath, "/\\");
    if (strpos($trimmed, '..') !== false) {
        return null;
    }
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        $resolved = realpath($base);
        if ($resolved === false) {
            return null;
        }
        return [
            'type' => 'folder',
            'dir' => $resolved,
        ];
    }

    $pathInBase = $base . DIRECTORY_SEPARATOR . $trimmed;
    $candidate = realpath($pathInBase);
    $exists = file_exists($pathInBase);
    $isSymlink = is_link($pathInBase);

    if ($candidate === false && !$exists) {
        return null;
    }

    $target = $candidate !== false ? $candidate : $pathInBase;
    $insideBase = strpos($target, $base) === 0;

    // Allow symlinks even when they point outside the base directory.
    if (!$insideBase && !$isSymlink) {
        return null;
    }

    if (is_dir($target)) {
        return [
            'type' => 'folder',
            'dir' => $target,
        ];
    }
    if (is_file($target)) {
        return [
            'type' => 'file',
            'dir' => dirname($target),
            'file' => basename($target),
            'path' => $target,
        ];
    }
    return null;
}

function cmsResolveTarget(string $rootDir, string $relativePath): ?array
{
    $trimmed = trim($relativePath, "/\\");
    if (cmsIsVirtualLayoutPath($trimmed)) {
        $subjectRelativePath = cmsVirtualLayoutSubjectPath($trimmed);
        $subject = cmsResolvePhysicalTarget($rootDir, $subjectRelativePath);
        if ($subject === null) {
            return null;
        }

        return [
            'type' => 'layout',
            'dir' => $subject['dir'],
            'file' => $subject['file'] ?? null,
            'path' => $subject['path'] ?? null,
            'subjectType' => $subject['type'],
            'subjectRelativePath' => $subjectRelativePath,
            'virtualPath' => $trimmed,
        ];
    }

    return cmsResolvePhysicalTarget($rootDir, $trimmed);
}

function cmsNormalizeRouteSlug(string $value): string
{
    return strtolower(trim(str_replace('\\', '/', $value), "/ \t\n\r\0\x0B"));
}

function cmsResolveSlugRouteInTree(string $rootDir, string $relativeDir, string $slug, int $depth = 0): ?array
{
    if ($depth > 8 || !class_exists('PoffConfig')) {
        return null;
    }

    $dir = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($relativeDir !== '') {
        $dir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($relativeDir, "/\\"));
    }
    if (!is_dir($dir)) {
        return null;
    }

    $config = PoffConfig::ensure($dir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item) || (($item['visible'] ?? true) === false)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $itemSlug = cmsNormalizeRouteSlug((string) ($item['slug'] ?? PoffConfig::slugify((string) ($item['title'] ?? $name))));
        $itemPath = trim((string) ($item['path'] ?? $name), "/\\");
        $relativePath = $relativeDir !== ''
            ? trim($relativeDir, "/\\") . '/' . $itemPath
            : $itemPath;
        $type = (string) ($item['type'] ?? 'file');

        if ($itemSlug === $slug || cmsNormalizeRouteSlug($itemPath) === $slug) {
            return [
                'path' => $relativePath,
                'type' => $type === 'folder' ? 'folder' : 'file',
                'isFile' => $type !== 'folder',
                'slug' => (string) ($item['slug'] ?? $itemSlug),
            ];
        }

        if ($type === 'folder') {
            $found = cmsResolveSlugRouteInTree($rootDir, $relativePath, $slug, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function cmsHandleResolveRoute(string $rootDir): void
{
    $slug = cmsNormalizeRouteSlug((string) ($_GET['slug'] ?? $_GET['route'] ?? ''));
    if ($slug === '' || str_contains($slug, '..')) {
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Invalid route.',
        ], 400);
    }

    $resolved = cmsResolveSlugRouteInTree($rootDir, '', $slug);
    if ($resolved === null) {
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Route not found.',
        ], 404);
    }

    cmsJsonResponse([
        'resolved' => true,
    ] + $resolved);
}

function cmsLoadEnv(string $rootDir): array
{
    $envPath = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return [];
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            $env[$key] = $value;
        }
    }
    return $env;
}

function cmsEnvValue(array $env, string $key): ?string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $env[$key] ?? null;
}

function cmsHttpPost(string $url, array $headers, array $payload): array
{
    $override = $GLOBALS['__poff_prompt_http_post'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers, $payload);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($payload),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
    $status = 0;
    $statusLine = '';
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
        $statusLine = (string) $http_response_header[0];
    }
    return [
        'ok' => $response !== false && $status >= 200 && $status < 400,
        'status' => $status,
        'statusLine' => $statusLine,
        'body' => $response !== false ? $response : '',
    ];
}

function cmsHttpGet(string $url, array $headers = []): array
{
    $override = $GLOBALS['__poff_prompt_http_get'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    try {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return [
                    'ok' => false,
                    'status' => 0,
                    'statusLine' => '',
                    'body' => '',
                ];
            }

            $responseBody = '';
            $status = 0;
            $statusLine = '';
            curl_setopt_array($curl, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                    $trimmed = trim($headerLine);
                    if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                        $status = (int) $matches[1];
                        $statusLine = $trimmed;
                    }
                    return strlen($headerLine);
                },
            ]);

            $result = curl_exec($curl);
            if (is_string($result)) {
                $responseBody = $result;
            }
            if ($status === 0) {
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            }
            if ($statusLine === '') {
                $statusLine = 'HTTP ' . $status;
            }
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => $result !== false && $status >= 200 && $status < 400,
                'status' => $status,
                'statusLine' => $statusLine,
                'body' => $responseBody,
                'error' => $error,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        $statusLine = '';
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
            $statusLine = (string) $http_response_header[0];
        }
        return [
            'ok' => $response !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $response !== false ? $response : '',
        ];
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
}

function cmsHttpPostStream(string $url, array $headers, array $payload, ?callable $onChunk = null): array
{
    $override = $GLOBALS['__poff_prompt_http_post_stream'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers, $payload, $onChunk);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    $responseBody = '';
    $status = 0;
    $statusLine = '';
    try {
        if (!function_exists('curl_init')) {
            $response = cmsHttpPost($url, $headers, $payload);
            $responseBody = (string) ($response['body'] ?? '');
            if (is_callable($onChunk) && $responseBody !== '') {
                $onChunk($responseBody);
            }
            return $response;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'ok' => false,
                'status' => 0,
                'statusLine' => '',
                'body' => '',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$responseBody, $onChunk) {
                $responseBody .= $chunk;
                if (is_callable($onChunk)) {
                    $onChunk($chunk);
                }
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                    $status = (int) $matches[1];
                    $statusLine = $trimmed;
                }
                return strlen($headerLine);
            },
        ]);

        $ok = curl_exec($curl);
        if ($status === 0) {
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        }
        if ($statusLine === '') {
            $statusLine = 'HTTP ' . $status;
        }
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'ok' => $ok !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $responseBody,
            'error' => $error,
        ];
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
}

function cmsPromptDebugCapture(string $rootDir, array $entry): string
{
    $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff-prompt-debug';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }

    $workspace = basename(rtrim($rootDir, DIRECTORY_SEPARATOR));
    $workspace = preg_replace('/[^a-z0-9._-]+/i', '-', (string) $workspace) ?: 'workspace';
    $targetPath = $baseDir . DIRECTORY_SEPARATOR . $workspace . '-last-local-prompt.json';
    $entry['capturedAt'] = date('c');

    @file_put_contents($targetPath, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $targetPath;
}

function cmsPromptNormalizeErrorText(string $text): string
{
    $normalized = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/sk-[A-Za-z0-9_-]{12,}/', 'sk-***', $normalized) ?? $normalized;
    $normalized = preg_replace('/AIza[0-9A-Za-z_-]{12,}/', 'AIza***', $normalized) ?? $normalized;
    $normalized = trim($normalized, " \t\n\r\0\x0B.:");
    if ($normalized === '') {
        return '';
    }
    if (strlen($normalized) > 220) {
        $normalized = substr($normalized, 0, 217) . '...';
    }

    return $normalized;
}

function cmsPromptExtractErrorDetail(mixed $value): string
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $detail = cmsPromptExtractErrorDetail($decoded);
            if ($detail !== '') {
                return $detail;
            }
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $trimmed, $matches)) {
            return cmsPromptNormalizeErrorText((string) $matches[1]);
        }

        return cmsPromptNormalizeErrorText($trimmed);
    }

    if (!is_array($value)) {
        return '';
    }

    foreach (['message', 'detail'] as $key) {
        if (array_key_exists($key, $value)) {
            $detail = cmsPromptExtractErrorDetail($value[$key]);
            if ($detail !== '') {
                return $detail;
            }
        }
    }

    if (array_key_exists('error', $value)) {
        $detail = cmsPromptExtractErrorDetail($value['error']);
        if ($detail !== '') {
            return $detail;
        }
    }

    if (array_key_exists('details', $value)) {
        $detail = cmsPromptExtractErrorDetail($value['details']);
        if ($detail !== '') {
            return $detail;
        }
    }

    foreach ($value as $item) {
        $detail = cmsPromptExtractErrorDetail($item);
        if ($detail !== '') {
            return $detail;
        }
    }

    return '';
}

function cmsFormatPromptHttpError(string $label, array $response): string
{
    $status = (int) ($response['status'] ?? 0);
    $detail = cmsPromptExtractErrorDetail($response['body'] ?? '');
    $message = $label . ' request failed';
    if ($status > 0) {
        $message .= ' (HTTP ' . $status . ')';
    }
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }

    return $message . '.';
}

function sanitizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');
    return $path;
}

function detectFileType(string $path): string
{
    return MediaType::classifyExtension(basename($path));
}
