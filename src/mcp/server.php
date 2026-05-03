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
require_once __DIR__ . '/routes/create.php';
require_once __DIR__ . '/routes/edit-config.php';
require_once __DIR__ . '/routes/prompt-template.php';
require_once __DIR__ . '/routes/style.php';

$rootDir = getcwd();
$configPath = $rootDir . DIRECTORY_SEPARATOR . 'poff.config.toon';
$route = mcpQueryString('route', 'info') ?? 'info';
$prompt = mcpQueryString('prompt', '') ?? '';
$targetFile = mcpQueryString('file', '') ?? '';
$stylePrompt = mcpQueryString('style', '') ?? '';

header('Content-Type: application/json');

$tree = mcpBuildFileTree($rootDir, $rootDir);
$configState = mcpEnsureConfig($configPath, $tree);

$mcpUrl = rtrim($_SERVER['SCRIPT_NAME'] ?? '/index.php', '/') . '#mcp';

switch ($route) {
    case 'workprompt':
        mcpJsonResponse(handleWorkPrompt([
            'rootDir' => $rootDir,
            'file' => $targetFile,
            'style' => $stylePrompt,
        ]));
    case 'create':
        mcpJsonResponse(handleCreate([
            'rootDir' => $rootDir,
            'dest' => mcpQueryString('dest', '') ?? '',
            'path' => mcpQueryString('path', null),
            'url' => mcpQueryString('url', null),
            'poffDir' => getenv('POFF_BASE') ? rtrim(getenv('POFF_BASE'), '/\\') : null,
        ]));
    case 'edit-config':
        mcpJsonResponse(handleEditConfig([
            'rootDir' => $rootDir,
            'path' => mcpQueryString('path', '') ?? '',
        ]));
    case 'prompt-template':
        mcpJsonResponse(handlePromptTemplate([
            'rootDir' => $rootDir,
            'path' => mcpQueryString('path', '') ?? '',
        ]));
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

mcpJsonResponse($response);
