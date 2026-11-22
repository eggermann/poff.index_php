<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Converter.php';

function mcpConverterNodeRouteUrl(string $nodeUrl, string $route): string
{
    $trimmed = trim($nodeUrl);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = (string) ($parts['path'] ?? '/index.php');
    if ($path === '' || str_ends_with($path, '/')) {
        $path .= 'index.php';
    }

    return $scheme . '://' . $host . $port . $path
        . '?mcp=1&route=' . rawurlencode($route);
}

function mcpConverterCurrentNodeUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    return $host !== '' ? $scheme . '://' . $host . $script : $script;
}

function mcpConverterOfferDefinition(array $definition): array
{
    return [
        'id' => (string) ($definition['id'] ?? ''),
        'type' => 'converter',
        'name' => (string) ($definition['name'] ?? ''),
        'label' => (string) ($definition['label'] ?? ($definition['name'] ?? '')),
        'description' => (string) ($definition['description'] ?? ''),
        'accepts' => array_values((array) ($definition['accepts'] ?? [])),
        'outputs' => array_values((array) ($definition['outputs'] ?? [])),
        'formats' => array_values((array) ($definition['formats'] ?? [])),
        'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
        'ui' => is_array($definition['ui'] ?? null) ? $definition['ui'] : [],
    ];
}

function mcpConverterConsumePeerNonce(string $peerId, string $nonce): bool
{
    $configuredPath = getenv('POFF_CONVERTER_REPLAY_FILE');
    if (is_string($configuredPath) && trim($configuredPath) !== '') {
        $path = trim($configuredPath);
    } else {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'pageoff-converter-replay-' . substr(hash('sha256', (string) gethostname()), 0, 20);
        if (is_link($directory) || (!is_dir($directory) && !@mkdir($directory, 0700, true))) {
            return false;
        }
        @chmod($directory, 0700);
        $directoryMode = @fileperms($directory);
        if (!is_int($directoryMode) || (($directoryMode & 0077) !== 0) || !is_writable($directory)) {
            return false;
        }
        $path = $directory . DIRECTORY_SEPARATOR . 'nonces.json';
    }
    if (is_link($path)) {
        return false;
    }
    $handle = @fopen($path, 'c+');
    if (!is_resource($handle) || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        return false;
    }

    rewind($handle);
    $contents = stream_get_contents($handle, 2097153);
    if (!is_string($contents) || strlen($contents) > 2097152) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    $records = $contents !== '' ? json_decode($contents, true) : [];
    if (!is_array($records)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }

    $now = time();
    foreach ($records as $key => $record) {
        $seenAt = is_array($record) ? (int) ($record['time'] ?? 0) : (int) $record;
        // A request dated five minutes ahead can remain fresh for ten minutes
        // after its first acceptance. Keep its nonce for that entire window.
        if ($seenAt < $now - 600 || $seenAt > $now + 300) {
            unset($records[$key]);
        }
    }
    $recordKey = hash('sha256', $peerId . "\n" . $nonce);
    if (isset($records[$recordKey]) || count($records) >= 5000) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    $records[$recordKey] = ['time' => $now];
    $encoded = json_encode($records, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        flock($handle, LOCK_UN);
        fclose($handle);
        return false;
    }
    rewind($handle);
    $written = fwrite($handle, $encoded);
    $ok = $written === strlen($encoded) && ftruncate($handle, strlen($encoded)) && fflush($handle);
    @chmod($path, 0600);
    $fileMode = @fileperms($path);
    $ok = $ok && is_int($fileMode) && (($fileMode & 0077) === 0);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $ok;
}

function mcpConverterAuthenticatePeer(array $payload): bool
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        return false;
    }
    $peerId = trim((string) ($_SERVER['HTTP_X_POFF_NODE_ID'] ?? ''));
    $timestamp = trim((string) ($_SERVER['HTTP_X_POFF_TIMESTAMP'] ?? ''));
    $nonce = strtolower(trim((string) ($_SERVER['HTTP_X_POFF_NONCE'] ?? '')));
    $signature = strtolower(trim((string) ($_SERVER['HTTP_X_POFF_SIGNATURE'] ?? '')));
    if (
        !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $peerId)
        || !preg_match('/^\d{10}$/', $timestamp)
        || abs(time() - (int) $timestamp) > 300
        || !preg_match('/^[a-f0-9]{32}$/', $nonce)
        || !preg_match('/^[a-f0-9]{64}$/', $signature)
    ) {
        return false;
    }

    $trustedJson = getenv('POFF_CONVERTER_TRUSTED_PEERS');
    $trusted = is_string($trustedJson) ? json_decode($trustedJson, true) : null;
    $secret = is_array($trusted) ? (string) ($trusted[$peerId] ?? '') : '';
    if (strlen($secret) < 32) {
        return false;
    }
    $expected = Converter::peerRequestSignature($payload, $timestamp, $nonce, $secret);
    return $expected !== ''
        && hash_equals($expected, $signature)
        && mcpConverterConsumePeerNonce($peerId, $nonce);
}

