<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/viewer/link-targets.php';
require_once __DIR__ . '/../../includes/edit-mode.php';
require_once __DIR__ . '/../../includes/prompt-template-sanitize.php';

const MCP_PROMPT_HTTP_TIMEOUT_SECONDS = 300;

function mcpPromptResponseCandidates(string $raw): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return [];
    }

    $candidates = [$trimmed];

    if (preg_match_all('/```(?:[a-z0-9_-]+)?\s*([\s\S]*?)```/i', $trimmed, $matches)) {
        foreach ($matches[1] as $match) {
            $candidate = trim((string) $match);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }

    $firstBrace = strpos($trimmed, '{');
    $lastBrace = strrpos($trimmed, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $candidate = trim(substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1));
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
    }

    $normalized = [];
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $normalized, true)) {
            continue;
        }
        $normalized[] = $candidate;
    }

    return $normalized;
}

function mcpPromptDecodeLooseString(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $decoded = json_decode($trimmed, true);
    if (is_string($decoded)) {
        return $decoded;
    }

    if ($trimmed[0] === '"') {
        $trimmed = substr($trimmed, 1);
    }
    if ($trimmed !== '' && substr($trimmed, -1) === '"') {
        $trimmed = substr($trimmed, 0, -1);
    }

    return stripcslashes($trimmed);
}

function mcpPromptExtractLooseScalarField(string $payload, string $key): ?string
{
    $knownKeys = [
        'template',
        'css',
        'style',
        'js',
        'script',
        'work',
        'title',
        'description',
        'model',
        'content',
        'response',
    ];
    $otherKeys = array_values(array_filter($knownKeys, static fn (string $candidate): bool => $candidate !== $key));
    $otherKeysPattern = implode('|', array_map(static fn (string $candidate): string => preg_quote($candidate, '/'), $otherKeys));
    $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*([\s\S]*?)(?=\s*(?:,\s*"(?:' . $otherKeysPattern . ')"\s*:|\}\s*$|$))/i';
    if (preg_match($pattern, $payload, $matches) !== 1) {
        return null;
    }

    return mcpPromptDecodeLooseString((string) $matches[1]);
}

