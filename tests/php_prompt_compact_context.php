<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-refs.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-context.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-compact.php';

$root = realpath(__DIR__ . '/poff-tests');
if ($root === false) {
    fwrite(STDERR, "Invalid test root\n");
    exit(1);
}

$fileName = 'viewer-file.txt';
$fileConfigPath = $root . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.config.json';
$fileConfig = json_decode((string) file_get_contents($fileConfigPath), true);
if (!is_array($fileConfig)) {
    fwrite(STDERR, "Invalid file config\n");
    exit(1);
}
$fileContext = cmsPromptCompactContext(cmsBuildPromptContext($fileName, 'file', $fileConfig, $fileName));

$folderConfigPath = $root . DIRECTORY_SEPARATOR . 'viewer-folder' . DIRECTORY_SEPARATOR . 'poff.config.json';
$folderConfig = json_decode((string) file_get_contents($folderConfigPath), true);
if (!is_array($folderConfig)) {
    fwrite(STDERR, "Invalid folder config\n");
    exit(1);
}
$folderContext = cmsPromptCompactContext(cmsBuildPromptContext('viewer-folder', 'folder', $folderConfig));

echo json_encode([
    'file' => $fileContext,
    'folder' => $folderContext,
], JSON_UNESCAPED_SLASHES);
