<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$rootDirArg = $argv[1] ?? '';
$rootDir = realpath($rootDirArg);
if ($rootDir === false || !is_dir($rootDir)) {
    fwrite(STDERR, "Invalid root dir: {$rootDirArg}\n");
    exit(1);
}

$relativePath = $argv[2] ?? '';
$payloadArg = $argv[3] ?? '{}';
$mockResponseArg = $argv[4] ?? '';
$capturePath = $argv[5] ?? '';
$payload = json_decode($payloadArg, true);
if (!is_array($payload)) {
    $payload = [];
}

if ($mockResponseArg !== '') {
    $mockResponse = json_decode($mockResponseArg, true);
    $GLOBALS['__poff_prompt_http_post'] = static function (string $url, array $headers, array $requestPayload) use ($mockResponse, $capturePath): array {
        if ($capturePath !== '') {
            file_put_contents($capturePath, json_encode([
                'url' => $url,
                'headers' => $headers,
                'payload' => $requestPayload,
            ], JSON_UNESCAPED_SLASHES));
        }

        return is_array($mockResponse) ? $mockResponse : [
            'ok' => false,
            'status' => 500,
            'statusLine' => 'HTTP/1.1 500 Test Override Invalid',
            'body' => '',
        ];
    };
}

$_GET = [
    'edit' => 'prompt',
    'path' => $relativePath,
];
$_POST = $payload;
$_SERVER['REQUEST_METHOD'] = 'POST';

chdir($rootDir);
if (!is_file($rootDir . DIRECTORY_SEPARATOR . '.edit.allow')) {
    touch($rootDir . DIRECTORY_SEPARATOR . '.edit.allow');
}
cmsHandleEditAction();
