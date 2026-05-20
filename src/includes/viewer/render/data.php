<?php

function cmsFolderViewerCacheDirectory(): string
{
    static $cacheDir = null;

    if (is_string($cacheDir) && $cacheDir !== '') {
        return $cacheDir;
    }

    $rootHash = function_exists('cmsProjectRootDir')
        ? sha1(cmsProjectRootDir())
        : sha1((string) getcwd());
    $cacheDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff-folder-viewer-cache' . DIRECTORY_SEPARATOR . $rootHash;

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }

    return $cacheDir;
}

function cmsFolderViewerCachePath(string $fullPath, string $relativePath): string
{
    return cmsFolderViewerCacheDirectory() . DIRECTORY_SEPARATOR . sha1($fullPath . '|' . $relativePath) . '.json';
}

function cmsFolderViewerTrackDependency(array &$dependencies, string $path): void
{
    if ($path === '') {
        return;
    }

    clearstatcache(false, $path);
    $mtime = @filemtime($path);
    if ($mtime === false) {
        return;
    }

    $dependencies[$path] = (int) $mtime;
}

function cmsFolderViewerCollectDependencies(array $dependencies): array
{
    ksort($dependencies, SORT_STRING);

    $resolved = [];
    foreach ($dependencies as $path => $mtime) {
        $resolved[] = [
            'path' => $path,
            'mtime' => (int) $mtime,
        ];
    }

    return $resolved;
}

function cmsFolderViewerCacheIsFresh(array $dependencies): bool
{
    foreach ($dependencies as $dependency) {
        if (!is_array($dependency)) {
            return false;
        }

        $path = (string) ($dependency['path'] ?? '');
        $expectedMtime = (int) ($dependency['mtime'] ?? 0);
        if ($path === '' || $expectedMtime <= 0) {
            return false;
        }

        clearstatcache(false, $path);
        if (!file_exists($path)) {
            return false;
        }

        $currentMtime = @filemtime($path);
        if ($currentMtime === false || (int) $currentMtime !== $expectedMtime) {
            return false;
        }
    }

    return true;
}

function cmsFolderViewerCacheLoad(string $fullPath, string $relativePath): ?array
{
    $cachePath = cmsFolderViewerCachePath($fullPath, $relativePath);
    if (!is_file($cachePath)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($cachePath), true);
    if (!is_array($decoded)) {
        return null;
    }

    if (!isset($decoded['dependencies']) || !is_array($decoded['dependencies'])) {
        return null;
    }

    if (!cmsFolderViewerCacheIsFresh($decoded['dependencies'])) {
        return null;
    }

    return is_array($decoded['data'] ?? null) ? $decoded['data'] : null;
}

