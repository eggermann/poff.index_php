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
    $buildContent .= '$__embeddedLightnCandySources = ' . var_export($embeddedLightnCandySources, true) . ";\n";
    $buildContent .= <<<'PHP'
if (!class_exists('\LightnCandy\LightnCandy', false)) {
    foreach ($__embeddedLightnCandySources as $__lightnCandySource) {
        eval("namespace LightnCandy {\n" . $__lightnCandySource . "\n}");
    }
}
unset($__embeddedLightnCandySources, $__lightnCandySource);

PHP;
    $buildContent .= "\n";

    // Get the first file header comment only
  
    


    // Add function definition without its doc comment
    $functionsContent = ComponentReader::readComponentFile($sourceDir . '/includes/functions.php');
    if (preg_match('/function\s+extractLinkFileUrl.*?^}/ms', $functionsContent, $matches)) {
        $buildContent .= $matches[0] . "\n\n";
    }

    // Add MediaType helper
    $mediaTypeContent = ComponentReader::readComponentFile($sourceDir . '/includes/MediaType.php');
    $mediaTypeContent = str_replace(['<?php', '?>'], '', $mediaTypeContent);
    $buildContent .= trim($mediaTypeContent) . "\n\n";

    // Add Worktype helper as a standalone class without runtime require_once dependencies.
    $worktypeState = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype/State.php');
    $worktypeDefinitions = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype/Definitions.php');
    $worktypeContext = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype/Context.php');
    $worktypeLayout = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype/Layout.php');
    $worktypeRender = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype/Render.php');
    $worktypeContent = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype.php');
    $stripStandalonePhp = static function (string $content): string {
        $content = str_replace(['<?php', '?>'], '', $content);
        return preg_replace('/^\s*require_once[^\n]*\n/m', '', $content);
    };
    $buildContent .= trim($stripStandalonePhp($worktypeState)) . "\n\n";
    $buildContent .= trim($stripStandalonePhp($worktypeDefinitions)) . "\n\n";
    $buildContent .= trim($stripStandalonePhp($worktypeContext)) . "\n\n";
    $buildContent .= trim($stripStandalonePhp($worktypeLayout)) . "\n\n";
    $buildContent .= trim($stripStandalonePhp($worktypeRender)) . "\n\n";
    $buildContent .= trim($stripStandalonePhp($worktypeContent)) . "\n\n";
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
    $buildContent .= '$__worktypeEmbedded = ' . var_export($embeddedWorktypes, true) . ";\n";
    $buildContent .= "Worktype::setEmbedded(\$__worktypeEmbedded);\n\n";
    $buildContent .= '$__worktypeTemplates = ' . var_export($embeddedTemplates, true) . ";\n";
    $buildContent .= "Worktype::setEmbeddedTemplates(\$__worktypeTemplates);\n\n";
    $buildContent .= '$__worktypeLayoutAssets = ' . var_export($embeddedLayoutAssets, true) . ";\n";
    $buildContent .= "Worktype::setEmbeddedLayoutAssets(\$__worktypeLayoutAssets);\n\n";
    $viewerShellCssAsset = __DIR__ . '/assets/app.css';
    $buildContent .= '$__embeddedViewerShellCss = ' . var_export(
        is_file($viewerShellCssAsset) ? trim((string) file_get_contents($viewerShellCssAsset)) : '',
        true
    ) . ";\n\n";

    // Add edit-mode helpers, root detection, prompt/template sanitizers, and PoffConfig model so the built output stays single-file.
    $editModeHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/edit-mode.php');
    $projectRootHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/project-root.php');
    $promptTemplateSanitize = ComponentReader::readComponentFile($sourceDir . '/includes/prompt-template-sanitize.php');
    $poffConfigLayoutHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-helpers.php');
    $poffConfigCoreHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/core-helpers.php');
    $poffConfigLayoutFiles = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-files.php');
    $poffConfigLayoutView = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-view.php');
    $poffConfigLayoutCollections = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-collections.php');
    $poffConfigPromptHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/prompt-helpers.php');
    $viewerLinkTargets = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/link-targets.php');
    $poffConfigContent = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig.php');
    $stripPhp = static function (string $content): string {
        $content = str_replace(['<?php', '?>'], '', $content);
        return preg_replace('/^\s*require_once[^\n]*\n/m', '', $content);
    };
    $editModeHelpers = $stripPhp($editModeHelpers);
    $projectRootHelpers = $stripPhp($projectRootHelpers);
    $promptTemplateSanitize = $stripPhp($promptTemplateSanitize);
    $poffConfigContent = PoffConfigBuilder::buildClass($poffConfigContent, [
        $poffConfigLayoutHelpers,
        $poffConfigCoreHelpers,
        $poffConfigLayoutFiles,
        $poffConfigLayoutView,
        $poffConfigLayoutCollections,
        $poffConfigPromptHelpers,
    ]);
    $poffConfigContent = $stripPhp($poffConfigContent);
    $viewerLinkTargets = $stripPhp($viewerLinkTargets);
    $buildContent .= trim($editModeHelpers) . "\n\n";
    $buildContent .= trim($projectRootHelpers) . "\n\n";
    $buildContent .= trim($promptTemplateSanitize) . "\n\n";
    $buildContent .= trim($viewerLinkTargets) . "\n\n";
    $buildContent .= trim($poffConfigContent) . "\n\n";

    // Inline viewer helpers so the built output stays single-file (no require_once).
    $stripRequires = static function (string $content): string {
        return preg_replace('/^\s*require_once[^\n]*\n/m', '', $content);
    };
    $viewerEditParts = [
        '/includes/viewer/utils.php',
        '/includes/viewer/edit/core/config.php',
        '/includes/viewer/edit/core/parent.php',
        '/includes/viewer/edit/core/transport.php',
        '/includes/viewer/edit/prompt-parse/loose.php',
        '/includes/viewer/edit/prompt-parse/model.php',
        '/includes/viewer/edit/prompt-compact/base.php',
        '/includes/viewer/edit/prompt-compact/config.php',
        '/includes/viewer/edit/prompt-compact/context.php',
        '/includes/viewer/edit/prompt-compact/history.php',
        '/includes/viewer/edit/prompt-context/wrapper.php',
        '/includes/viewer/edit/prompt-context/parent.php',
        '/includes/viewer/edit/prompt/context-state.php',
        '/includes/viewer/edit/prompt/context-build.php',
        '/includes/viewer/edit/upload.php',
        '/includes/viewer/edit/delete.php',
        '/includes/viewer/edit/reset.php',
        '/includes/viewer/edit/action/context.php',
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
        '/includes/viewer/edit/action/dispatch.php',
        '/includes/viewer/render/entry.php',
        '/includes/viewer/render/file.php',
        '/includes/viewer/render/folder.php',
        '/includes/viewer/render/data.php',
        '/includes/viewer/render/shell.php',
    ];
    foreach ($viewerEditParts as $relativePath) {
        $content = ComponentReader::readComponentFile($sourceDir . $relativePath);
        $content = $stripRequires($content);
        $buildContent .= trim($content) . "\n\n";
    }
    $buildContent .= "cmsHandleEditAction();\n\n";

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
    $buildContent .= trim($content) . "\n";

    // Add layout.php content and inject nav placeholder from includes/nav.php
    $layout = ComponentReader::readComponentFile($sourceDir . '/includes/layout.php');
    $layout = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $layout);
    $nav = ComponentReader::readComponentFile($sourceDir . '/includes/nav.php');
    $nav = preg_replace('/<\?php\s*|\?>/s', '', $nav); // strip PHP tags before inlining
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
    $buildContent .= trim($content);

    // Write the concatenated content to the output file
    if (file_put_contents($outputFile, $buildContent) === false) {
        throw new Exception("Failed to write output file: $outputFile");
    }

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
    }

    echo "Build completed successfully!\n";

    // Copy the built index.php to all directories
    FileCopier::copyFileToAllDirectories($outputFile, $outputDir);

    // Copy bundled default layout assets into each public .layout folder so wrapper assets stay web-accessible.
    $layoutAsset = $sourceDir . '/includes/worktypes/templates/layout/default/eggman_profile-image.jpg';
    if (is_file($layoutAsset)) {
        FileCopier::copyFileToLayoutDirectories($layoutAsset, $outputDir);
    }

    // Trigger SSH upload using SSHUploader
    require_once __DIR__ . '/SSHUploader.php';
    //--> SSHUploader::upload();

} catch (Exception $e) {
    echo "Build failed: " . $e->getMessage() . "\n";
    exit(1);
}
