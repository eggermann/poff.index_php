<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Core/RemoteFeedImport.php';
require_once __DIR__ . '/../../includes/viewer/link-targets.php';
require_once __DIR__ . '/../../includes/viewer/render/data.php';

const MCP_REMOTE_HTTP_TIMEOUT_SECONDS = 30;
const MCP_REMOTE_COPY_MAX_FILES = 48;
const MCP_REMOTE_COPY_MAX_FILE_BYTES = 134217728;
const MCP_REMOTE_COPY_MAX_TOTAL_BYTES = 536870912;

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
            'follow_location' => 0,
            'max_redirects' => 0,
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

function mcpRemoteExportPathIsPublic(string $path): bool
{
    if ($path === '') {
        return true;
    }
    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        $segment = trim($segment);
        if ($segment === '' || $segment === '.' || $segment === '..' || str_starts_with($segment, '.')) {
            return false;
        }
        if (in_array(strtolower($segment), ['edit.not-allow', 'poff.config.json', 'poff.config.toon'], true)) {
            return false;
        }
    }

    return true;
}

function mcpRemoteWorkIdentityForExport(array $item, string $nodeRootDir): array
{
    if (!empty($item['isVirtual'])) {
        $remoteNodeId = trim((string) ($item['remoteNodeId'] ?? ''));
        $remoteWorkId = trim((string) ($item['remoteWorkId'] ?? ''));
        return $remoteNodeId !== '' && $remoteWorkId !== ''
            ? ['reference' => ['nodeId' => $remoteNodeId, 'workId' => $remoteWorkId]]
            : [];
    }

    $relativePath = trim((string) ($item['relativePath'] ?? $item['path'] ?? ''), "/\\");
    $absolutePath = mcpResolvePathInsideRoot($nodeRootDir, $relativePath);
    if ($absolutePath === null) {
        return [];
    }

    if (is_dir($absolutePath)) {
        $config = PoffConfig::ensure($absolutePath);
    } elseif (is_file($absolutePath)) {
        $config = PoffConfig::ensureFileConfig(dirname($absolutePath), basename($absolutePath));
    } else {
        return [];
    }

    $workId = trim((string) ($config['id'] ?? ''));
    return $workId !== '' ? ['workId' => $workId] : [];
}

function mcpRemoteNormalizeExportItem(array $item, string $baseUrl, string $nodeRootDir): array
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
        'size',
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
    $result = array_merge($result, mcpRemoteWorkIdentityForExport($item, $nodeRootDir));

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
            static fn(array $child): array => mcpRemoteNormalizeExportItem($child, $baseUrl, $nodeRootDir),
            array_values(array_filter($item['children'], 'is_array'))
        );
    }

    return $result;
}

function mcpRemoteStableNodeId(mixed $value): string
{
    $nodeId = trim(is_string($value) ? $value : '');
    return preg_match('/^poff_node_[a-f0-9]{32}$/', $nodeId) === 1 ? $nodeId : '';
}

function mcpRemoteExportSourceId(string $url, array $payload): string
{
    $declared = trim((string) (($payload['source']['id'] ?? $payload['origin']['host'] ?? '')));
    if ($declared !== '') {
        return preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($declared)) ?: 'remote';
    }

    $nodeId = mcpRemoteStableNodeId($payload['source']['nodeId'] ?? '');
    if ($nodeId !== '') {
        return $nodeId;
    }

    $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if ($host !== '') {
        return preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($host)) ?: 'remote';
    }

    return 'remote';
}

function mcpRemoteUniqueImportedName(string $preferred, string $sourceId, array &$usedNames): string
{
    return PoffConfig::uniqueImportedName($preferred, $sourceId, $usedNames);
}

function mcpRemoteImportTreeEntries(array $payload, string $sourceUrl, string $sourceId, array $existingTree): array
{
    return PoffRemoteFeedImport::normalizeTreeEntries($payload, $sourceUrl, $sourceId, $existingTree);
}

function mcpRemotePublicHttpUrl(string $url, ?string $requiredHost = null): ?array
{
    $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || mcpRemoteUrlHostIsPrivate($host)) {
        return null;
    }
    if ($requiredHost !== null && strtolower($requiredHost) !== $host) {
        return null;
    }

    return [
        'scheme' => $scheme,
        'host' => $host,
    ];
}

function mcpRemoteFlattenCopyItems(array $items): array
{
    $result = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $isFolder = ($item['isFolder'] ?? false) === true || (string) ($item['type'] ?? '') === 'folder';
        $srcUrl = trim((string) ($item['srcUrl'] ?? ''));
        if (!$isFolder && $srcUrl !== '') {
            $result[] = $item;
        }
        if (isset($item['children']) && is_array($item['children'])) {
            foreach (mcpRemoteFlattenCopyItems($item['children']) as $child) {
                $result[] = $child;
            }
        }
    }

    return $result;
}

