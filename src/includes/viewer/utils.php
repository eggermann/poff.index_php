<?php
/**
 * Shared CMS utilities: JSON responses, config helpers, HTTP helpers.
 */

const CMS_HTTP_TIMEOUT_SECONDS = 90;

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

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
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
