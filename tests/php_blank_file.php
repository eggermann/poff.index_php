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
$fileName = $argv[2] ?? '';
$contents = $argv[3] ?? '';

if ($targetDir === '') {
    fwrite(STDERR, "Usage: php_blank_file.php <targetDir> <fileName> [contents]\n");
    exit(1);
}

$result = cmsCreateBlankFile($targetDir, $fileName, $contents);
$result['config'] = PoffConfig::ensure($targetDir);
echo json_encode($result, JSON_UNESCAPED_SLASHES);
