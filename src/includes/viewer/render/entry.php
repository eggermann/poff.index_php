<?php
/**
 * Viewer rendering: HTML output for files and folders.
 */

function renderViewer(string $baseDir, string $requestedPath): void
{
    $relativePath = sanitizeRelativePath($requestedPath);

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
