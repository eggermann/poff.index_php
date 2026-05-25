<?php
/**
 * Build script that concatenates modular PHP files into a single index.php file
 * and triggers SSH upload after successful build.
 */

// Load configuration and modules
$config = require __DIR__ . '/BuildConfig.php';
require_once __DIR__ . '/ComponentReader.php';
require_once __DIR__ . '/FileCopier.php';
require_once __DIR__ . '/PoffConfigBuilder.php';

// Extract configuration
$sourceDir = $config['sourceDir'];
$outputDir = $config['outputDir'];
$outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
$outputFileName = $config['outputFile'];
$outputFile = $outputDir . $outputFileName;
$legacyOutputFile = rtrim((string) $config['outputDir'], '/\\') . $outputFileName;
$buildMode = getenv('POFF_BUILD_MODE') === 'production' ? 'production' : 'development';
$isProductionBuild = $buildMode === 'production';
$skipDistributionCopies = getenv('POFF_SKIP_DISTRIBUTION_COPIES') === '1';
$standaloneCopyTargets = [
    '/Applications/MAMP/htdocs/MAUSMAUS',
];
// Prevent accidental creation of "<outputDir>index.php" when the separator is missing.
if ($legacyOutputFile !== $outputFile && file_exists($legacyOutputFile)) {
    unlink($legacyOutputFile);
}

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

