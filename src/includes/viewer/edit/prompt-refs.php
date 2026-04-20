<?php

require_once __DIR__ . '/../link-targets.php';

function cmsPromptViewerUrl(string $relativePath, bool $isFile): string
{
    return cmsBuildViewerHrefFromRelativePath($relativePath, $isFile);
}

function cmsPromptAssetUrl(string $relativePath, bool $isFile): string
{
    return cmsBuildAssetHrefFromRelativePath($relativePath, $isFile);
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

    $configuredType = strtolower(trim((string) ($item['type'] ?? 'file')));
    $explicitLinkTarget = cmsConfiguredTreeLinkTarget($item);
    $relativePath = cmsConfiguredTreeDisplayPath($basePath, $item, $name);
    if ($relativePath === '') {
        $relativePath = cmsConfiguredTreeFilesystemRelativePath($basePath, $item, $name);
    }

    $isFolder = $configuredType === 'folder';
    $kind = $isFolder
        ? 'folder'
        : (($configuredType === 'link' || $explicitLinkTarget !== '') ? 'link' : cmsPromptRefKind($name, $configuredType));
    $pageLink = $explicitLinkTarget !== ''
        ? $explicitLinkTarget
        : cmsPromptViewerUrl($relativePath, !$isFolder);
    $assetUrl = $explicitLinkTarget !== ''
        ? $explicitLinkTarget
        : cmsPromptAssetUrl($relativePath, !$isFolder);
    $linkUrl = cmsConfiguredTreeExternalLinkUrl($item);

    $result = [
        'name' => $name,
        'title' => (string) ($item['title'] ?? $name),
        'slug' => (string) ($item['slug'] ?? PoffConfig::slugify($name)),
        'type' => $isFolder ? 'folder' : 'file',
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

    if ($linkUrl !== '') {
        $result['linkUrl'] = $linkUrl;
    }

    return $result;
}