function mcpRemoteSafeCopyName(array $item, int $index): string
{
    $preferred = trim((string) ($item['basename'] ?? $item['name'] ?? ''));
    if ($preferred === '') {
        $path = (string) (parse_url((string) ($item['srcUrl'] ?? ''), PHP_URL_PATH) ?? '');
        $preferred = basename($path);
    }
    $preferred = basename(str_replace('\\', '/', $preferred));
    $preferred = preg_replace('/[^a-z0-9._ -]+/i', '-', $preferred) ?: '';
    $preferred = trim($preferred, ". \t\n\r\0\x0B-");
    if ($preferred === '') {
        $extension = trim((string) ($item['extension'] ?? ''), ". \t\n\r\0\x0B");
        $preferred = 'remote-file-' . ($index + 1) . ($extension !== '' ? '.' . $extension : '');
    }

    return substr($preferred, 0, 180);
}

function mcpRemoteUniqueCopyName(string $name, array &$usedNames): string
{
    $key = strtolower($name);
    if (!isset($usedNames[$key])) {
        $usedNames[$key] = true;
        return $name;
    }

    $extension = pathinfo($name, PATHINFO_EXTENSION);
    $stem = $extension !== '' ? substr($name, 0, -(strlen($extension) + 1)) : $name;
    $suffix = 2;
    do {
        $candidate = $stem . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
        $suffix++;
    } while (isset($usedNames[strtolower($candidate)]));
    $usedNames[strtolower($candidate)] = true;

    return $candidate;
}

