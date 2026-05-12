<?php

function cmsEditPromptRunOpenAi(array $request): array
{
    $key = $request['apiKey'] !== '' ? $request['apiKey'] : (cmsEnvValue($request['env'], 'OPENAI_API_KEY') ?? '');
    if ($key === '') {
        cmsJsonResponse(['allowed' => true, 'error' => 'OpenAI API key not set.']);
    }
    $usedModel = $request['model'] !== '' ? $request['model'] : 'gpt-4o-mini';
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
    if ($request['streamRequested']) {
        cmsPromptSendSseHeaders();
        $payload['stream'] = true;
        $response = cmsHttpPostStream('https://api.openai.com/v1/chat/completions', ['Authorization: Bearer ' . $key], $payload, $request['streamChunkParser']);
        if (!$response['ok']) {
            cmsPromptSendSseEvent('final', ['allowed' => true, 'error' => cmsFormatPromptHttpError('OpenAI', $response)]);
            exit;
        }
        return ['template' => $request['streamTemplate'], 'usedModel' => $usedModel, 'modelReturnedReasoningOnly' => false];
    }
    $response = cmsHttpPost('https://api.openai.com/v1/chat/completions', ['Authorization: Bearer ' . $key], $payload);
    if (!$response['ok']) {
        cmsJsonResponse(['allowed' => true, 'error' => cmsFormatPromptHttpError('OpenAI', $response)]);
    }
    $decoded = json_decode($response['body'], true);
    return ['template' => (string) ($decoded['choices'][0]['message']['content'] ?? ''), 'usedModel' => $usedModel, 'modelReturnedReasoningOnly' => false];
}
