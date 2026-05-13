<?php

function cmsHandleEditModelsAction(array $ctx): void
{
    $openAiFallbackModels = [
        'gpt-4o-mini',
        'gpt-4o',
        'gpt-4.1-mini',
        'gpt-4.1',
        'o4-mini',
        'o3-mini',
    ];

    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Models requires POST.'], 405);
    }

    $provider = trim((string) ($ctx['data']['provider'] ?? 'local'));
    $endpoint = trim((string) ($ctx['data']['endpoint'] ?? ''));
    $apiKey = trim((string) ($ctx['data']['apiKey'] ?? ''));
    if ($provider !== 'openai' && $endpoint === '') {
        $endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    }

    $modelsUrl = 'http://127.0.0.1:1234/v1/models';
    $headers = ['Accept: application/json'];
    if ($provider === 'openai') {
        if ($apiKey === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Add an OpenAI API key to load models.',
                'models' => $openAiFallbackModels,
            ]);
        }
        $modelsUrl = 'https://api.openai.com/v1/models';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
    } else {
        $modelsUrl = preg_replace('#/v1/(chat/completions|responses)$#i', '/v1/models', $endpoint);
        if (!is_string($modelsUrl) || trim($modelsUrl) === '') {
            $modelsUrl = 'http://127.0.0.1:1234/v1/models';
        }
        if (!preg_match('#/v1/models$#i', $modelsUrl)) {
            $modelsUrl = 'http://127.0.0.1:1234/v1/models';
        }
    }

    $response = cmsHttpGet($modelsUrl, $headers);
    if (!$response['ok']) {
        if ($provider === 'openai') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => cmsFormatPromptHttpError('OpenAI models endpoint', $response),
                'models' => $openAiFallbackModels,
            ]);
        }
        cmsJsonResponse([
            'allowed' => true,
            'error' => cmsFormatPromptHttpError($provider === 'openai' ? 'OpenAI models endpoint' : 'Local models endpoint', $response),
            'models' => [],
        ], 502);
    }

    $decoded = json_decode((string) ($response['body'] ?? ''), true);
    $models = [];
    if (is_array($decoded['data'] ?? null)) {
        foreach ($decoded['data'] as $item) {
            $id = is_array($item) ? trim((string) ($item['id'] ?? '')) : '';
            if ($id === '' || preg_match('#^text-embedding[-/]#i', $id)) {
                continue;
            }
            if ($provider === 'openai' && preg_match('#^(whisper|tts|omni-moderation|text-embedding)#i', $id)) {
                continue;
            }
            $models[] = $id;
        }
    }

    cmsJsonResponse([
        'allowed' => true,
        'models' => array_values(array_unique($models)),
    ]);
}
