<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$cwdArg = $argv[1] ?? '';
$cwd = realpath($cwdArg);
if ($cwd === false || !is_dir($cwd)) {
    fwrite(STDERR, "Invalid cwd: {$cwdArg}\n");
    exit(1);
}

$relativePath = $argv[2] ?? '';
$payloadArg = $argv[3] ?? '{}';
$payload = json_decode($payloadArg, true);
if (!is_array($payload)) {
    $payload = [];
}

$_GET = [
    'edit' => 'save',
    'path' => $relativePath,
];
$_POST = $payload;
$_SERVER['REQUEST_METHOD'] = 'POST';

chdir($cwd);
$runtimeRoot = realpath(getcwd() ?: '.') ?: $cwd;
if (!is_file($runtimeRoot . DIRECTORY_SEPARATOR . '.edit.allow')) {
    touch($runtimeRoot . DIRECTORY_SEPARATOR . '.edit.allow');
}
cmsHandleEditAction();
