<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$baseDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';
$editMode = ($argv[3] ?? '') === 'edit';

$baseDir = realpath($baseDir);
if ($baseDir === false || !is_dir($baseDir)) {
    fwrite(STDERR, "Invalid base dir\n");
    exit(1);
}

if ($editMode) {
    $_GET['edit'] = 'true';
}

$currentRelativePath = trim($relativePath, "/\\");
$currentAbsolutePath = $baseDir;
if ($currentRelativePath !== '') {
    $currentAbsolutePath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $currentRelativePath);
}
$currentScript = 'index.php';
$config = PoffConfig::ensure($currentAbsolutePath);
$folderPoffConfig = $config;

ob_start();
include __DIR__ . '/../src/includes/nav.php';
echo ob_get_clean();
