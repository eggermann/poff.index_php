<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/viewer/link-targets.php';
require_once __DIR__ . '/../../includes/viewer/render/data.php';

const MCP_REMOTE_HTTP_TIMEOUT_SECONDS = 30;

function mcpRemoteContentError(string $message, bool $allowed = true): array
{
    return [
        'allowed' => $allowed,
        'error' => $message,
    ];
}

function mcpRemoteUrlHostIsPrivate(string $host): bool
{
    if ($host === '') {
        return true;
    }
    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
        return true;
    }

    $ips = gethostbynamel($host);
    if (!is_array($ips) || $ips === []) {
        $ips = [$host];
    }

    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return true;
            }
            continue;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $normalized = strtolower($ip);
            if (
                $normalized === '::1'
                || str_starts_with($normalized, 'fe80:')
                || str_starts_with($normalized, 'fc')
                || str_starts_with($normalized, 'fd')
            ) {
                return true;
            }
            $isPublic = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($isPublic === false) {
                return true;
            }
        }
    }

    return false;
}

function mcpRemoteAbsoluteUrl(string $baseUrl, string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    if (cmsIsExternalLinkTarget($trimmed) || cmsIsHashLinkTarget($trimmed)) {
        return $trimmed;
    }

    $parts = parse_url($baseUrl);
    $scheme = (string) ($parts['scheme'] ?? 'https');
    $host = (string) ($parts['host'] ?? '');
    $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
    $basePath = (string) ($parts['path'] ?? '/');
    $baseDir = preg_replace('~/[^/]*$~', '/', $basePath);
    if (!is_string($baseDir) || $baseDir === '') {
        $baseDir = '/';
    }

    if (str_starts_with($trimmed, '?')) {
        return $scheme . '://' . $host . $port . $basePath . $trimmed;
    }
    if (str_starts_with($trimmed, '/')) {
        return $scheme . '://' . $host . $port . $trimmed;
    }

    return $scheme . '://' . $host . $port . $baseDir . ltrim($trimmed, '/');
}

function mcpRemoteHttpGet(string $url, array $headers = []): array
{
    $override = $GLOBALS['__poff_mcp_remote_http_get'] ?? null;
    if (is_callable($override)) {
        return $override($url, $headers);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => MCP_REMOTE_HTTP_TIMEOUT_SECONDS,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    @set_time_limit(MCP_REMOTE_HTTP_TIMEOUT_SECONDS + 5);
    @ini_set('default_socket_timeout', (string) MCP_REMOTE_HTTP_TIMEOUT_SECONDS);

    $body = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) && is_array($http_response_header)
        ? $http_response_header
        : [];

    $status = 0;
    foreach ($responseHeaders as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $matches)) {
            $status = (int) $matches[1];
            break;
        }
    }

    if ($body === false) {
        return [
            'status' => $status > 0 ? $status : 0,
            'headers' => $responseHeaders,
            'body' => '',
        ];
    }

    return [
        'status' => $status > 0 ? $status : 200,
        'headers' => $responseHeaders,
        'body' => $body,
    ];
}

function mcpRemoteContentBaseUrl(array $opts): string
{
    $explicit = trim((string) ($opts['baseUrl'] ?? ''));
    if ($explicit !== '') {
        return rtrim($explicit, '/');
    }

    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $scriptName = trim((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    if ($host === '') {
        return $scriptName !== '' ? $scriptName : '/index.php';
    }

    return $scheme . '://' . $host . ($scriptName !== '' ? $scriptName : '/index.php');
}

function mcpRemoteNormalizeExportItem(array $item, string $baseUrl): array
{
    $result = [];
    $routePath = trim((string) ($item['routePath'] ?? ($item['relativePath'] ?? ($item['path'] ?? ''))));
    $routeSlug = trim((string) ($item['routeSlug'] ?? ''));
    if ($routeSlug === '') {
        $routeBase = $routePath !== '' ? basename(str_replace('\\', '/', $routePath)) : trim((string) ($item['name'] ?? ''));
        $routeSlug = class_exists('PoffConfig') ? PoffConfig::slugify($routeBase) : (preg_replace('/[^a-z0-9]+/i', '-', strtolower($routeBase)) ?: '');
        $routeSlug = trim($routeSlug, '-');
    }
    foreach ([
        'name',
        'title',
        'type',
        'kind',
        'path',
        'relativePath',
        'basename',
        'depth',
        'description',
        'slug',
        'visible',
        'isFolder',
        'isFile',
        'isVirtual',
        'childCount',
        'mimeType',
        'extension',
        'renderedHtml',
    ] as $key) {
        if (array_key_exists($key, $item)) {
            $result[$key] = $item[$key];
        }
    }
    if ($routePath !== '') {
        $result['routePath'] = $routePath;
    }
    if ($routeSlug !== '') {
        $result['routeSlug'] = $routeSlug;
    }

    $pageLink = trim((string) ($item['pageLink'] ?? $item['viewerHref'] ?? ''));
    $srcUrl = trim((string) ($item['srcUrl'] ?? $item['assetUrl'] ?? $item['rawHref'] ?? ''));
    $linkUrl = trim((string) ($item['linkUrl'] ?? ''));

    $result['pageLink'] = $pageLink !== '' ? mcpRemoteAbsoluteUrl($baseUrl, $pageLink) : '';
    $result['srcUrl'] = $srcUrl !== '' ? mcpRemoteAbsoluteUrl($baseUrl, $srcUrl) : '';
    if ($linkUrl !== '') {
        $result['linkUrl'] = mcpRemoteAbsoluteUrl($baseUrl, $linkUrl);
    }

    if (!empty($item['children']) && is_array($item['children'])) {
        $result['children'] = array_map(
            static fn(array $child): array => mcpRemoteNormalizeExportItem($child, $baseUrl),
            array_values(array_filter($item['children'], 'is_array'))
        );
    }

    return $result;
}

function mcpRemoteExportSourceId(string $url, array $payload): string
{
    $declared = trim((string) (($payload['source']['id'] ?? $payload['origin']['host'] ?? '')));
    if ($declared !== '') {
        return preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($declared)) ?: 'remote';
    }

    $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host !== '') {
        return preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($host)) ?: 'remote';
    }

    return 'remote';
}

