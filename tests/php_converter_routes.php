<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/Converter.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/mcp/helpers.php';
require_once __DIR__ . '/../src/mcp/routes/converters.php';
require_once __DIR__ . '/../src/mcp/routes/convert.php';
require_once __DIR__ . '/../src/mcp/routes/converter-network.php';
require_once __DIR__ . '/../src/mcp/routes/create-converter.php';
require_once __DIR__ . '/../src/mcp/routes/converter-prompt.php';

$mode = $argv[1] ?? '';
$rootDir = $argv[2] ?? '';
$payloadRaw = $argv[3] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

function createOptedInNetworkConverterFixture(string $engine = 'imagemagick'): string
{
    $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'poff-converter-optin-' . bin2hex(random_bytes(8));
    $folder = $root . DIRECTORY_SEPARATOR . 'poff' . DIRECTORY_SEPARATOR . 'converters' . DIRECTORY_SEPARATOR . 'convert-image';
    if (!mkdir($folder, 0700, true)) {
        throw new RuntimeException('Unable to create converter test fixture.');
    }
    file_put_contents($folder . DIRECTORY_SEPARATOR . 'converter.json', json_encode([
        'type' => 'converter',
        'name' => 'convert-image',
        'label' => 'Convert Image',
        'accepts' => ['image/*'],
        'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
        'formats' => ['webp', 'jpeg', 'png'],
        'engine' => $engine,
        'enabled' => true,
        'network' => ['publish' => true],
        'defaults' => ['format' => 'png', 'quality' => 'default'],
        'ui' => [],
    ], JSON_UNESCAPED_SLASHES));
    return $root;
}

function removeConverterTestDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            removeConverterTestDirectory($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($path);
}

function signTestConverterPeerRequest(array $requestPayload, string $replayFile, int $timestampOffset = 0): void
{
    $peerId = 'test-consumer';
    $secret = 'test-shared-secret-32-bytes-long';
    $timestamp = (string) (time() + $timestampOffset);
    $nonce = bin2hex(random_bytes(16));
    putenv('POFF_CONVERTER_TRUSTED_PEERS=' . json_encode([$peerId => $secret]));
    putenv('POFF_CONVERTER_REPLAY_FILE=' . $replayFile);
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_POFF_NODE_ID'] = $peerId;
    $_SERVER['HTTP_X_POFF_TIMESTAMP'] = $timestamp;
    $_SERVER['HTTP_X_POFF_NONCE'] = $nonce;
    $_SERVER['HTTP_X_POFF_SIGNATURE'] = Converter::peerRequestSignature($requestPayload, $timestamp, $nonce, $secret);
}

