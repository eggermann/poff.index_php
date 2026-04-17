<?php

function cmsPromptViewerUrl(string $relativePath, bool $isFile): string
{
    return $isFile
        ? '?view=1&file=' . rawurlencode($relativePath)
        : '?view=1&path=' . rawurlencode($relativePath);
}

function cmsPromptAssetUrl(string $relativePath, bool $isFile): string
{
    if (!$isFile) {
        return '?path=' . rawurlencode($relativePath);
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}

function cmsPromptEncodeRelativePath(string $path): string
{
    $parts = explode('/', str_replace('\\', '/', trim($path, "/\\")));
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if ($parts === []) {
        return '';
    }

    return implode('/', array_map(static fn(string $part): string => rawurlencode($part), $parts));
}

function cmsPromptTemplateTarget(string $relativePath, bool $isFile, string $section): string
{
    $layoutPath = PoffConfig::relativeLayoutPath($relativePath, $isFile);
    $sectionFile = $section === 'works' ? 'works.hbs' : 'work.hbs';

    return $layoutPath . '/' . $sectionFile;
}

function cmsPromptLayoutTemplateTarget(string $relativePath, bool $isFile): string
{
    return PoffConfig::relativeLayoutPath($relativePath, $isFile) . '/template.hbs';
}

function cmsPromptRefKind(string $name, string $type): string
{
    if ($type === 'folder') {
        return 'folder';
    }

    return MediaType::classifyExtension($name);
}

function cmsBuildPromptRef(string $basePath, array $item): ?array
{
    $name = trim((string) ($item['name'] ?? $item['path'] ?? ''));
    if ($name === '') {
        return null;
    }

    $relativePath = trim((string) ($item['path'] ?? $name), "/\\");
    if ($relativePath === '') {
        $relativePath = $name;
    }
    if ($basePath !== '') {
        $normalizedBase = trim($basePath, "/\\");
        if (!str_starts_with($relativePath, $normalizedBase . '/') && $relativePath !== $normalizedBase) {
            $relativePath = $normalizedBase . '/' . ltrim($relativePath, "/\\");
        }
    }

    $type = (string) ($item['type'] ?? 'file');
    $isFolder = $type === 'folder';
    $kind = cmsPromptRefKind($name, $type);
    $pageLink = cmsPromptViewerUrl($relativePath, !$isFolder);
    $assetUrl = cmsPromptAssetUrl($relativePath, !$isFolder);

    return [
        'name' => $name,
        'title' => (string) ($item['title'] ?? $name),
        'slug' => (string) ($item['slug'] ?? PoffConfig::slugify($name)),
        'type' => $type,
        'kind' => $kind,
        'path' => $relativePath,
        'pageLink' => $pageLink,
        'pageUrl' => $pageLink,
        'workUrl' => $pageLink,
        'viewUrl' => $pageLink,
        'viewerHref' => $pageLink,
        'assetUrl' => $assetUrl,
        'assetLink' => $assetUrl,
        'rawHref' => $assetUrl,
        'srcUrl' => $assetUrl,
        'sourceUrl' => $assetUrl,
        'isFolder' => $isFolder,
        'isFile' => !$isFolder,
        'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
    ];
}