function mcpRemoteUniqueImportedName(string $preferred, string $sourceId, array &$usedNames): string
{
    $base = trim($preferred);
    if ($base === '') {
        $base = $sourceId;
    }

    if (!isset($usedNames[$base])) {
        $usedNames[$base] = true;
        return $base;
    }

    $candidate = $base . ' (' . $sourceId . ')';
    if (!isset($usedNames[$candidate])) {
        $usedNames[$candidate] = true;
        return $candidate;
    }

    $index = 2;
    while (isset($usedNames[$candidate . ' ' . $index])) {
        $index++;
    }

    $final = $candidate . ' ' . $index;
    $usedNames[$final] = true;
    return $final;
}

function mcpRemoteImportTreeEntries(array $payload, string $sourceUrl, string $sourceId, array $existingTree): array
{
    $rawItems = $payload['items'] ?? $payload['tree'] ?? [];
    if (!is_array($rawItems)) {
        return [];
    }

    $usedNames = [];
    foreach ($existingTree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        if ($name !== '') {
            $usedNames[$name] = true;
        }
    }

    $entries = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $pageLink = trim((string) ($item['pageLink'] ?? ''));
        if ($pageLink === '') {
            continue;
        }

        $preferredName = trim((string) ($item['title'] ?? $item['name'] ?? ''));
        $uniqueName = mcpRemoteUniqueImportedName($preferredName, $sourceId, $usedNames);
        $type = trim((string) ($item['type'] ?? 'file'));
        $isFolder = ($item['isFolder'] ?? false) === true || $type === 'folder';
        $routePath = trim((string) ($item['routePath'] ?? ($item['relativePath'] ?? ($item['path'] ?? ''))));
        $routeSlug = trim((string) ($item['routeSlug'] ?? ''));
        if ($routeSlug === '') {
            $routeBase = $routePath !== '' ? basename(str_replace('\\', '/', $routePath)) : $uniqueName;
            $routeSlug = class_exists('PoffConfig') ? PoffConfig::slugify($routeBase) : (preg_replace('/[^a-z0-9]+/i', '-', strtolower($routeBase)) ?: '');
            $routeSlug = trim($routeSlug, '-');
        }
        $entry = [
            'name' => $uniqueName,
            'title' => trim((string) ($item['title'] ?? $item['name'] ?? $uniqueName)),
            'slug' => $routeSlug !== '' ? $routeSlug : trim((string) ($item['slug'] ?? (class_exists('PoffConfig') ? PoffConfig::slugify($uniqueName) : ''))),
            'routeSlug' => $routeSlug,
            'routePath' => $routePath,
            'type' => $isFolder
                ? 'folder'
                : (trim((string) ($item['kind'] ?? $type)) === 'link' ? 'link' : 'file'),
            'path' => trim((string) ($item['relativePath'] ?? $item['path'] ?? $uniqueName)),
            'pageLink' => $pageLink,
            'linkUrl' => trim((string) ($item['linkUrl'] ?? '')),
            'srcUrl' => trim((string) ($item['srcUrl'] ?? '')),
            'renderedHtml' => trim((string) ($item['renderedHtml'] ?? '')),
            'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
            'remoteSource' => $sourceId,
            'remoteFeedUrl' => $sourceUrl,
            'remotePath' => trim((string) ($item['path'] ?? '')),
            'remoteKind' => trim((string) ($item['kind'] ?? $type)),
            'importedAt' => date('c'),
        ];
        if ($entry['renderedHtml'] !== '') {
            $entry['template'] = 'external';
        }
        $entries[] = $entry;
    }

    return $entries;
}

