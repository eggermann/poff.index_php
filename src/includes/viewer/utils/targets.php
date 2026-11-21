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

    $physical = cmsResolvePhysicalTarget($rootDir, $trimmed);
    if ($physical !== null) {
        return $physical;
    }

    $configured = cmsResolveConfiguredTreeItem($rootDir, $trimmed, true);
    if (!is_array($configured)) {
        return null;
    }

    $resolvedPath = trim((string) ($configured['resolvedPath'] ?? $configured['path'] ?? $trimmed), "/\\");
    $configuredType = (string) ($configured['type'] ?? 'file');
    if ($configuredType === 'folder' || $resolvedPath === '') {
        return null;
    }

    $parentRelativePath = dirname($resolvedPath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentTarget = cmsResolvePhysicalTarget($rootDir, $parentRelativePath);
    if (!is_array($parentTarget) || ($parentTarget['type'] ?? '') !== 'folder') {
        return null;
    }

    return [
        'type' => 'file',
        'dir' => (string) $parentTarget['dir'],
        'file' => basename($resolvedPath),
        'path' => rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resolvedPath),
        'virtual' => true,
        'configured' => $configured,
    ];
}

function cmsNormalizeRelativeViewerPath(string $relativePath): string
{
    return trim(str_replace('\\', '/', $relativePath), '/');
}

function cmsResolveConfiguredTreeItemInList(array $tree, string $targetPath, string $prefix = '', bool $includeHidden = false): ?array
{
    $normalizedTarget = cmsNormalizeRelativeViewerPath($targetPath);
    if ($normalizedTarget === '') {
        return null;
    }

    $bestMatch = null;
    $bestScore = PHP_INT_MIN;
    foreach ($tree as $item) {
        if (!is_array($item) || (!$includeHidden && (($item['visible'] ?? true) === false))) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $rawPath = trim((string) ($item['path'] ?? $item['relativePath'] ?? $name), "/\\");
        $resolvedPath = cmsJoinBaseRelativePath($prefix, $rawPath);
        $slug = trim((string) ($item['slug'] ?? ''));
        $routeSlug = trim((string) ($item['routeSlug'] ?? ''));
        if ($routeSlug === '') {
            $routeBase = $resolvedPath !== '' ? $resolvedPath : ($rawPath !== '' ? $rawPath : $name);
            $routeSlug = cmsRouteSlugFromPath($routeBase);
        }
        $candidates = array_values(array_unique(array_filter([
            cmsNormalizeRelativeViewerPath($resolvedPath),
            cmsNormalizeRelativeViewerPath($rawPath),
            cmsNormalizeRelativeViewerPath($name),
            cmsNormalizeRelativeViewerPath(basename($rawPath)),
            cmsNormalizeRelativeViewerPath($slug),
            cmsNormalizeRelativeViewerPath($routeSlug),
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
            $found = cmsResolveConfiguredTreeItemInList($item['children'], $normalizedTarget, $resolvedPath, $includeHidden);
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

function cmsResolveConfiguredTreeItem(string $rootDir, string $relativePath, bool $includeHidden = false): ?array
{
    if (!class_exists('PoffConfig')) {
        return null;
    }

    $config = PoffConfig::ensure($rootDir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    $resolved = cmsResolveConfiguredTreeItemInList($tree, $relativePath, '', $includeHidden);
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

function cmsResolveSlugRouteInTree(string $rootDir, string $relativeDir, string $slug, int $depth = 0, bool $includeHidden = false): ?array
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
        if (!is_array($item) || (!$includeHidden && (($item['visible'] ?? true) === false))) {
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
            $found = cmsResolveSlugRouteInTree($rootDir, $relativePath, $slug, $depth + 1, $includeHidden);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function cmsHandleNodeIdentityRoute(string $rootDir): void
{
    header('Cache-Control: no-store');
    try {
        $nodeId = PoffNodeIdentity::read($rootDir);
    } catch (RuntimeException) {
        cmsJsonResponse(['error' => 'Node identity is invalid.'], 500);
    }
    if ($nodeId === null) {
        cmsJsonResponse(['error' => 'Node identity not found.'], 404);
    }
    cmsJsonResponse(['nodeId' => $nodeId]);
}

function cmsHandleResolveRoute(string $rootDir): void
{
    $workId = trim((string) ($_GET['workId'] ?? ''));
    if ($workId !== '') {
        $nodeId = trim((string) ($_GET['nodeId'] ?? ''));
        if (
            PoffNodeIdentity::normalizeId($nodeId) === ''
            || preg_match('/^poff_[a-z0-9._-]{1,120}$/iD', $workId) !== 1
        ) {
            cmsJsonResponse(['resolved' => false, 'error' => 'Invalid Work reference.'], 400);
        }

        try {
            $localNodeId = class_exists('PoffNodeIdentity') ? PoffNodeIdentity::read($rootDir) : null;
        } catch (Throwable) {
            cmsJsonResponse(['resolved' => false, 'error' => 'Node identity is invalid.'], 500);
        }
        if ($localNodeId === null || !hash_equals($localNodeId, $nodeId)) {
            cmsJsonResponse(['resolved' => false, 'error' => 'Node or Work not found.'], 404);
        }
        if (!class_exists('PoffConfig')) {
            cmsJsonResponse(['resolved' => false, 'error' => 'Work resolver unavailable.'], 503);
        }

        $includeHiddenWorks = (string) ($_GET['edit'] ?? '') === 'true'
            && function_exists('cmsIsEditorAuthenticated')
            && cmsIsEditorAuthenticated($rootDir);
        $matches = PoffConfig::findWorkLocationsById($rootDir, $workId, $includeHiddenWorks);
        if ($matches === []) {
            cmsJsonResponse(['resolved' => false, 'error' => 'Node or Work not found.'], 404);
        }
        if (count($matches) > 1) {
            cmsJsonResponse([
                'resolved' => false,
                'ambiguous' => true,
                'error' => 'Work ID matches more than one visible Work.',
                'nodeId' => $nodeId,
                'workId' => $workId,
                'matches' => $matches,
            ], 409);
        }

        $match = $matches[0];
        if (($_GET['open'] ?? '') === '1') {
            $target = $match['type'] === 'folder' ? 'path' : 'file';
            $url = '?view=1&' . $target . '=' . rawurlencode((string) $match['path']);
            if ($includeHiddenWorks) {
                $url .= '&edit=true';
            }
            header('Cache-Control: no-store');
            header('Location: ' . $url, true, 302);
            exit;
        }
        cmsJsonResponse([
            'resolved' => true,
            'nodeId' => $nodeId,
            'workId' => $workId,
            'path' => (string) $match['path'],
            'type' => (string) $match['type'],
            'isFile' => (string) $match['type'] !== 'folder',
        ]);
    }

    $slug = cmsNormalizeRouteSlug((string) ($_GET['slug'] ?? $_GET['route'] ?? ''));
    if ($slug === '' || str_contains($slug, '..')) {
        cmsJsonResponse(['resolved' => false, 'error' => 'Invalid route.'], 400);
    }

    $includeHidden = (string) ($_GET['edit'] ?? '') === 'true'
        && function_exists('cmsIsEditorAuthenticated')
        && cmsIsEditorAuthenticated($rootDir);
    $resolved = cmsResolveSlugRouteInTree($rootDir, '', $slug, 0, $includeHidden);
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
