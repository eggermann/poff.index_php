<?php
/**
 * Shared CMS utilities: JSON responses, config helpers, HTTP helpers.
 */

require_once __DIR__ . '/../project-root.php';
require_once __DIR__ . '/../edit-mode.php';

const CMS_HTTP_TIMEOUT_SECONDS = 300;

function cmsJsonResponse(array $payload, int $status = 200): void
{
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function cmsReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function cmsIsVirtualLayoutPath(string $relativePath): bool
{
    $trimmed = trim($relativePath, "/\\");
    return $trimmed === '.layout' || str_ends_with($trimmed, '/.layout');
}

function cmsIsHiddenSystemEntry(string $entry): bool
{
    return in_array($entry, [
        'poff.config.json',
        '.poff-auth.php',
        'auth.config.php',
        '.works',
        '.layout',
        '.DS_Store',
        'Thumbs.db',
        '.git',
        '.idea',
        'node_modules',
        '.edit.allow',
        'edit.allow',
        '.edit.not-allow',
        'edit.not-allow',
    ], true);
}

function cmsVirtualLayoutSubjectPath(string $relativePath): string
{
    $trimmed = trim($relativePath, "/\\");
    if ($trimmed === '.layout') {
        return '';
    }
    if (str_ends_with($trimmed, '/.layout')) {
        return substr($trimmed, 0, -strlen('/.layout'));
    }

    return $trimmed;
}

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
    $insideBase = strpos($target, $base) === 0;

    // Allow symlinks even when they point outside the base directory.
    if (!$insideBase && !$isSymlink) {
        return null;
    }

    if (is_dir($target)) {
        return [
            'type' => 'folder',
            'dir' => $target,
        ];
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
            continue;
        }
        if (is_string($value)) {
            $score += trim($value) === '' ? 0 : 4;
            continue;
        }
        if ($value !== null) {
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

function cmsExtractHtmlBodyFragment(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (preg_match('/<body\b[^>]*>(.*)<\/body>/is', $html, $matches) === 1) {
        $body = trim((string) ($matches[1] ?? ''));
        if ($body !== '') {
            return $body;
        }
    }

    return $html;
}

function cmsExtractPreferredRemoteRenderedHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $needsWrapperParsing = stripos($html, 'poff-default-layout') !== false
        || stripos($html, 'appShell') !== false
        || stripos($html, 'contentFrame') !== false
        || stripos($html, 'viewer') !== false;
    if (!$needsWrapperParsing) {
        return $html;
    }

    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $contentFrameNodes = $xpath->query('//*[@id="contentFrame"]');
        if ($contentFrameNodes instanceof DOMNodeList && $contentFrameNodes->length > 0) {
            $node = $contentFrameNodes->item(0);
            if ($node instanceof DOMNode) {
                $viewer = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " viewer ")]', $node);
                if ($viewer instanceof DOMNodeList && $viewer->length > 0) {
                    $viewerNode = $viewer->item(0);
                    if ($viewerNode instanceof DOMNode) {
                        $preferred = cmsDomNodeInnerHtml($viewerNode, $document);
                        if ($preferred !== '') {
                            return $preferred;
                        }
                    }
                }

                return '';
            }
        }

        $mainNodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " poff-default-layout__main ")]');
        if ($mainNodes instanceof DOMNodeList && $mainNodes->length > 0) {
            $node = $mainNodes->item(0);
            if ($node instanceof DOMNode) {
                $preferred = cmsDomNodeInnerHtml($node, $document);
                if ($preferred !== '') {
                    return $preferred;
                }
            }
        }

        $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " poff-default-layout ")]');
        if ($nodes instanceof DOMNodeList && $nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node instanceof DOMNode) {
                $main = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " poff-default-layout__main ")]', $node);
                if ($main instanceof DOMNodeList && $main->length > 0) {
                    $mainNode = $main->item(0);
                    if ($mainNode instanceof DOMNode) {
                        $preferred = cmsDomNodeInnerHtml($mainNode, $document);
                        if ($preferred !== '') {
                            return $preferred;
                        }
                    }
                }
                $preferred = cmsDomNodeInnerHtml($node, $document);
                if ($preferred !== '') {
                    return $preferred;
                }
            }
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $html;
}

