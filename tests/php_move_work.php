<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit/move.php';

$rootDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';
$destination = $argv[3] ?? '';
$name = $argv[4] ?? '';
$failure = $argv[5] ?? '';
if (in_array($failure, ['metadata', 'tree', 'tree-rollback'], true)) {
    $rootDir = realpath($rootDir) ?: $rootDir;
    $failPath = $failure === 'metadata'
        ? $rootDir . '/' . trim($destination, '/') . '/.works/' . $name . '.config.json'
        : $rootDir . '/' . trim($destination, '/') . '/poff.config.json';
    $GLOBALS['__poff_move_write_guard'] = static fn(string $path): bool => $path !== $failPath;
}
if (in_array($failure, ['layout', 'rollback', 'tree-rollback'], true)) {
    $GLOBALS['__poff_move_rename_guard'] = static function (string $from, string $to, bool $rollback) use ($failure): bool {
        if ($failure !== 'tree-rollback' && !$rollback && str_ends_with($from, '.layout')) {
            return false;
        }
        return !($failure !== 'layout' && $rollback && str_ends_with($from, '.config.json'));
    };
}
$result = cmsMoveTarget($rootDir, $relativePath, $destination, $name);

echo json_encode([
    'errors' => $result['errors'] ?? [],
    'moved' => $result['moved'] ?? [],
    'sourceTreeNames' => array_column($result['sourceParentConfig']['tree'] ?? [], 'name'),
    'destinationTreeNames' => array_column($result['destinationParentConfig']['tree'] ?? [], 'name'),
], JSON_UNESCAPED_SLASHES);
