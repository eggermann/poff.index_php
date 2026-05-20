<?php

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

function cmsCurrentRequestBaseUrl(): string
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $scriptName = trim((string) ($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    $directory = dirname('/' . $scriptName);
    $path = $directory === '/' || $directory === '\\' || $directory === '.' ? '' : $directory;

    return rtrim($scheme . '://' . $host . $path, '/');
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
        $value = trim(trim($parts[1]), " \t\n\r\0\x0B\"'");
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

function sanitizeRelativePath(string $path): string
{
    return trim(str_replace('\\', '/', $path), '/');
}

function detectFileType(string $path): string
{
    return MediaType::classifyExtension(basename($path));
}