function cmsDomNodeInnerHtml(DOMNode $node, DOMDocument $document): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $fragment = $document->saveHTML($child);
        if (is_string($fragment) && $fragment !== '') {
            $html .= $fragment;
        }
    }

    return trim($html);
}

function cmsStripRemoteAppChrome(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $selectors = [
            '//*[@id="appSidebar"]',
            '//*[@id="sidebarToggle"]',
            '//*[@id="editPanel"]',
            '//*[@id="editDrawer"]',
            '//*[@id="promptDock"]',
            '//*[@id="iframeLoading"]',
            '//*[@id="sidebarLoading"]',
            '//*[@id="editActionsMenu"]',
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " app-edit-toggle-wrap ")]',
        ];

        foreach ($selectors as $query) {
            $nodes = $xpath->query($query);
            if (!($nodes instanceof DOMNodeList)) {
                continue;
            }
            for ($index = $nodes->length - 1; $index >= 0; $index--) {
                $node = $nodes->item($index);
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMNode) {
            $html = cmsDomNodeInnerHtml($body, $document);
        } else {
            $html = trim($document->saveHTML());
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $html !== '' ? $html : '';
}

function cmsResolveRemoteRenderedHtml(array $item): string
{
    $baseUrl = trim((string) ($item['baseUrl'] ?? ($item['linkUrl'] ?? ($item['pageLink'] ?? ($item['pageUrl'] ?? '')))));

    $snapshotUrl = cmsResolveRemoteRenderedSnapshotSourceUrl($baseUrl, $item);
    if ($snapshotUrl !== '' && cmsIsExternalLinkTarget($snapshotUrl)) {
        $response = cmsHttpGet($snapshotUrl, ['Accept: application/json']);
        if (is_array($response) && ($response['ok'] ?? false)) {
            $decoded = json_decode((string) ($response['body'] ?? ''), true);
            $renderedSnapshot = cmsRenderRemoteSnapshotHtml(is_array($decoded) ? $decoded : []);
            if ($renderedSnapshot !== '') {
                return $renderedSnapshot;
            }
        }
    }

    $sourceUrl = cmsResolveRemoteRenderedSourceUrl($baseUrl, $item);
    if ($sourceUrl !== '' && cmsIsExternalLinkTarget($sourceUrl)) {
        $response = cmsHttpGet($sourceUrl);
        if (is_array($response) && ($response['ok'] ?? false)) {
            $liveHtml = cmsNormalizeRenderedHtmlBaseUrl(
                cmsStripRemoteAppChrome(cmsExtractPreferredRemoteRenderedHtml(cmsExtractHtmlBodyFragment((string) ($response['body'] ?? '')))),
                $baseUrl
            );
            if ($liveHtml !== '') {
                return $liveHtml;
            }
        }
    }

    $renderedHtml = trim((string) ($item['renderedHtml'] ?? ''));
    if ($renderedHtml !== '') {
        return cmsNormalizeRenderedHtmlBaseUrl(
            cmsStripRemoteAppChrome(cmsExtractPreferredRemoteRenderedHtml($renderedHtml)),
            $baseUrl
        );
    }

    return '';
}

function cmsRenderRemoteSnapshotHtml(array $snapshot): string
{
    $context = is_array($snapshot['context'] ?? null) ? $snapshot['context'] : [];
    if ($context === []) {
        return '';
    }

    $kind = trim((string) ($snapshot['kind'] ?? ($context['kind'] ?? ($context['work']['type'] ?? ''))));
    if ($kind === '') {
        return '';
    }

    $renderedHtml = Worktype::render($kind, $context);
    if ($renderedHtml === '') {
        return '';
    }

    $baseUrl = trim((string) ($context['baseUrl'] ?? ($snapshot['baseUrl'] ?? '')));
    if ($baseUrl !== '') {
        $renderedHtml = cmsNormalizeRenderedHtmlBaseUrl($renderedHtml, $baseUrl);
    }

    return cmsStripRemoteAppChrome($renderedHtml);
}

function cmsParseExternalCmsViewerUrl(string $url): ?array
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts)) {
        return null;
    }

    $query = trim((string) ($parts['query'] ?? ''));
    if ($query === '') {
        return null;
    }

    parse_str($query, $params);
    $isFile = array_key_exists('file', $params);
    $path = $isFile ? ($params['file'] ?? '') : ($params['path'] ?? '');
    if (!is_scalar($path)) {
        $path = '';
    }
    $path = trim((string) $path, "/\\");
    if ($path === '') {
        return null;
    }

    if (!array_key_exists('view', $params) && !$isFile) {
        return null;
    }

    return [
        'path' => $path,
        'isFile' => $isFile,
    ];
}

