<?php

trait WorktypeContextTrait
{
    use WorktypeDefinitionsTrait;
    use WorktypeStateTrait;

    private static function buildRenderContext(string $kind, array $ctx, array $work, array $layout): array
    {
        $path = (string) ($ctx['path'] ?? '');
        $viewerHref = (string) ($ctx['viewerHref'] ?? self::defaultViewerHref($kind, $path));
        $rawHref = (string) ($ctx['rawHref'] ?? self::defaultAssetHref($kind, $path));
        $directoryPath = $kind === 'folder'
            ? trim($path, '/')
            : trim((string) preg_replace('~/[^/]+$~', '', $path), '/');
        $parentPath = '';
        if ($directoryPath !== '') {
            $parentPath = trim(dirname($directoryPath), '/.');
        }
        $directoryPageLink = self::defaultViewerHref('folder', $directoryPath);
        $parentPageLink = $directoryPath !== '' ? self::defaultViewerHref('folder', $parentPath) : '';

        $context = [
            'kind' => $kind,
            'path' => $path,
            'directoryPath' => $directoryPath,
            'directoryPageLink' => $directoryPageLink,
            'showDirectoryPageLink' => $directoryPath !== '',
            'parentPath' => $parentPath,
            'parentPageLink' => $parentPageLink,
            'mimeType' => (string) ($ctx['mimeType'] ?? ''),
            'name' => (string) ($ctx['name'] ?? ''),
            'title' => (string) ($ctx['title'] ?? ($ctx['name'] ?? '')),
            'description' => (string) ($ctx['description'] ?? ''),
            'descriptionHtml' => (string) ($ctx['descriptionHtml'] ?? ''),
            'linkUrl' => (string) ($ctx['linkUrl'] ?? ''),
            'slug' => (string) ($ctx['slug'] ?? ''),
            'viewerHref' => $viewerHref,
            'viewUrl' => (string) ($ctx['viewUrl'] ?? $viewerHref),
            'workUrl' => (string) ($ctx['workUrl'] ?? $viewerHref),
            'pageLink' => (string) ($ctx['pageLink'] ?? ($ctx['workUrl'] ?? $viewerHref)),
            'pageUrl' => (string) ($ctx['pageUrl'] ?? ($ctx['viewUrl'] ?? $viewerHref)),
            'rawHref' => $rawHref,
            'assetUrl' => (string) ($ctx['assetUrl'] ?? $rawHref),
            'assetLink' => (string) ($ctx['assetLink'] ?? ($ctx['assetUrl'] ?? $rawHref)),
            'srcUrl' => (string) ($ctx['srcUrl'] ?? ($ctx['assetUrl'] ?? $rawHref)),
            'sourceUrl' => (string) ($ctx['sourceUrl'] ?? ($ctx['srcUrl'] ?? ($ctx['assetUrl'] ?? $rawHref))),
            'layout' => $layout,
            'work' => $work,
            'isFolder' => $kind === 'folder',
            'isImage' => $kind === 'image',
            'isVideo' => $kind === 'video',
            'isAudio' => $kind === 'audio',
            'isPdf' => $kind === 'pdf',
            'isText' => in_array($kind, ['text', 'htaccess'], true),
            'isLink' => $kind === 'link',
            'isOther' => $kind === 'other',
        ];

        $categories = self::normalizeCategories($work['categories'] ?? ($work['category'] ?? null), $kind);
        $context['categories'] = $categories;
        $context['category'] = $categories;
        $context['work']['categories'] = $categories;
        $context['work']['category'] = $categories;

        foreach ($ctx as $key => $value) {
            if ($key === 'work') {
                continue;
            }
            $context[$key] = $value;
        }

        foreach ($work as $key => $value) {
            if ($key === 'fields' && is_array($value)) {
                $context['fields'] = $value;
                $context['work']['fields'] = $value;
                continue;
            }
            if ($key === 'categories' || $key === 'category') {
                continue;
            }
            if (is_bool($value)) {
                $context[$key] = $value;
                $context[$key . 'Attr'] = $value ? $key : '';
                $context['work'][$key . 'Attr'] = $value ? $key : '';
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $context[$key] = $value;
                $context['work'][$key] = $value;
            }
        }

        return self::hydrateRenderCollections($context);
    }

    private static function defaultViewerHref(string $kind, string $path): string
    {
        if ($kind === 'folder') {
            return '?view=1&path=' . rawurlencode($path);
        }

        if ($path === '') {
            return '';
        }

        return '?view=1&file=' . rawurlencode($path);
    }

    private static function defaultAssetHref(string $kind, string $path): string
    {
        if ($kind === 'folder') {
            return '?path=' . rawurlencode($path);
        }

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);
        $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

        return implode('/', $encoded);
    }

    private static function hydrateRenderCollections(array $context): array
    {
        foreach ([
            'tree',
            'items',
            'allItems',
            'allFiles',
            'allFolders',
            'allImages',
            'allVideos',
            'allAudio',
            'allPdfs',
            'allTexts',
            'allLinks',
            'allOther',
        ] as $key) {
            if (isset($context[$key]) && is_array($context[$key])) {
                $context[$key] = self::hydrateRenderItems($context[$key]);
            }
        }

        if (isset($context['workTree']) && is_array($context['workTree'])) {
            $context['workTree'] = self::hydrateRenderItem($context['workTree']);
        }

        return $context;
    }

    private static function hydrateRenderItems(array $items): array
    {
        $hydrated = [];
        foreach ($items as $index => $item) {
            $hydrated[$index] = is_array($item) ? self::hydrateRenderItem($item) : $item;
        }

        return $hydrated;
    }

    private static function hydrateRenderItem(array $item): array
    {
        $path = (string) ($item['path'] ?? $item['relativePath'] ?? '');
        $isFolder = array_key_exists('isFolder', $item)
            ? (bool) $item['isFolder']
            : (($item['type'] ?? $item['kind'] ?? '') === 'folder');
        $kind = $isFolder ? 'folder' : 'file';
        $viewerHref = (string) ($item['viewerHref'] ?? self::defaultViewerHref($kind, $path));
        $rawHref = (string) ($item['rawHref'] ?? self::defaultAssetHref($kind, $path));

        $item['viewerHref'] = $viewerHref;
        $item['viewUrl'] = (string) ($item['viewUrl'] ?? $viewerHref);
        $item['workUrl'] = (string) ($item['workUrl'] ?? $viewerHref);
        $item['pageLink'] = (string) ($item['pageLink'] ?? ($item['workUrl'] ?? $viewerHref));
        $item['pageUrl'] = (string) ($item['pageUrl'] ?? ($item['viewUrl'] ?? $viewerHref));
        $item['rawHref'] = $rawHref;
        $item['assetUrl'] = (string) ($item['assetUrl'] ?? $rawHref);
        $item['assetLink'] = (string) ($item['assetLink'] ?? ($item['assetUrl'] ?? $rawHref));
        $item['srcUrl'] = (string) ($item['srcUrl'] ?? ($item['assetUrl'] ?? $rawHref));
        $item['sourceUrl'] = (string) ($item['sourceUrl'] ?? ($item['srcUrl'] ?? ($item['assetUrl'] ?? $rawHref)));

        if (isset($item['children']) && is_array($item['children'])) {
            $item['children'] = self::hydrateRenderItems($item['children']);
        }

        return $item;
    }
}
