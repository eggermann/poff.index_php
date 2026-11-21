<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/NodeIdentity.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$rootDir = $argv[1] ?? '';
$_GET = [
    'nodeId' => $argv[2] ?? '',
    'workId' => $argv[3] ?? '',
];
if (($argv[4] ?? '') === 'edit') {
    $_GET['edit'] = 'true';
}

cmsHandleResolveRoute($rootDir);
