<?php
/**
 * Simple MCP endpoint for exposing the current file tree and config state.
 * - Creates or updates poff.config.toon on first contact.
 * - Exposes JSON routes under /index.php?mcp=1[&route=...]
 */

declare(strict_types=1);

$rootDir = getcwd();
$configPath = $rootDir . DIRECTORY_SEPARATOR . 'poff.config.toon';
$route = isset($_GET['route']) ? trim((string) $_GET['route']) : 'info';
$prompt = isset($_GET['prompt']) ? trim((string) $_GET['prompt']) : '';

header('Content-Type: application/json');

/**
 * Recursively build a lightweight file tree for the current workspace.
 */
function buildFileTree(string $dir, string $base): array
{
    $items = [];
    $entries = @scandir($dir) ?: [];

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        // Ignore common noisy/system folders
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
                'children' => buildFileTree($fullPath, $base),
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

/**
 * Create or update the poff.config.toon file with the current tree hash.
 */
function ensureConfig(string $path, array $tree): array
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

$tree = buildFileTree($rootDir, $rootDir);
$configState = ensureConfig($configPath, $tree);

$mcpUrl = rtrim($_SERVER['SCRIPT_NAME'] ?? '/index.php', '/') . '#mcp';

switch ($route) {
    case 'style':
        $response = [
            'route' => 'style',
            'prompt' => $prompt,
            'message' => $prompt !== ''
                ? 'Style prompt accepted.'
                : 'Provide a prompt query parameter to describe desired style.',
            'mcpUrl' => $mcpUrl,
            'configPath' => $configPath,
        ];
        break;
    default:
        $response = [
            'route' => 'info',
            'mcpUrl' => $mcpUrl,
            'configPath' => $configPath,
            'configCreated' => $configState['created'],
            'configUpdated' => $configState['updated'],
            'config' => $configState['config'],
        ];
        break;
}

echo json_encode($response, JSON_PRETTY_PRINT);
exit;
