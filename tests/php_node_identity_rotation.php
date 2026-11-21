<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/NodeIdentity.php';

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'poff-node-rotation-' . bin2hex(random_bytes(6));
$sourceRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'source';
$cloneRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'clone';
mkdir($sourceRoot, 0755, true);
mkdir($cloneRoot, 0755, true);

try {
    $sourceId = PoffNodeIdentity::ensure($sourceRoot);
    file_put_contents($sourceRoot . DIRECTORY_SEPARATOR . 'poff.config.json', json_encode(['id' => 'poff_existing_work_id']));
    copy(PoffNodeIdentity::path($sourceRoot), PoffNodeIdentity::path($cloneRoot));
    copy($sourceRoot . DIRECTORY_SEPARATOR . 'poff.config.json', $cloneRoot . DIRECTORY_SEPARATOR . 'poff.config.json');

    $rotated = PoffNodeIdentity::rotate($cloneRoot);
    $cloneFile = json_decode((string) file_get_contents(PoffNodeIdentity::path($cloneRoot)), true);

    echo json_encode([
        'sourceId' => $sourceId,
        'rotated' => $rotated,
        'sourceRead' => PoffNodeIdentity::read($sourceRoot),
        'cloneRead' => PoffNodeIdentity::read($cloneRoot),
        'cloneRotatedAt' => $cloneFile['rotatedAt'] ?? null,
        'sourceWorkId' => json_decode((string) file_get_contents($sourceRoot . DIRECTORY_SEPARATOR . 'poff.config.json'), true)['id'] ?? null,
        'cloneWorkId' => json_decode((string) file_get_contents($cloneRoot . DIRECTORY_SEPARATOR . 'poff.config.json'), true)['id'] ?? null,
    ], JSON_UNESCAPED_SLASHES);
} finally {
    foreach ([$sourceRoot, $cloneRoot] as $nodeRoot) {
        foreach (scandir($nodeRoot) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($nodeRoot . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($nodeRoot);
    }
    @rmdir($temporaryRoot);
}
