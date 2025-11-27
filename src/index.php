<?php
/**
 * File: index.php
 * Desc: Simple PHP file browser with sidebar navigation and an iframe preview pane.
 *       Displays folder metadata (title, description, link) from an optional
 *       poff.config.json file in each directory, rendered as a header above the
 *       iframe. The title becomes a hyperlink (if a link/url field exists) that
 *       loads in the preview pane.
 */
// MCP JSON endpoint switch: /index.php?mcp=1
if (isset($_GET['mcp'])) {
    require __DIR__ . '/mcp/server.php';
}

require __DIR__ . '/includes/PoffConfig.php';
require __DIR__ . '/includes/viewer.php';

function extractLinkFileUrl(string $filePath): ?string {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $content = @file_get_contents($filePath);
    if (!$content) {
        return null;
    }

    switch ($ext) {
        case 'webloc':   // macOS .webloc (plist xml)
            if (preg_match('/<key>URL<\/key>\s*<string>([^<]+)<\/string>/i', $content, $m)) {
                return trim($m[1]);
            }
            break;

        case 'url':      // Windows Internet Shortcut (.url)
        case 'desktop':  // Linux .desktop
            if (preg_match('/^URL=(.+)$/mi', $content, $m)) {
                return trim($m[1]);
            }
            break;
    }
    return null;
}

// Initialize path variables
$baseDir = getcwd(); // Use current working directory
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$currentRelativePath = isset($_GET['path']) ? trim($_GET['path'], '/\\') : '';

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

$currentAbsolutePath = $baseDir . ($currentRelativePath ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentRelativePath) : '');

// Read folder config if it exists
$folderPoffConfig = null;
if (is_dir($currentAbsolutePath)) {
    $folderPoffConfig = PoffConfig::ensure($currentAbsolutePath);
}
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/layout.php'; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
