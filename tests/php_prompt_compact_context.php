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
$fileConfig['work']['fields'] = [
    [
        'type' => 'text',
        'name' => 'text1',
        'label' => 'Text 1',
        'value' => 'Prominent section copy',
    ],
];
$fileConfig['work']['text1'] = 'Prominent section copy';
$fileContext = cmsPromptCompactContext(cmsBuildPromptContext($fileName, 'file', $fileConfig, $fileName));
$fileCompactConfig = cmsPromptCompactConfig($fileConfig, false);

$folderConfigPath = $root . DIRECTORY_SEPARATOR . 'viewer-folder' . DIRECTORY_SEPARATOR . 'poff.config.json';
$folderConfig = json_decode((string) file_get_contents($folderConfigPath), true);
if (!is_array($folderConfig)) {
    fwrite(STDERR, "Invalid folder config\n");
    exit(1);
}
$folderConfig['work']['fields'] = [
    [
        'type' => 'text',
        'name' => 'text1',
        'label' => 'Text 1',
        'value' => 'Folder prominent copy',
    ],
];
$folderConfig['work']['text1'] = 'Folder prominent copy';
$folderContext = cmsPromptCompactContext(cmsBuildPromptContext('viewer-folder', 'folder', $folderConfig));
$folderCompactConfig = cmsPromptCompactConfig($folderConfig, false);

echo json_encode([
    'file' => $fileContext,
    'fileConfig' => $fileCompactConfig,
    'folder' => $folderContext,
    'folderConfig' => $folderCompactConfig,
], JSON_UNESCAPED_SLASHES);
