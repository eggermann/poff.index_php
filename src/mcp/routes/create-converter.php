<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Converter.php';

function handleCreateConverter(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    $path = trim((string) ($payload['path'] ?? $opts['path'] ?? ''), "/\\");
    $name = trim((string) ($payload['name'] ?? ''));

    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'create-converter'], $access);
    }

    if ($name === '') {
        return [
            'route' => 'create-converter',
            'ok' => false,
            'error' => 'Missing converter name.',
        ];
    }

    $result = Converter::ensureVisibleConverterFolder($rootDir, $name);
    if (($result['ok'] ?? false) !== true) {
        return [
            'route' => 'create-converter',
            'ok' => false,
            'error' => (string) ($result['error'] ?? 'Failed to create converter.'),
        ];
    }

    return [
        'route' => 'create-converter',
        'ok' => true,
        'folder' => (string) ($result['folder'] ?? ''),
        'definition' => $result['definition'] ?? null,
        'targetType' => 'converter',
        'promptPath' => (string) ($result['folder'] ?? ''),
    ];
}
