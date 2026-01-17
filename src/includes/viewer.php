<?php
/**
 * Viewer partial: renders different templates for images, videos, links, and other files.
 */

function cmsJsonResponse(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function cmsReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function cmsResolveTarget(string $rootDir, string $relativePath): ?array
{
    $trimmed = trim($relativePath, "/\\");
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        $resolved = realpath($base);
        if ($resolved === false) {
            return null;
        }
        return [
            'type' => 'folder',
            'dir' => $resolved,
        ];
    }
    $candidate = realpath($base . DIRECTORY_SEPARATOR . $trimmed);
    if ($candidate === false) {
        return null;
    }
    if (strpos($candidate, $base) !== 0) {
        return null;
    }
    if (is_dir($candidate)) {
        return [
            'type' => 'folder',
            'dir' => $candidate,
        ];
    }
    if (is_file($candidate)) {
        return [
            'type' => 'file',
            'dir' => dirname($candidate),
            'file' => basename($candidate),
            'path' => $candidate,
        ];
    }
    return null;
}

function cmsLoadEnv(string $rootDir): array
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

function cmsEnvValue(array $env, string $key): ?string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $env[$key] ?? null;
}

function cmsHttpPost(string $url, array $headers, array $payload): array
{
    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => json_encode($payload),
            'timeout' => 20,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
    }
    return [
        'ok' => $response !== false && $status < 400,
        'status' => $status,
        'body' => $response !== false ? $response : '',
    ];
}

