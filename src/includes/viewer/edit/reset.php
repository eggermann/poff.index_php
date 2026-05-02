<?php
/**
 * Reset helpers for edit actions.
 */

require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../PoffConfig.php';
require_once __DIR__ . '/delete.php';

function cmsResetFolderTarget(string $rootDir, string $relativePath): array
{
    $rootReal = realpath($rootDir);
    if ($rootReal === false) {
        return [
            'reset' => [],
            'errors' => ['Workspace root unavailable.'],
        ];
    }

    $target = cmsResolveTarget($rootReal, $relativePath);
    if ($target === null || ($target['type'] ?? '') !== 'folder') {
        return [
            'reset' => [],
            'errors' => ['Invalid reset target.'],
        ];
    }

    $targetDir = (string) ($target['dir'] ?? '');
    if ($targetDir === '' || !is_dir($targetDir)) {
        return [
            'reset' => [],
            'errors' => ['Reset target not found.'],
        ];
    }

    $layoutDir = PoffConfig::folderLayoutDir($targetDir);
    if (is_dir($layoutDir) && !cmsDeletePathRecursive($layoutDir)) {
        return [
            'reset' => [],
            'errors' => ['Failed to remove the local .layout override.'],
        ];
    }

    $config = PoffConfig::ensure($targetDir);
    $config['work'] = Worktype::definition('folder');
    $config['updatedAt'] = date('c');

    $configPath = PoffConfig::configPath($targetDir);
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        return [
            'reset' => [],
            'errors' => ['Failed to encode config JSON.'],
        ];
    }
    file_put_contents($configPath, $encoded);

    return [
        'reset' => [
            [
                'name' => '.layout',
                'path' => trim((string) $relativePath, "/\\") === '' ? '.layout' : trim((string) $relativePath, "/\\") . '/.layout',
                'type' => 'layout',
            ],
        ],
        'errors' => [],
        'config' => PoffConfig::hydrateConfigLayout(
            json_decode($encoded, true) ?: $config,
            $targetDir
        ),
    ];
}
