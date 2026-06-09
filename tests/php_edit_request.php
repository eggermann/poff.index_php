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
$action = $argv[2] ?? '';
$relativePath = $argv[3] ?? '';
$payloadArg = $argv[4] ?? '{}';
$sessionId = $argv[5] ?? '';

$cwd = realpath($cwdArg);
if ($cwd === false || !is_dir($cwd)) {
    fwrite(STDERR, "Invalid cwd: {$cwdArg}\n");
    exit(1);
}

$payload = json_decode($payloadArg, true);
if (!is_array($payload)) {
    $payload = [];
}

if ($sessionId !== '') {
    session_id($sessionId);
}

$_GET = ['edit' => $action];
if ($relativePath !== '') {
    $_GET['path'] = $relativePath;
}
$_POST = $payload;
$_SERVER['REQUEST_METHOD'] = strtoupper((string) ($payload['_method'] ?? 'POST'));
unset($_POST['_method']);

chdir($cwd);
cmsHandleEditAction();
