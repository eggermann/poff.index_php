<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/viewer/edit/action/save/finalize.php';

$rootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'poff-edit-cache-' . bin2hex(random_bytes(4));
$nestedDir = $rootDir . DIRECTORY_SEPARATOR . 'nested';
@mkdir($nestedDir, 0775, true);

$ctx = [
    'targetDir' => $nestedDir,
    'subjectRelativePath' => 'nested/example.txt',
    'subjectType' => 'file',
    'targetFile' => 'example.txt',
];

$subjectCachePath = cmsEditFolderViewerCachePath($nestedDir . DIRECTORY_SEPARATOR . 'example.txt', 'nested/example.txt');
$parentCachePath = cmsEditFolderViewerCachePath($nestedDir, 'nested');
@file_put_contents($subjectCachePath, 'subject-cache');
@file_put_contents($parentCachePath, 'parent-cache');

if (!is_file($subjectCachePath) || !is_file($parentCachePath)) {
    fwrite(STDERR, "Failed to seed cache files.\n");
    exit(1);
}

cmsEditClearFolderViewerCachesForSave($ctx);

if (is_file($subjectCachePath)) {
    fwrite(STDERR, "Subject cache was not cleared.\n");
    exit(1);
}

if (is_file($parentCachePath)) {
    fwrite(STDERR, "Parent cache was not cleared.\n");
    exit(1);
}

echo "cache invalidation ok\n";
