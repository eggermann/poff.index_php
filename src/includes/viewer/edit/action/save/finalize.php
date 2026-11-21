<?php

function cmsEditFolderViewerCacheDirectory(): string
{
    $rootHash = function_exists('cmsProjectRootDir')
        ? sha1(cmsProjectRootDir())
        : sha1((string) getcwd());

    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff-folder-viewer-cache' . DIRECTORY_SEPARATOR . $rootHash;
}

function cmsEditFolderViewerCachePath(string $fullPath, string $relativePath): string
{
    return cmsEditFolderViewerCacheDirectory() . DIRECTORY_SEPARATOR . sha1($fullPath . '|' . $relativePath) . '.json';
}

function cmsEditClearFolderViewerCache(string $fullPath, string $relativePath): void
{
    $cachePath = cmsEditFolderViewerCachePath($fullPath, $relativePath);
    if (is_file($cachePath)) {
        @unlink($cachePath);
    }
}

function cmsEditClearFolderViewerCachesForSave(array $ctx): void
{
    $targetDir = rtrim((string) ($ctx['targetDir'] ?? ''), DIRECTORY_SEPARATOR);
    $subjectRelativePath = trim(str_replace('\\', '/', (string) ($ctx['subjectRelativePath'] ?? '')), '/');
    $subjectType = (string) ($ctx['subjectType'] ?? '');
    $targetFile = (string) ($ctx['targetFile'] ?? '');

    if ($targetDir === '') {
        return;
    }

    $subjectFullPath = $subjectType === 'file'
        ? ($targetDir . DIRECTORY_SEPARATOR . trim($targetFile, "/\\"))
        : $targetDir;
    cmsEditClearFolderViewerCache($subjectFullPath, $subjectRelativePath);

    if ($subjectRelativePath === '') {
        return;
    }

    $parentRelativePath = dirname($subjectRelativePath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentFullPath = $subjectType === 'file'
        ? $targetDir
        : dirname($targetDir);
    $parentFullPath = rtrim($parentFullPath, DIRECTORY_SEPARATOR);
    if ($parentFullPath !== '' && is_dir($parentFullPath)) {
        cmsEditClearFolderViewerCache($parentFullPath, $parentRelativePath);
    }
}

function cmsEditSaveFinalize(array &$config, string $targetDir, string $subjectType, ?string $targetFile): void
{
    $configPath = $subjectType === 'file'
        ? PoffConfig::fileConfigPath($targetDir, (string) $targetFile)
        : PoffConfig::configPath($targetDir);
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Failed to encode config JSON.'], 500);
    }
    file_put_contents($configPath, $encoded);
}
