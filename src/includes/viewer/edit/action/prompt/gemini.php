<?php

function cmsEditPromptRunGemini(array $request): array
{
    $key = $request['apiKey'] !== '' ? $request['apiKey'] : (cmsEnvValue($request['env'], 'GEMINI_API_KEY') ?? '');
    if ($key === '') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Gemini API key not set.']);
    }
    $usedModel = $request['model'] !== '' ? $request['model'] : 'gemini-1.5-flash';
    $promptText = $request['systemPrompt'] . "\n\n" . $request['userPrompt'];
    $payload = ['contents' => [[ 'parts' => [['text' => $promptText], ...($request['image'] ? [[
        'inline_data' => ['mime_type' => $request['image']['mimeType'], 'data' => $request['image']['base64']],
    ]] : [])], ]]];
    $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($usedModel), $key);
    $response = cmsHttpPost($url, [], $payload);
    if (!$response['ok']) {
        cmsJsonResponse(['allowed' => true, 'error' => cmsFormatPromptHttpError('Gemini', $response)]);
    }
    $decoded = json_decode($response['body'], true);
    return ['template' => (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? ''), 'usedModel' => $usedModel, 'modelReturnedReasoningOnly' => false];
}
