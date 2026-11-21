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
@require_once __DIR__ . '/../includes/NodeIdentity.php';
require_once __DIR__ . '/routes/workprompt.php';
require_once __DIR__ . '/routes/create.php';
require_once __DIR__ . '/routes/edit-config.php';
require_once __DIR__ . '/routes/prompt-template.php';
require_once __DIR__ . '/routes/remote-content.php';
require_once __DIR__ . '/routes/reconnect-node.php';
require_once __DIR__ . '/routes/remote-presentation.php';
require_once __DIR__ . '/routes/converters.php';
require_once __DIR__ . '/routes/convert.php';
require_once __DIR__ . '/routes/converter-network.php';
require_once __DIR__ . '/routes/create-converter.php';
require_once __DIR__ . '/routes/converter-prompt.php';
require_once __DIR__ . '/routes/style.php';

$runtime = mcpRuntimeContext();
$rootDir = $runtime['rootDir'];
$configPath = $runtime['configPath'];
$route = mcpQueryString('route', 'info') ?? 'info';
$prompt = mcpQueryString('prompt', '') ?? '';
$targetFile = mcpRouteFile();
$stylePrompt = mcpRouteStyle();

header('Content-Type: application/json');

if ($route === 'reconnect-node') {
    mcpJsonResponse(handleReconnectNode($rootDir, mcpReadRequestData(), $_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

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
        $remoteInput = mcpReadRequestData();
        mcpJsonResponse(handleImportRemote([
            'rootDir' => $rootDir,
            'path' => (string) ($remoteInput['path'] ?? mcpRoutePath()),
            'url' => (string) ($remoteInput['url'] ?? (mcpQueryString('url', '') ?? '')),
            'sourceId' => (string) ($remoteInput['sourceId'] ?? (mcpQueryString('sourceId', '') ?? '')),
            'replace' => (bool) ($remoteInput['replace'] ?? in_array(strtolower(mcpQueryString('replace', '') ?? ''), ['1', 'true', 'yes'], true)),
            'mode' => (string) ($remoteInput['mode'] ?? 'follow'),
            'destinationName' => (string) ($remoteInput['destinationName'] ?? ''),
            'createPlayer' => (bool) ($remoteInput['createPlayer'] ?? false),
        ]));
    case 'remote-work-presentation':
        mcpJsonResponse(handleRemoteWorkPresentation([
            'rootDir' => $rootDir,
            'input' => mcpReadRequestData(),
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
    case 'converter-offers':
        mcpJsonResponse(handleConverterOffers([
            'rootDir' => $rootDir,
            'input' => mcpReadRequestData(),
        ]));
    case 'remote-converters':
        mcpJsonResponse(handleRemoteConverters([
            'rootDir' => $rootDir,
            'input' => mcpReadRequestData(),
        ]));
    case 'register-node':
        mcpJsonResponse(handleRegisterPoffNode([
            'rootDir' => $rootDir,
            'input' => mcpReadRequestData(),
        ]));
    case 'convert-offered':
        mcpJsonResponse(handleConvertOffered([
            'rootDir' => $rootDir,
            'payload' => mcpReadLimitedRequestData(Converter::MAX_REMOTE_REQUEST_BYTES),
        ]));
    case 'create-converter':
        mcpJsonResponse(handleCreateConverter([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
            'payload' => mcpReadRequestData(),
        ]));
    case 'save-converted-work':
        mcpJsonResponse(handleSaveConvertedWork([
            'rootDir' => $rootDir,
            'payload' => mcpReadRequestData(),
        ]));
    case 'bulk-convert':
        mcpJsonResponse(handleBulkConvert([
            'rootDir' => $rootDir,
            'payload' => mcpReadRequestData(),
        ]));
    case 'converter-prompt':
        mcpJsonResponse(handleConverterPrompt([
            'rootDir' => $rootDir,
            'path' => mcpRoutePath(),
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