function handleExportContent(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = trim((string) ($opts['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'export-content'], $access);
    }

    if (!class_exists('PoffConfig')) {
        return array_merge([
            'route' => 'export-content',
        ], mcpRemoteContentError('PoffConfig unavailable.'));
    }

    $targetDir = mcpResolveDirectoryInsideRoot($rootDir, $path);
    if ($targetDir === null) {
        return array_merge([
            'route' => 'export-content',
        ], mcpRemoteContentError('Invalid folder path.'));
    }

    $config = PoffConfig::ensure($targetDir);
    $folderName = (string) ($config['folderName'] ?? basename($targetDir));
    $title = (string) ($config['title'] ?? $folderName);
    $slug = trim((string) ($config['slug'] ?? ''));
    if ($slug === '') {
        $slug = class_exists('PoffConfig') ? PoffConfig::slugify($folderName) : 'item';
    }

    $viewerData = buildFolderViewerData($path, $targetDir, $config, [
        'name' => $folderName,
        'title' => $title,
        'slug' => $slug,
    ]);
    $baseUrl = mcpRemoteContentBaseUrl($opts);

    return [
        'route' => 'export-content',
        'allowed' => true,
        'exportedAt' => date('c'),
        'source' => [
            'id' => trim((string) ($opts['sourceId'] ?? parse_url($baseUrl, PHP_URL_HOST) ?? basename($rootDir))),
            'baseUrl' => $baseUrl,
            'path' => $path,
        ],
        'root' => [
            'name' => $folderName,
            'title' => $title,
            'slug' => $slug,
            'path' => $path,
            'pageLink' => mcpRemoteAbsoluteUrl($baseUrl, '?view=1&path=' . rawurlencode($path)),
        ],
        'items' => array_map(
            static fn(array $item): array => mcpRemoteNormalizeExportItem($item, $baseUrl),
            $viewerData['tree']
        ),
        'counts' => [
            'items' => count($viewerData['tree']),
            'allItems' => count($viewerData['allItems']),
            'allFiles' => count($viewerData['allFiles']),
            'allFolders' => count($viewerData['allFolders']),
        ],
    ];
}

function handleImportRemote(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = trim((string) ($opts['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'import-remote'], $access);
    }

    $url = trim((string) ($opts['url'] ?? ''));
    $sourceId = trim((string) ($opts['sourceId'] ?? ''));
    $replace = (bool) ($opts['replace'] ?? false);

    if (!class_exists('PoffConfig')) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('PoffConfig unavailable.'));
    }
    if ($url === '') {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('Missing remote export URL.'));
    }

    $targetDir = mcpResolveDirectoryInsideRoot($rootDir, $path);
    if ($targetDir === null) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('Invalid folder path.'));
    }

    $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('Only http and https remote exports are allowed.'));
    }
    if ($host === '' || mcpRemoteUrlHostIsPrivate($host)) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('Remote export host is not allowed.'));
    }

    $response = mcpRemoteHttpGet($url, ['Accept: application/json']);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300) {
        return array_merge([
            'route' => 'import-remote',
            'status' => $response['status'] ?? 0,
        ], mcpRemoteContentError('Remote export request failed.'));
    }

    $payload = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($payload)) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError('Remote export returned invalid JSON.'));
    }

    $config = PoffConfig::ensure($targetDir);
    $existingTree = isset($config['tree']) && is_array($config['tree']) ? $config['tree'] : [];
    $resolvedSourceId = $sourceId !== '' ? $sourceId : mcpRemoteExportSourceId($url, $payload);
    $importedEntries = mcpRemoteImportTreeEntries($payload, $url, $resolvedSourceId, $existingTree);

    if ($replace) {
        $existingTree = array_values(array_filter($existingTree, static function ($item) use ($resolvedSourceId): bool {
            return !is_array($item) || (string) ($item['remoteSource'] ?? '') !== $resolvedSourceId;
        }));
    }

    $config['tree'] = array_merge($existingTree, $importedEntries);
    $config['treeHash'] = hash('sha256', json_encode($config['tree']));
    $config['updatedAt'] = date('c');
    $config['remoteSources'] = array_values(array_filter(array_merge(
        isset($config['remoteSources']) && is_array($config['remoteSources']) ? $config['remoteSources'] : [],
        [[
            'id' => $resolvedSourceId,
            'url' => $url,
            'path' => $payload['source']['path'] ?? '',
            'importedAt' => $config['updatedAt'],
        ]]
    ), 'is_array'));

    $writeError = mcpWriteJsonFile(PoffConfig::configPath($targetDir), $config);
    if ($writeError !== null) {
        return array_merge([
            'route' => 'import-remote',
        ], mcpRemoteContentError($writeError));
    }

    return [
        'route' => 'import-remote',
        'allowed' => true,
        'saved' => true,
        'sourceId' => $resolvedSourceId,
        'importedCount' => count($importedEntries),
        'config' => PoffConfig::hydrateConfigLayout($config, $targetDir),
    ];
}
