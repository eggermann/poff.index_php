<?php
/**
 * Parent config and tree sync helpers for edit actions.
 */

require_once __DIR__ . '/../../utils.php';

function cmsPromptParentConfig(string $rootDir, string $subjectRelativePath, string $subjectType, string $targetDir): array
{
    $normalizedPath = trim(str_replace('\\', '/', $subjectRelativePath), '/');
    if ($normalizedPath === '') {
        return [];
    }

    $parentRelativePath = dirname($normalizedPath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentDir = $subjectType === 'file' ? $targetDir : dirname($targetDir);
    $rootReal = realpath($rootDir);
    $parentReal = realpath($parentDir);
    if ($rootReal === false || $parentReal === false || !str_starts_with($parentReal, $rootReal)) {
        return [];
    }

    $configPath = PoffConfig::configPath($parentReal);
    if (!is_file($configPath)) {
        return [];
    }

    $parentConfig = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($parentConfig)) {
        return [];
    }

    return ['relativePath' => $parentRelativePath, 'config' => $parentConfig];
}

function cmsPromptFolderViewData(string $relativePath, string $fullPath, array $config, array $rootMeta): array
{
    if (!is_dir($fullPath)) {
        return [];
    }

    return buildFolderViewerData($relativePath, $fullPath, $config, $rootMeta);
}

function cmsApplyParentTreeVisible(string $rootDir, string $subjectRelativePath, string $subjectType, string $targetDir, mixed $treeVisible): void
{
    if (!is_array($treeVisible)) {
        return;
    }

    $parentPrompt = cmsPromptParentConfig($rootDir, $subjectRelativePath, $subjectType, $targetDir);
    $parentConfig = is_array($parentPrompt['config'] ?? null) ? $parentPrompt['config'] : [];
    if ($parentConfig === [] || !is_array($parentConfig['tree'] ?? null)) {
        return;
    }

    $visibleKeys = [];
    foreach ($treeVisible as $key) {
        if (is_scalar($key)) {
            $visibleKeys[trim((string) $key, "/\\")] = true;
        }
    }

    $changed = false;
    foreach ($parentConfig['tree'] as &$item) {
        if (!is_array($item)) {
            continue;
        }
        $key = trim((string) ($item['path'] ?? $item['name'] ?? ''), "/\\");
        $name = trim((string) ($item['name'] ?? ''), "/\\");
        if ($key === '' && $name === '') {
            continue;
        }
        $parentRelativePath = trim(str_replace('\\', '/', (string) ($parentPrompt['relativePath'] ?? '')), '/');
        $fullKey = trim($parentRelativePath . '/' . $key, '/');
        $nextVisible = isset($visibleKeys[$key]) || isset($visibleKeys[$fullKey]) || ($name !== '' && isset($visibleKeys[$name]));
        if (!array_key_exists('visible', $item) || (bool) $item['visible'] !== $nextVisible) {
            $item['visible'] = $nextVisible;
            $changed = true;
        }
    }
    unset($item);

    if (!$changed) {
        return;
    }

    $parentDir = $subjectType === 'file' ? $targetDir : dirname($targetDir);
    $parentConfig['treeHash'] = hash('sha256', json_encode($parentConfig['tree'] ?? []));
    $parentConfig['updatedAt'] = date('c');
    file_put_contents(PoffConfig::configPath($parentDir), json_encode($parentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cmsSyncParentTreeItemMeta(string $rootDir, string $subjectRelativePath, string $subjectType, array $config): void
{
    $normalizedPath = trim(str_replace('\\', '/', $subjectRelativePath), '/');
    if ($normalizedPath === '') {
        return;
    }

    $itemName = basename($normalizedPath);
    $parentRelativePath = dirname($normalizedPath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($parentRelativePath !== '') {
        $parentDir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parentRelativePath);
    }
    if (!is_dir($parentDir)) {
        return;
    }

    $parentConfigPath = PoffConfig::configPath($parentDir);
    $parentConfig = is_file($parentConfigPath)
        ? json_decode((string) file_get_contents($parentConfigPath), true)
        : PoffConfig::ensure($parentDir);
    if (!is_array($parentConfig) || !is_array($parentConfig['tree'] ?? null)) {
        return;
    }

    $changed = false;
    foreach ($parentConfig['tree'] as &$item) {
        if (!is_array($item) || (string) ($item['name'] ?? '') !== $itemName) {
            continue;
        }
        if (isset($config['slug']) && is_string($config['slug']) && trim($config['slug']) !== '') {
            $item['slug'] = trim($config['slug']);
            $changed = true;
        }
        if (isset($config['title']) && is_string($config['title'])) {
            $item['title'] = $config['title'];
            $changed = true;
        }
        $item['type'] = $subjectType;
        break;
    }
    unset($item);

    if (!$changed) {
        return;
    }

    $parentConfig['treeHash'] = hash('sha256', json_encode($parentConfig['tree']));
    $parentConfig['updatedAt'] = date('c');
    file_put_contents($parentConfigPath, json_encode($parentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