function cmsResolveRemoteRenderedSnapshotSourceUrl(string $sourceUrl, array $item): string
{
    $trimmed = trim($sourceUrl);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = (string) $parts['scheme'];
    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $basePath = trim((string) ($parts['path'] ?? '/'));
    if ($basePath === '') {
        $basePath = '/';
    }
    $basePath = preg_replace('~/index\.php$~i', '/', $basePath);
    if (!is_string($basePath) || $basePath === '') {
        $basePath = '/';
    }

    $fragment = trim((string) ($parts['fragment'] ?? ''));
    if ($fragment !== '' && str_starts_with($fragment, '/')) {
        $slug = trim(ltrim($fragment, '/'), "/\\");
        if ($slug !== '') {
            $origin = $scheme . '://' . $host . $port;
            $resolveUrl = $origin . $basePath . '?ajax=resolve&slug=' . rawurlencode($slug);
            $response = cmsHttpGet($resolveUrl);
            if (is_array($response) && ($response['ok'] ?? false)) {
                $payload = json_decode((string) ($response['body'] ?? ''), true);
                if (is_array($payload) && ($payload['resolved'] ?? false)) {
                    $resolvedPath = trim((string) ($payload['path'] ?? ''), "/\\");
                    if ($resolvedPath !== '') {
                        $isFile = array_key_exists('isFile', $payload)
                            ? (bool) $payload['isFile']
                            : trim((string) ($payload['type'] ?? 'file')) !== 'folder';
                        $query = $isFile
                            ? '?ajax=snapshot&file=' . rawurlencode($resolvedPath)
                            : '?ajax=snapshot&path=' . rawurlencode($resolvedPath);

                        return $origin . $basePath . $query;
                    }
                }
            }
        }
    }

    $cmsQuery = cmsParseExternalCmsViewerUrl($trimmed);
    if (is_array($cmsQuery)) {
        $query = '?ajax=snapshot' . ($cmsQuery['isFile'] ? '&file=' : '&path=') . rawurlencode((string) $cmsQuery['path']);
        return $scheme . '://' . $host . $port . $basePath . $query;
    }

    return '';
}

function cmsResolveRemoteRenderedSourceUrl(string $sourceUrl, array $item): string
{
    $trimmed = trim($sourceUrl);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $trimmed;
    }

    $fragment = trim((string) ($parts['fragment'] ?? ''));
    if ($fragment === '' || !str_starts_with($fragment, '/')) {
        return $trimmed;
    }

    $resolvedHashRoute = cmsResolveRemoteHashRouteSourceUrl($parts, $fragment);
    if ($resolvedHashRoute !== '') {
        return $resolvedHashRoute;
    }

    $targetPath = trim(ltrim($fragment, '/'), "/\\");
    if ($targetPath === '') {
        return $trimmed;
    }

    $isFolder = (($item['isFolder'] ?? false) === true)
        || trim((string) ($item['type'] ?? '')) === 'folder';
    $viewerQuery = $isFolder
        ? '?view=1&path=' . rawurlencode($targetPath)
        : '?view=1&file=' . rawurlencode($targetPath);

    $resolved = $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? '/')
        . $viewerQuery;

    return $resolved;
}