function mcpPromptDecodeLoosePayload(string $raw): ?array
{
    $candidates = mcpPromptResponseCandidates($raw);
    foreach ($candidates as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    foreach ($candidates as $candidate) {
        if (!preg_match('/"(template|css|style|js|script|title|description|model|content|response)"\s*:/i', $candidate)) {
            continue;
        }

        $result = [];
        foreach (['template', 'css', 'style', 'js', 'script', 'title', 'description', 'model', 'content', 'response'] as $key) {
            $value = mcpPromptExtractLooseScalarField($candidate, $key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }

        if ($result !== []) {
            return $result;
        }
    }

    return null;
}

function mcpPromptImagePayload(array $data): ?array
{
    $image = $data['image'] ?? null;
    if (!is_array($image)) {
        return null;
    }
    $dataUrl = isset($image['dataUrl']) ? trim((string) $image['dataUrl']) : '';
    if ($dataUrl === '' || !preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#i', $dataUrl, $matches)) {
        return null;
    }

    return [
        'name' => trim((string) ($image['name'] ?? 'clipboard-image.png')),
        'mimeType' => strtolower($matches[1]),
        'dataUrl' => $dataUrl,
        'base64' => $matches[2],
    ];
}

function mcpParsePromptModelResult(string $raw): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return ['template' => ''];
    }

    $decoded = mcpPromptDecodeLoosePayload($trimmed);
    if (!is_array($decoded)) {
        return ['template' => cmsSanitizePromptTemplateForTarget($trimmed, false)];
    }

    $template = '';
    $modelReturnedReasoningOnly = false;
    $extractedFromEnvelope = false;
    if (isset($decoded['choices'][0]['message']['content'])) {
        $extractedFromEnvelope = true;
        $content = $decoded['choices'][0]['message']['content'];
        if (is_string($content)) {
            $template = trim($content);
        } elseif (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $parts[] = trim($part);
                    continue;
                }
                if (is_array($part) && isset($part['text']) && is_scalar($part['text'])) {
                    $parts[] = trim((string) $part['text']);
                }
            }
            $template = trim(implode("\n", array_filter($parts)));
        }
    } elseif (isset($decoded['choices'][0]['text']) && is_scalar($decoded['choices'][0]['text'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['choices'][0]['text']);
    } elseif (isset($decoded['message']['content']) && is_scalar($decoded['message']['content'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['message']['content']);
    } elseif (isset($decoded['response']) && is_scalar($decoded['response'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['response']);
    } elseif (isset($decoded['template'])) {
        $template = trim((string) $decoded['template']);
    } elseif (isset($decoded['content'])) {
        $template = trim((string) $decoded['content']);
    }

    if ($extractedFromEnvelope && $template !== '' && $template !== $trimmed) {
        return mcpParsePromptModelResult($template);
    }

    if ($extractedFromEnvelope && $template === '') {
        return ['template' => ''];
    }

    if ($template === '') {
        return ['template' => ''];
    }

    $result = ['template' => cmsSanitizePromptTemplateForTarget($template, false)];
    foreach (['title', 'description', 'model'] as $key) {
        if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
            $result[$key] = trim((string) $decoded[$key]);
        }
    }
    if (isset($decoded['work']) && is_array($decoded['work'])) {
        $result['work'] = $decoded['work'];
    }

    return $result;
}

function mcpPromptResolvePath(string $rootDir, string $relativePath): ?string
{
    $trimmed = trim($relativePath, "/\\");
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        return realpath($base) ?: null;
    }
    $candidate = realpath($base . DIRECTORY_SEPARATOR . $trimmed);
    if ($candidate === false) {
        return null;
    }
    if (strpos($candidate, $base) !== 0) {
        return null;
    }
    if (!is_dir($candidate)) {
        return null;
    }
    return $candidate;
}

function mcpPromptReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function mcpPromptLoadEnv(string $rootDir): array
{
    $envPath = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return [];
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            $env[$key] = $value;
        }
    }
    return $env;
}

function mcpPromptEnvValue(array $env, string $key): ?string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $env[$key] ?? null;
}

function mcpPromptHttpPost(string $url, array $headers, array $payload): array
{
    $override = $GLOBALS['__poff_mcp_prompt_http_post'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers, $payload);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(MCP_PROMPT_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) MCP_PROMPT_HTTP_TIMEOUT_SECONDS);
    }

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($payload),
                'timeout' => MCP_PROMPT_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
    $status = 0;
    $statusLine = '';
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
        $statusLine = (string) $http_response_header[0];
    }
    return [
        'ok' => $response !== false && $status >= 200 && $status < 400,
        'status' => $status,
        'statusLine' => $statusLine,
        'body' => $response !== false ? $response : '',
    ];
}

function mcpPromptIsOpenAiCompatibleEndpoint(string $url): bool
{
    $normalized = strtolower(trim($url));
    return $normalized !== '' && str_contains($normalized, '/v1/chat/completions');
}

function mcpPromptNormalizeErrorText(string $text): string
{
    $normalized = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/sk-[A-Za-z0-9_-]{12,}/', 'sk-***', $normalized) ?? $normalized;
    $normalized = preg_replace('/AIza[0-9A-Za-z_-]{12,}/', 'AIza***', $normalized) ?? $normalized;
    $normalized = trim($normalized, " \t\n\r\0\x0B.:");
    if ($normalized === '') {
        return '';
    }
    if (strlen($normalized) > 220) {
        $normalized = substr($normalized, 0, 217) . '...';
    }

    return $normalized;
}

