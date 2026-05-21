<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$payloadRaw = $argv[1] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

if (isset($payload['mockResponse']) && is_array($payload['mockResponse'])) {
    $mockResponse = $payload['mockResponse'];
    $GLOBALS['__poff_prompt_http_get'] = static function (string $url, array $headers = []) use ($mockResponse): array {
        return [
            'ok' => (bool) ($mockResponse['ok'] ?? true),
            'status' => (int) ($mockResponse['status'] ?? 200),
            'statusLine' => (string) ($mockResponse['statusLine'] ?? 'HTTP/1.1 200 OK'),
            'body' => (string) ($mockResponse['body'] ?? ''),
        ];
    };
}
if (isset($payload['mockResponsesByUrl']) && is_array($payload['mockResponsesByUrl'])) {
    $mockResponsesByUrl = $payload['mockResponsesByUrl'];
    $GLOBALS['__poff_prompt_http_get'] = static function (string $url, array $headers = []) use ($mockResponsesByUrl): array {
        $mockResponse = $mockResponsesByUrl[$url] ?? null;
        if (!is_array($mockResponse)) {
            return [
                'ok' => false,
                'status' => 404,
                'statusLine' => 'HTTP/1.1 404 Not Found',
                'body' => '',
            ];
        }

        return [
            'ok' => (bool) ($mockResponse['ok'] ?? true),
            'status' => (int) ($mockResponse['status'] ?? 200),
            'statusLine' => (string) ($mockResponse['statusLine'] ?? 'HTTP/1.1 200 OK'),
            'body' => (string) ($mockResponse['body'] ?? ''),
        ];
    };
}

$item = is_array($payload['item'] ?? null) ? $payload['item'] : [];
echo cmsResolveRemoteRenderedHtml($item);
