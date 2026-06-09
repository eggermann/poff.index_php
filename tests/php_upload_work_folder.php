<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$rootDir = sys_get_temp_dir() . '/poff-upload-work-folder-' . bin2hex(random_bytes(4));
mkdir($rootDir, 0777, true);
file_put_contents($rootDir . '/original.txt', 'original');

$target = cmsResolveUploadTargetDir($rootDir, [
    'saveTarget' => 'work-folder',
    'activeWorkName' => 'Original Work',
    'activeWorkPath' => 'original.txt',
    'activeWorkIsFile' => '1',
], 'blank');

if (!empty($target['error'])) {
    fwrite(STDERR, 'Target failed');
    exit(1);
}

$result = cmsCreateBlankFile((string) $target['dir'], 'note.txt', 'note');
$folderName = (string) ($target['folderName'] ?? '');
$folderConfig = PoffConfig::ensure((string) $target['dir']);
$originalItem = null;
foreach (($folderConfig['tree'] ?? []) as $item) {
    if (is_array($item) && !empty($item['fullview'])) {
        $originalItem = $item;
        break;
    }
}

$checks = [
    'createdWorkFolder' => !empty($target['createdWorkFolder']),
    'folderName' => $folderName === 'Original-Work',
    'hasNote' => is_file((string) $target['dir'] . '/note.txt'),
    'hasOriginalFullview' => $originalItem !== null,
    'originalPath' => (string) ($originalItem['path'] ?? '') === '?view=1&file=original.txt',
    'layoutName' => (string) ($folderConfig['work']['layout']['name'] ?? '') === 'splitted',
];
$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));

echo json_encode([
    'target' => $target,
    'result' => $result,
    'folderName' => $folderName,
    'checks' => $checks,
    'originalPath' => (string) ($originalItem['path'] ?? ''),
    'layoutName' => (string) ($folderConfig['work']['layout']['name'] ?? ''),
], JSON_UNESCAPED_SLASHES);

if ($failed !== []) {
    fwrite(STDERR, "\nFailed checks: " . implode(', ', $failed) . "\n");
    exit(1);
}