function cmsHandleEditAction(): void
{
    $action = $_GET['edit'] ?? '';
    if (!in_array($action, ['config', 'save', 'prompt'], true)) {
        return;
    }

    $rootDir = getcwd();
    $allowFile = $rootDir . DIRECTORY_SEPARATOR . '.edit.allow';
    if (!is_file($allowFile)) {
        cmsJsonResponse([
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ]);
    }

    if (!class_exists('PoffConfig')) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ]);
    }

    $data = ($action === 'save' || $action === 'prompt') ? cmsReadJsonBody() : [];
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $target = cmsResolveTarget($rootDir, $path);
    if ($target === null) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ]);
    }
    $targetType = $target['type'];
    $targetDir = $target['dir'];
    $targetFile = $target['file'] ?? null;

    if ($targetType === 'file') {
        $config = PoffConfig::ensureFileConfig($targetDir, (string) $targetFile);
    } else {
        $config = PoffConfig::ensure($targetDir);
    }

    if ($action === 'config') {
        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'config' => $config,
        ]);
    }

    if ($action === 'save') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Save requires POST.',
            ], 405);
        }

        if (array_key_exists('title', $data)) {
            $config['title'] = trim((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $config['description'] = trim((string) $data['description']);
        }
        if (array_key_exists('link', $data)) {
            $link = trim((string) $data['link']);
            if ($link !== '') {
                $config['link'] = $link;
            } else {
                unset($config['link']);
            }
        }
        if (array_key_exists('url', $data)) {
            $url = trim((string) $data['url']);
            if ($url !== '') {
                $config['url'] = $url;
            } else {
                unset($config['url']);
            }
        }

        $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
        $hasWorkType = false;
        $workType = '';
        if (isset($data['work']) && is_array($data['work']) && array_key_exists('type', $data['work'])) {
            $workType = trim((string) $data['work']['type']);
            $hasWorkType = true;
        } elseif (array_key_exists('work_type', $data)) {
            $workType = trim((string) $data['work_type']);
            $hasWorkType = true;
        }
        if ($hasWorkType && $workType !== '') {
            $work['type'] = $workType;
        }

        $layoutPayload = isset($data['layout']) && is_array($data['layout']) ? $data['layout'] : null;
        $layoutMode = '';
        $layoutModel = null;
        $layoutTemplateProvided = false;
        $layoutTemplate = null;
        $hasLayoutUpdate = false;

        if (is_array($layoutPayload)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) ($layoutPayload['mode'] ?? ''));
            $layoutModel = $layoutPayload['model'] ?? null;
            if (array_key_exists('template', $layoutPayload)) {
                $layoutTemplateProvided = true;
                $layoutTemplate = (string) $layoutPayload['template'];
            }
        }

        if (array_key_exists('layout_mode', $data)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) $data['layout_mode']);
        }
        if (array_key_exists('layout_model', $data)) {
            $hasLayoutUpdate = true;
            $layoutModel = $data['layout_model'];
        }
        if (array_key_exists('layout_template', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layout_template'];
        }
        if (array_key_exists('layoutTemplate', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layoutTemplate'];
        }

        $workLayout = '';
        if (array_key_exists('work_layout', $data)) {
            $workLayout = trim((string) $data['work_layout']);
        }

        if ($hasLayoutUpdate) {
            $layoutValue = $work['layout'] ?? null;
            $layout = is_array($layoutValue) ? $layoutValue : [];
            if (is_string($layoutValue) && $layoutValue !== '') {
                $layout['mode'] = $layoutValue;
            }
            if ($layoutMode !== '') {
                $layout['mode'] = $layoutMode;
            }
            if ($layoutTemplateProvided) {
                $layout['template'] = $layoutTemplate;
            }
            if (is_string($layoutModel) && $layoutModel !== '') {
                $layout['model'] = $layoutModel;
            }
            $work['layout'] = $layout;
        } elseif ($workLayout !== '') {
            $work['layout'] = $workLayout;
        }

        $config['work'] = $work;

        if ($targetType === 'folder') {
            $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
            $hasTreeUpdate = array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data);
            if ($hasTreeUpdate && is_array($config['tree'] ?? null)) {
                $visibleKeys = [];
                if (is_array($treeVisible)) {
                    foreach ($treeVisible as $key) {
                        if (is_scalar($key)) {
                            $visibleKeys[(string) $key] = true;
                        }
                    }
                }
                foreach ($config['tree'] as &$item) {
                    $key = $item['path'] ?? $item['name'] ?? null;
                    if ($key === null) {
                        continue;
                    }
                    $item['visible'] = isset($visibleKeys[$key]);
                }
                unset($item);
            }
        }

        $config['updatedAt'] = date('c');
        if ($targetType === 'folder') {
            $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
        }
        $configPath = $targetType === 'file'
            ? PoffConfig::fileConfigPath($targetDir, (string) $targetFile)
            : PoffConfig::configPath($targetDir);
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Failed to encode config JSON.',
            ], 500);
        }
        file_put_contents($configPath, $encoded);

        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'saved' => true,
            'config' => $config,
        ]);
    }

    if ($action === 'prompt') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Prompt requires POST.',
            ], 405);
        }

        $provider = strtolower((string) ($data['provider'] ?? 'local'));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $apiKey = trim((string) ($data['apiKey'] ?? ''));
        $history = is_array($data['history'] ?? null) ? $data['history'] : [];

        if ($prompt === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Missing prompt.',
            ]);
        }

        $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $systemPrompt = 'You are a template generator. Return only the template string. Do not wrap in code fences.';
        $historyText = '';
        foreach ($history as $msg) {
            if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            $role = strtolower((string) $msg['role']);
            $content = trim((string) $msg['content']);
            if ($content === '') {
                continue;
            }
            $historyText .= strtoupper($role) . ": " . $content . "\n";
        }
        $userPrompt = "Config JSON:\n" . $configJson . "\n\n" . $historyText . "USER: " . $prompt;

        $env = cmsLoadEnv($rootDir);
        $template = '';
        $usedModel = $model;

        if ($provider === 'openai') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'OPENAI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'OpenAI API key not set.',
                ]);
            }
            if ($usedModel === '') {
                $usedModel = 'gpt-4o-mini';
            }
            $payload = [
                'model' => $usedModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
            ];
            $response = cmsHttpPost('https://api.openai.com/v1/chat/completions', [
                'Authorization: Bearer ' . $key,
            ], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'OpenAI request failed.',
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        } elseif ($provider === 'gemini') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'GEMINI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Gemini API key not set.',
                ]);
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
                        ],
                    ],
                ],
            ];
            $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($usedModel), $key);
            $response = cmsHttpPost($url, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Gemini request failed.',
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
        } else {
            if ($endpoint === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Local endpoint URL missing.',
                ]);
            }
            $payload = [
                'prompt' => $prompt,
                'history' => $history,
                'config' => $config,
                'instruction' => $systemPrompt,
            ];
            $response = cmsHttpPost($endpoint, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Local endpoint request failed.',
                ]);
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

        $template = trim($template);
        if ($template === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Template was empty.',
            ]);
        }

        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'provider' => $provider,
            'model' => $usedModel,
            'template' => $template,
        ]);
    }
}

