<?php

function cmsEditPromptRunLocal(array $request): array
{
    $endpoint = $request['endpoint'] !== '' ? $request['endpoint'] : 'http://127.0.0.1:1234/v1/chat/completions';
    $usedModel = $request['model'] !== '' ? $request['model'] : 'gemma4';
    $promptIsOpenAiCompatible = cmsIsOpenAiCompatibleEndpoint($endpoint);
    if ($promptIsOpenAiCompatible) {
        $messages = [['role' => 'system', 'content' => $request['systemPrompt']]];
        foreach ($request['history'] as $message) {
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
        $messages[] = ['role' => 'user', 'content' => $request['image'] ? [
            ['type' => 'text', 'text' => $request['userPrompt']],
            ['type' => 'image_url', 'image_url' => ['url' => $request['image']['dataUrl']]],
        ] : $request['userPrompt']];
        $payload = ['model' => $usedModel, 'messages' => $messages, 'temperature' => 0.4];
    } else {
        $payload = [
            'prompt' => $request['prompt'],
            'history' => $request['history'],
            'config' => cmsPromptCompactConfig($request['promptConfig'], $request['promptIsLayoutTarget']),
            'instruction' => $request['systemPrompt'],
            'image' => $request['image'],
            'promptContext' => cmsPromptCompactContext($request['promptContext']),
        ];
    }
    $debugEntry = [
        'provider' => 'local',
        'endpoint' => $endpoint,
        'requestPayload' => $payload,
    ];
    if ($request['streamRequested'] && $promptIsOpenAiCompatible) {
        cmsPromptSendSseHeaders();
        $payload['stream'] = true;
        $response = cmsHttpPostStream($endpoint, [], $payload, $request['streamChunkParser']);
        if (!$response['ok']) {
            $debugPath = cmsPromptDebugCapture($request['rootDir'], array_merge($debugEntry, ['failure' => 'http', 'response' => $response]));
            cmsPromptSendSseEvent('final', ['allowed' => true, 'error' => cmsFormatPromptHttpError('Local endpoint', $response) . ' Debug saved to ' . $debugPath . '.']);
            exit;
        }
        return ['template' => $request['streamTemplate'], 'usedModel' => $usedModel, 'modelReturnedReasoningOnly' => false];
    }
    $response = cmsHttpPost($endpoint, [], $payload);
    if (!$response['ok']) {
        $debugPath = cmsPromptDebugCapture($request['rootDir'], array_merge($debugEntry, ['failure' => 'http', 'response' => $response]));
        cmsJsonResponse(['allowed' => true, 'error' => cmsFormatPromptHttpError('Local endpoint', $response) . ' Debug saved to ' . $debugPath . '.']);
    }
    $decoded = json_decode($response['body'], true);
    $template = '';
    $modelReturnedReasoningOnly = false;
    if ($promptIsOpenAiCompatible && !is_array($decoded)) {
        $debugPath = cmsPromptDebugCapture($request['rootDir'], array_merge($debugEntry, ['failure' => 'invalid_json_envelope', 'response' => $response]));
        cmsJsonResponse(['allowed' => true, 'error' => 'Local endpoint returned an invalid JSON chat envelope. Debug saved to ' . $debugPath . '.'], 502);
    }
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
    return ['template' => $template, 'usedModel' => $usedModel, 'modelReturnedReasoningOnly' => $modelReturnedReasoningOnly];
}
