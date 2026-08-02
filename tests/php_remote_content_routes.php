<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/render.php';
require_once __DIR__ . '/../src/mcp/helpers.php';
require_once __DIR__ . '/../src/mcp/routes/remote-content.php';
require_once __DIR__ . '/../src/mcp/routes/remote-presentation.php';

$mode = $argv[1] ?? '';
$rootDir = $argv[2] ?? '';
$path = $argv[3] ?? '';
$payloadRaw = $argv[4] ?? '';

if ($rootDir === '') {
    fwrite(STDERR, "Missing root dir\n");
    exit(1);
}

$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

if ($mode === 'export') {
    $result = handleExportContent([
        'rootDir' => $rootDir,
        'path' => $path,
        'baseUrl' => $payload['baseUrl'] ?? '',
        'sourceId' => $payload['sourceId'] ?? '',
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'import') {
    if (isset($payload['mockResponses']) && is_array($payload['mockResponses'])) {
        $mockResponses = $payload['mockResponses'];
        $GLOBALS['__poff_mcp_remote_http_get'] = static function (string $url, array $headers) use ($mockResponses): array {
            $mockResponse = isset($mockResponses[$url]) && is_array($mockResponses[$url])
                ? $mockResponses[$url]
                : [];
            $body = array_key_exists('bodyRaw', $mockResponse)
                ? (string) $mockResponse['bodyRaw']
                : json_encode($mockResponse['body'] ?? [], JSON_UNESCAPED_SLASHES);
            return [
                'status' => (int) ($mockResponse['status'] ?? 404),
                'headers' => $headers,
                'body' => $body,
            ];
        };
    } elseif (isset($payload['mockResponse']) && is_array($payload['mockResponse'])) {
        $mockResponse = $payload['mockResponse'];
        $GLOBALS['__poff_mcp_remote_http_get'] = static function (string $url, array $headers) use ($mockResponse): array {
            return [
                'status' => (int) ($mockResponse['status'] ?? 200),
                'headers' => $headers,
                'body' => json_encode($mockResponse['body'] ?? [], JSON_UNESCAPED_SLASHES),
            ];
        };
    }

    $result = handleImportRemote([
        'rootDir' => $rootDir,
        'path' => $path,
        'url' => (string) ($payload['url'] ?? ''),
        'sourceId' => (string) ($payload['sourceId'] ?? ''),
        'replace' => (bool) ($payload['replace'] ?? false),
        'mode' => (string) ($payload['mode'] ?? 'follow'),
        'destinationName' => (string) ($payload['destinationName'] ?? ''),
        'createPlayer' => (bool) ($payload['createPlayer'] ?? false),
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'presentation') {
    if (isset($payload['mockResponses']) && is_array($payload['mockResponses'])) {
        $mockResponses = $payload['mockResponses'];
        $GLOBALS['__poff_mcp_remote_http_get'] = static function (string $url, array $headers) use ($mockResponses): array {
            $mockResponse = isset($mockResponses[$url]) && is_array($mockResponses[$url])
                ? $mockResponses[$url]
                : [];
            return [
                'status' => (int) ($mockResponse['status'] ?? 404),
                'headers' => $headers,
                'body' => json_encode($mockResponse['body'] ?? [], JSON_UNESCAPED_SLASHES),
            ];
        };
    }

    $result = handleRemoteWorkPresentation([
        'rootDir' => $rootDir,
        'input' => [
            'path' => $path,
            'url' => (string) ($payload['url'] ?? ''),
            'action' => (string) ($payload['action'] ?? 'inspect'),
            'confirmReplace' => (bool) ($payload['confirmReplace'] ?? false),
        ],
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

fwrite(STDERR, "Unsupported mode\n");
exit(1);
