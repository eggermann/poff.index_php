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
$result = cmsMoveTarget($rootDir, $relativePath, $destination, $name);

echo json_encode([
    'errors' => $result['errors'] ?? [],
    'moved' => $result['moved'] ?? [],
    'sourceTreeNames' => array_column($result['sourceParentConfig']['tree'] ?? [], 'name'),
    'destinationTreeNames' => array_column($result['destinationParentConfig']['tree'] ?? [], 'name'),
], JSON_UNESCAPED_SLASHES);
