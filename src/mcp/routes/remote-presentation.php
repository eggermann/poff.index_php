<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/remote-content.php';

const MCP_REMOTE_PRESENTATION_MAX_RESPONSE_BYTES = 2097152;
const MCP_REMOTE_PRESENTATION_MAX_HBS_BYTES = 262144;
const MCP_REMOTE_PRESENTATION_MAX_ASSET_BYTES = 524288;

function mcpRemotePresentationError(string $message, array $extra = []): array
{
    return $extra + [
        'ok' => false,
        'route' => 'remote-work-presentation',
        'error' => $message,
    ];
}

function mcpRemotePresentationCurrentHost(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $parsed = parse_url('http://' . $host, PHP_URL_HOST);
    return strtolower(is_string($parsed) ? $parsed : '');
}

function mcpRemotePresentationHostAllowed(string $host): bool
{
    $normalized = strtolower(trim($host));
    if ($normalized === '') {
        return false;
    }
    if (!mcpRemoteUrlHostIsPrivate($normalized)) {
        return true;
    }

    return $normalized === mcpRemotePresentationCurrentHost();
}

function mcpRemotePresentationBaseUrl(array $parts): string
{
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }

    return $scheme . '://' . $host . $port . $path;
}

function mcpRemotePresentationSnapshotUrl(string $sourceUrl): array
{
    $parts = parse_url(trim($sourceUrl));
    if (!is_array($parts)) {
        return ['error' => 'Invalid remote work URL.'];
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || !mcpRemotePresentationHostAllowed($host)) {
        return ['error' => 'Remote work host is not allowed.'];
    }

    $baseUrl = mcpRemotePresentationBaseUrl($parts);
    parse_str((string) ($parts['query'] ?? ''), $query);
    $filePath = trim((string) ($query['file'] ?? ''), "/\\");

    if ($filePath === '') {
        $fragment = trim((string) ($parts['fragment'] ?? ''));
        $slug = str_starts_with($fragment, '/') ? trim(ltrim($fragment, '/'), "/\\") : '';
        if ($slug !== '') {
            $resolveUrl = $baseUrl . '?' . http_build_query([
                'ajax' => 'resolve',
                'slug' => $slug,
            ]);
            $resolvedResponse = mcpRemoteHttpGet($resolveUrl, ['Accept: application/json']);
            if ((int) ($resolvedResponse['status'] ?? 0) < 200 || (int) ($resolvedResponse['status'] ?? 0) >= 300) {
                return ['error' => 'Remote work route could not be resolved.'];
            }
            $resolved = json_decode((string) ($resolvedResponse['body'] ?? ''), true);
            $resolvedIsFile = is_array($resolved)
                && (array_key_exists('isFile', $resolved)
                    ? (bool) $resolved['isFile']
                    : (($resolved['type'] ?? 'file') !== 'folder'));
            if (!is_array($resolved) || empty($resolved['resolved']) || !$resolvedIsFile) {
                return ['error' => 'Remote URL must point to one file work.'];
            }
            $filePath = trim((string) ($resolved['path'] ?? ''), "/\\");
        }
    }

    if ($filePath === '' || str_contains($filePath, '..')) {
        return ['error' => 'Remote URL must contain a file work.'];
    }

    return [
        'url' => $baseUrl . '?' . http_build_query([
            'ajax' => 'snapshot',
            'file' => $filePath,
        ]),
        'path' => $filePath,
    ];
}

function mcpRemotePresentationSanitizeHbs(string $template): string
{
    $sanitized = preg_replace('#<(script|style)\b[^>]*>[\s\S]*?</\1>#i', '', $template) ?? $template;
    $sanitized = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $sanitized) ?? $sanitized;
    $sanitized = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*javascript:[\s\S]*?\2/i', '', $sanitized) ?? $sanitized;
    return trim($sanitized);
}

