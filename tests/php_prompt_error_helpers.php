<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/includes/viewer/edit.php';
require_once __DIR__ . '/../src/mcp/routes/prompt-template.php';

$payload = [
    'cmsOpenAi' => cmsFormatPromptHttpError('OpenAI', [
        'status' => 401,
        'body' => '{"error":{"message":"Incorrect API key provided: sk-proj-1234567890abcdef"}}',
    ]),
    'cmsLocalHtml' => cmsFormatPromptHttpError('Local endpoint', [
        'status' => 502,
        'body' => '<html><head><title>Bad Gateway</title></head><body>upstream failed</body></html>',
    ]),
    'mcpGemini' => mcpPromptFormatHttpError('Gemini', [
        'status' => 429,
        'body' => '{"error":{"message":"Quota exceeded for model gemini-1.5-flash"}}',
    ]),
];

echo json_encode($payload, JSON_UNESCAPED_SLASHES);
