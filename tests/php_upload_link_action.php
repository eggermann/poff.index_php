<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$targetDir = sys_get_temp_dir() . '/poff-upload-link-' . bin2hex(random_bytes(4));
mkdir($targetDir, 0777, true);
$_SERVER['REQUEST_METHOD'] = 'POST';

cmsHandleEditUploadAction([
    'isLayoutTarget' => false,
    'subjectType' => 'folder',
    'data' => [
        'source' => 'url',
        'fileName' => 'my-link',
        'linkUrl' => 'https://remote.example/index.php?view=1&path=portfolio',
    ],
    'targetDir' => $targetDir,
    'targetFile' => null,
]);
