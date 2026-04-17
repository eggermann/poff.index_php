<?php
declare(strict_types=1);

require_once __DIR__ . '/edit-config/helpers.php';
require_once __DIR__ . '/edit-config/payload.php';
require_once __DIR__ . '/edit-config/apply.php';

function handleEditConfig(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = $opts['path'] ?? '';
    $allowFile = $opts['allowFile'] ?? ($rootDir . DIRECTORY_SEPARATOR . '.edit.allow');
    $allowed = is_file($allowFile);

    if (!$allowed) {
        return [
            'route' => 'edit-config',
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ];
    }

    if (!class_exists('PoffConfig')) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ];
    }

    $targetDir = mcpResolveEditPath($rootDir, (string) $path);
    if ($targetDir === null) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ];
    }

    $config = PoffConfig::ensure($targetDir);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'POST') {
        $data = mcpReadEditConfigRequest();
        $payload = mcpParseEditConfigPayload($data);
        $result = mcpApplyEditConfigPayload($config, $payload, $targetDir);

        if ($result['error'] !== null) {
            return [
                'route' => 'edit-config',
                'allowed' => true,
                'error' => $result['error'],
            ];
        }

        $config = $result['config'];
        $configPath = PoffConfig::configPath($targetDir);
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return [
                'route' => 'edit-config',
                'allowed' => true,
                'error' => 'Failed to encode config JSON.',
            ];
        }
        file_put_contents($configPath, $encoded);

        return [
            'route' => 'edit-config',
            'allowed' => true,
            'saved' => true,
            'config' => PoffConfig::hydrateConfigLayout($config, $targetDir),
        ];
    }

    return [
        'route' => 'edit-config',
        'allowed' => true,
        'config' => $config,
    ];
}
