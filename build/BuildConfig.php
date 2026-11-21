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
$mampHtdocs = rtrim((string) ($sharedConfig['mampHtdocs'] ?? ''), '/\\');
$standaloneCopyTargets = array_values(array_filter(array_map(
    static fn(mixed $target): string => trim(is_string($target) ? $target : '', '/\\'),
    is_array($sharedConfig['standaloneCopyTargets'] ?? null) ? $sharedConfig['standaloneCopyTargets'] : []
)));
$additionalIndexCopyTargets = array_values(array_filter(array_map(
    static function (mixed $target): ?array {
        if (!is_array($target)) {
            return null;
        }
        $name = trim((string) ($target['name'] ?? ''), '/\\');
        return $name !== '' ? [
            'name' => $name,
            'cleanSubdirectories' => (bool) ($target['cleanSubdirectories'] ?? true),
        ] : null;
    },
    is_array($sharedConfig['additionalIndexCopyTargets'] ?? null) ? $sharedConfig['additionalIndexCopyTargets'] : []
)));

$defaultOutputDir = __DIR__ . '/../' . $pagesDir . '/' . $siteHost;
$outputDir = $outputDirOverride !== ''
    ? __DIR__ . '/../' . trim($outputDirOverride, '/\\')
    : $defaultOutputDir;

return [
    'sourceDir' => __DIR__ . '/../' . $sourceDir,
    'pagesDir' => __DIR__ . '/../' . $pagesDir,
    'siteHost' => $siteHost,
    'outputDir' => $outputDir,
    'outputFile' => $outputFile,
    'mampHtdocs' => $mampHtdocs,
    'standaloneCopyTargets' => $standaloneCopyTargets,
    'additionalIndexCopyTargets' => $additionalIndexCopyTargets,
];
