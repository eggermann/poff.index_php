<?php

function buildFolderViewerData(string $relativePath, string $fullPath, ?array $folderConfig, array $rootMeta): array
{
    $visited = [];
    $realPath = realpath($fullPath);
    if (is_string($realPath) && $realPath !== '') {
        $visited[$realPath] = true;
    }

    $flatItems = [];
    $tree = buildFolderViewerItems($relativePath, $fullPath, $folderConfig, $flatItems, $visited);
    $allFiles = array_values(array_filter($flatItems, static fn(array $item): bool => !empty($item['isFile'])));
    $allFolders = array_values(array_filter($flatItems, static fn(array $item): bool => !empty($item['isFolder'])));

    return [
        'tree' => $tree,
        'workTree' => [
            'name' => (string) ($rootMeta['name'] ?? basename($fullPath)),
            'title' => (string) ($rootMeta['title'] ?? $rootMeta['name'] ?? basename($fullPath)),
            'slug' => (string) ($rootMeta['slug'] ?? 'item'),
            'type' => 'folder',
            'kind' => 'folder',
            'path' => $relativePath,
            'displayPath' => $relativePath === '' ? '.' : $relativePath,
            'viewerHref' => '?view=1&path=' . rawurlencode($relativePath),
            'viewUrl' => '?view=1&path=' . rawurlencode($relativePath),
            'workUrl' => '?view=1&path=' . rawurlencode($relativePath),
            'rawHref' => '?path=' . rawurlencode($relativePath),
            'assetUrl' => '?path=' . rawurlencode($relativePath),
            'isFolder' => true,
            'isFile' => false,
            'childCount' => count($tree),
            'children' => $tree,
        ],
        'allItems' => $flatItems,
        'allFiles' => $allFiles,
        'allFolders' => $allFolders,
        'allImages' => filterFolderViewerItemsByKind($allFiles, 'image'),
        'allVideos' => filterFolderViewerItemsByKind($allFiles, 'video'),
        'allAudio' => filterFolderViewerItemsByKind($allFiles, 'audio'),
        'allPdfs' => filterFolderViewerItemsByKind($allFiles, 'pdf'),
        'allTexts' => filterFolderViewerItemsByKind($allFiles, 'text'),
        'allLinks' => filterFolderViewerItemsByKind($allFiles, 'link'),
        'allOther' => filterFolderViewerItemsByKind($allFiles, 'other'),
    ];
}

function buildFolderViewerItems(string $relativePath, string $fullPath, ?array $folderConfig, array &$flatItems, array &$visited): array
{
    $tree = resolveFolderViewerTree($fullPath, $folderConfig);
    $items = [];

    foreach ($tree as $entry) {
        if (!is_array($entry) || (($entry['visible'] ?? true) === false)) {
            continue;
        }
        $entryName = trim((string) ($entry['name'] ?? ''));
        if ($entryName === '') {
            continue;
        }

        $entryRelativePath = $relativePath === '' ? $entryName : $relativePath . '/' . $entryName;
        $entryFullPath = $fullPath . DIRECTORY_SEPARATOR . $entryName;
        if (!file_exists($entryFullPath)) {
            continue;
        }

        $isFolder = is_dir($entryFullPath);
        $entryType = $isFolder ? 'folder' : 'file';
        $entryKind = $isFolder ? 'folder' : detectFileType($entryFullPath);
        $viewerHref = $isFolder
            ? '?view=1&path=' . rawurlencode($entryRelativePath)
            : '?view=1&file=' . rawurlencode($entryRelativePath);
        $rawHref = $isFolder
            ? '?path=' . rawurlencode($entryRelativePath)
            : viewerAssetHref($entryRelativePath);
        $item = array_merge($entry, [
            'name' => $entryName,
            'title' => $entry['title'] ?? $entryName,
            'type' => $entryType,
            'kind' => $entryKind,
            'path' => $entryRelativePath,
            'relativePath' => $entryRelativePath,
            'basename' => $entryName,
            'depth' => substr_count($entryRelativePath, '/'),
            'viewerHref' => $viewerHref,
            'viewUrl' => $viewerHref,
            'workUrl' => $viewerHref,
            'rawHref' => $rawHref,
            'assetUrl' => $rawHref,
            'isFolder' => $isFolder,
            'isFile' => !$isFolder,
        ]);

        if ($isFolder) {
            $childConfig = readExistingFolderViewerConfig($entryFullPath);
            if (is_array($childConfig)) {
                if (isset($childConfig['title']) && is_string($childConfig['title']) && trim($childConfig['title']) !== '') {
                    $item['title'] = $childConfig['title'];
                }
                if (isset($childConfig['slug']) && is_string($childConfig['slug']) && trim($childConfig['slug']) !== '') {
                    $item['slug'] = $childConfig['slug'];
                }
                if (isset($childConfig['description']) && is_string($childConfig['description'])) {
                    $item['description'] = $childConfig['description'];
                }
            }
            $children = [];
            $realChildPath = realpath($entryFullPath);
            if (!is_string($realChildPath) || $realChildPath === '' || !isset($visited[$realChildPath])) {
                if (is_string($realChildPath) && $realChildPath !== '') {
                    $visited[$realChildPath] = true;
                }
                $children = buildFolderViewerItems($entryRelativePath, $entryFullPath, $childConfig, $flatItems, $visited);
            }
            $item['children'] = $children;
            $item['childCount'] = count($children);
        } else {
            $item['extension'] = strtolower((string) pathinfo($entryName, PATHINFO_EXTENSION));
            $item['mimeType'] = MediaType::detectMimeType($entryFullPath, $entryName) ?? '';
            $fileConfig = readExistingFolderViewerFileConfig($fullPath, $entryName);
            if (is_array($fileConfig)) {
                if (isset($fileConfig['title']) && is_string($fileConfig['title']) && trim($fileConfig['title']) !== '') {
                    $item['title'] = $fileConfig['title'];
                }
                if (isset($fileConfig['slug']) && is_string($fileConfig['slug']) && trim($fileConfig['slug']) !== '') {
                    $item['slug'] = $fileConfig['slug'];
                }
                if (isset($fileConfig['description']) && is_string($fileConfig['description'])) {
                    $item['description'] = $fileConfig['description'];
                }
            }
        }

        $flatItem = $item;
        unset($flatItem['children']);
        $flatItems[] = $flatItem;
        $items[] = $item;
    }

    return $items;
}

function resolveFolderViewerTree(string $fullPath, ?array $folderConfig): array
{
    if (is_array($folderConfig) && isset($folderConfig['tree']) && is_array($folderConfig['tree'])) {
        return $folderConfig['tree'];
    }
    if (class_exists('PoffConfig')) {
        return PoffConfig::buildFirstLevelTree($fullPath);
    }

    return [];
}

function readExistingFolderViewerConfig(string $dir): ?array
{
    if (!class_exists('PoffConfig')) {
        return null;
    }

    $configPath = PoffConfig::configPath($dir);
    if (!is_file($configPath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);

    return is_array($decoded) ? $decoded : null;
}

function readExistingFolderViewerFileConfig(string $dir, string $fileName): ?array
{
    if (!class_exists('PoffConfig')) {
        return null;
    }

    $configPath = PoffConfig::fileConfigPath($dir, $fileName);
    if (!is_file($configPath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);

    return is_array($decoded) ? $decoded : null;
}

function filterFolderViewerItemsByKind(array $items, string $kind): array
{
    return array_values(array_filter($items, static fn(array $item): bool => ($item['kind'] ?? '') === $kind));
}

function viewerAssetHref(string $relativePath): string
{
    if ($relativePath === '') {
        return '';
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}
