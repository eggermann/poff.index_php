<?php

function cmsResolvePhysicalTarget(string $rootDir, string $relativePath): ?array
{
    $trimmed = trim($relativePath, "/\\");
    if (strpos($trimmed, '..') !== false) {
        return null;
    }
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        $resolved = realpath($base);
        if ($resolved === false) {
            return null;
        }
        return [
            'type' => 'folder',
            'dir' => $resolved,
        ];
    }

    $pathInBase = $base . DIRECTORY_SEPARATOR . $trimmed;
    $candidate = realpath($pathInBase);
    $exists = file_exists($pathInBase);
    $isSymlink = is_link($pathInBase);

    if ($candidate === false && !$exists) {
        return null;
    }

    $target = $candidate !== false ? $candidate : $pathInBase;
    if (strpos($target, $base) !== 0 && !$isSymlink) {
        return null;
    }

    if (is_dir($target)) {
        return ['type' => 'folder', 'dir' => $target];
    }
    if (is_file($target)) {
        return [
            'type' => 'file',
            'dir' => dirname($target),
            'file' => basename($target),
            'path' => $target,
        ];
    }

    return null;
}

function cmsResolveTarget(string $rootDir, string $relativePath): ?array
{
    $trimmed = trim($relativePath, "/\\");
    if (cmsIsVirtualLayoutPath($trimmed)) {
        $subjectRelativePath = cmsVirtualLayoutSubjectPath($trimmed);
        $subject = cmsResolvePhysicalTarget($rootDir, $subjectRelativePath);
        if ($subject === null) {
            return null;
        }

        return [
            'type' => 'layout',
            'dir' => $subject['dir'],
            'file' => $subject['file'] ?? null,
            'path' => $subject['path'] ?? null,
            'subjectType' => $subject['type'],
            'subjectRelativePath' => $subjectRelativePath,
            'virtualPath' => $trimmed,
        ];
    }

    return cmsResolvePhysicalTarget($rootDir, $trimmed);
}

function cmsNormalizeRelativeViewerPath(string $relativePath): string
{
    return trim(str_replace('\\', '/', $relativePath), '/');
}

function cmsResolveConfiguredTreeItemInList(array $tree, string $targetPath, string $prefix = ''): ?array
{
    $normalizedTarget = cmsNormalizeRelativeViewerPath($targetPath);
    if ($normalizedTarget === '') {
        return null;
    }

    $bestMatch = null;
    $bestScore = PHP_INT_MIN;
    foreach ($tree as $item) {
        if (!is_array($item) || (($item['visible'] ?? true) === false)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? $name), "/\\");
        $resolvedPath = cmsJoinBaseRelativePath($prefix, $rawPath);
        $candidates = array_values(array_unique(array_filter([
            cmsNormalizeRelativeViewerPath($resolvedPath),
            cmsNormalizeRelativeViewerPath($rawPath),
            cmsNormalizeRelativeViewerPath($name),
            cmsNormalizeRelativeViewerPath(basename($rawPath)),
        ])));

        if (in_array($normalizedTarget, $candidates, true)) {
            $item['resolvedPath'] = cmsNormalizeRelativeViewerPath($resolvedPath);
            $score = cmsConfiguredTreeItemScore($item);
            if ($score > $bestScore) {
                $bestMatch = $item;
                $bestScore = $score;
            }
        }

        if (is_array($item['children'] ?? null)) {
            $found = cmsResolveConfiguredTreeItemInList($item['children'], $normalizedTarget, $resolvedPath);
            if (is_array($found)) {
                $score = cmsConfiguredTreeItemScore($found);
                if ($score > $bestScore) {
                    $bestMatch = $found;
                    $bestScore = $score;
                }
            }
        }
    }

    return $bestMatch;
}

function cmsConfiguredTreeItemScore(array $item): int
{
    $score = 0;
    foreach (['title', 'description', 'kind', 'pageLink', 'pageUrl', 'linkUrl', 'baseUrl', 'renderedHtml', 'template', 'work', 'routeSlug', 'routePath'] as $key) {
        if (!array_key_exists($key, $item)) {
            continue;
        }
        $value = $item[$key];
        if (is_array($value)) {
            $score += $value === [] ? 0 : 4;
        } elseif (is_string($value)) {
            $score += trim($value) === '' ? 0 : 4;
        } elseif ($value !== null) {
            $score += 2;
        }
    }

    return $score;
}

