<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function mcpSanitizeRelPath(string $path): string
{
    $parts = preg_split('/[\\/]+/', $path) ?: [];
    $cleanParts = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || $part === '.' || $part === '..') {
            continue;
        }
        $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $part);
        $clean = trim($clean, '-');
        if ($clean !== '') {
            $cleanParts[] = $clean;
        }
    }

    return $cleanParts === [] ? 'untitled' : implode(DIRECTORY_SEPARATOR, $cleanParts);
}

function mcp_sanitize_relpath(string $path): string
{
    // Internal compatibility wrapper for older MCP helper callers.
    return mcpSanitizeRelPath($path);
}

function mcpCopyRecursive(string $src, string $dst): void
{
    if (is_dir($src)) {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $items = scandir($src);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . DIRECTORY_SEPARATOR . $item;
            $to = $dst . DIRECTORY_SEPARATOR . $item;
            mcpCopyRecursive($from, $to);
        }
    } else {
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($src, $dst);
    }
}

function mcp_copy_recursive(string $src, string $dst): void
{
    // Internal compatibility wrapper for older MCP helper callers.
    mcpCopyRecursive($src, $dst);
}

function mcpUrlHostIsPrivate(string $host): bool
{
    if ($host === '') {
        return true;
    }
    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
        return true;
    }

    $ips = gethostbynamel($host);
    if (!is_array($ips) || $ips === []) {
        $ips = [$host];
    }

    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return true;
            }
            continue;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            if ($ip === '::1' || str_starts_with(strtolower($ip), 'fe80:') || str_starts_with(strtolower($ip), 'fc') || str_starts_with(strtolower($ip), 'fd')) {
                return true;
            }
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return true;
            }
        }
    }

    return false;
}

function mcp_url_host_is_private(string $host): bool
{
    // Internal compatibility wrapper for older MCP helper callers.
    return mcpUrlHostIsPrivate($host);
}

function handleCreate(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $dest = $opts['dest'] ?? '';
    $path = $opts['path'] ?? null;
    $url = $opts['url'] ?? null;
    $poffDir = $opts['poffDir'] ?? ($rootDir . DIRECTORY_SEPARATOR . 'poff');
    if (!is_dir($poffDir)) {
        mkdir($poffDir, 0755, true);
    }
    $poffDir = realpath($poffDir) ?: $poffDir;
    $pathBase = $poffDir;

    if ($dest === '') {
        mcpJsonError('Missing dest parameter', ['route' => 'create']);
    }

    $safeDest = mcpSanitizeRelPath($dest);
    $destDir = $poffDir . DIRECTORY_SEPARATOR . $safeDest;

    $created = false;
    $copied = false;
    $downloaded = false;
    $errors = [];
    $details = [];

    if ($path) {
        $absSrc = mcpResolvePathInsideRoot($pathBase, (string) $path);
        if ($absSrc === null) {
            $errors[] = 'Source path not found or outside /poff';
        } else {
            mcpCopyRecursive($absSrc, $destDir);
            $copied = true;
            $details['copiedFrom'] = $absSrc;
        }
    } elseif ($url) {
        $scheme = strtolower((string) (parse_url((string) $url, PHP_URL_SCHEME) ?? ''));
        $host = strtolower((string) (parse_url((string) $url, PHP_URL_HOST) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            $errors[] = 'Only http and https URLs are allowed';
        }
        if ($host === '' || mcpUrlHostIsPrivate($host)) {
            $errors[] = 'URL host is not allowed';
        }
        $dirOk = is_dir($destDir) || mkdir($destDir, 0755, true);
        if ($dirOk && $errors === []) {
            $fname = basename(parse_url($url, PHP_URL_PATH) ?: '');
            if ($fname === '' || $fname === '/') {
                $fname = 'download.bin';
            }
            $targetFile = $destDir . DIRECTORY_SEPARATOR . $fname;
            $data = file_get_contents((string) $url);
            if ($data === false) {
                $errors[] = 'Download failed or URL was unreachable';
            } else {
                file_put_contents($targetFile, $data);
                $downloaded = true;
                $details['downloadedFile'] = $targetFile;
            }
        }
    } else {
        if (pathinfo($safeDest, PATHINFO_EXTENSION)) {
            $errors[] = 'Destination looks like a file; provide --path to copy an existing file from /poff.';
        } else {
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
                $created = true;
            }
        }
    }

    return [
        'route' => 'create',
        'dest' => $safeDest,
        'destPath' => $destDir,
        'created' => $created,
        'copied' => $copied,
        'downloaded' => $downloaded,
        'errors' => $errors,
        'details' => $details,
    ];
}