function mcpPromptExtractErrorDetail(mixed $value): string
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $detail = mcpPromptExtractErrorDetail($decoded);
            if ($detail !== '') {
                return $detail;
            }
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $trimmed, $matches)) {
            return mcpPromptNormalizeErrorText((string) $matches[1]);
        }

        return mcpPromptNormalizeErrorText($trimmed);
    }

    if (!is_array($value)) {
        return '';
    }

    foreach (['message', 'detail'] as $key) {
        if (array_key_exists($key, $value)) {
            $detail = mcpPromptExtractErrorDetail($value[$key]);
            if ($detail !== '') {
                return $detail;
            }
        }
    }

    if (array_key_exists('error', $value)) {
        $detail = mcpPromptExtractErrorDetail($value['error']);
        if ($detail !== '') {
            return $detail;
        }
    }

    if (array_key_exists('details', $value)) {
        $detail = mcpPromptExtractErrorDetail($value['details']);
        if ($detail !== '') {
            return $detail;
        }
    }

    foreach ($value as $item) {
        $detail = mcpPromptExtractErrorDetail($item);
        if ($detail !== '') {
            return $detail;
        }
    }

    return '';
}

function mcpPromptFormatHttpError(string $label, array $response): string
{
    $status = (int) ($response['status'] ?? 0);
    $detail = mcpPromptExtractErrorDetail($response['body'] ?? '');
    $message = $label . ' request failed';
    if ($status > 0) {
        $message .= ' (HTTP ' . $status . ')';
    }
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }

    return $message . '.';
}

function mcpPromptViewerUrl(string $relativePath, bool $isFile): string
{
    return cmsBuildViewerHrefFromRelativePath($relativePath, $isFile);
}

function mcpPromptAssetUrl(string $relativePath, bool $isFile): string
{
    return cmsBuildAssetHrefFromRelativePath($relativePath, $isFile);
}

function mcpPromptTemplateTarget(string $relativePath): string
{
    return PoffConfig::relativeLayoutPath($relativePath, false) . '/works.hbs';
}

function mcpPromptLayoutTemplateTarget(string $relativePath): string
{
    return PoffConfig::relativeLayoutPath($relativePath, false) . '/template.hbs';
}

function mcpPromptOuterWrapperReference(array $layoutValue): array
{
    $layoutName = trim((string) ($layoutValue['name'] ?? Worktype::defaultLayoutName()));
    if ($layoutName === '') {
        $layoutName = Worktype::defaultLayoutName();
    }

    $storage = trim((string) ($layoutValue['storage'] ?? ''));
    if ($storage === '') {
        $storage = 'default';
    }

    $templateName = $layoutName;
    $templateCandidate = Worktype::template($templateName);
    if (!is_string($templateCandidate) || $templateCandidate === '') {
        $templateName = Worktype::defaultLayoutName();
        $templateCandidate = Worktype::template($templateName);
    }

    $template = '';
    if (isset($layoutValue['template']) && is_string($layoutValue['template']) && trim($layoutValue['template']) !== '') {
        $template = $layoutValue['template'];
    } else {
        $template = (string) ($templateCandidate ?? '');
    }

    $css = '';
    if (isset($layoutValue['css']) && is_string($layoutValue['css']) && trim($layoutValue['css']) !== '') {
        $css = $layoutValue['css'];
    } else {
        $css = (string) (Worktype::layoutBundleAsset($templateName, 'style.css') ?? '');
    }

    $js = '';
    if (isset($layoutValue['js']) && is_string($layoutValue['js']) && trim($layoutValue['js']) !== '') {
        $js = $layoutValue['js'];
    } else {
        $js = (string) (Worktype::layoutBundleAsset($templateName, 'script.js') ?? '');
    }

    return [
        'name' => $layoutName,
        'storage' => $storage,
        'sectionPartial' => 'works',
        'source' => $storage === 'filesystem'
            ? 'resolved active wrapper'
            : ($storage === 'inline' ? 'inline wrapper config' : 'bundled default wrapper reference'),
        'template' => $template,
        'css' => $css,
        'js' => $js,
    ];
}

function mcpPromptRefKind(string $name, string $type): string
{
    if ($type === 'folder') {
        return 'folder';
    }

    return MediaType::classifyExtension($name);
}