function cmsResolveRemoteHashRouteSourceUrl(array $parts, string $fragment): string
{
    $slug = trim(ltrim($fragment, '/'), "/\\");
    $scheme = trim((string) ($parts['scheme'] ?? ''));
    $host = trim((string) ($parts['host'] ?? ''));
    if ($slug === '' || $scheme === '' || $host === '') {
        return '';
    }

    $basePath = trim((string) ($parts['path'] ?? '/'));
    if ($basePath === '') {
        $basePath = '/';
    }
    $basePath = preg_replace('~/index\.php$~i', '/', $basePath);
    if (!is_string($basePath) || $basePath === '') {
        $basePath = '/';
    }

    $origin = $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $resolveUrl = $origin . $basePath . '?ajax=resolve&slug=' . rawurlencode($slug);
    $response = cmsHttpGet($resolveUrl);
    if (!is_array($response) || !($response['ok'] ?? false)) {
        return '';
    }

    $payload = json_decode((string) ($response['body'] ?? ''), true);
    if (!is_array($payload) || !($payload['resolved'] ?? false)) {
        return '';
    }

    $resolvedPath = trim((string) ($payload['path'] ?? ''), "/\\");
    if ($resolvedPath === '') {
        return '';
    }

    $isFile = array_key_exists('isFile', $payload)
        ? (bool) $payload['isFile']
        : trim((string) ($payload['type'] ?? 'file')) !== 'folder';
    $query = $isFile
        ? '?view=1&file=' . rawurlencode($resolvedPath)
        : '?view=1&path=' . rawurlencode($resolvedPath);

    return $origin . $basePath . $query;
}

function cmsNormalizeRenderedHtmlBaseUrl(string $html, string $baseUrl): string
{
    $html = trim($html);
    if ($html === '' || $baseUrl === '') {
        return $html;
    }

    $previousUseErrors = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    try {
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        if (!$loaded) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $absoluteBase = rtrim($baseUrl, "/\\");
        $attributes = [
            'a' => 'href',
            'link' => 'href',
            'img' => 'src',
            'script' => 'src',
            'iframe' => 'src',
            'source' => 'src',
            'video' => 'src',
            'audio' => 'src',
            'form' => 'action',
            'object' => 'data',
        ];

        foreach ($attributes as $tagName => $attributeName) {
            foreach ($xpath->query('//' . $tagName . '[@' . $attributeName . ']') as $node) {
                if (!($node instanceof DOMElement)) {
                    continue;
                }
                $value = trim((string) $node->getAttribute($attributeName));
                if ($value === '' || cmsShouldKeepRelativeRemoteUrl($value)) {
                    continue;
                }
                $node->setAttribute($attributeName, cmsRemoteAbsoluteUrl($absoluteBase, $value));
            }
        }

        foreach ($xpath->query('//*[@style]') as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $style = trim((string) $node->getAttribute('style'));
            if ($style === '') {
                continue;
            }
            $rewrittenStyle = preg_replace_callback(
                '/url\\((["\']?)([^"\')]+)\\1\\)/i',
                static function (array $matches) use ($absoluteBase): string {
                    $url = trim((string) $matches[2]);
                    if ($url === '' || cmsShouldKeepRelativeRemoteUrl($url)) {
                        return $matches[0];
                    }
                    return 'url("' . cmsRemoteAbsoluteUrl($absoluteBase, $url) . '")';
                },
                $style
            );
            if (is_string($rewrittenStyle) && $rewrittenStyle !== '') {
                $node->setAttribute('style', $rewrittenStyle);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMNode) {
            $html = cmsDomNodeInnerHtml($body, $document);
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);
    }

    return $html;
}

function cmsShouldKeepRelativeRemoteUrl(string $value): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return true;
    }
    return str_starts_with($trimmed, '#')
        || str_starts_with($trimmed, 'data:')
        || str_starts_with($trimmed, 'mailto:')
        || str_starts_with($trimmed, 'tel:')
        || preg_match('/^[a-z][a-z0-9+.-]*:/i', $trimmed) === 1;
}