function cmsFolderViewerCacheStore(string $fullPath, string $relativePath, array $data, array $dependencies): void
{
    $cachePath = cmsFolderViewerCachePath($fullPath, $relativePath);
    $payload = [
        'data' => $data,
        'dependencies' => cmsFolderViewerCollectDependencies($dependencies),
    ];

    @file_put_contents($cachePath, json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function buildFolderViewerData(string $relativePath, string $fullPath, ?array $folderConfig, array $rootMeta): array
{
    $dependencies = [];
    cmsFolderViewerTrackDependency($dependencies, $fullPath);
    if (class_exists('PoffConfig')) {
        $rootConfigPath = PoffConfig::configPath($fullPath);
        if (is_file($rootConfigPath)) {
            cmsFolderViewerTrackDependency($dependencies, $rootConfigPath);
        }
    }

    $cached = cmsFolderViewerCacheLoad($fullPath, $relativePath);
    if (is_array($cached)) {
        return $cached;
    }

    $visited = [];
    $realPath = realpath($fullPath);
    if (is_string($realPath) && $realPath !== '') {
        $visited[$realPath] = true;
    }

    $flatItems = [];
    $tree = buildFolderViewerItems($relativePath, $fullPath, $folderConfig, $flatItems, $visited, $dependencies);
    $allFiles = array_values(array_filter($flatItems, static fn(array $item): bool => !empty($item['isFile'])));
    $allFolders = array_values(array_filter($flatItems, static fn(array $item): bool => !empty($item['isFolder'])));

    $data = [
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

    cmsFolderViewerCacheStore($fullPath, $relativePath, $data, $dependencies);

    return $data;
}

function buildFolderViewerItems(string $relativePath, string $fullPath, ?array $folderConfig, array &$flatItems, array &$visited, array &$dependencies): array
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

        $entryRelativePath = cmsConfiguredTreeDisplayPath($relativePath, $entry, $entryName);
        $filesystemRelativePath = cmsConfiguredTreeFilesystemRelativePath($relativePath, $entry, $entryName);
        $entryTarget = cmsConfiguredTreeLinkTarget($entry);
        $directLinkTarget = cmsConfiguredTreeDirectLinkTarget($entry);
        $entryLinkUrl = cmsConfiguredTreeExternalLinkUrl($entry);
        $entryStoredPath = trim((string) ($entry['path'] ?? $entry['relativePath'] ?? ''));
        $entryFullPath = '';
        if ($entryStoredPath !== '' && !cmsIsSpecialLinkTarget($entryStoredPath)) {
            $entryFullPath = $fullPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($entryStoredPath, "/\\"));
        } elseif ($entryName !== '') {
            $entryFullPath = $fullPath . DIRECTORY_SEPARATOR . $entryName;
        }
        $hasPhysicalTarget = $entryFullPath !== '' && file_exists($entryFullPath);
        if (!$hasPhysicalTarget && $entryTarget === '') {
            continue;
        }

        if ($hasPhysicalTarget) {
            cmsFolderViewerTrackDependency($dependencies, $entryFullPath);
        }

        $configuredType = strtolower(trim((string) ($entry['type'] ?? '')));
        $isFolder = $hasPhysicalTarget ? is_dir($entryFullPath) : ($configuredType === 'folder');
        $entryType = $isFolder ? 'folder' : 'file';
        $entryKind = $isFolder
            ? 'folder'
            : (($configuredType === 'link' || (!$hasPhysicalTarget && $entryTarget !== '')) ? 'link' : detectFileType($entryFullPath));
        $preferLocalViewer = !$isFolder && ($hasPhysicalTarget || ($entryTarget !== '' && $directLinkTarget === ''));
        $viewerHref = $preferLocalViewer
            ? cmsBuildViewerHrefFromRelativePath($filesystemRelativePath, true)
            : ($entryTarget !== ''
                ? $entryTarget
                : cmsBuildViewerHrefFromRelativePath($filesystemRelativePath, !$isFolder));
        $rawHref = $preferLocalViewer
            ? ($hasPhysicalTarget
                ? cmsBuildAssetHrefFromRelativePath($filesystemRelativePath, true)
                : ($entryTarget !== '' ? $entryTarget : cmsBuildAssetHrefFromRelativePath($filesystemRelativePath, true)))
            : ($entryTarget !== ''
                ? $entryTarget
                : cmsBuildAssetHrefFromRelativePath($filesystemRelativePath, !$isFolder));
        $item = array_merge($entry, [
            'name' => $entryName,
            'title' => $entry['title'] ?? $entryName,
            'type' => $entryType,
            'kind' => $entryKind,
            'path' => $entryRelativePath !== '' ? $entryRelativePath : $filesystemRelativePath,
            'relativePath' => $filesystemRelativePath !== '' ? $filesystemRelativePath : $entryRelativePath,
            'basename' => $entryName,
            'depth' => substr_count($filesystemRelativePath !== '' ? $filesystemRelativePath : $entryRelativePath, '/'),
            'viewerHref' => $viewerHref,
            'viewUrl' => $viewerHref,
            'workUrl' => $viewerHref,
            'pageLink' => $viewerHref,
            'pageUrl' => $viewerHref,
            'rawHref' => $rawHref,
            'assetUrl' => $rawHref,
            'assetLink' => $rawHref,
            'srcUrl' => $rawHref,
            'sourceUrl' => $rawHref,
            'linkUrl' => $entryLinkUrl,
            'isFolder' => $isFolder,
            'isFile' => !$isFolder,
            'isVirtual' => !$hasPhysicalTarget,
        ]);

        if ($isFolder && $hasPhysicalTarget) {
            $childConfig = readExistingFolderViewerConfig($entryFullPath);
            if (is_array($childConfig)) {
                if (class_exists('PoffConfig')) {
                    $childConfigPath = PoffConfig::configPath($entryFullPath);
                    if (is_file($childConfigPath)) {
                        cmsFolderViewerTrackDependency($dependencies, $childConfigPath);
                    }
                }
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
                $children = buildFolderViewerItems($entryRelativePath, $entryFullPath, $childConfig, $flatItems, $visited, $dependencies);
            }
            $item['children'] = $children;
            $item['childCount'] = count($children);
        } elseif ($isFolder) {
            $item['children'] = [];
            $item['childCount'] = 0;
        } else {
            $item['extension'] = strtolower((string) pathinfo($entryName, PATHINFO_EXTENSION));
            if ($hasPhysicalTarget) {
                cmsFolderViewerTrackDependency($dependencies, $entryFullPath);
                $item['mimeType'] = MediaType::detectMimeType($entryFullPath, $entryName) ?? '';
                $fileConfig = readExistingFolderViewerFileConfig($fullPath, $entryName);
                if (is_array($fileConfig)) {
                    if (class_exists('PoffConfig')) {
                        $fileConfigPath = PoffConfig::fileConfigPath($fullPath, $entryName);
                        if (is_file($fileConfigPath)) {
                            cmsFolderViewerTrackDependency($dependencies, $fileConfigPath);
                        }
                    }
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
    static $cache = [];

    if (!class_exists('PoffConfig')) {
        return null;
    }

    $configPath = PoffConfig::configPath($dir);
    if (!is_file($configPath)) {
        return null;
    }

    $cacheKey = $configPath . '|' . (string) (@filemtime($configPath) ?: 0);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);

    $cache[$cacheKey] = is_array($decoded) ? $decoded : null;

    return $cache[$cacheKey];
}

function readExistingFolderViewerFileConfig(string $dir, string $fileName): ?array
{
    static $cache = [];

    if (!class_exists('PoffConfig')) {
        return null;
    }

    $configPath = PoffConfig::fileConfigPath($dir, $fileName);
    if (!is_file($configPath)) {
        return null;
    }

    $cacheKey = $configPath . '|' . (string) (@filemtime($configPath) ?: 0);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);

    $cache[$cacheKey] = is_array($decoded) ? $decoded : null;

    return $cache[$cacheKey];
}

function filterFolderViewerItemsByKind(array $items, string $kind): array
{
    return array_values(array_filter($items, static fn(array $item): bool => ($item['kind'] ?? '') === $kind));
}

function viewerAssetHref(string $relativePath): string
{
    return cmsBuildAssetHrefFromRelativePath($relativePath, true);
}
