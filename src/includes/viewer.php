<?php
/**
 * Viewer bootstrap: loads edit endpoints and render helpers.
 */

require_once __DIR__ . '/viewer/utils.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/nav-render.php';
require_once __DIR__ . '/viewer/edit.php';
require_once __DIR__ . '/viewer/render.php';

if ((string) ($_GET['mcp'] ?? '') === '1') {
    require_once __DIR__ . '/../mcp/helpers.php';
    @require_once __DIR__ . '/MediaType.php';
    @require_once __DIR__ . '/Worktype.php';
    @require_once __DIR__ . '/Converter.php';
    @require_once __DIR__ . '/PoffConfig.php';
    require_once __DIR__ . '/../mcp/routes/workprompt.php';
    require_once __DIR__ . '/../mcp/routes/create.php';
    require_once __DIR__ . '/../mcp/routes/edit-config.php';
    require_once __DIR__ . '/../mcp/routes/prompt-template.php';
    require_once __DIR__ . '/../mcp/routes/remote-content.php';
    require_once __DIR__ . '/../mcp/routes/converters.php';
    require_once __DIR__ . '/../mcp/routes/convert.php';
    require_once __DIR__ . '/../mcp/routes/create-converter.php';
    require_once __DIR__ . '/../mcp/routes/converter-prompt.php';
    require_once __DIR__ . '/../mcp/routes/style.php';

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true) || headers_sent()) {
            return;
        }
        mcpJsonResponse([
            'ok' => false,
            'error' => sprintf(
                'MCP route fatal error: %s in %s:%d',
                (string) ($error['message'] ?? 'Unknown error'),
                (string) ($error['file'] ?? 'unknown'),
                (int) ($error['line'] ?? 0)
            ),
        ], 500);
    });

    try {
        $runtime = mcpRuntimeContext();
        $rootDir = $runtime['rootDir'];
        $route = mcpQueryString('route', 'info') ?? 'info';
        $prompt = mcpQueryString('prompt', '') ?? '';
        $targetFile = mcpRouteFile();
        $stylePrompt = mcpRouteStyle();
        $mcpUrl = $runtime['mcpUrl'];

        header('Content-Type: application/json');

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
            case 'converter-prompt':
                mcpJsonResponse(handleConverterPrompt([
                    'rootDir' => $rootDir,
                    'path' => mcpRoutePath(),
                ]));
            case 'style':
                mcpJsonResponse(handleStyleRoute($prompt, $mcpUrl, $rootDir . DIRECTORY_SEPARATOR . 'poff.config.toon'));
            default:
                mcpJsonResponse([
                    'route' => 'info',
                    'mcpUrl' => $mcpUrl,
                    'rootDir' => $rootDir,
                ]);
        }
    } catch (Throwable $error) {
        mcpJsonResponse([
            'ok' => false,
            'error' => sprintf(
                'MCP route error: %s in %s:%d',
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            ),
        ], 500);
    }
}

if (($_GET['ajax'] ?? '') === 'snapshot') {
    $baseDir = cmsProjectRootDir();
    cmsHandleSnapshotRoute($baseDir);
}

if (($_GET['ajax'] ?? '') === 'nav') {
    $baseDir = cmsProjectRootDir();
    $currentRelativePath = isset($_GET['path']) ? trim((string) $_GET['path'], '/\\') : '';
    $currentAbsolutePath = $baseDir . ($currentRelativePath ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentRelativePath) : '');
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $folderPoffConfig = is_dir($currentAbsolutePath) && class_exists('PoffConfig')
        ? PoffConfig::ensure($currentAbsolutePath)
        : null;

    header('Content-Type: text/html; charset=UTF-8');
    echo '<ul id="navList" class="nav-list">';
    echo cmsRenderNavListMarkup([
        'baseDir' => $baseDir,
        'currentRelativePath' => $currentRelativePath,
        'currentAbsolutePath' => $currentAbsolutePath,
        'currentScript' => $currentScript,
        'folderPoffConfig' => $folderPoffConfig,
    ]);
    echo '</ul>';
    return;
}

// Handle edit/prompt requests if present.
$viewerEditAction = $_GET['edit'] ?? '';
if (in_array($viewerEditAction, ['auth', 'config', 'save', 'prompt', 'upload', 'delete', 'reset', 'models'], true)) {
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true) || headers_sent()) {
            return;
        }
        cmsJsonResponse([
            'allowed' => true,
            'error' => sprintf(
                'Edit endpoint fatal error: %s in %s:%d',
                (string) ($error['message'] ?? 'Unknown error'),
                (string) ($error['file'] ?? 'unknown'),
                (int) ($error['line'] ?? 0)
            ),
        ], 500);
    });

    try {
        cmsHandleEditAction();
    } catch (Throwable $error) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => sprintf(
                'Edit endpoint error: %s in %s:%d',
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            ),
        ], 500);
    }
} else {
    cmsHandleEditAction();
}
