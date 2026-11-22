<?php
declare(strict_types=1);

// Controlled test router; never use a real Node content directory here.
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/Converter.php';
require_once __DIR__ . '/../src/mcp/helpers.php';
require_once __DIR__ . '/../src/mcp/routes/converter-network.php';

header('Content-Type: application/json');
if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/health') {
    echo '{"ready":true}';
    return;
}
$root = (string) getenv('POFF_TEST_PROVIDER_ROOT');
if ($root === '' || !is_dir($root)) {
    http_response_code(500);
    echo '{"ok":false}';
    return;
}
$payload = mcpReadLimitedRequestData(Converter::MAX_REMOTE_REQUEST_BYTES);
if (($_GET['route'] ?? '') === 'converter-offers') {
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/worktypes/templates/converter/convert-text-editor/converter.php';
    $result = handleConverterOffers(['rootDir' => $root, 'input' => $payload, 'nodeUrl' => $url, 'nodeId' => 'test-provider']);
} elseif (($_GET['route'] ?? '') === 'convert-offered') {
    $result = handleConvertOffered(['rootDir' => $root, 'payload' => $payload]);
} else {
    http_response_code(404);
    $result = ['ok' => false];
}
echo json_encode($result, JSON_UNESCAPED_SLASHES);