function cmsRemoteAbsoluteUrl(string $baseUrl, string $value): string
{
    $trimmedBase = trim($baseUrl);
    $trimmedValue = trim($value);
    if ($trimmedValue === '') {
        return '';
    }
    if ($trimmedBase === '' || cmsShouldKeepRelativeRemoteUrl($trimmedValue)) {
        return $trimmedValue;
    }

    $baseParts = parse_url($trimmedBase);
    if (!is_array($baseParts) || empty($baseParts['scheme']) || empty($baseParts['host'])) {
        return $trimmedValue;
    }

    if (str_starts_with($trimmedValue, '//')) {
        return $baseParts['scheme'] . ':' . $trimmedValue;
    }
    if (str_starts_with($trimmedValue, '/')) {
        return $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '')
            . $trimmedValue;
    }
    if (str_starts_with($trimmedValue, '?')) {
        $path = $baseParts['path'] ?? '';
        return $baseParts['scheme'] . '://' . $baseParts['host']
            . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '')
            . ($path !== '' ? $path : '/')
            . $trimmedValue;
    }

    $basePath = $baseParts['path'] ?? '';
    $directory = $basePath !== '' ? preg_replace('~/[^/]*$~', '/', $basePath) : '/';
    if (!is_string($directory) || $directory === '') {
        $directory = '/';
    }
    $resolvedPath = preg_replace('~/{2,}~', '/', $directory . $trimmedValue);
    $segments = [];
    foreach (explode('/', (string) $resolvedPath) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }
    $normalizedPath = '/' . implode('/', $segments);

    return $baseParts['scheme'] . '://' . $baseParts['host']
        . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '')
        . $normalizedPath;
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
        $relativePath = $relativeDir !== ''
            ? trim($relativeDir, "/\\") . '/' . $itemPath
            : $itemPath;
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
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Invalid route.',
        ], 400);
    }

    $resolved = cmsResolveSlugRouteInTree($rootDir, '', $slug);
    if ($resolved === null) {
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Route not found.',
        ], 404);
    }

    cmsJsonResponse([
        'resolved' => true,
    ] + $resolved);
}

function cmsHandleSnapshotRoute(string $rootDir): void
{
    $requestedPath = trim((string) ($_GET['file'] ?? $_GET['path'] ?? ''));
    if ($requestedPath === '' || str_contains($requestedPath, '..')) {
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Invalid path.',
        ], 400);
    }

    $resolved = cmsResolveTarget($rootDir, $requestedPath);
    if (!is_array($resolved) || ($resolved['type'] ?? '') !== 'file' || empty($resolved['path'])) {
        cmsJsonResponse([
            'resolved' => false,
            'error' => 'Snapshot not available.',
        ], 404);
    }

    $relativePath = trim((string) ($requestedPath !== '' ? $requestedPath : ($resolved['path'] ?? '')), "/\\");
    $snapshot = buildFileViewerSnapshotPayload($relativePath, (string) $resolved['path'], null, class_exists('PoffConfig') ? cmsResolveConfiguredTreeItem($rootDir, $relativePath) : null);
    cmsJsonResponse([
        'resolved' => true,
        'kind' => $snapshot['type'],
        'context' => $snapshot['snapshotContext'],
    ]);
}

function cmsLoadEnv(string $rootDir): array
{
    $envPath = rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($envPath)) {
        return [];
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            $env[$key] = $value;
        }
    }
    return $env;
}

function cmsEnvValue(array $env, string $key): ?string
{
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $env[$key] ?? null;
}

function cmsHttpPost(string $url, array $headers, array $payload): array
{
    $override = $GLOBALS['__poff_prompt_http_post'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers, $payload);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($payload),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
    $status = 0;
    $statusLine = '';
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int) $matches[1];
        $statusLine = (string) $http_response_header[0];
    }
    return [
        'ok' => $response !== false && $status >= 200 && $status < 400,
        'status' => $status,
        'statusLine' => $statusLine,
        'body' => $response !== false ? $response : '',
    ];
}

function cmsHttpGet(string $url, array $headers = []): array
{
    $override = $GLOBALS['__poff_prompt_http_get'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    try {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                return [
                    'ok' => false,
                    'status' => 0,
                    'statusLine' => '',
                    'body' => '',
                ];
            }

            $responseBody = '';
            $status = 0;
            $statusLine = '';
            curl_setopt_array($curl, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                    $trimmed = trim($headerLine);
                    if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                        $status = (int) $matches[1];
                        $statusLine = $trimmed;
                    }
                    return strlen($headerLine);
                },
            ]);

            $result = curl_exec($curl);
            if (is_string($result)) {
                $responseBody = $result;
            }
            if ($status === 0) {
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            }
            if ($statusLine === '') {
                $statusLine = 'HTTP ' . $status;
            }
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => $result !== false && $status >= 200 && $status < 400,
                'status' => $status,
                'statusLine' => $statusLine,
                'body' => $responseBody,
                'error' => $error,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        $statusLine = '';
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int) $matches[1];
            $statusLine = (string) $http_response_header[0];
        }
        return [
            'ok' => $response !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $response !== false ? $response : '',
        ];
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
}

