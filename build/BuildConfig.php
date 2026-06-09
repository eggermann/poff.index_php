<?php
/**
 * Configuration for the build process.
 */

$sharedConfigPath = __DIR__ . '/BuildConfig.shared.json';
$sharedConfig = [];

if (is_file($sharedConfigPath)) {
    $decoded = json_decode((string) file_get_contents($sharedConfigPath), true);
    if (is_array($decoded)) {
        $sharedConfig = $decoded;
    }
}

$sourceDir = trim((string) ($sharedConfig['sourceDir'] ?? 'src'), '/\\');
$pagesDir = trim((string) ($sharedConfig['pagesDir'] ?? 'pages'), '/\\');
$siteHost = trim((string) ($sharedConfig['siteHost'] ?? 'dominikeggermann.com'), '/\\');
$outputFile = trim((string) ($sharedConfig['outputFile'] ?? 'index.php'));
$outputDirOverride = trim((string) getenv('POFF_OUTPUT_DIR'));

$defaultOutputDir = __DIR__ . '/../' . $pagesDir . '/' . $siteHost;
$outputDir = $outputDirOverride !== ''
    ? __DIR__ . '/../' . trim($outputDirOverride, '/\\')
    : $defaultOutputDir;

return [
    'sourceDir' => __DIR__ . '/../' . $sourceDir,
    'pagesDir' => __DIR__ . '/../' . $pagesDir,
    'siteHost' => $siteHost,
    'outputDir' => $outputDir,
    // 'outputDir' => '/Applications/MAMP/htdocs/',
    'outputFile' => $outputFile,
];
