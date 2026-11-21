<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/NodeIdentity.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$rootDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'poff-work-id-' . bin2hex(random_bytes(8));
mkdir($rootDir, 0755, true);
file_put_contents($rootDir . DIRECTORY_SEPARATOR . 'note.txt', 'identity stability fixture');

try {
    $firstFolder = PoffConfig::ensure($rootDir);
    $secondFolder = PoffConfig::ensure($rootDir);
    $firstFile = PoffConfig::ensureFileConfig($rootDir, 'note.txt');
    $secondFile = PoffConfig::ensureFileConfig($rootDir, 'note.txt');
    $firstNodeId = PoffNodeIdentity::ensure($rootDir);
    $secondNodeId = PoffNodeIdentity::ensure($rootDir);

    echo json_encode([
        'folder' => [$firstFolder['id'] ?? '', $secondFolder['id'] ?? ''],
        'file' => [$firstFile['id'] ?? '', $secondFile['id'] ?? ''],
        'newIds' => [PoffConfig::newWorkId(), PoffConfig::newWorkId()],
        'nodeIds' => [$firstNodeId, $secondNodeId],
    ], JSON_UNESCAPED_SLASHES);
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($rootDir);
}
