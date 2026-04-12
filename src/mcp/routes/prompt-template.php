<?php
declare(strict_types=1);

const MCP_PROMPT_HTTP_TIMEOUT_SECONDS = 90;

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

    $candidate = $trimmed;
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/si', $trimmed, $matches)) {
        $candidate = trim((string) $matches[1]);
    }

    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        return ['template' => $trimmed];
    }

    $template = '';
    if (isset($decoded['template'])) {
        $template = trim((string) $decoded['template']);
    } elseif (isset($decoded['content'])) {
        $template = trim((string) $decoded['content']);
    }

    if ($template === '') {
        return ['template' => $trimmed];
    }

    $result = ['template' => $template];
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
    $headerLines = array_merge(['Content-Type: application/json'], $headers);
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
    return $isFile
        ? '?view=1&file=' . rawurlencode($relativePath)
        : '?view=1&path=' . rawurlencode($relativePath);
}

function mcpPromptAssetUrl(string $relativePath, bool $isFile): string
{
    if (!$isFile) {
        return '?path=' . rawurlencode($relativePath);
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}

function mcpPromptTemplateTarget(string $relativePath): string
{
    return PoffConfig::relativeLayoutPath($relativePath, false) . '/works.hbs';
}

function mcpPromptLayoutTemplateTarget(string $relativePath): string
{
    return PoffConfig::relativeLayoutPath($relativePath, false) . '/template.hbs';
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

    $relativePath = trim((string) ($item['path'] ?? $name), "/\\");
    if ($relativePath === '') {
        $relativePath = $name;
    }
    if ($basePath !== '') {
        $normalizedBase = trim($basePath, "/\\");
        if (!str_starts_with($relativePath, $normalizedBase . '/') && $relativePath !== $normalizedBase) {
            $relativePath = $normalizedBase . '/' . ltrim($relativePath, "/\\");
        }
    }

    $type = (string) ($item['type'] ?? 'file');
    $isFolder = $type === 'folder';
    $kind = mcpPromptRefKind($name, $type);
    $pageLink = mcpPromptViewerUrl($relativePath, !$isFolder);
    $assetUrl = mcpPromptAssetUrl($relativePath, !$isFolder);

    return [
        'name' => $name,
        'title' => (string) ($item['title'] ?? $name),
        'slug' => (string) ($item['slug'] ?? PoffConfig::slugify($name)),
        'type' => $type,
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
}

function mcpBuildPromptContext(string $relativePath, array $config): array
{
    $normalizedPath = trim($relativePath, "/\\");
    $currentName = (string) ($config['folderName'] ?? basename($normalizedPath));
    $currentPageLink = mcpPromptViewerUrl($normalizedPath, false);
    $currentAssetUrl = mcpPromptAssetUrl($normalizedPath, false);

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
    foreach (['name', 'title', 'type', 'kind', 'path', 'pageLink', 'srcUrl', 'isFolder', 'isFile', 'visible'] as $key) {
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
                foreach (['name', 'mode', 'value', 'engine', 'section', 'storage', 'directory', 'defaultDirectory', 'sectionDirectory', 'phpTemplate'] as $layoutKey) {
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
        'current' => $context['current'] ?? [],
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
    $allowFile = $opts['allowFile'] ?? ($rootDir . DIRECTORY_SEPARATOR . '.edit.allow');
    $allowed = is_file($allowFile);

    if (!$allowed) {
        return [
            'route' => 'prompt-template',
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ];
    }

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
    $promptContext = mcpBuildPromptContext((string) $path, $config);
    $configJson = json_encode(mcpPromptCompactConfig($config), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $promptContextJson = json_encode(mcpPromptCompactContext($promptContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $responseFormatInstruction = implode("\n", [
        'Response format: return strict JSON.',
        'Required key: "template" with the wrapped inner HBS partial string.',
        'Optional keys: "title", "description", and "work".',
        'If the user requests work.* updates such as autoplay, loop, muted, poster, type, or layout, include them under "work".',
        'Example: {"template":"<div>{{title}}</div>","work":{"autoplay":true}}',
    ]);
    $systemPrompt = implode("\n", [
        'You are a Handlebars (HBS) template generator for this single-page CMS.',
        'Return one HBS template string for the wrapped inner section partial rendered through LightnCandy.',
        'The prompt edits the wrapped content partial, not the outer layout wrapper. Save target is works.hbs inside the active folder layout folder.',
        'Keep the current outer layout chain active unless the user explicitly changes layout mode separately. Do not return the outer wrapper template here.',
        'Default layout technique: the outer layout stays in template.hbs and wraps {{> works}} for folders or {{> work}} for files.',
        'Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.',
        'Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.',
        'Prompt context JSON includes current.templateTarget for the wrapped partial save target and current.layoutTemplateTarget for the outer wrapper path. Edit the wrapped partial target by default.',
        'Prompt context JSON includes resolved refs for the current folder contents. Use those refs directly instead of inventing paths.',
    ]);
    $historyText = mcpPromptHistoryText($history);
    $userPrompt = "Config JSON:\n" . $configJson . "\n\nPrompt context JSON:\n" . $promptContextJson . "\n\n" . $responseFormatInstruction . "\n\n" . $historyText . "USER: " . $prompt;
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
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => 'Local endpoint URL missing.',
            ];
        }
        $payload = [
            'prompt' => $prompt,
            'history' => $history,
            'config' => $config,
            'instruction' => $systemPrompt,
            'image' => $image,
        ];
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
            if (isset($decoded['template'])) {
                $template = (string) $decoded['template'];
            } elseif (isset($decoded['content'])) {
                $template = (string) $decoded['content'];
            }
        }
        if ($template === '') {
            $template = trim((string) $response['body']);
        }
    }

    $parsedResult = mcpParsePromptModelResult($template);
    $templateText = trim((string) ($parsedResult['template'] ?? ''));
    if ($templateText === '') {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'Template was empty.',
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