function mcpRemoteRemoveTemporaryDirectory(string $directory): void
{
    if (!is_dir($directory) || !str_starts_with(basename($directory), '.poff-import-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($directory);
}

function mcpRemotePlayerLayoutFiles(): array
{
    return [
        'template.hbs' => '<main class="remote-player-layout">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main>',
        'works.hbs' => <<<'HBS'
<section class="remote-player" data-remote-playlist>
    <header><p>LOCAL COPY · PLAYLIST</p><h1>{{title}}</h1><span>{{videoCount}} films</span><button class="remote-player__loop is-on" type="button" data-remote-loop aria-pressed="true" aria-label="Turn playlist loop off"><span aria-hidden="true">↻</span><span data-remote-loop-label>Loop on</span></button></header>
    <div class="remote-player__grid">
        <div class="remote-player__stage">
            <video data-remote-video controls autoplay muted playsinline preload="metadata">
                {{#each allVideos}}{{#if @first}}<source src="{{srcUrl}}">{{/if}}{{/each}}
            </video>
            <strong data-remote-title>{{#each allVideos}}{{#if @first}}{{title}}{{/if}}{{/each}}</strong>
        </div>
        <ol>{{#each allVideos}}<li><button type="button"{{#if @first}} class="is-active" aria-current="true"{{/if}} data-remote-src="{{srcUrl}}" data-remote-title-value="{{title}}" title="Play {{title}}">{{title}}</button></li>{{/each}}</ol>
    </div>
</section>
HBS,
        'work.hbs' => '<video class="remote-player__single" src="{{srcUrl}}" controls autoplay muted playsinline></video>',
        'style.css' => <<<'CSS'
.remote-player-layout{min-height:100%;padding:clamp(1.5rem,5vw,5rem);color:#f5f2ec;background:radial-gradient(circle at 15% 0,rgba(230,91,62,.22),transparent 32rem),#0b0d0e;font-family:Inter,system-ui,sans-serif}.remote-player{max-width:90rem;margin:auto}.remote-player header{display:flex;align-items:end;gap:1rem;margin-bottom:2rem}.remote-player header p{color:#e7775e;font-size:.72rem;font-weight:800;letter-spacing:.16em}.remote-player h1{margin:0 auto 0 0;font:400 clamp(3rem,8vw,7rem)/.9 Georgia,serif}.remote-player__loop{display:inline-flex;align-items:center;gap:.5rem;padding:.65rem .85rem;border:1px solid #343839;border-radius:999px;color:#c8cccb;background:#15191a;cursor:pointer;transition:all .16s ease}.remote-player__loop:hover,.remote-player__loop:focus-visible{border-color:#71c69a;outline:0;color:#fff;background:rgba(113,198,154,.12);box-shadow:0 0 0 4px rgba(113,198,154,.1);transform:translateY(-1px)}.remote-player__loop:not(.is-on) span:first-child{color:#69706f}.remote-player__grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(16rem,23rem);gap:2rem}.remote-player__stage,.remote-player ol{overflow:hidden;border:1px solid #2a2d2e;border-radius:1.25rem;background:#111415}.remote-player video{display:block;width:100%;aspect-ratio:4/3;background:#000;object-fit:contain}.remote-player__stage strong{display:block;padding:1rem}.remote-player ol{margin:0;padding:.5rem;list-style:none}.remote-player ol button{width:100%;padding:1rem;border:0;border-radius:.75rem;color:#c8cccb;background:transparent;text-align:left;cursor:pointer;transition:all .15s ease}.remote-player ol button:hover,.remote-player ol button:focus-visible{outline:0;color:#fff;background:rgba(255,255,255,.085);box-shadow:inset 3px 0 0 #e7775e;transform:translateX(3px)}.remote-player ol button.is-active{color:#fff;background:#2a1d1a}.remote-player__single{display:block;width:min(100%,70rem);margin:auto}@media(max-width:800px){.remote-player__grid{grid-template-columns:1fr}.remote-player header{align-items:start;flex-direction:column}}
CSS,
        'script.js' => <<<'JS'
(() => {
    const root = document.querySelector('[data-remote-playlist]');
    if (!root || root.dataset.ready === 'true') return;
    const video = root.querySelector('[data-remote-video]');
    const title = root.querySelector('[data-remote-title]');
    const tracks = Array.from(root.querySelectorAll('[data-remote-src]'));
    const loopToggle = root.querySelector('[data-remote-loop]');
    const loopLabel = root.querySelector('[data-remote-loop-label]');
    if (!video || tracks.length === 0) return;
    let index = 0;
    let loopEnabled = true;
    root.dataset.ready = 'true';
    const play = (next) => {
        index = (next + tracks.length) % tracks.length;
        tracks.forEach((track, trackIndex) => {
            const active = trackIndex === index;
            track.classList.toggle('is-active', active);
            track.setAttribute('aria-current', active ? 'true' : 'false');
        });
        video.src = tracks[index].dataset.remoteSrc || '';
        title.textContent = tracks[index].dataset.remoteTitleValue || `Film ${index + 1}`;
        video.load();
        const attempt = video.play();
        if (attempt && typeof attempt.catch === 'function') attempt.catch(() => {});
    };
    const setLoop = (enabled) => {
        loopEnabled = enabled;
        loopToggle?.classList.toggle('is-on', enabled);
        loopToggle?.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        loopToggle?.setAttribute('aria-label', enabled ? 'Turn playlist loop off' : 'Turn playlist loop on');
        if (loopLabel) loopLabel.textContent = enabled ? 'Loop on' : 'Loop off';
    };
    tracks.forEach((track, trackIndex) => track.addEventListener('click', () => play(trackIndex)));
    loopToggle?.addEventListener('click', () => setLoop(!loopEnabled));
    video.addEventListener('ended', () => {
        if (index < tracks.length - 1) play(index + 1);
        else if (loopEnabled) play(0);
    });
    setLoop(true);
    play(0);
})();
JS,
    ];
}

function mcpRemoteCopyExport(
    string $rootDir,
    string $targetDir,
    array $payload,
    string $sourceUrl,
    string $destinationName,
    bool $createPlayer
): array {
    $sourceHost = strtolower((string) (parse_url($sourceUrl, PHP_URL_HOST) ?? ''));
    $rawItems = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
    $items = mcpRemoteFlattenCopyItems($rawItems);
    if ($items === []) {
        return mcpRemoteContentError('The remote work has no downloadable files.');
    }
    if (count($items) > MCP_REMOTE_COPY_MAX_FILES) {
        return mcpRemoteContentError('The remote work contains too many files to copy at once.');
    }

    $fallbackTitle = trim((string) ($payload['root']['title'] ?? $payload['root']['name'] ?? 'remote-work'));
    $folderBase = trim($destinationName) !== '' ? trim($destinationName) : $fallbackTitle;
    $folderBase = preg_replace('/[^a-z0-9._ -]+/i', '-', $folderBase) ?: 'remote-work';
    $folderBase = trim($folderBase, ". \t\n\r\0\x0B-");
    if ($folderBase === '') {
        $folderBase = 'remote-work';
    }
    $folderName = $folderBase;
    $suffix = 2;
    while (file_exists($targetDir . DIRECTORY_SEPARATOR . $folderName)) {
        $folderName = $folderBase . '-' . $suffix;
        $suffix++;
    }

    $temporaryDir = $targetDir . DIRECTORY_SEPARATOR . '.poff-import-' . bin2hex(random_bytes(6));
    if (!mkdir($temporaryDir, 0755, true)) {
        return mcpRemoteContentError('Failed to create a temporary import folder.');
    }

    $tree = [];
    $usedNames = [];
    $totalBytes = 0;
    try {
        foreach ($items as $index => $item) {
            $srcUrl = trim((string) ($item['srcUrl'] ?? ''));
            if (mcpRemotePublicHttpUrl($srcUrl, $sourceHost) === null) {
                throw new RuntimeException('A remote asset URL is not allowed.');
            }
            $response = mcpRemoteHttpGet($srcUrl, ['Accept: application/octet-stream,*/*;q=0.8']);
            $status = (int) ($response['status'] ?? 0);
            if ($status < 200 || $status >= 300) {
                throw new RuntimeException('A remote asset request failed.');
            }
            $body = (string) ($response['body'] ?? '');
            $size = strlen($body);
            if ($size <= 0 || $size > MCP_REMOTE_COPY_MAX_FILE_BYTES) {
                throw new RuntimeException('A remote asset is empty or exceeds the per-file limit.');
            }
            $totalBytes += $size;
            if ($totalBytes > MCP_REMOTE_COPY_MAX_TOTAL_BYTES) {
                throw new RuntimeException('The remote work exceeds the total copy limit.');
            }

            $name = mcpRemoteUniqueCopyName(mcpRemoteSafeCopyName($item, $index), $usedNames);
            if (file_put_contents($temporaryDir . DIRECTORY_SEPARATOR . $name, $body) === false) {
                throw new RuntimeException('Failed to store a copied remote asset.');
            }
            $tree[] = [
                'name' => $name,
                'slug' => PoffConfig::slugify($name),
                'type' => 'file',
                'path' => $name,
                'modifiedAt' => date('c'),
                'visible' => true,
            ];
        }

        $hasVideos = count(array_filter($tree, static fn(array $item): bool => preg_match('/\.(?:mp4|webm|mov|m4v)$/i', (string) $item['name']) === 1)) > 0;
        $workLayout = [
            'mode' => 'poff-layout',
            'name' => 'poff-layout',
            'engine' => 'lightncandy',
            'section' => 'works',
        ];
        if ($createPlayer && $hasVideos) {
            $layoutDir = $temporaryDir . DIRECTORY_SEPARATOR . '.layout';
            if (!mkdir($layoutDir, 0755, true)) {
                throw new RuntimeException('Failed to create the player layout.');
            }
            foreach (mcpRemotePlayerLayoutFiles() as $name => $contents) {
                if (file_put_contents($layoutDir . DIRECTORY_SEPARATOR . $name, $contents) === false) {
                    throw new RuntimeException('Failed to write the player layout.');
                }
            }
            $workLayout = [
                'mode' => 'custom-layout',
                'name' => 'custom-layout',
                'engine' => 'lightncandy',
                'section' => 'works',
                'preset' => 'custom',
            ];
        }

        $config = [
            '$schema' => 'https://dominikeggermann.com/poff-config.schema.json',
            'folderName' => $folderName,
            'slug' => PoffConfig::slugify($folderName),
            'title' => $fallbackTitle !== '' ? $fallbackTitle : $folderName,
            'description' => 'Local copy from ' . $sourceHost,
            'type' => 'folder',
            'id' => PoffConfig::newWorkId(),
            'tree' => $tree,
            'treeHash' => hash('sha256', json_encode($tree)),
            'updatedAt' => date('c'),
            'work' => [
                'type' => 'folder',
                'layout' => $workLayout,
                'categories' => $createPlayer && $hasVideos
                    ? ['folder', 'collection', 'video', 'playlist']
                    : ['folder', 'collection'],
                'template' => 'folder',
            ],
            'copiedFrom' => [
                'url' => $sourceUrl,
                'host' => $sourceHost,
                'copiedAt' => date('c'),
            ],
        ];
        $writeError = mcpWriteJsonFile($temporaryDir . DIRECTORY_SEPARATOR . 'poff.config.json', $config);
        if ($writeError !== null) {
            throw new RuntimeException($writeError);
        }

        $finalDir = $targetDir . DIRECTORY_SEPARATOR . $folderName;
        if (!rename($temporaryDir, $finalDir)) {
            throw new RuntimeException('Failed to finish the local copy.');
        }
        $resolvedRootDir = realpath($rootDir) ?: $rootDir;
        $resolvedFinalDir = realpath($finalDir) ?: $finalDir;
        $relativePath = PoffConfig::relativePathFromBase($resolvedFinalDir, $resolvedRootDir);
        $playerCreated = $createPlayer && $hasVideos;
        $firstFileName = trim((string) ($tree[0]['name'] ?? ''));
        $openPath = $playerCreated || $firstFileName === ''
            ? $relativePath
            : trim($relativePath . '/' . $firstFileName, '/');

        return [
            'allowed' => true,
            'saved' => true,
            'mode' => 'copy',
            'copiedCount' => count($tree),
            'copiedBytes' => $totalBytes,
            'destinationName' => $folderName,
            'destinationPath' => $relativePath,
            'openPath' => $openPath,
            'playerCreated' => $playerCreated,
        ];
    } catch (Throwable $error) {
        mcpRemoteRemoveTemporaryDirectory($temporaryDir);
        return mcpRemoteContentError($error->getMessage());
    }
}

function handleExportContent(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = trim((string) ($opts['path'] ?? ''), "/\\");
    if (!mcpRemoteExportPathIsPublic($path)) {
        return array_merge([
            'route' => 'export-content',
        ], mcpRemoteContentError('Invalid public folder path.'));
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
    $nodeId = PoffNodeIdentity::ensure($rootDir);

    return [
        'route' => 'export-content',
        'allowed' => true,
        'exportedAt' => date('c'),
        'source' => [
            'id' => trim((string) ($opts['sourceId'] ?? parse_url($baseUrl, PHP_URL_HOST) ?? basename($rootDir))),
            'nodeId' => $nodeId,
            'baseUrl' => $baseUrl,
            'path' => $path,
        ],
        'root' => [
            'workId' => (string) ($config['id'] ?? ''),
            'name' => $folderName,
            'title' => $title,
            'slug' => $slug,
            'path' => $path,
            'pageLink' => mcpRemoteAbsoluteUrl($baseUrl, '?view=1&path=' . rawurlencode($path)),
        ],
        'items' => array_map(
            static fn(array $item): array => mcpRemoteNormalizeExportItem($item, $baseUrl, $rootDir),
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
    $mode = strtolower(trim((string) ($opts['mode'] ?? 'follow')));
    $destinationName = trim((string) ($opts['destinationName'] ?? ''));
    $createPlayer = (bool) ($opts['createPlayer'] ?? false);

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

    if ($mode === 'copy') {
        $copyResult = mcpRemoteCopyExport(
            $rootDir,
            $targetDir,
            $payload,
            $url,
            $destinationName,
            $createPlayer
        );
        return array_merge([
            'route' => 'import-remote',
        ], $copyResult);
    }

    $config = PoffConfig::ensure($targetDir);
    $existingTree = isset($config['tree']) && is_array($config['tree']) ? $config['tree'] : [];
    $resolvedSourceId = $sourceId !== '' ? $sourceId : mcpRemoteExportSourceId($url, $payload);
    $sourceNodeId = mcpRemoteStableNodeId($payload['source']['nodeId'] ?? '');

    if ($replace) {
        $existingTree = array_values(array_filter($existingTree, static function ($item) use ($resolvedSourceId, $sourceNodeId): bool {
            if (!is_array($item)) {
                return true;
            }
            $sameLegacySource = (string) ($item['remoteSource'] ?? '') === $resolvedSourceId;
            $sameStableNode = $sourceNodeId !== '' && (string) ($item['remoteFeedNodeId'] ?? '') === $sourceNodeId;
            return !$sameLegacySource && !$sameStableNode;
        }));
    }

    $importedEntries = mcpRemoteImportTreeEntries($payload, $url, $resolvedSourceId, $existingTree);

    $config['tree'] = array_merge($existingTree, $importedEntries);
    $config['treeHash'] = hash('sha256', json_encode($config['tree']));
    $config['updatedAt'] = date('c');
    $existingRemoteSources = isset($config['remoteSources']) && is_array($config['remoteSources'])
        ? $config['remoteSources']
        : [];
    if ($replace) {
        $existingRemoteSources = array_values(array_filter($existingRemoteSources, static function ($source) use ($resolvedSourceId, $sourceNodeId): bool {
            if (!is_array($source)) {
                return false;
            }
            $sameLegacySource = (string) ($source['id'] ?? '') === $resolvedSourceId;
            $sameStableNode = $sourceNodeId !== '' && (string) ($source['nodeId'] ?? '') === $sourceNodeId;
            return !$sameLegacySource && !$sameStableNode;
        }));
    }
    $config['remoteSources'] = array_values(array_filter(array_merge(
        $existingRemoteSources,
        [[
            'id' => $resolvedSourceId,
            'nodeId' => mcpRemoteStableNodeId($payload['source']['nodeId'] ?? ''),
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
