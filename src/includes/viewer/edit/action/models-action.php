<?php

function cmsHandleEditModelsAction(array $ctx): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Models requires POST.'], 405);
    }

    $endpoint = trim((string) ($ctx['data']['endpoint'] ?? ''));
    if ($endpoint === '') {
        $endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    }

    $modelsUrl = preg_replace('#/v1/(chat/completions|responses)$#i', '/v1/models', $endpoint);
    if (!is_string($modelsUrl) || trim($modelsUrl) === '') {
        $modelsUrl = 'http://127.0.0.1:1234/v1/models';
    }
    if (!preg_match('#/v1/models$#i', $modelsUrl)) {
        $modelsUrl = 'http://127.0.0.1:1234/v1/models';
    }

    $response = cmsHttpGet($modelsUrl, ['Accept: application/json']);
    if (!$response['ok']) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => cmsFormatPromptHttpError('Local models endpoint', $response),
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
            $models[] = $id;
        }
    }

    cmsJsonResponse([
        'allowed' => true,
        'models' => array_values(array_unique($models)),
    ]);
}
