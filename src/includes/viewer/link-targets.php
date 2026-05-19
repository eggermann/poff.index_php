<?php

function cmsJoinBaseRelativePath(string $basePath, string $path): string
{
    $trimmedPath = trim($path, "/\\");
    if ($trimmedPath === '') {
        return trim($basePath, "/\\");
    }

    $trimmedBase = trim($basePath, "/\\");
    if ($trimmedBase === '' || $trimmedPath === $trimmedBase || str_starts_with($trimmedPath, $trimmedBase . '/')) {
        return $trimmedPath;
    }

    return $trimmedBase . '/' . $trimmedPath;
}

function cmsIsHashLinkTarget(string $value): bool
{
    return str_starts_with(trim($value), '#');
}

function cmsIsExternalLinkTarget(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return false;
    }

    if (str_starts_with($trimmed, '//')) {
        return true;
    }

    return preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) === 1;
}

function cmsIsCmsQueryLinkTarget(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '' || !str_starts_with($trimmed, '?')) {
        return false;
    }

    parse_str(ltrim($trimmed, '?'), $params);

    return array_key_exists('file', $params)
        || array_key_exists('path', $params)
        || (($params['view'] ?? '') === '1');
}

function cmsParseCmsQueryLinkTarget(string $value): ?array
{
    if (!cmsIsCmsQueryLinkTarget($value)) {
        return null;
    }

    parse_str(ltrim(trim($value), '?'), $params);
    $isFile = array_key_exists('file', $params);
    $pathValue = $isFile ? ($params['file'] ?? '') : ($params['path'] ?? '');
    if (!is_scalar($pathValue)) {
        $pathValue = '';
    }

    return [
        'path' => trim((string) $pathValue, "/\\"),
        'isFile' => $isFile,
    ];
}

function cmsIsSpecialLinkTarget(string $value): bool
{
    return cmsIsCmsQueryLinkTarget($value)
        || cmsIsExternalLinkTarget($value)
        || cmsIsHashLinkTarget($value);
}

function cmsConfiguredTreeLinkTarget(array $item): string
{
    foreach (['pageLink', 'pageUrl', 'viewUrl', 'workUrl', 'viewerHref', 'linkUrl', 'link', 'url'] as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? ''));
    if ($rawPath !== '' && cmsIsSpecialLinkTarget($rawPath)) {
        return $rawPath;
    }

    return '';
}

function cmsConfiguredTreeExternalLinkUrl(array $item): string
{
    foreach (['linkUrl', 'link', 'url'] as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $target = cmsConfiguredTreeLinkTarget($item);
    return cmsIsExternalLinkTarget($target) ? $target : '';
}

function cmsConfiguredTreeDirectLinkTarget(array $item): string
{
    foreach (['link', 'url'] as $key) {
        $value = trim((string) ($item[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? ''));
    if ($rawPath !== '' && (cmsIsExternalLinkTarget($rawPath) || cmsIsCmsQueryLinkTarget($rawPath) || cmsIsHashLinkTarget($rawPath))) {
        return $rawPath;
    }

    return '';
}

function cmsConfiguredTreeFilesystemRelativePath(string $basePath, array $item, string $fallbackName = ''): string
{
    $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? ''), "/\\");
    if ($rawPath !== '' && !cmsIsSpecialLinkTarget($rawPath)) {
        return cmsJoinBaseRelativePath($basePath, $rawPath);
    }

    $fallback = trim($fallbackName, "/\\");
    if ($fallback === '') {
        return '';
    }

    return cmsJoinBaseRelativePath($basePath, $fallback);
}

function cmsConfiguredTreeDisplayPath(string $basePath, array $item, string $fallbackName = ''): string
{
    $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? ''));
    if ($rawPath !== '') {
        if (cmsIsCmsQueryLinkTarget($rawPath)) {
            $parsed = cmsParseCmsQueryLinkTarget($rawPath);
            return trim((string) ($parsed['path'] ?? ''), "/\\");
        }
        if (cmsIsExternalLinkTarget($rawPath) || cmsIsHashLinkTarget($rawPath)) {
            return $rawPath;
        }

        return cmsJoinBaseRelativePath($basePath, trim($rawPath, "/\\"));
    }

    $target = cmsConfiguredTreeLinkTarget($item);
    if ($target !== '') {
        if (cmsIsCmsQueryLinkTarget($target)) {
            $parsed = cmsParseCmsQueryLinkTarget($target);
            return trim((string) ($parsed['path'] ?? ''), "/\\");
        }
        return $target;
    }

    $fallback = trim($fallbackName, "/\\");
    if ($fallback === '') {
        return '';
    }

    return cmsJoinBaseRelativePath($basePath, $fallback);
}

function cmsBuildViewerHrefFromRelativePath(string $relativePath, bool $isFile): string
{
    return $isFile
        ? '?view=1&file=' . rawurlencode($relativePath)
        : '?view=1&path=' . rawurlencode($relativePath);
}

function cmsBuildAssetHrefFromRelativePath(string $relativePath, bool $isFile): string
{
    if (!$isFile) {
        return '?path=' . rawurlencode($relativePath);
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}
