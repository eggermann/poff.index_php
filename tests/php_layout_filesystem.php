<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$action = $argv[1] ?? '';
$dir = $argv[2] ?? '';
$fileName = $argv[3] ?? '';
$payload = isset($argv[4]) ? json_decode((string) $argv[4], true) : null;

switch ($action) {
    case 'ensure-folder':
        echo json_encode(PoffConfig::ensure($dir), JSON_UNESCAPED_SLASHES);
        exit(0);
    case 'ensure-file':
        echo json_encode(PoffConfig::ensureFileConfig($dir, $fileName), JSON_UNESCAPED_SLASHES);
        exit(0);
    case 'persist-folder':
        echo json_encode(PoffConfig::persistLayoutFiles($dir, null, $payload ?? [], 'works'), JSON_UNESCAPED_SLASHES);
        exit(0);
    case 'persist-file':
        echo json_encode(PoffConfig::persistLayoutFiles($dir, $fileName, $payload ?? [], 'work'), JSON_UNESCAPED_SLASHES);
        exit(0);
    case 'persist-original':
        echo json_encode([
            'target' => PoffConfig::persistOriginalLayoutFiles($dir, is_array($payload) ? $payload : []),
        ], JSON_UNESCAPED_SLASHES);
        exit(0);
    case 'resolve-target':
        echo json_encode(cmsResolveTarget($dir, $fileName), JSON_UNESCAPED_SLASHES);
        exit(0);
}

fwrite(STDERR, "Unknown action: {$action}\n");
exit(1);
