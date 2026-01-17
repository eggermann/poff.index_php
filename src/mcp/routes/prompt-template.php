<?php
declare(strict_types=1);

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

    if ($prompt === '') {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'Missing prompt.',
        ];
    }

    $config = PoffConfig::ensure($targetDir);
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
                ['role' => 'user', 'content' => $userPrompt],
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
                'error' => 'OpenAI request failed.',
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
                'error' => 'Gemini request failed.',
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
        ];
        $response = mcpPromptHttpPost($endpoint, [], $payload);
        if (!$response['ok']) {
            return [
                'route' => 'prompt-template',
                'allowed' => true,
                'error' => 'Local endpoint request failed.',
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

    $template = trim($template);
    if ($template === '') {
        return [
            'route' => 'prompt-template',
            'allowed' => true,
            'error' => 'Template was empty.',
        ];
    }

    return [
        'route' => 'prompt-template',
        'allowed' => true,
        'provider' => $provider,
        'model' => $usedModel,
        'template' => $template,
    ];
}
