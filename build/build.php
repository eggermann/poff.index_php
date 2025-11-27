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
$outputFile = $outputDir . $config['outputFile'];
// Prevent accidental creation of pages/dominikeggermann.comindex.php
if (file_exists($outputDir . 'index.php') && $outputDir !== (__DIR__ . '/../pages/dominikeggermann.com/')) {
    unlink($outputDir . 'index.php');
    echo ('????');
    die();
}

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

try {
    $buildContent = "<?php\n";

    // Get the first file header comment only
    $indexContent = file_get_contents($sourceDir . '/index.php');
    // Get header comment by finding first comment with `File:` in it
    preg_match_all('/\/\*\*.*?\*\//s', $indexContent, $matches);
    if ($matches[0]) {
        foreach ($matches[0] as $comment) {
            if (strpos($comment, 'File:') !== false) {
                $buildContent .= trim(str_replace('<?php', '', $comment)) . "\n";
                break;
            }
        }
    }

    // Add function definition without its doc comment
    $functionsContent = ComponentReader::readComponentFile($sourceDir . '/includes/functions.php');
    if (preg_match('/function\s+extractLinkFileUrl.*?^}/ms', $functionsContent, $matches)) {
        $buildContent .= $matches[0] . "\n\n";
    }

    // Add MediaType helper
    $mediaTypeContent = ComponentReader::readComponentFile($sourceDir . '/includes/MediaType.php');
    $mediaTypeContent = str_replace(['<?php', '?>'], '', $mediaTypeContent);
    $buildContent .= trim($mediaTypeContent) . "\n\n";

    // Add PoffConfig model
    $poffConfigContent = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig.php');
    $poffConfigContent = str_replace(['<?php', '?>'], '', $poffConfigContent);
    $buildContent .= trim($poffConfigContent) . "\n\n";

    // Add viewer partial (functions only)
    $viewerContent = ComponentReader::readComponentFile($sourceDir . '/includes/viewer.php');
    $viewerContent = preg_replace('/^<\?php\s*/', '', $viewerContent);
    $viewerContent = preg_replace('/\?>\s*$/', '', $viewerContent);
    $buildContent .= trim($viewerContent) . "\n\n";

    // Add initialization code
    $buildContent .= <<<'PHP'
// Initialize path variables
$baseDir = getcwd(); // Use current working directory
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
if (isset($_GET['view']) && isset($_GET['file'])) {
    renderViewer($baseDir, $_GET['file']);
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
    $content = ComponentReader::readComponentFile($sourceDir . '/includes/header.php');
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    $buildContent .= trim($content) . "\n";

    // Add layout.php content with navigation code
    $content = ComponentReader::readComponentFile($sourceDir . '/includes/layout.php');
    // Clean up HTML structure and PHP tags, preserving the navigation code
    if (preg_match('/(\/\/ Generate navigation.*?)(?=<\/ul>)/s', $content, $matches)) {
        $navCode = trim($matches[1]);
        // Clean up HTML structure
        $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
        // Clean up any trailing PHP tags in the nav code
        $navCode = preg_replace('/\s*\?>\s*$/', '', $navCode);
        // Add back the navigation code with proper PHP tags
        $content = str_replace(
            '<ul id="navList">',
            '<ul id="navList"><?php ' . $navCode . ' ?>',
            $content
        );
    }
    $buildContent .= trim($content) . "\n";

    // Add scripts.php content with JavaScript variables
    $content = ComponentReader::readComponentFile($sourceDir . '/includes/scripts.php');
    // Remove all PHP tags first
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    // Add back PHP expressions for JavaScript variables
    $content = str_replace(
        'const currentPoffConfig = ;',
        'const currentPoffConfig = <?php echo $folderPoffConfig ? json_encode($folderPoffConfig) : "null"; ?>;',
        $content
    );
    $content = str_replace(
        'const currentPathForIframe = ;',
        'const currentPathForIframe = <?php echo !empty($currentRelativePath) ? json_encode($currentRelativePath) : "null"; ?>;',
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

    // Trigger SSH upload using SSHUploader
    require_once __DIR__ . '/SSHUploader.php';
    //--> SSHUploader::upload();

} catch (Exception $e) {
    echo "Build failed: " . $e->getMessage() . "\n";
    exit(1);
}
