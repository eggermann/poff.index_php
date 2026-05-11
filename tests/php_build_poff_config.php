<?php

declare(strict_types=1);

require_once __DIR__ . '/../build/ComponentReader.php';
require_once __DIR__ . '/../build/PoffConfigBuilder.php';

$sourceDir = realpath(__DIR__ . '/../src');
if ($sourceDir === false) {
    throw new RuntimeException('Unable to resolve source directory.');
}

$classContent = ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig.php');
$traitContents = [
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-helpers.php'),
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/core-helpers.php'),
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-files.php'),
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-view.php'),
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/layout-collections.php'),
    ComponentReader::readComponentFile($sourceDir . '/includes/PoffConfig/prompt-helpers.php'),
];

$builtClass = PoffConfigBuilder::buildClass($classContent, $traitContents);
$tempFile = tempnam(sys_get_temp_dir(), 'poff-config-build-');
if ($tempFile === false) {
    throw new RuntimeException('Unable to create temp file for lint.');
}
file_put_contents($tempFile, "<?php\n" . $builtClass . "\n");

$command = 'php -l ' . escapeshellarg($tempFile);
$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);
unlink($tempFile);

echo json_encode([
    'containsTraitDefinition' => preg_match('/trait\s+PoffConfig[A-Za-z]+Helpers/', $builtClass) === 1,
    'containsHelperUseStatement' => preg_match('/^\s*use\s+PoffConfig[A-Za-z]+Helpers;\s*$/m', $builtClass) === 1,
    'containsHydrateConfigLayout' => str_contains($builtClass, 'public static function hydrateConfigLayout'),
    'containsPersistLayoutFiles' => str_contains($builtClass, 'public static function persistLayoutFiles'),
    'containsLayoutCollectionPackage' => str_contains($builtClass, 'private static function layoutCollectionPackage'),
    'lintExitCode' => $exitCode,
    'lintOutput' => implode("\n", $output),
], JSON_UNESCAPED_SLASHES);