function handleConverterOffers(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpReadRequestData();
    $nodeUrl = trim((string) ($opts['nodeUrl'] ?? mcpConverterCurrentNodeUrl()));
    $nodeId = trim((string) ($opts['nodeId'] ?? parse_url($nodeUrl, PHP_URL_HOST) ?? 'poff-node'));

    $available = Converter::offerableFor(
        (string) ($input['mimeType'] ?? ''),
        (string) ($input['kind'] ?? ''),
        (string) ($input['extension'] ?? ''),
        $rootDir
    );

    return [
        'ok' => true,
        'route' => 'converter-offers',
        'protocol' => 'poff-converter/1',
        'node' => [
            'id' => $nodeId !== '' ? $nodeId : 'poff-node',
            'url' => $nodeUrl,
            'endpoint' => mcpConverterNodeRouteUrl($nodeUrl, 'convert-offered'),
        ],
        'converters' => array_map('mcpConverterOfferDefinition', $available),
    ];
}

function mcpConverterCatalogRequest(string $url, array $payload): array
{
    $override = $GLOBALS['__poff_converter_catalog_post'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $payload);
        return is_array($response) ? $response : ['ok' => false, 'status' => 0, 'body' => ''];
    }

    $requestBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
    if (!is_string($requestBody) || strlen($requestBody) > Converter::MAX_REMOTE_REQUEST_BYTES) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Converter catalog request exceeds the allowed size.'];
    }
    return Converter::httpRequestPinned(
        'POST',
        $url,
        $requestBody,
        ['Content-Type: application/json', 'Accept: application/json'],
        20,
        1048576,
        getenv('POFF_ALLOW_PRIVATE_CONVERTER_HOSTS') === '1'
    );
}