try {
    $buildContent = "<?php\n";
    $stripPhp = static function (string $content): string {
        $content = str_replace(['<?php', '?>'], '', $content);
        $content = preg_replace('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/m', '', $content) ?? $content;
        return preg_replace('/^\s*@?require_once[^\n]*\n/m', '', $content);
    };
    $stripRequires = static function (string $content): string {
        $content = preg_replace('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/m', '', $content) ?? $content;
        return preg_replace('/^\s*@?require_once[^\n]*\n/m', '', $content);
    };
    $appendPhpBlock = static function (string &$buffer, string $content): void {
        $buffer .= trim($content) . "\n\n";
    };
    $readSourceFile = static function (string $relativePath) use ($sourceDir): string {
        return ComponentReader::readComponentFile($sourceDir . $relativePath);
    };
    $appendSourceParts = static function (string &$buffer, array $relativePaths, ?callable $transform = null) use ($readSourceFile, $appendPhpBlock): void {
        foreach ($relativePaths as $relativePath) {
            $content = $readSourceFile($relativePath);
            if ($transform !== null) {
                $content = $transform($content);
            }
            $appendPhpBlock($buffer, $content);
        }
    };
    $appendExportAssignment = static function (string &$buffer, string $variable, mixed $value, ?string $setter = null) use ($appendPhpBlock): void {
        $appendPhpBlock($buffer, $variable . ' = ' . var_export($value, true) . ';');
        if ($setter !== null) {
            $appendPhpBlock($buffer, $setter);
        }
    };

    // Embed the LightnCandy renderer so the built output can render HBS templates
    // without depending on the project vendor directory at runtime.
    $lightnCandyStripPhp = static function (string $content): string {
        $content = preg_replace('/^\s*<\?php\s*/', '', $content, 1) ?? $content;
        $content = preg_replace('/\s*\?>\s*$/', '', $content, 1) ?? $content;
        return preg_replace('/^\s*namespace\s+LightnCandy\s*;\s*/m', '', $content);
    };
    $lightnCandyFiles = [
        '/vendor/zordius/lightncandy/src/Flags.php',
        '/vendor/zordius/lightncandy/src/Context.php',
        '/vendor/zordius/lightncandy/src/Token.php',
        '/vendor/zordius/lightncandy/src/Encoder.php',
        '/vendor/zordius/lightncandy/src/SafeString.php',
        '/vendor/zordius/lightncandy/src/Parser.php',
        '/vendor/zordius/lightncandy/src/Expression.php',
        '/vendor/zordius/lightncandy/src/Validator.php',
        '/vendor/zordius/lightncandy/src/Partial.php',
        '/vendor/zordius/lightncandy/src/Exporter.php',
        '/vendor/zordius/lightncandy/src/Runtime.php',
        '/vendor/zordius/lightncandy/src/Compiler.php',
        '/vendor/zordius/lightncandy/src/LightnCandy.php',
    ];
    $embeddedLightnCandySources = [];
    foreach ($lightnCandyFiles as $lightnCandyRelativePath) {
        $embeddedLightnCandySources[] = trim($lightnCandyStripPhp(
            ComponentReader::readComponentFile(__DIR__ . '/..' . $lightnCandyRelativePath)
        ));
    }
    $appendPhpBlock($buildContent, '$__embeddedLightnCandySources = ' . var_export($embeddedLightnCandySources, true) . ';');
    $appendPhpBlock($buildContent, <<<'PHP'
if (!class_exists('\LightnCandy\LightnCandy', false)) {
    foreach ($__embeddedLightnCandySources as $__lightnCandySource) {
        eval("namespace LightnCandy {\n" . $__lightnCandySource . "\n}");
    }
}
unset($__embeddedLightnCandySources, $__lightnCandySource);

PHP
    );

    // Get the first file header comment only
  
    


    // Add function definition without its doc comment
    $functionsContent = $readSourceFile('/includes/functions.php');
    if (preg_match('/function\s+extractLinkFileUrl.*?^}/ms', $functionsContent, $matches)) {
        $appendPhpBlock($buildContent, $matches[0]);
    }

    // Add MediaType helper
    $appendSourceParts($buildContent, ['/includes/MediaType.php'], static function (string $content): string {
        return str_replace(['<?php', '?>'], '', $content);
    });

    // Add converter registry before edit-mode config references it.
    $appendSourceParts($buildContent, ['/includes/Converter.php'], $stripPhp);

    // Add Worktype helper as a standalone class without runtime require_once dependencies.
    $appendSourceParts($buildContent, [
        '/includes/Worktype/State.php',
        '/includes/Worktype/Definitions.php',
        '/includes/Worktype/Context.php',
        '/includes/Worktype/Layout.php',
        '/includes/Worktype/Render.php',
        '/includes/Worktype.php',
    ], $stripPhp);
    // Embed worktype definitions and templates (prefer bundle file)
    $bundlePath = $sourceDir . '/includes/worktypes/worktypes.php';
    $embeddedWorktypes = [];
    $embeddedTemplates = [];
    $embeddedLayoutAssets = [];
    if (file_exists($bundlePath)) {
        $bundle = include $bundlePath;
        if (is_array($bundle)) {
            if (isset($bundle['definitions']) && is_array($bundle['definitions'])) {
                $embeddedWorktypes = $bundle['definitions'];
            }
            if (isset($bundle['templates']) && is_array($bundle['templates'])) {
                $embeddedTemplates = $bundle['templates'];
            }
            if (!$embeddedWorktypes) {
                foreach ($bundle as $key => $value) {
                    if (!is_array($value)) {
                        continue;
                    }
                    if (isset($value['model'])) {
                        $embeddedWorktypes[$key] = $value['model'];
                    } elseif (isset($value['definition'])) {
                        $embeddedWorktypes[$key] = $value['definition'];
                    }
                    if (isset($value['template'])) {
                        $embeddedTemplates[$key] = $value['template'];
                    }
                }
            }
        }
    }
    if (!$embeddedWorktypes) {
        $worktypeFiles = glob($sourceDir . '/includes/worktypes/*.worktype.php') ?: [];
        foreach ($worktypeFiles as $wtFile) {
            $key = pathinfo($wtFile, PATHINFO_FILENAME);
            $data = include $wtFile;
            if (is_array($data)) {
                $embeddedWorktypes[$key] = $data['model'] ?? $data['definition'] ?? $data;
            }
        }
    }
    if (!$embeddedTemplates) {
        $templateFiles = glob($sourceDir . '/includes/worktypes/templates/*.hbs') ?: [];
        $templateFiles = array_merge($templateFiles, [
            'poff-layout' => $sourceDir . '/includes/worktypes/templates/layout/default/template.hbs',
            'filesystem-layout' => $sourceDir . '/includes/worktypes/templates/layout/file-system/template.hbs',
        ]);
        if (!$templateFiles) {
            $templateFiles = glob($sourceDir . '/includes/worktypes/templates/*.tpl') ?: [];
        }
        foreach ($templateFiles as $key => $tplFile) {
            if (!is_file($tplFile)) {
                continue;
            }
            if (!is_string($key)) {
                $key = pathinfo($tplFile, PATHINFO_FILENAME);
            }
            $embeddedTemplates[$key] = file_get_contents($tplFile);
        }
    }
    foreach ([
        'poff-layout' => $sourceDir . '/includes/worktypes/templates/layout/default',
        'filesystem-layout' => $sourceDir . '/includes/worktypes/templates/layout/file-system',
    ] as $layoutName => $layoutDir) {
        if (!is_dir($layoutDir)) {
            continue;
        }
        $layoutAssets = [];
        foreach (['style.css', 'script.js'] as $assetFile) {
            $assetPath = $layoutDir . DIRECTORY_SEPARATOR . $assetFile;
            if (!is_file($assetPath)) {
                continue;
            }
            $layoutAssets[$assetFile] = file_get_contents($assetPath);
        }
        if ($layoutAssets !== []) {
            $embeddedLayoutAssets[$layoutName] = $layoutAssets;
        }
    }
    $appendExportAssignment($buildContent, '$__worktypeEmbedded', $embeddedWorktypes, "Worktype::setEmbedded(\$__worktypeEmbedded);");
    $appendExportAssignment($buildContent, '$__worktypeTemplates', $embeddedTemplates, "Worktype::setEmbeddedTemplates(\$__worktypeTemplates);");
    $appendExportAssignment($buildContent, '$__worktypeLayoutAssets', $embeddedLayoutAssets, "Worktype::setEmbeddedLayoutAssets(\$__worktypeLayoutAssets);");
    $viewerShellCssAsset = __DIR__ . '/assets/app.css';
    $appendExportAssignment(
        $buildContent,
        '$__embeddedViewerShellCss',
        is_file($viewerShellCssAsset) ? trim((string) file_get_contents($viewerShellCssAsset)) : ''
    );

    // Add edit-mode helpers, root detection, prompt/template sanitizers, and PoffConfig model so the built output stays single-file.
    $editModeHelpers = $stripPhp($readSourceFile('/includes/edit-mode.php'));
    $projectRootHelpers = $stripPhp($readSourceFile('/includes/project-root.php'));
    $promptTemplateSanitize = $stripPhp($readSourceFile('/includes/prompt-template-sanitize.php'));
    $poffConfigLayoutHelpers = $readSourceFile('/includes/PoffConfig/layout-helpers.php');
    $poffConfigCoreHelpers = $readSourceFile('/includes/PoffConfig/core-helpers.php');
    $poffConfigLayoutFiles = $readSourceFile('/includes/PoffConfig/layout-files.php');
    $poffConfigLayoutView = $readSourceFile('/includes/PoffConfig/layout-view.php');
    $poffConfigLayoutCollections = $readSourceFile('/includes/PoffConfig/layout-collections.php');
    $poffConfigPromptHelpers = $readSourceFile('/includes/PoffConfig/prompt-helpers.php');
    $viewerLinkTargets = $stripPhp($readSourceFile('/includes/viewer/link-targets.php'));
    $poffConfigContent = $readSourceFile('/includes/PoffConfig.php');
    $poffConfigContent = PoffConfigBuilder::buildClass($poffConfigContent, [
        $poffConfigLayoutHelpers,
        $poffConfigCoreHelpers,
        $poffConfigLayoutFiles,
        $poffConfigLayoutView,
        $poffConfigLayoutCollections,
        $poffConfigPromptHelpers,
    ]);
    $poffConfigContent = $stripPhp($poffConfigContent);
    $authHelpers = $stripPhp($readSourceFile('/includes/auth.php'));
    $navRenderHelpers = $stripPhp($readSourceFile('/includes/nav-render.php'));
    foreach ([
        $editModeHelpers,
        $authHelpers,
        $projectRootHelpers,
        $promptTemplateSanitize,
        $viewerLinkTargets,
        $poffConfigContent,
        $navRenderHelpers,
    ] as $phpBlock) {
        $appendPhpBlock($buildContent, $phpBlock);
    }

    // Inline viewer helpers so the built output stays single-file (no require_once).
    $viewerSharedParts = [
        '/includes/viewer/utils/core.php',
        '/includes/viewer/utils/targets.php',
        '/includes/viewer/utils/remote-html.php',
        '/includes/viewer/utils/remote-links.php',
        '/includes/viewer/utils/http.php',
        '/includes/viewer/render/entry.php',
        '/includes/viewer/render/file.php',
        '/includes/viewer/render/folder.php',
        '/includes/viewer/render/data.php',
        '/includes/viewer/render/shell.php',
    ];
    $appendSourceParts($buildContent, $viewerSharedParts, $stripRequires);

    $viewerEditParts = [
        '/includes/viewer/edit/core/config.php',
        '/includes/viewer/edit/core/parent.php',
        '/includes/viewer/edit/core/transport.php',
        '/includes/viewer/edit/prompt-parse/loose.php',
        '/includes/viewer/edit/prompt-parse/model.php',
        '/includes/viewer/edit/prompt-compact/base.php',
        '/includes/viewer/edit/prompt-compact/config.php',
        '/includes/viewer/edit/prompt-compact/context.php',
        '/includes/viewer/edit/prompt-compact/history.php',
        '/includes/viewer/edit/prompt-refs.php',
        '/includes/viewer/edit/prompt-context/wrapper.php',
        '/includes/viewer/edit/prompt-context/parent.php',
        '/includes/viewer/edit/prompt/context-state.php',
        '/includes/viewer/edit/prompt/context-build.php',
        '/includes/viewer/edit/upload.php',
        '/includes/viewer/edit/delete.php',
        '/includes/viewer/edit/reset.php',
        '/includes/viewer/edit/action/context.php',
        '/includes/viewer/edit/action/auth-action.php',
        '/includes/viewer/edit/action/config.php',
        '/includes/viewer/edit/action/upload-action.php',
        '/includes/viewer/edit/action/delete-action.php',
        '/includes/viewer/edit/action/reset-action.php',
        '/includes/viewer/edit/action/save/meta.php',
        '/includes/viewer/edit/action/save/work.php',
        '/includes/viewer/edit/action/save/layout-tree.php',
        '/includes/viewer/edit/action/save/layout-original.php',
        '/includes/viewer/edit/action/save/layout-section.php',
        '/includes/viewer/edit/action/save/layout.php',
        '/includes/viewer/edit/action/save/finalize.php',
        '/includes/viewer/edit/action/save-action.php',
        '/includes/viewer/edit/action/prompt/helpers.php',
        '/includes/viewer/edit/action/prompt/openai.php',
        '/includes/viewer/edit/action/prompt/gemini.php',
        '/includes/viewer/edit/action/prompt/local.php',
        '/includes/viewer/edit/action/prompt-action.php',
        '/includes/viewer/edit/action/models-action.php',
        '/includes/viewer/edit/action/dispatch.php',
    ];
    $appendSourceParts($buildContent, $viewerEditParts, $stripRequires);
    $appendPhpBlock($buildContent, "cmsHandleEditAction();");

    $mcpParts = [
        '/mcp/helpers.php',
        '/mcp/routes/workprompt.php',
        '/mcp/routes/create.php',
        '/mcp/routes/edit-config.php',
        '/mcp/routes/prompt-template.php',
        '/mcp/routes/remote-content.php',
        '/mcp/routes/converters.php',
        '/mcp/routes/convert.php',
        '/mcp/routes/create-converter.php',
        '/mcp/routes/converter-prompt.php',
        '/mcp/routes/style.php',
    ];
    $appendSourceParts($buildContent, $mcpParts, $stripRequires);

    // Add initialization code
    $buildContent .= <<<'PHP'
// Initialize path variables
$baseDirOverride = '';
if (defined('POFF_BASE_DIR') && is_string(POFF_BASE_DIR)) {
    $baseDirOverride = trim(POFF_BASE_DIR);
} elseif (isset($_GET['base']) && is_string($_GET['base'])) {
    $baseDirOverride = trim($_GET['base']);
}
$resolvedBaseDir = $baseDirOverride !== '' ? realpath($baseDirOverride) : false;
$baseDir = ($resolvedBaseDir !== false && is_dir($resolvedBaseDir)) ? $resolvedBaseDir : (realpath(__DIR__) ?: __DIR__);
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$currentRelativePath = isset($_GET['path']) ? trim($_GET['path'], '/\\') : '';
$currentAbsolutePath = $baseDir . ($currentRelativePath ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentRelativePath) : '');

