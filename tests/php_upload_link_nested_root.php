<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$rootDir = sys_get_temp_dir() . '/poff-upload-link-root-' . bin2hex(random_bytes(4));
$targetDir = $rootDir . '/nested/client-folder';
mkdir($targetDir, 0777, true);
$response = cmsCreateLinkEntry($targetDir, 'client-link', 'https://client.example/path/to/work', [
    'pendingApproval' => false,
    'htaccessDir' => $rootDir,
]);

echo json_encode([
    'response' => $response,
    'rootHtaccess' => is_file($rootDir . '/.htaccess') ? (string) file_get_contents($rootDir . '/.htaccess') : '',
    'nestedHtaccessExists' => is_file($targetDir . '/.htaccess'),
    'nestedConfig' => PoffConfig::ensure($targetDir),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
