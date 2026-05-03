<?php
declare(strict_types=1);

function mcpBuildFileTree(string $dir, string $base): array
{
    $items = [];
    $entries = @scandir($dir) ?: [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (in_array($entry, ['.git', '.DS_Store', 'node_modules', '.works', '.layout'], true)) {
            continue;
        }

        $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
        $relPath = ltrim(str_replace($base, '', $fullPath), DIRECTORY_SEPARATOR);

        if (is_dir($fullPath)) {
            $items[] = [
                'type' => 'directory',
                'name' => $entry,
                'path' => $relPath,
                'children' => mcpBuildFileTree($fullPath, $base),
            ];
        } elseif (is_file($fullPath)) {
            $items[] = [
                'type' => 'file',
                'name' => $entry,
                'path' => $relPath,
                'size' => filesize($fullPath),
                'updatedAt' => date('c', filemtime($fullPath)),
            ];
        }
    }

    return $items;
}

function mcpEnsureConfig(string $path, array $tree): array
{
    $treeHash = hash('sha256', json_encode($tree));
    $now = date('c');
    $created = false;
    $updated = false;
    $error = null;
    $existing = [];
    $config = [
        'name' => basename(getcwd()),
        'createdAt' => $now,
        'updatedAt' => $now,
        'treeHash' => $treeHash,
        'tree' => $tree,
    ];

    if (file_exists($path)) {
        $existing = json_decode((string) file_get_contents($path), true) ?: [];
        $config['createdAt'] = $existing['createdAt'] ?? $now;
        if (($existing['treeHash'] ?? null) !== $treeHash) {
            $updated = true;
        } else {
            $config['tree'] = $existing['tree'] ?? $tree;
        }
    } else {
        $created = true;
    }

    if ($created || $updated) {
        $config['updatedAt'] = $now;
        $config['treeHash'] = $treeHash;
        $config['tree'] = $tree;
        $error = mcpWriteJsonFile($path, $config);
    } else {
        $config['updatedAt'] = $existing['updatedAt'] ?? $now;
    }

    return [
        'config' => $config,
        'created' => $created,
        'updated' => $updated,
        'error' => $error,
    ];
}

function mcpJsonError(string $msg, array $extra = [], int $status = 400): void
{
    mcpJsonResponse(array_merge(['error' => $msg], $extra), $status);
}

function mcpWriteJsonFile(string $path, array $data): ?string
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return 'Failed to encode JSON: ' . json_last_error_msg();
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return 'Failed to create directory.';
    }

    if (file_put_contents($path, $json) === false) {
        return 'Failed to write file.';
    }

    return null;
}

function mcpJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to encode JSON response',
            'jsonError' => json_last_error_msg(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    echo $json;
    exit;
}

function mcpQueryString(string $key, ?string $default = ''): ?string
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }
    return trim((string) $_GET[$key]);
}

function mcpReadRequestData(): array
{
    $raw = (string) file_get_contents('php://input');
    $data = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($data) || $data === []) {
        $data = $_POST;
    }
    return is_array($data) ? $data : [];
}

function mcpResolvePathInsideRoot(string $rootDir, string $relativePath): ?string
{
    $base = realpath(rtrim($rootDir, DIRECTORY_SEPARATOR));
    if ($base === false) {
        return null;
    }
    $trimmed = trim($relativePath, "/\\");
    $candidate = $trimmed === ''
        ? $base
        : realpath($base . DIRECTORY_SEPARATOR . $trimmed);
    if ($candidate === false) {
        return null;
    }
    $basePrefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($candidate !== $base && strpos($candidate, $basePrefix) !== 0) {
        return null;
    }
    return $candidate;
}

function mcpResolveDirectoryInsideRoot(string $rootDir, string $relativePath): ?string
{
    $path = mcpResolvePathInsideRoot($rootDir, $relativePath);
    return $path !== null && is_dir($path) ? $path : null;
}

function mcpResolveFileInsideRoot(string $rootDir, string $relativePath): ?string
{
    $path = mcpResolvePathInsideRoot($rootDir, $relativePath);
    return $path !== null && is_file($path) ? $path : null;
}
