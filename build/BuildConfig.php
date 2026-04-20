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

return [
    'sourceDir' => __DIR__ . '/../' . $sourceDir,
    'pagesDir' => __DIR__ . '/../' . $pagesDir,
    'siteHost' => $siteHost,
    'outputDir' => __DIR__ . '/../' . $pagesDir . '/' . $siteHost,
    // 'outputDir' => '/Applications/MAMP/htdocs/',
    'outputFile' => $outputFile,
];
