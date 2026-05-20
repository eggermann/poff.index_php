<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/render.php';

$baseDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';

if ($baseDir === '') {
    fwrite(STDERR, "Missing base dir\n");
    exit(1);
}

$fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR);
if ($relativePath !== '') {
    $fullPath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

if (!is_dir($fullPath)) {
    fwrite(STDERR, "Target is not a directory\n");
    exit(1);
}

$config = PoffConfig::ensure($fullPath);
$folderViewData = buildFolderViewerData($relativePath, $fullPath, $config, [
    'name' => (string) ($config['folderName'] ?? basename($fullPath)),
    'title' => (string) ($config['title'] ?? $config['folderName'] ?? basename($fullPath)),
    'slug' => (string) ($config['slug'] ?? PoffConfig::slugify((string) ($config['folderName'] ?? basename($fullPath)))),
]);

echo json_encode([
    'tree' => $folderViewData['tree'],
    'allItems' => $folderViewData['allItems'],
    'allFiles' => $folderViewData['allFiles'],
    'allFolders' => $folderViewData['allFolders'],
], JSON_UNESCAPED_SLASHES);
