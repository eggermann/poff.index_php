<?php
/**
 * Edit transport helpers.
 */

function cmsIsOpenAiCompatibleEndpoint(string $url): bool
{
    $normalized = strtolower(trim($url));
    return $normalized !== '' && str_contains($normalized, '/v1/chat/completions');
}

function cmsPromptSendSseHeaders(): void
{
    $GLOBALS['__poff_prompt_sse_active'] = true;
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
}

function cmsPromptIsSseActive(): bool
{
    return !empty($GLOBALS['__poff_prompt_sse_active']);
}

function cmsPromptSendSseEvent(string $event, array $payload): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}