function mcpBuildPromptRef(string $basePath, array $item): ?array
{
    $name = trim((string) ($item['name'] ?? $item['path'] ?? ''));
    if ($name === '') {
        return null;
    }

    $configuredType = strtolower(trim((string) ($item['type'] ?? 'file')));
    $explicitLinkTarget = cmsConfiguredTreeLinkTarget($item);
    $relativePath = cmsConfiguredTreeDisplayPath($basePath, $item, $name);
    if ($relativePath === '') {
        $relativePath = cmsConfiguredTreeFilesystemRelativePath($basePath, $item, $name);
    }

    $isFolder = $configuredType === 'folder';
    $kind = $isFolder
        ? 'folder'
        : (($configuredType === 'link' || $explicitLinkTarget !== '') ? 'link' : mcpPromptRefKind($name, $configuredType));
    $pageLink = $explicitLinkTarget !== ''
        ? $explicitLinkTarget
        : mcpPromptViewerUrl($relativePath, !$isFolder);
    $assetUrl = $explicitLinkTarget !== ''
        ? $explicitLinkTarget
        : mcpPromptAssetUrl($relativePath, !$isFolder);
    $linkUrl = cmsConfiguredTreeExternalLinkUrl($item);

    $result = [
        'name' => $name,
        'title' => (string) ($item['title'] ?? $name),
        'slug' => (string) ($item['slug'] ?? PoffConfig::slugify($name)),
        'type' => $isFolder ? 'folder' : 'file',
        'kind' => $kind,
        'path' => $relativePath,
        'pageLink' => $pageLink,
        'pageUrl' => $pageLink,
        'workUrl' => $pageLink,
        'viewUrl' => $pageLink,
        'viewerHref' => $pageLink,
        'assetUrl' => $assetUrl,
        'assetLink' => $assetUrl,
        'rawHref' => $assetUrl,
        'srcUrl' => $assetUrl,
        'sourceUrl' => $assetUrl,
        'isFolder' => $isFolder,
        'isFile' => !$isFolder,
        'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
    ];

    if ($linkUrl !== '') {
        $result['linkUrl'] = $linkUrl;
    }

    return $result;
}

function mcpBuildPromptContext(string $relativePath, array $config): array
{
    $normalizedPath = trim($relativePath, "/\\");
    $currentName = (string) ($config['folderName'] ?? basename($normalizedPath));
    $currentPageLink = mcpPromptViewerUrl($normalizedPath, false);
    $currentAssetUrl = mcpPromptAssetUrl($normalizedPath, false);
    $layoutValue = is_array($config['work'] ?? null) && is_array($config['work']['layout'] ?? null)
        ? $config['work']['layout']
        : [];

    $context = [
        'current' => [
            'targetType' => 'folder',
            'sectionPartial' => 'works',
            'name' => $currentName,
            'path' => $normalizedPath,
            'pageLink' => $currentPageLink,
            'pageUrl' => $currentPageLink,
            'workUrl' => $currentPageLink,
            'viewUrl' => $currentPageLink,
            'viewerHref' => $currentPageLink,
            'assetUrl' => $currentAssetUrl,
            'assetLink' => $currentAssetUrl,
            'rawHref' => $currentAssetUrl,
            'srcUrl' => $currentAssetUrl,
            'sourceUrl' => $currentAssetUrl,
            'templateTarget' => mcpPromptTemplateTarget($normalizedPath),
            'layoutTemplateTarget' => mcpPromptLayoutTemplateTarget($normalizedPath),
            'outerWrapper' => mcpPromptOuterWrapperReference($layoutValue),
        ],
        'items' => [],
        'allItems' => [],
        'allFiles' => [],
        'allFolders' => [],
        'allImages' => [],
        'allVideos' => [],
        'allAudio' => [],
        'allPdfs' => [],
        'allTexts' => [],
        'allLinks' => [],
        'allOther' => [],
    ];

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ref = mcpBuildPromptRef($normalizedPath, $item);
        if ($ref === null) {
            continue;
        }
        $context['items'][] = $ref;
        $context['allItems'][] = $ref;
        if ($ref['isFolder']) {
            $context['allFolders'][] = $ref;
            continue;
        }

        $context['allFiles'][] = $ref;
        switch ($ref['kind']) {
            case 'image':
                $context['allImages'][] = $ref;
                break;
            case 'video':
                $context['allVideos'][] = $ref;
                break;
            case 'audio':
                $context['allAudio'][] = $ref;
                break;
            case 'pdf':
                $context['allPdfs'][] = $ref;
                break;
            case 'text':
                $context['allTexts'][] = $ref;
                break;
            case 'link':
                $context['allLinks'][] = $ref;
                break;
            default:
                $context['allOther'][] = $ref;
                break;
        }
    }

    return $context;
}

