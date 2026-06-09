<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit/delete.php';
require_once __DIR__ . '/../src/includes/viewer/edit/reset.php';

$targetDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';

if ($targetDir === '') {
    fwrite(STDERR, "Usage: php_reset_folder.php <targetDir> <relativePath>\n");
    exit(1);
}

$result = cmsResetFolderTarget($targetDir, $relativePath);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