cmsHandleEditAction();

function sanitizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');
    return $path;
}

function detectFileType(string $path): string
{
    return MediaType::classifyExtension(basename($path));
}

function renderViewer(string $baseDir, string $requestedPath): void
{
    $relativePath = sanitizeRelativePath($requestedPath);

    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        http_response_code(400);
        echo 'Invalid file path.';
        return;
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo 'File not found.';
        return;
    }

    // Ensure per-file config under .works and load it for work/model data
    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }

    $type = detectFileType($fullPath);
    $mimeType = MediaType::detectMimeType($fullPath, basename($fullPath));
    $workDefaults = Worktype::definition($type, $mimeType);
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    $work = array_merge($workDefaults, $workData);

    $linkUrl = null;
    if ($type === 'link') {
        $linkUrl = extractLinkFileUrl($fullPath);
    }

    $safePath = htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars(basename($relativePath), ENT_QUOTES, 'UTF-8');

    $safeLinkUrl = $linkUrl ? htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') : '';

    $bodyContent = Worktype::render($type, [
        'safePath' => $safePath,
        'safeName' => $safeName,
        'safeLinkUrl' => $safeLinkUrl,
        'work' => $work,
    ]);

    $descriptionHtml = '';
    if (!empty($fileConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($fileConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer - {$safeName}</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #0b1021;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        header {
            padding: 12px 16px;
            background: #111827;
            border-bottom: 1px solid #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        header .meta {
            display: flex;
            flex-direction: column;
        }
        header .meta .name {
            font-weight: 600;
        }
        header .meta .path {
            font-size: 12px;
            color: #9ca3af;
        }
        header a {
            color: #93c5fd;
            text-decoration: none;
            font-size: 14px;
        }
        header a:hover { text-decoration: underline; }
        .viewer {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0b1021;
            overflow: hidden;
        }
        .viewer img, .viewer video, .viewer iframe {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,0.45);
            border-radius: 6px;
            background: #111827;
        }

        .viewer video, .viewer iframe {
            width: 100%;
            height: 100%;

        }

       .viewer iframe {
          background: #c3cddbff;

        }

        .work-description {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            padding: 12px 14px;
            background: rgba(17, 24, 39, 0.6);
            color: #e5e7eb;
            border-radius: 16px;
            backdrop-filter: blur(8px);
            margin: 0;
            max-width: 70%;
            line-height: 1.4;
            box-shadow: 0 20px 40px rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.08);
        }

        .message {
            padding: 24px;
            text-align: center;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <header>
        <div class="meta">
            <div class="name">{$safeName} <span style="opacity:0.7;font-size:12px;">({$type})</span></div>
            <div class="path">{$safePath}</div>
        </div>
        <div>
            <a href="{$safePath}" target="_blank" rel="noopener">Open Raw</a>
        </div>
    </header>
    <div class="viewer">
        {$bodyContent}
        {$descriptionHtml}
    </div>
</body>
</html>
HTML;

    echo $html;
}
