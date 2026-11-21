<?php
/**
 * Viewer rendering: HTML output for files and folders.
 */

function cmsFindConfirmedVirtualWork(string $rootDir, string $relativePath, bool $includeHidden, ?array $rootEntry): ?array
{
    $addresses = PoffRemoteNodeAddresses::load($rootDir);
    if ($addresses === []) {
        return null;
    }

    $virtualEntry = $rootEntry;
    $root = realpath($rootDir);
    for ($parent = dirname($relativePath); $parent !== '.' && $root !== false; $parent = dirname($parent)) {
        $folder = realpath($root . DIRECTORY_SEPARATOR . $parent);
        if ($folder === false || !str_starts_with($folder, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_dir($folder)) {
            continue;
        }
        $config = PoffConfig::read($folder);
        $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
        $candidate = cmsResolveConfiguredTreeItemInList($tree, $relativePath, $parent, $includeHidden);
        if (($candidate['resolvedPath'] ?? '') === $relativePath) {
            $virtualEntry = $candidate;
            break;
        }
    }
    if ($virtualEntry === null || (!$includeHidden && ($virtualEntry['visible'] ?? true) === false)) {
        return null;
    }
    $url = PoffRemoteNodeAddresses::workUrl($addresses, $virtualEntry);
    if ($url === '') {
        return null;
    }
    $virtualEntry['linkUrl'] = $url;
    return $virtualEntry;
}

function renderViewer(string $baseDir, string $requestedPath): void
{
    $relativePath = sanitizeRelativePath($requestedPath);
    $entryName = basename($relativePath);

    if ($entryName !== '' && cmsIsHiddenSystemEntry($entryName) && $entryName !== '.htaccess') {
        http_response_code(404);
        echo 'Path not found.';
        return;
    }

    if (strpos($relativePath, '..') !== false) {
        http_response_code(400);
        echo 'Invalid path.';
        return;
    }

    $fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR);
    if ($relativePath !== '') {
        $fullPath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
    if (!file_exists($fullPath)) {
        $includeHiddenVirtualItems = (string) ($_GET['edit'] ?? '') === 'true';
        $virtualConfig = class_exists('PoffConfig')
            ? cmsResolveConfiguredTreeItem($baseDir, $relativePath, $includeHiddenVirtualItems)
            : null;
        $virtualType = strtolower(trim((string) (($virtualConfig['kind'] ?? ($virtualConfig['type'] ?? '')))));
        $virtualTarget = is_array($virtualConfig) ? cmsConfiguredTreeLinkTarget($virtualConfig) : '';
        $virtualRenderedHtml = is_array($virtualConfig) ? trim((string) ($virtualConfig['renderedHtml'] ?? '')) : '';
        $confirmed = class_exists('PoffConfig') ? cmsFindConfirmedVirtualWork(
            $baseDir,
            $relativePath,
            $includeHiddenVirtualItems && function_exists('cmsIsEditorAuthenticated') && cmsIsEditorAuthenticated($baseDir),
            $virtualConfig
        ) : null;
        if ($confirmed !== null) {
            renderFileViewer($relativePath, $fullPath, null, $confirmed);
            return;
        }
        if (is_array($virtualConfig) && $virtualType !== 'folder' && ($virtualTarget !== '' || $virtualRenderedHtml !== '')) {
            renderFileViewer($relativePath, $fullPath, null, $virtualConfig);
            return;
        }
        http_response_code(404);
        echo 'Path not found.';
        return;
    }

    if (is_dir($fullPath)) {
        renderFolderViewer($relativePath, $fullPath, $baseDir);
        return;
    }

    renderFileViewer($relativePath, $fullPath);
}
