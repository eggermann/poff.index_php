<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/nav-render.php';

$baseDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';

if ($baseDir === '') {
    fwrite(STDERR, "Missing base dir\n");
    exit(1);
}

$currentAbsolutePath = rtrim($baseDir, DIRECTORY_SEPARATOR);
if ($relativePath !== '') {
    $currentAbsolutePath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

$folderPoffConfig = is_dir($currentAbsolutePath) && class_exists('PoffConfig')
    ? PoffConfig::ensure($currentAbsolutePath)
    : null;

echo '<ul id="navList" class="nav-list">';
echo cmsRenderNavListMarkup([
    'baseDir' => $baseDir,
    'currentRelativePath' => $relativePath,
    'currentAbsolutePath' => $currentAbsolutePath,
    'currentScript' => 'index.php',
    'folderPoffConfig' => $folderPoffConfig,
]);
echo '</ul>';
