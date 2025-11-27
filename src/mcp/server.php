<?php
/**
 * Simple MCP endpoint for exposing the current file tree and config state.
 * - Creates or updates poff.config.toon on first contact.
 * - Exposes JSON routes under /index.php?mcp=1[&route=...]
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
@require_once __DIR__ . '/../includes/MediaType.php';
@require_once __DIR__ . '/../includes/Worktype.php';
@require_once __DIR__ . '/../includes/PoffConfig.php';
require_once __DIR__ . '/routes/workprompt.php';
require_once __DIR__ . '/routes/style.php';

$rootDir = getcwd();
$configPath = $rootDir . DIRECTORY_SEPARATOR . 'poff.config.toon';
$route = isset($_GET['route']) ? trim((string) $_GET['route']) : 'info';
$prompt = isset($_GET['prompt']) ? trim((string) $_GET['prompt']) : '';
$targetFile = isset($_GET['file']) ? trim((string) $_GET['file']) : '';
$stylePrompt = isset($_GET['style']) ? trim((string) $_GET['style']) : '';

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

$tree = mcpLoadTreeFromConfig($configPath) ?? mcpBuildFileTree($rootDir, $rootDir);
$configState = mcpEnsureConfig($configPath, $tree);

$mcpUrl = rtrim($_SERVER['SCRIPT_NAME'] ?? '/index.php', '/') . '#mcp';

switch ($route) {
    case 'workprompt':
        echo json_encode(handleWorkPrompt([
            'rootDir' => $rootDir,
            'file' => $targetFile,
            'style' => $stylePrompt,
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    case 'style':
        $response = handleStyleRoute($prompt, $mcpUrl, $configPath);
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
