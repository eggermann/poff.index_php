<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';

$rootDir = $argv[1] ?? '';
$route = $argv[2] ?? 'info';
$payloadRaw = $argv[3] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

if ($rootDir !== '') {
    chdir($rootDir);
}

$_GET = [
    'mcp' => '1',
    'route' => $route,
];
$_POST = $payload;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'POST';

require __DIR__ . '/../src/includes/viewer.php';
