<?php
/**
 * Simple MCP endpoint for exposing the current file tree and config state.
 * - Creates or updates poff.config.toon on first contact.
 * - Exposes JSON routes under /index.php?mcp=1[&route=...]
 *
 * MCP is an adapter layer for tools/agents. Core layout inheritance and
 * rendering live in PoffConfig, Worktype, and viewer/render.
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
@require_once __DIR__ . '/../includes/MediaType.php';
@require_once __DIR__ . '/../includes/Worktype.php';
@require_once __DIR__ . '/../includes/Converter.php';
@require_once __DIR__ . '/../includes/PoffConfig.php';
require_once __DIR__ . '/routes/workprompt.php';
require_once __DIR__ . '/routes/create.php';
require_once __DIR__ . '/routes/edit-config.php';
require_once __DIR__ . '/routes/prompt-template.php';
require_once __DIR__ . '/routes/remote-content.php';
require_once __DIR__ . '/routes/converters.php';
require_once __DIR__ . '/routes/convert.php';
require_once __DIR__ . '/routes/style.php';

$runtime = mcpRuntimeContext();
$rootDir = $runtime['rootDir'];
$configPath = $runtime['configPath'];
$route = mcpQueryString('route', 'info') ?? 'info';
$prompt = mcpQueryString('prompt', '') ?? '';
$targetFile = mcpRouteFile();
$stylePrompt = mcpRouteStyle();

header('Content-Type: application/json');

$tree = mcpBuildFileTree($rootDir, $rootDir);
$configState = mcpEnsureConfig($configPath, $tree);

$mcpUrl = $runtime['mcpUrl'];

switch ($route) {
    case 'workprompt':
        mcpJsonResponse(handleWorkPrompt(mcpWorkPromptArgs($rootDir, $targetFile, $stylePrompt)));
    case 'create':
        mcpJsonResponse(handleCreate(mcpCreateArgs($rootDir, [
            'dest' => mcpRouteDest(),
            'path' => mcpQueryString('path', null),
            'url' => mcpQueryString('url', null),
            'poffDir' => $runtime['poffDir'],
        ])));
    case 'edit-config':
        mcpJsonResponse(handleEditConfig([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
        ]));
    case 'prompt-template':
        mcpJsonResponse(handlePromptTemplate([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
        ]));
    case 'export-content':
        mcpJsonResponse(handleExportContent([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
        ]));
    case 'import-remote':
        mcpJsonResponse(handleImportRemote([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
            'url' => mcpQueryString('url', '') ?? '',
            'sourceId' => mcpQueryString('sourceId', '') ?? '',
            'replace' => in_array(strtolower(mcpQueryString('replace', '') ?? ''), ['1', 'true', 'yes'], true),
        ]));
    case 'converters':
        mcpJsonResponse(handleConverters([
            'rootDir' => $rootDir,
            'input' => mcpConvertersInput(),
        ]));
    case 'convert':
        mcpJsonResponse(handleConvert([
            'rootDir' => $rootDir,
            'payload' => mcpReadRequestData(),
        ]));
    case 'save-converted-work':
        mcpJsonResponse(handleSaveConvertedWork([
            'rootDir' => $rootDir,
            'payload' => mcpReadRequestData(),
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