function mcpPromptTrimText(string $text, int $maxLength = 240): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return substr($normalized, 0, max(0, $maxLength - 3)) . '...';
}

function mcpPromptCompactRef(array $ref): array
{
    $compact = [];
    foreach (['name', 'title', 'type', 'kind', 'path', 'pageLink', 'linkUrl', 'srcUrl', 'isFolder', 'isFile', 'visible'] as $key) {
        if (!array_key_exists($key, $ref)) {
            continue;
        }
        $value = $ref[$key];
        $compact[$key] = is_string($value) ? mcpPromptTrimText($value, 160) : $value;
    }

    return $compact;
}

function mcpPromptCompactConfig(array $config): array
{
    $summary = [];

    foreach (['title', 'description', 'folderName', 'updatedAt', 'treeHash'] as $key) {
        if (array_key_exists($key, $config) && is_scalar($config[$key])) {
            $summary[$key] = is_string($config[$key])
                ? mcpPromptTrimText((string) $config[$key], 240)
                : $config[$key];
        }
    }

    $work = is_array($config['work'] ?? null) ? $config['work'] : [];
    if ($work !== []) {
        $summary['work'] = [];
        foreach ($work as $key => $value) {
            if ($key === 'layout' && is_array($value)) {
                $layoutSummary = [];
                foreach (['name', 'mode', 'value', 'engine', 'section', 'storage', 'directory', 'inheritedDirectory', 'sectionDirectory', 'phpTemplate'] as $layoutKey) {
                    if (array_key_exists($layoutKey, $value) && is_scalar($value[$layoutKey])) {
                        $layoutSummary[$layoutKey] = is_string($value[$layoutKey])
                            ? mcpPromptTrimText((string) $value[$layoutKey], 180)
                            : $value[$layoutKey];
                    }
                }
                foreach (['template', 'sectionTemplate', 'css', 'style', 'js', 'script'] as $layoutKey) {
                    if (isset($value[$layoutKey]) && is_string($value[$layoutKey]) && $value[$layoutKey] !== '') {
                        $layoutSummary[$layoutKey . 'Length'] = strlen($value[$layoutKey]);
                    }
                }
                if (is_array($value['assets'] ?? null)) {
                    $layoutSummary['assetCount'] = count($value['assets']);
                }
                $summary['work']['layout'] = $layoutSummary;
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $summary['work'][$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $summary['work'][$key] = mcpPromptTrimText($value, 180);
            }
        }
    }

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    if ($tree !== []) {
        $sample = [];
        foreach (array_slice($tree, 0, 24) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sample[] = [
                'name' => mcpPromptTrimText((string) ($item['name'] ?? $item['path'] ?? ''), 120),
                'type' => (string) ($item['type'] ?? 'file'),
                'path' => mcpPromptTrimText((string) ($item['path'] ?? ''), 160),
                'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
            ];
        }
        $summary['tree'] = [
            'count' => count($tree),
            'sample' => $sample,
        ];
    }

    return $summary;
}

function mcpPromptCompactContext(array $context): array
{
    $current = is_array($context['current'] ?? null) ? $context['current'] : [];
    if (is_array($current['outerWrapper'] ?? null)) {
        $outerWrapper = [];
        foreach (['name', 'storage', 'sectionPartial', 'source'] as $key) {
            if (array_key_exists($key, $current['outerWrapper'])) {
                $outerWrapper[$key] = $current['outerWrapper'][$key];
            }
        }
        foreach (['template', 'css', 'js'] as $key) {
            if (isset($current['outerWrapper'][$key]) && is_string($current['outerWrapper'][$key]) && $current['outerWrapper'][$key] !== '') {
                $outerWrapper[$key] = mcpPromptTrimText($current['outerWrapper'][$key], 2200);
                $outerWrapper[$key . 'Length'] = strlen($current['outerWrapper'][$key]);
            }
        }
        $current['outerWrapper'] = $outerWrapper;
    }

    $items = array_values(array_filter(array_map(
        static fn(array $ref): array => mcpPromptCompactRef($ref),
        array_slice(is_array($context['items'] ?? null) ? $context['items'] : [], 0, 24)
    )));

    $counts = [
        'items' => count(is_array($context['items'] ?? null) ? $context['items'] : []),
        'files' => count(is_array($context['allFiles'] ?? null) ? $context['allFiles'] : []),
        'folders' => count(is_array($context['allFolders'] ?? null) ? $context['allFolders'] : []),
        'images' => count(is_array($context['allImages'] ?? null) ? $context['allImages'] : []),
        'videos' => count(is_array($context['allVideos'] ?? null) ? $context['allVideos'] : []),
        'audio' => count(is_array($context['allAudio'] ?? null) ? $context['allAudio'] : []),
        'pdfs' => count(is_array($context['allPdfs'] ?? null) ? $context['allPdfs'] : []),
        'texts' => count(is_array($context['allTexts'] ?? null) ? $context['allTexts'] : []),
        'links' => count(is_array($context['allLinks'] ?? null) ? $context['allLinks'] : []),
        'other' => count(is_array($context['allOther'] ?? null) ? $context['allOther'] : []),
    ];

    return [
        'current' => $current,
        'counts' => $counts,
        'items' => $items,
    ];
}

function mcpPromptHistoryText(array $history): string
{
    $recentHistory = array_slice($history, -6);
    $historyText = '';
    foreach ($recentHistory as $msg) {
        if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
            continue;
        }
        $role = strtolower((string) $msg['role']);
        $content = mcpPromptTrimText((string) $msg['content'], 800);
        if ($content === '') {
            continue;
        }
        $historyText .= strtoupper($role) . ": " . $content . "\n";
    }

    return $historyText;
}

