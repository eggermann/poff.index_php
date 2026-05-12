<?php

require_once __DIR__ . '/base.php';

function cmsPromptHistoryText(array $history): string
{
    if ($history === []) {
        return '';
    }

    $chunks = [];
    $items = array_slice($history, -CMS_PROMPT_HISTORY_MAX_ITEMS);
    foreach ($items as $message) {
        if (!is_array($message)) {
            continue;
        }
        $role = strtolower(trim((string) ($message['role'] ?? '')));
        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '') {
            continue;
        }
        $label = $role === 'assistant' ? 'ASSISTANT' : ($role === 'system' ? 'SYSTEM' : 'USER');
        $chunks[] = $label . ': ' . cmsPromptTrimText($content, CMS_PROMPT_HISTORY_ENTRY_MAX);
    }

    return $chunks === [] ? '' : "Conversation history:\n" . implode("\n", $chunks) . "\n\n";
}
