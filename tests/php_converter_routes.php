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
require_once __DIR__ . '/../src/mcp/routes/create-converter.php';
require_once __DIR__ . '/../src/mcp/routes/converter-prompt.php';

$mode = $argv[1] ?? '';
$rootDir = $argv[2] ?? '';
$payloadRaw = $argv[3] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
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

if ($mode === 'remote-security') {
    echo json_encode([
        'error' => Converter::validateRemoteEndpoint((string) ($payload['url'] ?? '')),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit(0);
}

fwrite(STDERR, "Unsupported mode\n");
exit(1);
