<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-refs.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-context.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-compact.php';
require_once __DIR__ . '/../src/includes/viewer/render/data.php';

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
$rootConfig = [
    'folderName' => 'poff-tests',
    'title' => 'poff-tests',
    'slug' => 'poff-tests',
    'description' => '',
    'work' => ['type' => 'folder'],
    'tree' => [
        ['name' => 'viewer-file.txt', 'type' => 'file', 'path' => 'viewer-file.txt', 'visible' => true, 'title' => 'Viewer File'],
        ['name' => 'viewer-folder', 'type' => 'folder', 'path' => 'viewer-folder', 'visible' => true],
        ['name' => 'background.jpg', 'type' => 'file', 'path' => 'background.jpg', 'visible' => true],
        ['name' => 'overlay.mov', 'type' => 'file', 'path' => 'overlay.mov', 'visible' => true],
        ['name' => 'external-link', 'type' => 'link', 'path' => 'external-link', 'linkUrl' => 'https://example.com', 'visible' => true],
    ],
];
$fileContext = cmsPromptCompactContext(cmsBuildPromptContext($fileName, 'file', $fileConfig, $fileName, false, '', [], [
    'relativePath' => '',
    'config' => $rootConfig,
]));
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
$folderViewData = buildFolderViewerData('viewer-folder', $root . DIRECTORY_SEPARATOR . 'viewer-folder', $folderConfig, [
    'name' => 'viewer-folder',
    'title' => 'Folder Preview',
    'slug' => 'viewer-folder',
]);
$folderContext = cmsPromptCompactContext(cmsBuildPromptContext('viewer-folder', 'folder', $folderConfig, null, false, '', [], [
    'relativePath' => '',
    'config' => $rootConfig,
], $folderViewData));
$folderCompactConfig = cmsPromptCompactConfig($folderConfig, false);

echo json_encode([
    'file' => $fileContext,
    'fileConfig' => $fileCompactConfig,
    'folder' => $folderContext,
    'folderConfig' => $folderCompactConfig,
], JSON_UNESCAPED_SLASHES);
