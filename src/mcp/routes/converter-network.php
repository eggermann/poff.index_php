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

function handleConverterOffers(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpReadRequestData();
    $mimeType = strtolower(trim((string) ($input['mimeType'] ?? '')));
    $kind = strtolower(trim((string) ($input['kind'] ?? '')));
    $extension = strtolower(trim((string) ($input['extension'] ?? ''), '. '));
    $nodeUrl = trim((string) ($opts['nodeUrl'] ?? mcpConverterCurrentNodeUrl()));
    $nodeId = trim((string) ($opts['nodeId'] ?? parse_url($nodeUrl, PHP_URL_HOST) ?? 'poff-node'));

    $available = array_values(array_filter(
        Converter::availableFor($mimeType, $kind, $extension, $rootDir),
        static fn(array $definition): bool => Converter::canOffer($definition)
    ));

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

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAccept: application/json",
            'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'timeout' => 20,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    foreach (($http_response_header ?? []) as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string) $header, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }
    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body !== false ? $body : '',
    ];
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

function handleConvertOffered(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    $result = Converter::convertOffered($payload, $rootDir);
    $result['route'] = 'convert-offered';
    return $result;
}