function mcpRemotePresentationSafetyIssues(string $css, string $js): array
{
    $issues = [];
    if (preg_match('/@import\b|expression\s*\(|url\s*\(\s*["\']?\s*(?:https?:|\/\/|javascript:)/i', $css) === 1) {
        $issues[] = 'style.css contains external or executable CSS.';
    }
    if (preg_match('/<\/style/i', $css) === 1) {
        $issues[] = 'style.css contains an unsafe closing tag.';
    }
    if (preg_match('/\b(?:fetch|XMLHttpRequest|WebSocket|EventSource|importScripts)\b|navigator\s*\.\s*sendBeacon|document\s*\.\s*cookie|\b(?:localStorage|sessionStorage)\b/i', $js) === 1) {
        $issues[] = 'script.js contains network, cookie, or browser-storage access.';
    }
    if (preg_match('/<\/script/i', $js) === 1) {
        $issues[] = 'script.js contains an unsafe closing tag.';
    }

    return $issues;
}

function mcpRemotePresentationCompatibility(array $local, array $remote): array
{
    $matches = [];
    if ($local['extension'] !== '' && $local['extension'] === $remote['extension']) {
        $matches[] = 'same suffix';
    }
    if ($local['mimeType'] !== '' && $local['mimeType'] === $remote['mimeType']) {
        $matches[] = 'same MIME';
    }
    if ($local['kind'] !== '' && $local['kind'] !== 'other' && $local['kind'] === $remote['kind']) {
        $matches[] = 'same work type';
    }

    return [
        'compatible' => $matches !== [],
        'matches' => array_values(array_unique($matches)),
    ];
}

function mcpRemotePresentationLocalState(string $targetDir, string $targetFile): array
{
    $layoutDir = PoffConfig::fileLayoutDir($targetDir, $targetFile);
    $files = [];
    foreach (['work.hbs', 'style.css', 'script.js'] as $fileName) {
        $path = $layoutDir . DIRECTORY_SEPARATOR . $fileName;
        $files[$fileName] = [
            'exists' => is_file($path),
            'bytes' => is_file($path) ? (int) (filesize($path) ?: 0) : 0,
        ];
    }

    return [
        'directory' => '.works/' . $targetFile . '.layout',
        'hasPresentation' => count(array_filter($files, static fn(array $file): bool => $file['exists'])) > 0,
        'files' => $files,
    ];
}

function mcpRemotePresentationAtomicWrite(string $path, string $content): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    try {
        $suffix = bin2hex(random_bytes(5));
    } catch (Throwable) {
        $suffix = str_replace('.', '', uniqid('', true));
    }
    $temporary = $path . '.tmp-' . $suffix;
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        return false;
    }
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