// Directory Traversal Protection
if (
    strpos($currentRelativePath, '..') !== false ||
    strpos($currentRelativePath, "\0") !== false ||
    preg_match('#^[\\/]|[\\/]{2,}#', $currentRelativePath)
) {
    http_response_code(400);
    die('Invalid path.');
}

// Viewer route for typed rendering
if (isset($_GET['view']) && (isset($_GET['file']) || array_key_exists('path', $_GET))) {
    renderViewer($baseDir, $_GET['file'] ?? $_GET['path']);
    return;
}

if ((string) ($_GET['mcp'] ?? '') === '1') {
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
        $route = mcpQueryString('route', 'info') ?? 'info';
        $prompt = mcpQueryString('prompt', '') ?? '';
        $targetFile = mcpRouteFile();
        $stylePrompt = mcpRouteStyle();
        $mcpUrl = rtrim((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'), '/') . '#mcp';

        header('Content-Type: application/json');

        switch ($route) {
            case 'workprompt':
                mcpJsonResponse(handleWorkPrompt(mcpWorkPromptArgs($baseDir, $targetFile, $stylePrompt)));
            case 'create':
                mcpJsonResponse(handleCreate(mcpCreateArgs($baseDir, [
                    'dest' => mcpRouteDest(),
                    'path' => mcpQueryString('path', null),
                    'url' => mcpQueryString('url', null),
                    'poffDir' => mcpPoffDirOverride(),
                ])));
            case 'edit-config':
                mcpJsonResponse(handleEditConfig([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                ]));
            case 'prompt-template':
                mcpJsonResponse(handlePromptTemplate([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                ]));
            case 'export-content':
                mcpJsonResponse(handleExportContent([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                ]));
            case 'import-remote':
                mcpJsonResponse(handleImportRemote([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                    'url' => mcpQueryString('url', '') ?? '',
                    'sourceId' => mcpQueryString('sourceId', '') ?? '',
                    'replace' => in_array(strtolower(mcpQueryString('replace', '') ?? ''), ['1', 'true', 'yes'], true),
                ]));
            case 'converters':
                mcpJsonResponse(handleConverters([
                    'rootDir' => $baseDir,
                    'input' => mcpConvertersInput(),
                ]));
            case 'convert':
                mcpJsonResponse(handleConvert([
                    'rootDir' => $baseDir,
                    'payload' => mcpReadRequestData(),
                ]));
            case 'create-converter':
                mcpJsonResponse(handleCreateConverter([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                    'payload' => mcpReadRequestData(),
                ]));
            case 'save-converted-work':
                mcpJsonResponse(handleSaveConvertedWork([
                    'rootDir' => $baseDir,
                    'payload' => mcpReadRequestData(),
                ]));
            case 'converter-prompt':
                mcpJsonResponse(handleConverterPrompt([
                    'rootDir' => $baseDir,
                    'path' => mcpRoutePath(),
                ]));
            case 'style':
                mcpJsonResponse(handleStyleRoute($prompt, $mcpUrl, $baseDir . DIRECTORY_SEPARATOR . 'poff.config.toon'));
            default:
                mcpJsonResponse([
                    'route' => 'info',
                    'mcpUrl' => $mcpUrl,
                    'rootDir' => $baseDir,
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

if (isset($_GET['ajax']) && $_GET['ajax'] === 'nav') {
    if (is_dir($currentAbsolutePath)) {
        $folderPoffConfig = class_exists('PoffConfig') ? PoffConfig::ensure($currentAbsolutePath) : null;
    }
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

// Route mapper for hash-only slugs. Browser hashes are not sent on refresh,
// so the client asks this endpoint to map #slug back to the filesystem item.
if (isset($_GET['ajax']) && $_GET['ajax'] === 'resolve') {
    cmsHandleResolveRoute($baseDir);
}

// Read folder config if it exists
$folderPoffConfig = null;
if (is_dir($currentAbsolutePath)) {
    $folderPoffConfig = PoffConfig::ensure($currentAbsolutePath);
}

PHP;

    // Close PHP tag for HTML content
    $buildContent .= "?>\n";

    // Add HTML structure from header.php without PHP tags
    $content = ComponentReader::readComponentFile($sourceDir . '/includes/header.built.php');
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    $content = str_replace('<html lang="en">', '<html lang="de">', $content);
    $buildContent .= trim($content) . "\n";

    // Add layout.php content and inject nav placeholder from includes/nav.php
    $layout = ComponentReader::readComponentFile($sourceDir . '/includes/layout.php');
    $layout = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $layout);
    $nav = ComponentReader::readComponentFile($sourceDir . '/includes/nav.php');
    $nav = preg_replace('/<\?php\s*|\?>/s', '', $nav); // strip PHP tags before inlining
    $nav = preg_replace('/^\s*require_once[^\n]*\n/m', '', $nav);
    $layout = str_replace('<!-- NAV_PLACEHOLDER -->', '<?php ' . trim($nav) . ' ?>', $layout);
    $buildContent .= trim($layout) . "\n";

    // Add scripts.php content with JavaScript variables
    $content = ComponentReader::readComponentFile($sourceDir . '/includes/scripts.built.php');
    // Remove all PHP tags first
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    // Add back PHP expressions for JavaScript variables
    $content = str_replace(
        'const currentPoffConfig = /* POFF_CONTEXT */ null;',
        'const currentPoffConfig = <?php echo $folderPoffConfig ? json_encode($folderPoffConfig) : "null"; ?>;',
        $content
    );
    $content = str_replace(
        'const currentPathForIframe = /* POFF_IFRAME_PATH */ null;',
        'const currentPathForIframe = <?php echo json_encode($currentRelativePath); ?>;',
        $content
    );
    $content = str_replace(
        'window.POFF_CONTEXT = { currentPoffConfig, currentPathForIframe };',
        'const cmsAuth = <?php $poffAuthScopeDir = is_dir($currentAbsolutePath) ? $currentAbsolutePath : dirname($currentAbsolutePath); echo json_encode(cmsBuildEditorAuthView($baseDir, cmsEditModeAllowedForDirectory($poffAuthScopeDir, $baseDir))); ?>;' . "\n" . 'window.POFF_CONTEXT = { currentPoffConfig, currentPathForIframe, cmsAuth };',
        $content
    );
    $buildContent .= trim($content);

    if ($isProductionBuild) {
        $tempOutputFile = tempnam(sys_get_temp_dir(), 'poff-release-');
        if ($tempOutputFile === false) {
            throw new Exception('Failed to allocate temporary file for production build.');
        }
        if (file_put_contents($tempOutputFile, $buildContent) === false) {
            @unlink($tempOutputFile);
            throw new Exception("Failed to write temporary output file: $tempOutputFile");
        }
        $strippedBuildContent = php_strip_whitespace($tempOutputFile);
        @unlink($tempOutputFile);
        if ($strippedBuildContent === '') {
            throw new Exception('Failed to strip PHP whitespace for production build.');
        }
        $buildContent = $strippedBuildContent;
    }

    // Write the concatenated content to the output file
    if (file_put_contents($outputFile, $buildContent) === false) {
        throw new Exception("Failed to write output file: $outputFile");
    }

    if (!$skipDistributionCopies) {
        foreach ($standaloneCopyTargets as $targetDir) {
            $resolvedTargetDir = rtrim((string) $targetDir, '/\\');
            if ($resolvedTargetDir === '') {
                continue;
            }
            if (!is_dir($resolvedTargetDir) && !mkdir($resolvedTargetDir, 0755, true) && !is_dir($resolvedTargetDir)) {
                throw new Exception("Failed to create standalone output directory: $resolvedTargetDir");
            }

            $targetFile = $resolvedTargetDir . DIRECTORY_SEPARATOR . $outputFileName;
            if (!copy($outputFile, $targetFile)) {
                throw new Exception("Failed to copy standalone build to: $targetFile");
            }

            $layoutAsset = $sourceDir . '/includes/worktypes/templates/layout/default/poff.profile.jpg';
            if (is_file($layoutAsset)) {
                FileCopier::copyFileToLayoutDirectories($layoutAsset, $resolvedTargetDir);
            }
        }

        // Copy bundled default layout assets into each public .layout folder so wrapper assets stay web-accessible.
        $layoutAsset = $sourceDir . '/includes/worktypes/templates/layout/default/poff.profile.jpg';
        if (is_file($layoutAsset)) {
            FileCopier::copyFileToLayoutDirectories($layoutAsset, $outputDir);
        }
    }

    echo "Build completed successfully ($buildMode): $outputFile\n";

    // Trigger SSH upload using SSHUploader
    require_once __DIR__ . '/SSHUploader.php';
    //--> SSHUploader::upload();

} catch (Exception $e) {
    echo "Build failed: " . $e->getMessage() . "\n";
    exit(1);
}
