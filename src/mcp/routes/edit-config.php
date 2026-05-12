<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/edit-mode.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/edit-config/payload.php';
require_once __DIR__ . '/edit-config/apply.php';

function handleEditConfig(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = $opts['path'] ?? '';
    $allowFile = $opts['allowFile'] ?? null;

    if (!class_exists('PoffConfig')) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ];
    }

    $targetDir = mcpResolveDirectoryInsideRoot($rootDir, (string) $path);
    if ($targetDir === null) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ];
    }

    $allowed = is_string($allowFile) && $allowFile !== ''
        ? is_file($allowFile)
        : cmsEditModeAllowedForDirectory($targetDir, $rootDir);
    if (!$allowed) {
        return [
            'route' => 'edit-config',
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ];
    }

    $config = PoffConfig::ensure($targetDir);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'POST') {
        $data = mcpReadRequestData();
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
        $writeError = mcpWriteJsonFile($configPath, $config);
        if ($writeError !== null) {
            return [
                'route' => 'edit-config',
                'allowed' => true,
                'error' => $writeError,
            ];
        }

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