function mcpRemotePresentationRestoreFiles(array $before): void
{
    foreach ($before as $path => $state) {
        if (!empty($state['exists'])) {
            mcpRemotePresentationAtomicWrite($path, (string) ($state['content'] ?? ''));
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
}

function mcpRemotePresentationApply(
    string $targetDir,
    string $targetFile,
    array $presentation,
    array $remote,
    bool $confirmReplace
): array {
    $localState = mcpRemotePresentationLocalState($targetDir, $targetFile);
    if ($localState['hasPresentation'] && !$confirmReplace) {
        return mcpRemotePresentationError('Local work presentation exists. Confirm replacement.', [
            'requiresConfirmation' => true,
            'localPresentation' => $localState,
        ]);
    }

    PoffConfig::ensureFileConfig($targetDir, $targetFile);
    $layoutDir = PoffConfig::fileLayoutDir($targetDir, $targetFile);
    $managed = [
        'work.hbs' => (string) $presentation['workHbs'],
        'style.css' => (string) $presentation['styleCss'],
        'script.js' => (string) $presentation['scriptJs'],
    ];
    $before = [];
    foreach (array_keys($managed) as $fileName) {
        $path = $layoutDir . DIRECTORY_SEPARATOR . $fileName;
        $before[$path] = [
            'exists' => is_file($path),
            'content' => is_file($path) ? (string) file_get_contents($path) : '',
        ];
    }
    $wrapperPath = $layoutDir . DIRECTORY_SEPARATOR . 'template.hbs';
    $before[$wrapperPath] = [
        'exists' => is_file($wrapperPath),
        'content' => is_file($wrapperPath) ? (string) file_get_contents($wrapperPath) : '',
    ];

    $backupPath = '';
    if ($localState['hasPresentation']) {
        try {
            $backupSuffix = bin2hex(random_bytes(4));
        } catch (Throwable) {
            $backupSuffix = substr(hash('sha256', uniqid('', true)), 0, 8);
        }
        $backupName = gmdate('Ymd-His') . '-' . $backupSuffix;
        $backupDir = $layoutDir . '.history' . DIRECTORY_SEPARATOR . $backupName;
        foreach ($before as $path => $state) {
            if (!empty($state['exists']) && !mcpRemotePresentationAtomicWrite(
                $backupDir . DIRECTORY_SEPARATOR . basename($path),
                (string) $state['content']
            )) {
                return mcpRemotePresentationError('Could not create presentation backup.');
            }
        }
        $backupPath = '.works/' . $targetFile . '.layout.history/' . $backupName;
    }

    if (!is_file($wrapperPath)) {
        $defaultWrapper = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), 'template.hbs');
        if (!is_string($defaultWrapper) || trim($defaultWrapper) === '' || !mcpRemotePresentationAtomicWrite($wrapperPath, $defaultWrapper)) {
            mcpRemotePresentationRestoreFiles($before);
            return mcpRemotePresentationError('Could not create local presentation wrapper.');
        }
    }
    foreach ($managed as $fileName => $content) {
        if (!mcpRemotePresentationAtomicWrite($layoutDir . DIRECTORY_SEPARATOR . $fileName, $content)) {
            mcpRemotePresentationRestoreFiles($before);
            return mcpRemotePresentationError('Could not replace complete work presentation.');
        }
    }

    $configPath = PoffConfig::fileConfigPath($targetDir, $targetFile);
    $config = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($config)) {
        mcpRemotePresentationRestoreFiles($before);
        return mcpRemotePresentationError('Could not read local work config.');
    }
    $work = is_array($config['work'] ?? null) ? $config['work'] : [];
    $layout = is_array($work['layout'] ?? null) ? $work['layout'] : [];
    foreach ([
        'template', 'css', 'js', 'sectionTemplate', 'workTemplate', 'worksTemplate',
        'storage', 'directory', 'localDirectory', 'inheritedDirectory', 'sectionDirectory',
        'baseHref', 'sectionBaseHref', 'cssHref', 'jsHref', 'assets', 'files', 'assetCount',
    ] as $legacyKey) {
        unset($layout[$legacyKey]);
    }
    $layout['mode'] = Worktype::filesystemLayoutName();
    $layout['name'] = Worktype::filesystemLayoutName();
    $layout['preset'] = 'custom';
    $work['layout'] = $layout;
    $work['presentationSource'] = [
        'url' => (string) ($remote['url'] ?? ''),
        'title' => (string) ($remote['title'] ?? $remote['path'] ?? 'Remote work'),
        'mimeType' => (string) ($remote['mimeType'] ?? ''),
        'copiedAt' => date('c'),
        'files' => array_keys($managed),
    ];
    $config['work'] = $work;
    $config['updatedAt'] = date('c');
    $writeError = mcpWriteJsonFile($configPath, $config);
    if ($writeError !== null) {
        mcpRemotePresentationRestoreFiles($before);
        return mcpRemotePresentationError($writeError);
    }

    return [
        'ok' => true,
        'route' => 'remote-work-presentation',
        'copied' => true,
        'replaced' => $localState['hasPresentation'],
        'backupPath' => $backupPath,
        'localPresentation' => mcpRemotePresentationLocalState($targetDir, $targetFile),
        'config' => PoffConfig::hydrateConfigLayout($config, $targetDir, $targetFile),
    ];
}