function cmsResolveConfiguredTreeItem(string $rootDir, string $relativePath): ?array
{
    if (!class_exists('PoffConfig')) {
        return null;
    }

    $config = PoffConfig::ensure($rootDir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    $resolved = cmsResolveConfiguredTreeItemInList($tree, $relativePath);
    if (!is_array($resolved)) {
        return null;
    }

    $resolved['rootConfig'] = $config;
    return $resolved;
}

function cmsNormalizeRouteSlug(string $value): string
{
    return strtolower(trim(str_replace('\\', '/', $value), "/ \t\n\r\0\x0B"));
}

function cmsRouteSlugFromPath(string $value): string
{
    $path = trim(str_replace('\\', '/', $value), "/ \t\n\r\0\x0B");
    if ($path === '') {
        return '';
    }

    return class_exists('PoffConfig')
        ? PoffConfig::slugify(basename($path))
        : cmsNormalizeRouteSlug(basename($path));
}

function cmsResolveSlugRouteInTree(string $rootDir, string $relativeDir, string $slug, int $depth = 0): ?array
{
    if ($depth > 8 || !class_exists('PoffConfig')) {
        return null;
    }

    $dir = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($relativeDir !== '') {
        $dir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, trim($relativeDir, "/\\"));
    }
    if (!is_dir($dir)) {
        return null;
    }

    $config = PoffConfig::ensure($dir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item) || (($item['visible'] ?? true) === false)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $routePath = trim((string) ($item['routePath'] ?? $item['path'] ?? $name), "/\\");
        $routeSlug = trim((string) ($item['routeSlug'] ?? ''));
        if ($routeSlug === '') {
            $routeSlug = cmsRouteSlugFromPath($routePath !== '' ? $routePath : $name);
        }
        $itemSlug = cmsNormalizeRouteSlug((string) ($item['slug'] ?? PoffConfig::slugify((string) ($item['title'] ?? $name))));
        $itemPath = trim((string) ($item['path'] ?? $name), "/\\");
        $itemRouteSlug = cmsRouteSlugFromPath($itemPath);
        $relativePath = $relativeDir !== '' ? trim($relativeDir, "/\\") . '/' . $itemPath : $itemPath;
        $type = (string) ($item['type'] ?? 'file');

        if ($routeSlug === $slug || $itemSlug === $slug || $itemRouteSlug === $slug) {
            return [
                'path' => $relativePath,
                'type' => $type === 'folder' ? 'folder' : 'file',
                'isFile' => $type !== 'folder',
                'slug' => (string) ($item['slug'] ?? $itemSlug),
                'routeSlug' => $routeSlug,
                'routePath' => $routePath,
            ];
        }

        if ($type === 'folder') {
            $found = cmsResolveSlugRouteInTree($rootDir, $relativePath, $slug, $depth + 1);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function cmsHandleResolveRoute(string $rootDir): void
{
    $slug = cmsNormalizeRouteSlug((string) ($_GET['slug'] ?? $_GET['route'] ?? ''));
    if ($slug === '' || str_contains($slug, '..')) {
        cmsJsonResponse(['resolved' => false, 'error' => 'Invalid route.'], 400);
    }

    $resolved = cmsResolveSlugRouteInTree($rootDir, '', $slug);
    if ($resolved === null) {
        cmsJsonResponse(['resolved' => false, 'error' => 'Route not found.'], 404);
    }

    cmsJsonResponse(['resolved' => true] + $resolved);
}

function cmsHandleSnapshotRoute(string $rootDir): void
{
    $requestedPath = trim((string) ($_GET['file'] ?? $_GET['path'] ?? ''));
    if ($requestedPath === '' || str_contains($requestedPath, '..')) {
        cmsJsonResponse(['resolved' => false, 'error' => 'Invalid path.'], 400);
    }

    $resolved = cmsResolveTarget($rootDir, $requestedPath);
    if (!is_array($resolved) || ($resolved['type'] ?? '') !== 'file' || empty($resolved['path'])) {
        cmsJsonResponse(['resolved' => false, 'error' => 'Snapshot not available.'], 404);
    }

    $relativePath = trim((string) ($requestedPath !== '' ? $requestedPath : ($resolved['path'] ?? '')), "/\\");
    $snapshot = buildFileViewerSnapshotPayload(
        $relativePath,
        (string) $resolved['path'],
        null,
        class_exists('PoffConfig') ? cmsResolveConfiguredTreeItem($rootDir, $relativePath) : null
    );

    cmsJsonResponse([
        'resolved' => true,
        'kind' => $snapshot['type'],
        'renderedHtml' => $snapshot['renderedHtml'],
        'assetsBaseUrl' => cmsCurrentRequestBaseUrl(),
        'meta' => [
            'title' => $snapshot['title'],
            'type' => $snapshot['type'],
            'path' => $snapshot['path'],
        ],
        'context' => $snapshot['snapshotContext'],
    ]);
}
