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
    if (isset($payload['mockResponse']) && is_array($payload['mockResponse'])) {
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
    ]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

fwrite(STDERR, "Unsupported mode\n");
exit(1);
