<?php

function cmsResolveRemoteRenderedHtml(array $item): string
{
    $sourceUrl = trim((string) ($item['linkUrl'] ?? ($item['pageLink'] ?? ($item['pageUrl'] ?? ($item['baseUrl'] ?? '')))));
    $baseUrl = trim((string) ($item['baseUrl'] ?? ''));
    if ($baseUrl === '' && $sourceUrl !== '') {
        $baseUrl = cmsNormalizeRemoteAssetBaseUrl($sourceUrl);
    }

    $snapshotUrl = cmsResolveRemoteRenderedSnapshotSourceUrl($sourceUrl !== '' ? $sourceUrl : $baseUrl, $item);
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

    $sourceUrl = cmsResolveRemoteRenderedSourceUrl($sourceUrl !== '' ? $sourceUrl : $baseUrl, $item);
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

function cmsNormalizeRemoteAssetBaseUrl(string $url): string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return rtrim($trimmed, '/');
    }

    $normalized = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }

    return $normalized;
}

function cmsRenderRemoteSnapshotHtml(array $snapshot): string
{
    $renderedHtml = trim((string) ($snapshot['renderedHtml'] ?? ($snapshot['render']['html'] ?? '')));
    if ($renderedHtml === '') {
        return '';
    }

    $baseUrl = trim((string) ($snapshot['assetsBaseUrl'] ?? ($snapshot['render']['assetsBaseUrl'] ?? ($snapshot['baseUrl'] ?? ''))));
    if ($baseUrl !== '') {
        $renderedHtml = cmsNormalizeRenderedHtmlBaseUrl($renderedHtml, $baseUrl);
    }

    return cmsSanitizeRemoteRenderedHtml(cmsStripRemoteAppChrome($renderedHtml));
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
    if ($path === '' || (!array_key_exists('view', $params) && !$isFile)) {
        return null;
    }

    return ['path' => $path, 'isFile' => $isFile];
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
                        $query = $isFile ? '?ajax=snapshot&file=' : '?ajax=snapshot&path=';
                        return $origin . $basePath . $query . rawurlencode($resolvedPath);
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

    $isFolder = (($item['isFolder'] ?? false) === true) || trim((string) ($item['type'] ?? '')) === 'folder';
    $viewerQuery = $isFolder ? '?view=1&path=' : '?view=1&file=';

    return $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? '/')
        . $viewerQuery . rawurlencode($targetPath);
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
    $response = cmsHttpGet($origin . $basePath . '?ajax=resolve&slug=' . rawurlencode($slug));
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
    $query = $isFile ? '?view=1&file=' : '?view=1&path=';

    return $origin . $basePath . $query . rawurlencode($resolvedPath);
}
