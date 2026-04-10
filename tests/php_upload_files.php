<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$targetDir = $argv[1] ?? '';
$sourcePath = $argv[2] ?? '';
$uploadName = $argv[3] ?? basename($sourcePath);

if ($targetDir === '' || $sourcePath === '' || !is_file($sourcePath)) {
    fwrite(STDERR, "Usage: php_upload_files.php <targetDir> <sourcePath> [uploadName]\n");
    exit(1);
}

$entries = [[
    'name' => $uploadName,
    'tmp_name' => $sourcePath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($sourcePath) ?: 0,
    'type' => '',
]];

$result = cmsStoreUploadEntries($targetDir, $entries);
$result['config'] = PoffConfig::ensure($targetDir);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
