<?php
/**
 * Delete helpers for edit actions.
 */

require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../PoffConfig.php';

function cmsDeletePathRecursive(string $path): bool
{
    if ($path === '' || (!file_exists($path) && !is_link($path))) {
        return false;
    }

    if (is_link($path) || is_file($path)) {
        return @unlink($path);
    }

    if (!is_dir($path)) {
        return false;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return false;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (!cmsDeletePathRecursive($path . DIRECTORY_SEPARATOR . $entry)) {
            return false;
        }
    }

    return @rmdir($path);
}

function cmsDeleteTarget(string $rootDir, string $relativePath): array
{
    $rootReal = realpath($rootDir);
    if ($rootReal === false) {
        return [
            'deleted' => [],
            'errors' => ['Workspace root unavailable.'],
        ];
    }

    $target = cmsResolveTarget($rootReal, $relativePath);
    if ($target === null) {
        return [
            'deleted' => [],
            'errors' => ['Invalid delete target.'],
        ];
    }
    if (($target['type'] ?? '') === 'layout') {
        return [
            'deleted' => [],
            'errors' => ['Delete is not supported for layout targets.'],
        ];
    }

    $deleted = [];
    $refreshDir = null;

    if (($target['type'] ?? '') === 'file') {
        $targetPath = (string) ($target['path'] ?? '');
        $targetDir = (string) ($target['dir'] ?? '');
        $targetFile = (string) ($target['file'] ?? '');
        if ($targetPath === '' || $targetDir === '' || $targetFile === '') {
            return [
                'deleted' => [],
                'errors' => ['Invalid delete target.'],
            ];
        }
        if (!is_file($targetPath) && !is_link($targetPath)) {
            return [
                'deleted' => [],
                'errors' => ['Delete target not found.'],
            ];
        }
        if (!cmsDeletePathRecursive($targetPath)) {
            return [
                'deleted' => [],
                'errors' => ['Failed to delete file.'],
            ];
        }

        $deleted[] = [
            'name' => $targetFile,
            'path' => trim((string) $relativePath, "/\\"),
            'type' => 'file',
        ];
        $refreshDir = $targetDir;

        cmsDeletePathRecursive(PoffConfig::fileConfigPath($targetDir, $targetFile));
        cmsDeletePathRecursive(PoffConfig::fileLayoutDir($targetDir, $targetFile));
    } elseif (($target['type'] ?? '') === 'folder') {
        $targetDir = (string) ($target['dir'] ?? '');
        if ($targetDir === '' || !is_dir($targetDir)) {
            return [
                'deleted' => [],
                'errors' => ['Delete target not found.'],
            ];
        }
        if ($targetDir === $rootReal) {
            return [
                'deleted' => [],
                'errors' => ['Cannot delete the workspace root folder.'],
            ];
        }
        if (!cmsDeletePathRecursive($targetDir)) {
            return [
                'deleted' => [],
                'errors' => ['Failed to delete folder.'],
            ];
        }

        $deleted[] = [
            'name' => basename($targetDir),
            'path' => trim((string) $relativePath, "/\\"),
            'type' => 'folder',
        ];
        $refreshDir = dirname($targetDir);
        if ($refreshDir === '.' || $refreshDir === '') {
            $refreshDir = $rootReal;
        }
    } else {
        return [
            'deleted' => [],
            'errors' => ['Unsupported delete target.'],
        ];
    }

    return [
        'deleted' => $deleted,
        'errors' => [],
        'refreshDir' => $refreshDir,
    ];
}
