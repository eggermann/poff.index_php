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
        if (in_array($entry, ['.git', '.DS_Store', 'node_modules'], true)) {
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
        file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT));
    } else {
        $config['updatedAt'] = $existing['updatedAt'] ?? $now;
    }

    return [
        'config' => $config,
        'created' => $created,
        'updated' => $updated,
    ];
}

function mcpLoadTreeFromConfig(string $path): ?array
{
    if (!file_exists($path)) {
        return null;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['tree']) || !is_array($data['tree'])) {
        return null;
    }
    return $data['tree'];
}

function mcpJsonError(string $msg, array $extra = []): void
{
    http_response_code(400);
    echo json_encode(array_merge(['error' => $msg], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}
