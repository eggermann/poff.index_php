<?php
declare(strict_types=1);

function handleStyleRoute(string $prompt, string $mcpUrl, string $configPath): array
{
    return [
        'route' => 'style',
        'prompt' => $prompt,
        'message' => $prompt !== ''
            ? 'Style prompt accepted.'
            : 'Provide a prompt query parameter to describe desired style.',
        'mcpUrl' => $mcpUrl,
        'configPath' => $configPath,
    ];
}