if ($mode === 'available') {
    echo json_encode(Converter::availableFor(
        (string) ($payload['mimeType'] ?? ''),
        (string) ($payload['kind'] ?? ''),
        (string) ($payload['extension'] ?? ''),
        $rootDir
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'discover') {
    echo json_encode(Converter::discover($rootDir), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'ensure-default') {
    echo json_encode(Converter::ensureDefaultConverterFolder(
        $rootDir,
        (string) ($payload['name'] ?? 'convert-image')
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'normalize') {
    echo json_encode(Converter::normalizeSelectedConverter($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'build-payload') {
    echo json_encode(Converter::buildPayload(
        is_array($payload['source'] ?? null) ? $payload['source'] : [],
        is_array($payload['converter'] ?? null) ? $payload['converter'] : []
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'converter-prompt') {
    echo json_encode(handleConverterPrompt([
        'rootDir' => $rootDir,
        'path' => (string) ($payload['path'] ?? ''),
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'create-converter') {
    echo json_encode(handleCreateConverter([
        'rootDir' => $rootDir,
        'path' => (string) ($payload['path'] ?? ''),
        'payload' => $payload,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'save') {
    echo json_encode(handleSaveConvertedWork([
        'rootDir' => $rootDir,
        'payload' => $payload,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'convert') {
    $mockHttpPost = $payload['mockHttpPost'] ?? null;
    $captureRemotePayload = (bool) ($payload['captureRemotePayload'] ?? false);
    $capturedRemotePayload = null;
    if (is_array($mockHttpPost)) {
        $GLOBALS['__poff_converter_http_post'] = static function (string $url, array $headers, array $requestPayload) use ($mockHttpPost, &$capturedRemotePayload): array {
            $capturedRemotePayload = $requestPayload;
            return [
                'ok' => (bool) ($mockHttpPost['ok'] ?? true),
                'status' => (int) ($mockHttpPost['status'] ?? 200),
                'body' => json_encode($mockHttpPost['body'] ?? [], JSON_UNESCAPED_SLASHES),
            ];
        };
        unset($payload['mockHttpPost']);
    }
    unset($payload['captureRemotePayload']);
    $result = handleConvert([
        'rootDir' => $rootDir,
        'payload' => $payload,
    ]);
    if ($captureRemotePayload) {
        $result['_capturedRemotePayload'] = $capturedRemotePayload;
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'offers') {
    $networkFixture = ($payload['testNetworkPublishOptIn'] ?? false) === true
        ? createOptedInNetworkConverterFixture()
        : '';
    unset($payload['testNetworkPublishOptIn']);
    if (($payload['enableImageMagickCli'] ?? false) === true) {
        putenv('POFF_ENABLE_IMAGEMAGICK_CLI=1');
        unset($payload['enableImageMagickCli']);
    }
    echo json_encode(handleConverterOffers([
        'rootDir' => $networkFixture !== '' ? $networkFixture : $rootDir,
        'input' => $payload,
        'nodeUrl' => (string) ($payload['nodeUrl'] ?? 'https://provider.example/index.php'),
        'nodeId' => (string) ($payload['nodeId'] ?? 'provider-example'),
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    removeConverterTestDirectory($networkFixture);
    exit(0);
}

if ($mode === 'remote-catalog') {
    $mockCatalog = is_array($payload['mockCatalog'] ?? null) ? $payload['mockCatalog'] : [];
    $GLOBALS['__poff_converter_catalog_post'] = static function (string $url, array $requestPayload) use ($mockCatalog): array {
        return [
            'ok' => true,
            'status' => 200,
            'body' => json_encode($mockCatalog, JSON_UNESCAPED_SLASHES),
        ];
    };
    unset($payload['mockCatalog']);
    echo json_encode(handleRemoteConverters([
        'rootDir' => $rootDir,
        'input' => $payload,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'register-node') {
    $mockRegistry = is_array($payload['mockRegistry'] ?? null) ? $payload['mockRegistry'] : [];
    $captured = [];
    $GLOBALS['__poff_registry_post'] = static function (string $url, array $requestPayload) use ($mockRegistry, &$captured): array {
        $captured = [
            'url' => $url,
            'payload' => $requestPayload,
        ];
        return [
            'ok' => (bool) ($mockRegistry['ok'] ?? true),
            'status' => (int) ($mockRegistry['status'] ?? 200),
            'body' => json_encode($mockRegistry['body'] ?? [
                'allowed' => true,
                'pendingApproval' => true,
                'uploaded' => [['pendingApproval' => true]],
            ], JSON_UNESCAPED_SLASHES),
        ];
    };
    unset($payload['mockRegistry']);
    $result = handleRegisterPoffNode([
        'rootDir' => $rootDir,
        'input' => $payload,
    ]);
    $result['_capturedRegistry'] = $captured;
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if (in_array($mode, ['convert-offered', 'convert-offered-unsigned', 'peer-auth-replay', 'peer-auth-expired', 'peer-auth-tampered'], true)) {
    $networkFixture = ($payload['testNetworkPublishOptIn'] ?? false) === true
        ? createOptedInNetworkConverterFixture()
        : '';
    unset($payload['testNetworkPublishOptIn']);
    if (($payload['enableImageMagickCli'] ?? false) === true) {
        putenv('POFF_ENABLE_IMAGEMAGICK_CLI=1');
        unset($payload['enableImageMagickCli']);
    }
    $timestampOffset = (int) ($payload['testPeerTimestampOffset'] ?? 0);
    unset($payload['testPeerTimestampOffset']);
    $replayFile = tempnam(sys_get_temp_dir(), 'poff-converter-replay-test-');
    if ($mode !== 'convert-offered-unsigned') {
        signTestConverterPeerRequest($payload, (string) $replayFile, $timestampOffset);
    }
    if ($mode === 'peer-auth-replay') {
        $result = [
            'first' => mcpConverterAuthenticatePeer($payload),
            'second' => mcpConverterAuthenticatePeer($payload),
        ];
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @unlink($replayFile);
        removeConverterTestDirectory($networkFixture);
        exit(0);
    }
    if ($mode === 'peer-auth-expired') {
        $result = mcpConverterAuthenticatePeer($payload);
        echo json_encode(['authenticated' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @unlink($replayFile);
        removeConverterTestDirectory($networkFixture);
        exit(0);
    }
    if ($mode === 'peer-auth-tampered') {
        $payload['source']['name'] = 'tampered-after-signing.tiff';
        $result = mcpConverterAuthenticatePeer($payload);
        echo json_encode(['authenticated' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        @unlink($replayFile);
        removeConverterTestDirectory($networkFixture);
        exit(0);
    }
    echo json_encode(handleConvertOffered([
        'rootDir' => $networkFixture !== '' ? $networkFixture : $rootDir,
        'payload' => $payload,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    @unlink($replayFile);
    removeConverterTestDirectory($networkFixture);
    exit(0);
}

if ($mode === 'bulk-convert') {
    if (($payload['enableImageMagickCli'] ?? false) === true) {
        putenv('POFF_ENABLE_IMAGEMAGICK_CLI=1');
        unset($payload['enableImageMagickCli']);
    }
    echo json_encode(handleBulkConvert([
        'rootDir' => $rootDir,
        'payload' => $payload,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'remote-security') {
    echo json_encode([
        'error' => Converter::validateRemoteEndpoint((string) ($payload['url'] ?? '')),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'dns-resolve') {
    $addresses = array_values((array) ($payload['addresses'] ?? []));
    $GLOBALS['__poff_converter_dns_lookup'] = static fn(string $host): array => $addresses;
    echo json_encode(Converter::resolveRemoteAddresses((string) ($payload['host'] ?? 'provider.example')), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($mode === 'pinned-http-probe') {
    $addresses = array_values((array) ($payload['addresses'] ?? []));
    $GLOBALS['__poff_converter_dns_lookup'] = static fn(string $host): array => $addresses;
    echo json_encode(Converter::httpRequestPinned(
        'GET',
        (string) ($payload['url'] ?? ''),
        null,
        ['Accept: application/json'],
        10,
        1024,
        true
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

fwrite(STDERR, "Unsupported mode\n");
exit(1);