function handleRemoteWorkPresentation(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpReadRequestData();
    $path = trim((string) ($input['path'] ?? ''), "/\\");
    $sourceUrl = trim((string) ($input['url'] ?? ''));
    $action = trim((string) ($input['action'] ?? 'inspect'));
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(mcpRemotePresentationError('Edit login required.'), $access);
    }
    if ($sourceUrl === '') {
        return mcpRemotePresentationError('Enter a remote work URL.');
    }

    $localFile = mcpResolveFileInsideRoot($rootDir, $path);
    if (!is_string($localFile) || !is_file($localFile)) {
        return mcpRemotePresentationError('Local presentation target must be a file work.');
    }

    $snapshotTarget = mcpRemotePresentationSnapshotUrl($sourceUrl);
    if (isset($snapshotTarget['error'])) {
        return mcpRemotePresentationError((string) $snapshotTarget['error']);
    }
    $response = mcpRemoteHttpGet((string) $snapshotTarget['url'], ['Accept: application/json']);
    $status = (int) ($response['status'] ?? 0);
    $body = (string) ($response['body'] ?? '');
    if ($status < 200 || $status >= 300) {
        return mcpRemotePresentationError('Remote work snapshot is unavailable.');
    }
    if ($body === '' || strlen($body) > MCP_REMOTE_PRESENTATION_MAX_RESPONSE_BYTES) {
        return mcpRemotePresentationError('Remote work snapshot is empty or too large.');
    }

    $snapshot = json_decode($body, true);
    $context = is_array($snapshot['context'] ?? null) ? $snapshot['context'] : [];
    $work = is_array($context['work'] ?? null) ? $context['work'] : [];
    $layout = is_array($work['layout'] ?? null) ? $work['layout'] : [];
    $presentation = [
        'workHbs' => mcpRemotePresentationSanitizeHbs((string) ($layout['sectionTemplate'] ?? $layout['defaultSectionTemplate'] ?? '')),
        'styleCss' => (string) ($layout['css'] ?? ''),
        'scriptJs' => (string) ($layout['js'] ?? ''),
    ];
    if ($presentation['workHbs'] === '' || strlen($presentation['workHbs']) > MCP_REMOTE_PRESENTATION_MAX_HBS_BYTES) {
        return mcpRemotePresentationError('Remote work does not expose a reusable work presentation.');
    }
    if (strlen($presentation['styleCss']) > MCP_REMOTE_PRESENTATION_MAX_ASSET_BYTES || strlen($presentation['scriptJs']) > MCP_REMOTE_PRESENTATION_MAX_ASSET_BYTES) {
        return mcpRemotePresentationError('Remote presentation CSS or JS is too large.');
    }

    $remotePath = trim((string) ($snapshot['meta']['path'] ?? $snapshotTarget['path'] ?? ''), "/\\");
    $remote = [
        'title' => trim((string) ($snapshot['meta']['title'] ?? $context['title'] ?? basename($remotePath))),
        'path' => $remotePath,
        'extension' => strtolower(pathinfo($remotePath, PATHINFO_EXTENSION)),
        'mimeType' => strtolower(trim((string) ($context['mimeType'] ?? ''))),
        'kind' => strtolower(trim((string) ($work['type'] ?? $snapshot['kind'] ?? ''))),
        'url' => $sourceUrl,
    ];
    $local = [
        'path' => $path,
        'extension' => strtolower(pathinfo($localFile, PATHINFO_EXTENSION)),
        'mimeType' => strtolower((string) (MediaType::detectMimeType($localFile, basename($localFile)) ?? '')),
        'kind' => strtolower(MediaType::classifyExtension(basename($localFile))),
    ];
    $compatibility = mcpRemotePresentationCompatibility($local, $remote);
    $safetyIssues = mcpRemotePresentationSafetyIssues($presentation['styleCss'], $presentation['scriptJs']);
    $compatible = $compatibility['compatible'] && $safetyIssues === [];
    $targetDir = dirname($localFile);
    $targetFile = basename($localFile);

    if ($action === 'copy') {
        if (!$compatible) {
            return mcpRemotePresentationError('Remote work presentation is not safe and compatible.', [
                'matches' => $compatibility['matches'],
                'safetyIssues' => $safetyIssues,
            ]);
        }
        return mcpRemotePresentationApply(
            $targetDir,
            $targetFile,
            $presentation,
            $remote,
            (bool) ($input['confirmReplace'] ?? false)
        );
    }
    if ($action !== 'inspect') {
        return mcpRemotePresentationError('Unsupported presentation action.');
    }

    return [
        'ok' => true,
        'route' => 'remote-work-presentation',
        'compatible' => $compatible,
        'matches' => $compatibility['matches'],
        'safetyIssues' => $safetyIssues,
        'local' => $local,
        'remote' => $remote,
        'presentation' => $presentation,
        'presentationBytes' => [
            'work.hbs' => strlen($presentation['workHbs']),
            'style.css' => strlen($presentation['styleCss']),
            'script.js' => strlen($presentation['scriptJs']),
        ],
        'localPresentation' => mcpRemotePresentationLocalState($targetDir, $targetFile),
    ];
}
