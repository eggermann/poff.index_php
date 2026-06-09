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
$mockStreamResponseArg = $argv[6] ?? '';
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

if ($mockStreamResponseArg !== '') {
    $mockStreamResponse = json_decode($mockStreamResponseArg, true);
    $GLOBALS['__poff_prompt_http_post_stream'] = static function (string $url, array $headers, array $requestPayload, ?callable $onChunk) use ($mockStreamResponse, $capturePath): array {
        if ($capturePath !== '') {
            file_put_contents($capturePath, json_encode([
                'url' => $url,
                'headers' => $headers,
                'payload' => $requestPayload,
                'stream' => true,
            ], JSON_UNESCAPED_SLASHES));
        }

        if (is_array($mockStreamResponse) && is_callable($onChunk)) {
            foreach (($mockStreamResponse['chunks'] ?? []) as $chunk) {
                if (is_string($chunk) && $chunk !== '') {
                    $onChunk($chunk);
                }
            }
        }

        if (is_array($mockStreamResponse)) {
            return [
                'ok' => (bool) ($mockStreamResponse['ok'] ?? true),
                'status' => (int) ($mockStreamResponse['status'] ?? 200),
                'statusLine' => (string) ($mockStreamResponse['statusLine'] ?? 'HTTP/1.1 200 OK'),
                'body' => (string) ($mockStreamResponse['body'] ?? ''),
            ];
        }

        return [
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