function cmsHttpPostStream(string $url, array $headers, array $payload, ?callable $onChunk = null): array
{
    $override = $GLOBALS['__poff_prompt_http_post_stream'] ?? null;
    if (is_callable($override)) {
        $response = $override($url, $headers, $payload, $onChunk);
        if (is_array($response)) {
            return $response;
        }
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    }

    $previousSocketTimeout = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) {
        @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    }

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    $responseBody = '';
    $status = 0;
    $statusLine = '';
    try {
        if (!function_exists('curl_init')) {
            $response = cmsHttpPost($url, $headers, $payload);
            $responseBody = (string) ($response['body'] ?? '');
            if (is_callable($onChunk) && $responseBody !== '') {
                $onChunk($responseBody);
            }
            return $response;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return [
                'ok' => false,
                'status' => 0,
                'statusLine' => '',
                'body' => '',
            ];
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$responseBody, $onChunk) {
                $responseBody .= $chunk;
                if (is_callable($onChunk)) {
                    $onChunk($chunk);
                }
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                    $status = (int) $matches[1];
                    $statusLine = $trimmed;
                }
                return strlen($headerLine);
            },
        ]);

        $ok = curl_exec($curl);
        if ($status === 0) {
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        }
        if ($statusLine === '') {
            $statusLine = 'HTTP ' . $status;
        }
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'ok' => $ok !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $responseBody,
            'error' => $error,
        ];
    } finally {
        if ($previousSocketTimeout !== false && function_exists('ini_set')) {
            @ini_set('default_socket_timeout', (string) $previousSocketTimeout);
        }
    }
}

function cmsPromptDebugCapture(string $rootDir, array $entry): string
{
    $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff-prompt-debug';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }

    $workspace = basename(rtrim($rootDir, DIRECTORY_SEPARATOR));
    $workspace = preg_replace('/[^a-z0-9._-]+/i', '-', (string) $workspace) ?: 'workspace';
    $targetPath = $baseDir . DIRECTORY_SEPARATOR . $workspace . '-last-local-prompt.json';
    $entry['capturedAt'] = date('c');

    @file_put_contents($targetPath, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $targetPath;
}

function cmsPromptNormalizeErrorText(string $text): string
{
    $normalized = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/sk-[A-Za-z0-9_-]{12,}/', 'sk-***', $normalized) ?? $normalized;
    $normalized = preg_replace('/AIza[0-9A-Za-z_-]{12,}/', 'AIza***', $normalized) ?? $normalized;
    $normalized = trim($normalized, " \t\n\r\0\x0B.:");
    if ($normalized === '') {
        return '';
    }
    if (strlen($normalized) > 220) {
        $normalized = substr($normalized, 0, 217) . '...';
    }

    return $normalized;
}

function cmsPromptExtractErrorDetail(mixed $value): string
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $detail = cmsPromptExtractErrorDetail($decoded);
            if ($detail !== '') {
                return $detail;
            }
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $trimmed, $matches)) {
            return cmsPromptNormalizeErrorText((string) $matches[1]);
        }

        return cmsPromptNormalizeErrorText($trimmed);
    }

    if (!is_array($value)) {
        return '';
    }

    foreach (['message', 'detail'] as $key) {
        if (array_key_exists($key, $value)) {
            $detail = cmsPromptExtractErrorDetail($value[$key]);
            if ($detail !== '') {
                return $detail;
            }
        }
    }

    if (array_key_exists('error', $value)) {
        $detail = cmsPromptExtractErrorDetail($value['error']);
        if ($detail !== '') {
            return $detail;
        }
    }

    if (array_key_exists('details', $value)) {
        $detail = cmsPromptExtractErrorDetail($value['details']);
        if ($detail !== '') {
            return $detail;
        }
    }

    foreach ($value as $item) {
        $detail = cmsPromptExtractErrorDetail($item);
        if ($detail !== '') {
            return $detail;
        }
    }

    return '';
}

function cmsFormatPromptHttpError(string $label, array $response): string
{
    $status = (int) ($response['status'] ?? 0);
    $detail = cmsPromptExtractErrorDetail($response['body'] ?? '');
    $message = $label . ' request failed';
    if ($status > 0) {
        $message .= ' (HTTP ' . $status . ')';
    }
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }

    return $message . '.';
}

function sanitizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');
    return $path;
}

function detectFileType(string $path): string
{
    return MediaType::classifyExtension(basename($path));
}
