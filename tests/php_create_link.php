<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$targetDir = (string) ($argv[1] ?? '');
$label = (string) ($argv[2] ?? '');
$linkUrl = (string) ($argv[3] ?? '');
$options = json_decode((string) ($argv[4] ?? '{}'), true);
if (!is_array($options)) {
    $options = [];
}

$result = cmsCreateLinkEntry($targetDir, $label, $linkUrl, $options);
$config = PoffConfig::ensure($targetDir);
$htaccessPath = $targetDir !== '' ? rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess' : '';

echo json_encode([
    'result' => $result,
    'config' => $config,
    'htaccessExists' => $htaccessPath !== '' && is_file($htaccessPath),
    'htaccess' => $htaccessPath !== '' && is_file($htaccessPath) ? (string) file_get_contents($htaccessPath) : '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
