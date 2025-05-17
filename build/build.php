<?php
/**
 * Build script that concatenates modular PHP files into a single index.php file
 * and triggers SSH upload after successful build.
 */

// Configuration
$sourceDir = __DIR__ . '/../src';
$outputDir = __DIR__ . '/../pages/dominikeggermann.com';
$outputFile = $outputDir . '/index.php';

// Ensure output directory exists
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Function to read and clean file contents
function readComponentFile($path) {
    if (!file_exists($path)) {
        throw new Exception("File not found: $path");
    }
    $content = file_get_contents($path);
    if ($content === false) {
        throw new Exception("Failed to read file: $path");
    }
    
    // Remove PHP tags
    $content = preg_replace('/^<\?php\s*/', '', $content);
    $content = preg_replace('/\?>\s*$/', '', $content);
    
    // Remove PHP doc comments that shouldn't be in the output
    $content = preg_replace('/\/\*\*\s*\n\s*\*[^*]*\*\/\s*\n/', '', $content);
    
    return $content;
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
    $functionsContent = readComponentFile($sourceDir . '/includes/functions.php');
    if (preg_match('/function\s+extractLinkFileUrl.*?^}/ms', $functionsContent, $matches)) {
        $buildContent .= $matches[0] . "\n\n";
    }
    
    // Add the initialization code from index.php
    if (preg_match('/\$baseDir = realpath.*?(?=require_once.*?header\.php)/s', $indexContent, $matches)) {
        $buildContent .= trim($matches[0]) . "\n";
    }
    
    // Close PHP tag for HTML content
    $buildContent .= "?>\n";
    
    // Add HTML structure from header.php without PHP tags
    $content = readComponentFile($sourceDir . '/includes/header.php');
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    $buildContent .= trim($content) . "\n";
    
    // Add layout.php content with navigation code
    $content = readComponentFile($sourceDir . '/includes/layout.php');
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
    $content = readComponentFile($sourceDir . '/includes/scripts.php');
    // Remove all PHP tags first
    $content = preg_replace('/<\?php.*?\?>\s*|^\s*\?>\s*|\s*\?>\s*$/s', '', $content);
    // Add back PHP expressions for JavaScript variables
    $content = str_replace('const currentPoffConfig = ;',
                         'const currentPoffConfig = <?php echo $folderPoffConfig ? json_encode($folderPoffConfig) : "null"; ?>;', $content);
    $content = str_replace('const currentPathForIframe = ;',
                         'const currentPathForIframe = <?php echo !empty($currentRelativePath) ? json_encode($currentRelativePath) : "null"; ?>;', $content);
    $buildContent .= trim($content);
    
    // Write the concatenated content to the output file
    if (file_put_contents($outputFile, $buildContent) === false) {
        throw new Exception("Failed to write output file: $outputFile");
    }

    echo "Build completed successfully!\n";
    
    // Trigger SSH upload
    $sshUploadScript = __DIR__ . '/../SSH-upload.node.js';
    if (file_exists($sshUploadScript) && false) {
        echo "Starting SSH upload...\n";
        $command = "node " . escapeshellarg($sshUploadScript);
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            echo "SSH upload completed successfully!\n";
            foreach ($output as $line) {
                echo $line . "\n";
            }
        } else {
            throw new Exception("SSH upload failed with code $returnCode");
        }
    } else {
        echo "Warning: SSH-upload.node.js not found. Skipping upload.\n";
    }

} catch (Exception $e) {
    echo "Build failed: " . $e->getMessage() . "\n";
    exit(1);
}