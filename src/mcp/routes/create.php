<?php
declare(strict_types=1);

function mcp_sanitize_name(string $name): string
{
    $clean = preg_replace('/[^a-zA-Z0-9._-]+/', '-', trim($name));
    $clean = trim($clean, '-');
    return $clean !== '' ? $clean : 'untitled';
}

function mcp_copy_recursive(string $src, string $dst): void
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
            mcp_copy_recursive($from, $to);
        }
    } else {
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        copy($src, $dst);
    }
}

function handleCreate(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $dest = $opts['dest'] ?? '';
    $path = $opts['path'] ?? null;
    $url = $opts['url'] ?? null;
    $poffDir = $opts['poffDir'] ?? ($rootDir . DIRECTORY_SEPARATOR . 'poff');

    if ($dest === '') {
        mcpJsonError('Missing dest parameter', ['route' => 'create']);
    }

    $safeDest = mcp_sanitize_name($dest);
    $destDir = $poffDir . DIRECTORY_SEPARATOR . $safeDest;
    if (!is_dir($poffDir)) {
        mkdir($poffDir, 0755, true);
    }

    $created = false;
    $copied = false;
    $downloaded = false;
    $errors = [];
    $details = [];

    if ($path) {
        $absSrc = realpath($rootDir . DIRECTORY_SEPARATOR . ltrim($path, '/\\'));
        if ($absSrc === false || strpos($absSrc, $rootDir) !== 0) {
            $errors[] = 'Source path not found or outside project';
        } else {
            mcp_copy_recursive($absSrc, $destDir);
            $copied = true;
            $details['copiedFrom'] = $absSrc;
        }
    } elseif ($url) {
        $dirOk = is_dir($destDir) || mkdir($destDir, 0755, true);
        if ($dirOk) {
            $fname = basename(parse_url($url, PHP_URL_PATH) ?: '');
            if ($fname === '' || $fname === '/') {
                $fname = 'download.bin';
            }
            $targetFile = $destDir . DIRECTORY_SEPARATOR . $fname;
            $data = @file_get_contents($url);
            if ($data === false) {
                $errors[] = 'Download failed';
            } else {
                file_put_contents($targetFile, $data);
                $downloaded = true;
                $details['downloadedFile'] = $targetFile;
            }
        }
    } else {
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
            $created = true;
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
