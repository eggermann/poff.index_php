<?php

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
    if (!$needsWrapperParsing || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html)) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $contentFrameNodes = $xpath->query('//*[@id="contentFrame"]');
        if ($contentFrameNodes instanceof DOMNodeList && $contentFrameNodes->length > 0) {
            $node = $contentFrameNodes->item(0);
            if ($node instanceof DOMNode) {
                $preferred = cmsExtractPreferredRemoteRenderedNodeHtml($xpath, $node, $document);
                if ($preferred !== '') {
                    return $preferred;
                }
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

function cmsExtractPreferredRemoteRenderedNodeHtml(DOMXPath $xpath, DOMNode $node, DOMDocument $document): string
{
    foreach ([
        './/*[contains(concat(" ", normalize-space(@class), " "), " poff-default-layout__main ")]',
        './/*[@id="contentFrame"]',
        './/*[contains(concat(" ", normalize-space(@class), " "), " viewer ")]',
        './/main',
        './/article',
        './/section',
        './/picture',
        './/figure',
    ] as $query) {
        $matches = $xpath->query($query, $node);
        if (!($matches instanceof DOMNodeList) || $matches->length === 0) {
            continue;
        }
        $match = $matches->item(0);
        if ($match instanceof DOMNode) {
            $preferred = cmsDomNodeInnerHtml($match, $document);
            if ($preferred !== '' && !cmsHtmlLooksLikeRemoteShell($preferred)) {
                return $preferred;
            }
        }
    }

    foreach (['.//video', './/img', './/iframe', './/audio', './/canvas', './/svg'] as $query) {
        $matches = $xpath->query($query, $node);
        if (!($matches instanceof DOMNodeList) || $matches->length === 0) {
            continue;
        }
        $match = $matches->item(0);
        if ($match instanceof DOMNode) {
            $preferred = cmsDomNodeOuterHtml($match, $document);
            if ($preferred !== '' && !cmsHtmlLooksLikeRemoteShell($preferred)) {
                return $preferred;
            }
        }
    }

    $fragment = cmsDomNodeInnerHtml($node, $document);
    return ($fragment !== '' && !cmsHtmlLooksLikeRemoteShell($fragment)) ? $fragment : '';
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

function cmsDomNodeOuterHtml(DOMNode $node, DOMDocument $document): string
{
    $html = $document->saveHTML($node);
    return is_string($html) ? trim($html) : '';
}

function cmsStripRemoteAppChrome(string $html): string
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (!$document->loadHTML('<?xml encoding="UTF-8">' . $html)) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        foreach ([
            '//*[@id="appSidebar"]',
            '//*[@id="sidebarToggle"]',
            '//*[@id="editPanel"]',
            '//*[@id="editDrawer"]',
            '//*[@id="promptDock"]',
            '//*[@id="iframeLoading"]',
            '//*[@id="sidebarLoading"]',
            '//*[@id="editActionsMenu"]',
            '//*[@class and contains(concat(" ", normalize-space(@class), " "), " app-edit-toggle-wrap ")]',
        ] as $query) {
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
        $html = $body instanceof DOMNode ? cmsDomNodeInnerHtml($body, $document) : trim($document->saveHTML());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $html !== '' ? $html : '';
}

function cmsHtmlLooksLikeRemoteShell(string $html): bool
{
    $html = trim($html);
    if ($html === '') {
        return false;
    }

    return stripos($html, 'currentPoffConfig') !== false
        || stripos($html, 'poff-shell-standalone') !== false
        || stripos($html, 'appShell') !== false
        || stripos($html, 'contentFrame') !== false
        || stripos($html, 'iframeLoading') !== false;
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
        if (!@$document->loadHTML(
            '<?xml encoding="utf-8" ?><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        )) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        $absoluteBase = rtrim($baseUrl, "/\\");
        foreach ([
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
        ] as $tagName => $attributeName) {
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

    return $baseParts['scheme'] . '://' . $baseParts['host']
        . (isset($baseParts['port']) ? ':' . $baseParts['port'] : '')
        . '/' . implode('/', $segments);
}

function cmsSanitizeRemoteRenderedHtml(string $html): string
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument') || !class_exists('DOMXPath')) {
        return $html;
    }

    $previous = libxml_use_internal_errors(true);
    try {
        $document = new DOMDocument('1.0', 'UTF-8');
        if (!$document->loadHTML(
            '<?xml encoding="UTF-8"><!doctype html><html><body>' . $html . '</body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        )) {
            return $html;
        }

        $xpath = new DOMXPath($document);
        foreach (['script', 'object', 'embed'] as $tagName) {
            $nodes = $document->getElementsByTagName($tagName);
            for ($index = $nodes->length - 1; $index >= 0; $index--) {
                $node = $nodes->item($index);
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        foreach ($xpath->query('//*[@*]') as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }
            $removeAttributes = [];
            foreach ($node->attributes as $attribute) {
                $name = strtolower((string) $attribute->name);
                $value = trim((string) $attribute->value);
                if (str_starts_with($name, 'on')) {
                    $removeAttributes[] = $attribute->name;
                    continue;
                }
                if (in_array($name, ['href', 'src', 'poster', 'xlink:href'], true) && preg_match('/^\s*javascript:/i', $value) === 1) {
                    $removeAttributes[] = $attribute->name;
                }
            }
            foreach ($removeAttributes as $attributeName) {
                $node->removeAttribute($attributeName);
            }
        }

        $body = $document->getElementsByTagName('body')->item(0);
        if ($body instanceof DOMNode) {
            return cmsDomNodeInnerHtml($body, $document);
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return $html;
}