function handlePromptTemplate(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = $opts['path'] ?? '';
    $allowFile = $opts['allowFile'] ?? null;
    if (!class_exists('PoffConfig')) {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ];
    }

    $targetDir = mcpPromptResolvePath($rootDir, (string) $path);
    if ($targetDir === null) {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ];
    }

    $allowed = is_string($allowFile) && $allowFile !== ''
        ? is_file($allowFile)
        : cmsEditModeAllowedForDirectory($targetDir, $rootDir);
    if (!$allowed) {
        return [
            'route' => 'prompt-template',
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ];
    }

    $data = mcpPromptReadJsonBody();
    if ($data === []) {
        $data = $_POST;
    }

    $provider = strtolower((string) ($data['provider'] ?? 'local'));
    $prompt = trim((string) ($data['prompt'] ?? ''));
    $model = trim((string) ($data['model'] ?? ''));
    $endpoint = trim((string) ($data['endpoint'] ?? ''));
    $apiKey = trim((string) ($data['apiKey'] ?? ''));
    $history = is_array($data['history'] ?? null) ? $data['history'] : [];
    $image = mcpPromptImagePayload($data);

    if ($prompt === '' && !$image) {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'Missing prompt or image.',
        ];
    }

    $config = PoffConfig::ensure($targetDir);
    $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $systemPrompt = implode("\n", [
        'You are a Handlebars (HBS) template generator for this single-page CMS.',
        'Return one HBS template string for the wrapped inner section partial rendered through LightnCandy.',
        'Save target is work.hbs for files and works.hbs for folders inside the active item layout folder.',
        'Template sources live in .layout and .works layout folders; keep the source files as the authoring target.',
        'Return only the template (no Markdown, no fences).',
        'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
        'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
        'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
        'Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
        'Prompt context JSON current.outerWrapper contains a compact summary of the active outer layout wrapper, with template/css/js excerpts. Use it as structure and styling reference only.',
        'When the current folder is root or otherwise sparse, use current.outerWrapper as the main visual grounding instead of inventing a generic standalone page.',
        'Align your inner partial with the current outer wrapper semantics and class language when useful, but do not return or rewrite the wrapper itself.',
        'Do not return the outer layout wrapper, page shell, navigation chrome, or a full page template.',
        'Never return {{> work}}, {{> works}}, {{> poff-layout}}, {{> filesystem-layout}}, or a poff-default-layout wrapper from this prompt.',
        'Never emit outer shell blocks like <header class="poff-default-layout__header">, <main class="poff-default-layout__main">, footer/nav/sidebar chrome, or wrapper-only include chains from this prompt.',
        'Return only the inner partial content that will be rendered inside the existing layout wrapper.',
        'For layout wrappers that should look consistent for folders and files, put sibling partials in work: {"works.hbs":"folder inner partial","work.hbs":"file inner partial"}.',
        'If a provided item/pageLink/path/linkUrl value already contains a full CMS viewer URL like ?view=1&path=... or ?view=1&file=..., or an external URL, use it verbatim. Never prepend another ?view=1&path= or ?view=1&file= around it.',
        'Configured tree items may be virtual navigation links without a backing local file or folder. Respect their provided pageLink/linkUrl instead of forcing them into a filesystem path.',
        'Tailwind first. Use utility classes for the common layout and visual structure.',
        'Use scoped CSS only for exceptions that are awkward or unreadable as utilities.',
        'Do not embed global CSS, and do not use inline style attributes.',
        'Template sources live in .layout and .works layout folders; keep the source files as the authoring target.',
        'Use static Tailwind utilities from the built app.css vocabulary: flex/grid, spacing, borders, rounded, shadows, slate/white/blue/emerald colors, responsive md/lg/xl variants. Avoid dynamic class names built from Handlebars values because runtime templates cannot trigger a rebuild.',
        'Avoid arbitrary-value utilities like text-[13px], grid-cols-[...], [background:...], and [&_img]:... unless there is no regular utility that works.',
        'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
    ]);
    $promptContext = mcpPromptCompactContext(mcpBuildPromptContext((string) $path, $config));
    $promptContextJson = json_encode($promptContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $historyText = mcpPromptHistoryText($history);
    $userPrompt = "Config JSON:\n" . $configJson . "\n\nPrompt context JSON:\n" . $promptContextJson . "\n\n" . $historyText . "USER: " . $prompt;
    if ($image) {
        $userPrompt .= "\n\nAttached image: " . ($image['name'] ?: 'clipboard-image.png');
    }

    $env = mcpPromptLoadEnv($rootDir);
    $template = '';
    $usedModel = $model;

    if ($provider === 'openai') {
        $key = $apiKey !== '' ? $apiKey : (mcpPromptEnvValue($env, 'OPENAI_API_KEY') ?? '');
        if ($key === '') {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => 'OpenAI API key not set.',
            ];
        }
        if ($usedModel === '') {
            $usedModel = 'gpt-4o-mini';
        }
        $payload = [
            'model' => $usedModel,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $image ? [
                    ['type' => 'text', 'text' => $userPrompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $image['dataUrl']]],
                ] : $userPrompt],
            ],
            'temperature' => 0.4,
        ];
        $response = mcpPromptHttpPost('https://api.openai.com/v1/chat/completions', [
            'Authorization: Bearer ' . $key,
        ], $payload);
        if (!$response['ok']) {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => mcpPromptFormatHttpError('OpenAI', $response),
            ];
        }
        $decoded = json_decode($response['body'], true);
        $template = (string) ($decoded['choices'][0]['message']['content'] ?? '');
    } elseif ($provider === 'gemini') {
        $key = $apiKey !== '' ? $apiKey : (mcpPromptEnvValue($env, 'GEMINI_API_KEY') ?? '');
        if ($key === '') {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => 'Gemini API key not set.',
            ];
        }
        if ($usedModel === '') {
            $usedModel = 'gemini-1.5-flash';
        }
        $promptText = $systemPrompt . "\n\n" . $userPrompt;
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $promptText],
                        ...($image ? [[
                            'inline_data' => [
                                'mime_type' => $image['mimeType'],
                                'data' => $image['base64'],
                            ],
                        ]] : []),
                    ],
                ],
            ],
        ];
        $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($usedModel), $key);
        $response = mcpPromptHttpPost($url, [], $payload);
        if (!$response['ok']) {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => mcpPromptFormatHttpError('Gemini', $response),
            ];
        }
        $decoded = json_decode($response['body'], true);
        $template = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
    } else {
        if ($endpoint === '') {
            $endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
        }
        if ($usedModel === '') {
            $usedModel = 'gemma4';
        }
        if (mcpPromptIsOpenAiCompatibleEndpoint($endpoint)) {
            $messages = [['role' => 'system', 'content' => $systemPrompt]];
            foreach ($history as $message) {
                if (!is_array($message)) {
                    continue;
                }
                $role = strtolower(trim((string) ($message['role'] ?? 'user')));
                $content = trim((string) ($message['content'] ?? ''));
                if ($content === '' || !in_array($role, ['system', 'user', 'assistant'], true)) {
                    continue;
                }
                $messages[] = ['role' => $role, 'content' => $content];
            }
            $messages[] = ['role' => 'user', 'content' => $image ? [
                ['type' => 'text', 'text' => $userPrompt],
                ['type' => 'image_url', 'image_url' => ['url' => $image['dataUrl']]],
            ] : $userPrompt];
            $payload = [
                'model' => $usedModel,
                'messages' => $messages,
                'temperature' => 0.4,
            ];
        } else {
            $payload = [
                'prompt' => $prompt,
                'history' => $history,
                'config' => $config,
                'instruction' => $systemPrompt,
                'image' => $image,
            ];
        }
        $response = mcpPromptHttpPost($endpoint, [], $payload);
        if (!$response['ok']) {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => mcpPromptFormatHttpError('Local endpoint', $response),
            ];
        }
        $decoded = json_decode($response['body'], true);
        if (is_array($decoded)) {
            $message = $decoded['choices'][0]['message'] ?? null;
            if (is_array($message) && array_key_exists('content', $message)) {
                $template = (string) $message['content'];
                $reasoningContent = trim((string) ($message['reasoning_content'] ?? ''));
                $modelReturnedReasoningOnly = trim($template) === '' && $reasoningContent !== '';
            } elseif (isset($decoded['template'])) {
                $template = (string) $decoded['template'];
            } elseif (isset($decoded['content'])) {
                $template = (string) $decoded['content'];
            }
        } elseif ($template === '') {
            $template = trim((string) $response['body']);
        }
    }

    $parsedResult = mcpParsePromptModelResult($template);
    $templateText = trim((string) ($parsedResult['template'] ?? ''));
    if ($templateText === '') {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => $modelReturnedReasoningOnly
                ? 'Model returned reasoning only and no template text. Disable reasoning/thinking in LM Studio or ask the model to return final template text.'
                : 'Template was empty.',
        ];
    }

    $response = [
        'route' => 'prompt-template',
        'allowed' => true,
        'provider' => $provider,
        'model' => $usedModel,
        'template' => $templateText,
    ];
    foreach (['title', 'description', 'work'] as $key) {
        if (array_key_exists($key, $parsedResult)) {
            $response[$key] = $parsedResult[$key];
        }
    }

    return $response;
}