function handleRemoteConverters(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpReadRequestData();
    $path = trim((string) ($input['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'remote-converters'], $access);
    }

    $nodeUrl = trim((string) ($input['nodeUrl'] ?? ''));
    $catalogUrl = mcpConverterNodeRouteUrl($nodeUrl, 'converter-offers');
    $validation = Converter::validateRemoteEndpoint($catalogUrl);
    if ($validation !== null) {
        return ['ok' => false, 'route' => 'remote-converters', 'error' => $validation];
    }

    $request = [
        'mimeType' => strtolower(trim((string) ($input['mimeType'] ?? ''))),
        'kind' => strtolower(trim((string) ($input['kind'] ?? ''))),
        'extension' => strtolower(trim((string) ($input['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)), '. ')),
    ];
    $response = mcpConverterCatalogRequest($catalogUrl, $request);
    if (($response['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'route' => 'remote-converters',
            'error' => 'Remote Poff converter catalog unavailable.',
            'status' => (int) ($response['status'] ?? 0),
        ];
    }

    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded) || ($decoded['protocol'] ?? '') !== 'poff-converter/1') {
        return ['ok' => false, 'route' => 'remote-converters', 'error' => 'Invalid Poff converter catalog.'];
    }

    $node = is_array($decoded['node'] ?? null) ? $decoded['node'] : [];
    $nodeId = trim((string) ($node['id'] ?? parse_url($nodeUrl, PHP_URL_HOST) ?? 'remote'));
    $endpoint = mcpConverterNodeRouteUrl($nodeUrl, 'convert-offered');
    if (Converter::validateRemoteEndpoint($endpoint) !== null) {
        return ['ok' => false, 'route' => 'remote-converters', 'error' => 'Remote Poff converter endpoint is invalid.'];
    }

    $definitions = [];
    foreach ((array) ($decoded['converters'] ?? []) as $offered) {
        if (!is_array($offered)) {
            continue;
        }
        $definition = Converter::remoteDefinition($offered, [
            'id' => $nodeId,
            'baseUrl' => $nodeUrl,
            'catalogUrl' => $catalogUrl,
            'endpoint' => $endpoint,
        ]);
        if ($definition !== null && Converter::matches(
            $definition,
            $request['mimeType'],
            $request['kind'],
            $request['extension']
        )) {
            $definitions[] = $definition;
        }
    }

    return [
        'ok' => true,
        'route' => 'remote-converters',
        'protocol' => 'poff-converter/1',
        'node' => [
            'id' => $nodeId,
            'url' => $nodeUrl,
            'endpoint' => $endpoint,
        ],
        'available' => $definitions,
    ];
}

function mcpPoffRegistryEndpoint(string $registryUrl, string $registryPath = ''): string
{
    $parts = parse_url(trim($registryUrl));
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = (string) ($parts['path'] ?? '');
    if ($path === '' || str_ends_with($path, '/')) {
        $path = rtrim($path, '/') . '/index.php';
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    unset($query['mcp'], $query['route'], $query['authToken']);
    $query['edit'] = 'upload';
    if (trim($registryPath) !== '') {
        $query['path'] = trim($registryPath, "/\\");
    }

    return $scheme . '://' . $host . $port . $path . '?' . http_build_query($query);
}

function mcpPoffRegistryCategories(mixed $value): array
{
    $source = is_array($value)
        ? $value
        : (is_string($value) ? (preg_split('/\r?\n|,/', $value) ?: []) : []);
    $categories = [];
    foreach ($source as $candidate) {
        if (!is_scalar($candidate)) {
            continue;
        }
        $normalized = strtolower(trim((string) $candidate));
        $normalized = substr($normalized, 0, 24);
        if ($normalized === '' || in_array($normalized, $categories, true)) {
            continue;
        }
        $categories[] = $normalized;
        if (count($categories) >= 12) {
            break;
        }
    }
    return $categories;
}

function mcpPoffNodeCatalogUrls(string $pageUrl, string $path): array
{
    $parts = parse_url($pageUrl);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return [];
    }

    $base = strtolower((string) $parts['scheme']) . '://' . (string) $parts['host'];
    if (isset($parts['port'])) {
        $base .= ':' . (int) $parts['port'];
    }
    $base .= (string) ($parts['path'] ?? '/index.php');

    return [
        'protocol' => 'poff-node/1',
        'baseUrl' => $base,
        'workCatalogUrl' => $base . '?' . http_build_query([
            'mcp' => '1',
            'route' => 'export-content',
            'path' => trim($path, "/\\"),
        ]),
        'converterCatalogUrl' => $base . '?' . http_build_query([
            'mcp' => '1',
            'route' => 'converter-offers',
        ]),
    ];
}

function mcpPoffRegistryRequest(string $url, array $payload): array
{
    $override = $GLOBALS['__poff_registry_post'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $payload);
        return is_array($response) ? $response : ['ok' => false, 'status' => 0, 'body' => ''];
    }
    return mcpConverterCatalogRequest($url, $payload);
}

function handleRegisterPoffNode(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpReadRequestData();
    $path = trim((string) ($input['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'register-node'], $access);
    }

    $target = mcpResolvePathInsideRoot($rootDir, $path);
    if ($target === null) {
        return ['ok' => false, 'route' => 'register-node', 'error' => 'Current work is unavailable.'];
    }
    $config = is_file($target)
        ? PoffConfig::ensureFileConfig(dirname($target), basename($target))
        : PoffConfig::read($target);
    $work = is_array($config['work'] ?? null) ? $config['work'] : [];
    $categories = mcpPoffRegistryCategories($work['categories'] ?? ($work['category'] ?? []));
    $title = trim((string) ($config['title'] ?? $config['name'] ?? basename($target)));
    if ($title === '') {
        $title = $path !== '' ? basename($path) : 'Poff node';
    }

    $pageUrl = trim((string) ($input['pageUrl'] ?? ''));
    $pageValidation = Converter::validateRemoteEndpoint($pageUrl);
    if ($pageValidation !== null) {
        return ['ok' => false, 'route' => 'register-node', 'error' => 'Current page needs a public http or https URL.'];
    }

    $registryUrl = trim((string) ($input['registryUrl'] ?? 'https://pageoff.de/index.php'));
    $registryEndpoint = mcpPoffRegistryEndpoint(
        $registryUrl,
        (string) ($input['registryPath'] ?? '')
    );
    if ($registryEndpoint === '' || Converter::validateRemoteEndpoint($registryEndpoint) !== null) {
        return ['ok' => false, 'route' => 'register-node', 'error' => 'Poff registry URL is invalid.'];
    }

    $node = mcpPoffNodeCatalogUrls($pageUrl, $path);
    $response = mcpPoffRegistryRequest($registryEndpoint, [
        'source' => 'url',
        'linkName' => $title,
        'linkUrl' => $pageUrl,
        'categories' => $categories,
        'poffNode' => $node,
    ]);
    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($decoded)) {
        $decoded = [];
    }
    if (($response['ok'] ?? false) !== true || !empty($decoded['error'])) {
        return [
            'ok' => false,
            'route' => 'register-node',
            'error' => (string) ($decoded['error'] ?? 'Poff registry unavailable.'),
            'status' => (int) ($response['status'] ?? 0),
        ];
    }

    $uploaded = is_array($decoded['uploaded'][0] ?? null) ? $decoded['uploaded'][0] : [];
    return [
        'ok' => true,
        'route' => 'register-node',
        'registry' => $registryUrl,
        'pageUrl' => $pageUrl,
        'categories' => $categories,
        'node' => $node,
        'pendingApproval' => !empty($decoded['pendingApproval']) || !empty($uploaded['pendingApproval']),
        'updatedRegistration' => !empty($uploaded['updatedRegistration']),
    ];
}

function handleConvertOffered(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    if (($payload['__poffRequestTooLarge'] ?? false) === true) {
        return ['ok' => false, 'route' => 'convert-offered', 'error' => 'Converter request exceeds the allowed size.'];
    }
    if (!mcpConverterAuthenticatePeer($payload)) {
        return ['ok' => false, 'route' => 'convert-offered', 'error' => 'Authenticated converter peer request required.'];
    }
    $result = Converter::convertOffered($payload, $rootDir);
    $result['route'] = 'convert-offered';
    return $result;
}
