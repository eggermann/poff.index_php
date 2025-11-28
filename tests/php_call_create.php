<?php
// Simple CLI helper to invoke handleCreate for tests
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/mcp/helpers.php';
require __DIR__ . '/../src/mcp/routes/create.php';

$rootDir = realpath(__DIR__ . '/..');
$name = '';
$path = null;
$url = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    } elseif (str_starts_with($arg, '--path=')) {
        $path = substr($arg, 7);
    } elseif (str_starts_with($arg, '--url=')) {
        $url = substr($arg, 6);
    }
}

$result = handleCreate([
    'rootDir' => $rootDir,
    'name' => $name,
    'path' => $path,
    'url' => $url,
]);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
