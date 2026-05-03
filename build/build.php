<?php
/**
 * Build script that concatenates modular PHP files into a single index.php file
 * and triggers SSH upload after successful build.
 */

// Load configuration and modules
$config = require __DIR__ . '/BuildConfig.php';
require_once __DIR__ . '/ComponentReader.php';
require_once __DIR__ . '/FileCopier.php';

// Extract configuration
$sourceDir = $config['sourceDir'];
$outputDir = $config['outputDir'];
$outputDir = rtrim($outputDir, '/\\') . DIRECTORY_SEPARATOR;
$outputFileName = $config['outputFile'];
$outputFile = $outputDir . $outputFileName;
$legacyOutputFile = rtrim((string) $config['outputDir'], '/\\') . $outputFileName;
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

    // Add Worktype helper
    $worktypeContent = ComponentReader::readComponentFile($sourceDir . '/includes/Worktype.php');
    $worktypeContent = str_replace(['<?php', '?>'], '', $worktypeContent);
    $buildContent .= trim($worktypeContent) . "\n\n";
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

    // Add edit-mode helpers, root detection, prompt/template sanitizers, and PoffConfig model so the built output stays single-file.
    $editModeHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/edit-mode.php');
    $projectRootHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/project-root.php');
    $promptTemplateSanitize = ComponentReader::readComponentFile($sourceDir . '/includes/prompt-template-sanitize.php');
    $poffConfigLayoutHelpers = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-helpers.php');
    $viewerLinkTargets = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/link-targets.php');
    $poffConfigContent = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig.php');
    $editModeHelpers = str_replace(['<?php', '?>'], '', $editModeHelpers);
    $projectRootHelpers = str_replace(['<?php', '?>'], '', $projectRootHelpers);
    $promptTemplateSanitize = str_replace(['<?php', '?>'], '', $promptTemplateSanitize);
    $poffConfigLayoutHelpers = str_replace(['<?php', '?>'], '', $poffConfigLayoutHelpers);
    $viewerLinkTargets = str_replace(['<?php', '?>'], '', $viewerLinkTargets);
    $poffConfigContent = str_replace(['<?php', '?>'], '', $poffConfigContent);
    $poffConfigContent = preg_replace('/^\s*require_once[^\n]*\n/m', '', $poffConfigContent);
    $buildContent .= trim($editModeHelpers) . "\n\n";
    $buildContent .= trim($projectRootHelpers) . "\n\n";
    $buildContent .= trim($promptTemplateSanitize) . "\n\n";
    $buildContent .= trim($poffConfigLayoutHelpers) . "\n\n";
    $buildContent .= trim($viewerLinkTargets) . "\n\n";
    $buildContent .= trim($poffConfigContent) . "\n\n";

    // Inline viewer helpers so the built output stays single-file (no require_once).
    $viewerUtils = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/utils.php');
    $viewerEdit = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit.php');
    $viewerEditPromptParse = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/prompt-parse.php');
    $viewerEditPromptRefs = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/prompt-refs.php');
    $viewerEditPromptContext = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/prompt-context.php');
    $viewerEditPromptCompact = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/prompt-compact.php');
    $viewerEditUpload = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/upload.php');
    $viewerEditDelete = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/edit/delete.php');
    $viewerRenderEntry = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/render/entry.php');
    $viewerRenderFile = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/render/file.php');
    $viewerRenderFolder = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/render/folder.php');
    $viewerRenderData = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/render/data.php');
    $viewerRenderShell = ComponentReader::readComponentFile($sourceDir . '/includes/viewer/render/shell.php');
    $stripRequires = static function (string $content): string {
        return preg_replace('/^\s*require_once[^\n]*\n/m', '', $content);
    };
    $viewerUtils = $stripRequires($viewerUtils);
    $viewerEdit = $stripRequires($viewerEdit);
    $viewerEditPromptParse = $stripRequires($viewerEditPromptParse);
    $viewerEditPromptRefs = $stripRequires($viewerEditPromptRefs);
    $viewerEditPromptContext = $stripRequires($viewerEditPromptContext);
    $viewerEditPromptCompact = $stripRequires($viewerEditPromptCompact);
    $viewerEditUpload = $stripRequires($viewerEditUpload);
    $viewerEditDelete = $stripRequires($viewerEditDelete);
    $viewerRenderEntry = $stripRequires($viewerRenderEntry);
    $viewerRenderFile = $stripRequires($viewerRenderFile);
    $viewerRenderFolder = $stripRequires($viewerRenderFolder);
    $viewerRenderData = $stripRequires($viewerRenderData);
    $viewerRenderShell = $stripRequires($viewerRenderShell);
    $buildContent .= trim($viewerUtils) . "\n\n";
    $buildContent .= trim($viewerEdit) . "\n\n";
    $buildContent .= trim($viewerEditPromptParse) . "\n\n";
    $buildContent .= trim($viewerEditPromptRefs) . "\n\n";
    $buildContent .= trim($viewerEditPromptContext) . "\n\n";
    $buildContent .= trim($viewerEditPromptCompact) . "\n\n";
    $buildContent .= trim($viewerEditUpload) . "\n\n";
    $buildContent .= trim($viewerEditDelete) . "\n\n";
    $buildContent .= trim($viewerRenderEntry) . "\n\n";
    $buildContent .= trim($viewerRenderFile) . "\n\n";
    $buildContent .= trim($viewerRenderFolder) . "\n\n";
    $buildContent .= trim($viewerRenderData) . "\n\n";
    $buildContent .= trim($viewerRenderShell) . "\n\n";
    $buildContent .= "cmsHandleEditAction();\n\n";

    // Add initialization code
    $buildContent .= <<<'PHP'
// Initialize path variables
$baseDir = realpath(__DIR__) ?: __DIR__;
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
