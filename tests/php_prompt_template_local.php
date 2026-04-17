<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/mcp/routes/prompt-template.php';

$rootDirArg = $argv[1] ?? '';
$rootDir = realpath($rootDirArg);
if ($rootDir === false || !is_dir($rootDir)) {
    fwrite(STDERR, "Invalid root dir: {$rootDirArg}\n");
    exit(1);
}

$relativePath = $argv[2] ?? '';
$allowFile = $argv[3] ?? __FILE__;
$payloadArg = $argv[4] ?? '{}';
$mockResponseArg = $argv[5] ?? '';
$capturePath = $argv[6] ?? '';
$payload = json_decode($payloadArg, true);
if (!is_array($payload)) {
    $payload = [];
}

if ($mockResponseArg !== '') {
    $mockResponse = json_decode($mockResponseArg, true);
    $GLOBALS['__poff_mcp_prompt_http_post'] = static function (string $url, array $headers, array $requestPayload) use ($mockResponse, $capturePath): array {
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

$_POST = $payload;

$result = handlePromptTemplate([
    'rootDir' => $rootDir,
    'path' => $relativePath,
    'allowFile' => $allowFile,
]);

echo json_encode($result, JSON_UNESCAPED_SLASHES);
