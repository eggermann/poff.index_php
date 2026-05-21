<?php
/**
 * Viewer rendering: HTML output for files and folders.
 */

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
        $virtualConfig = class_exists('PoffConfig')
            ? cmsResolveConfiguredTreeItem($baseDir, $relativePath)
            : null;
        $virtualType = strtolower(trim((string) (($virtualConfig['kind'] ?? ($virtualConfig['type'] ?? '')))));
        $virtualTarget = is_array($virtualConfig) ? cmsConfiguredTreeLinkTarget($virtualConfig) : '';
        $virtualRenderedHtml = is_array($virtualConfig) ? trim((string) ($virtualConfig['renderedHtml'] ?? '')) : '';
        if (is_array($virtualConfig) && $virtualType !== 'folder' && ($virtualTarget !== '' || $virtualRenderedHtml !== '')) {
            renderFileViewer($relativePath, $fullPath, null, $virtualConfig);
            return;
        }
        http_response_code(404);
        echo 'Path not found.';
        return;
    }

    if (is_dir($fullPath)) {
        renderFolderViewer($relativePath, $fullPath);
        return;
    }

    renderFileViewer($relativePath, $fullPath);
}
