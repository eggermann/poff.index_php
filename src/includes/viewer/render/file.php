<?php

function renderFileViewer(string $relativePath, string $fullPath): void
{
    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }
    $treeConfig = null;
    if (class_exists('PoffConfig')) {
        $treeConfig = cmsResolveConfiguredTreeItem(dirname($fullPath), $relativePath);
    }

    $type = detectFileType($fullPath);
    $mimeType = MediaType::detectMimeType($fullPath, basename($fullPath));
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    $workTemplateKey = trim((string) ($workData['template'] ?? ''));
    $workDefinitionKey = $workTemplateKey !== '' ? $workTemplateKey : $type;
    $workDefaults = Worktype::definition($workDefinitionKey, $mimeType);
    $work = array_merge($workDefaults, $workData);
    if (class_exists('PoffConfig')) {
        $resolvedWorkState = PoffConfig::resolveWorkTemplateState(dirname($fullPath), $work, $type, $mimeType, basename($fullPath));
        if (is_array($resolvedWorkState['work'] ?? null)) {
            $work = $resolvedWorkState['work'];
        }
    }
    if ((!isset($work['template']) || trim((string) $work['template']) === '') && isset($workDefaults['template']) && is_string($workDefaults['template'])) {
        $work['template'] = $workDefaults['template'];
    }
    $work['type'] = $work['type'] ?? $type;
    if (class_exists('PoffConfig')) {
        $work['layout'] = PoffConfig::prepareLayoutForView($work['layout'] ?? null, $relativePath, true, 'work');
    }

    $linkUrl = null;
    $configuredLinkUrl = trim((string) ($treeConfig['linkUrl'] ?? ($treeConfig['pageLink'] ?? ($treeConfig['pageUrl'] ?? ''))));
    $configuredBaseUrl = trim((string) ($treeConfig['baseUrl'] ?? ''));
    $configuredRenderedHtml = trim((string) ($treeConfig['renderedHtml'] ?? ''));
    if ($type === 'link') {
        $linkUrl = extractLinkFileUrl($fullPath);
    }
    if ($linkUrl === null && $configuredLinkUrl !== '') {
        $linkUrl = $configuredLinkUrl;
    }
    $renderedHtml = cmsResolveRemoteRenderedHtml([
        'linkUrl' => $linkUrl ?? '',
        'pageLink' => $fileConfig['pageLink'] ?? '',
        'pageUrl' => $fileConfig['pageUrl'] ?? '',
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'renderedHtml' => $configuredRenderedHtml !== '' ? $configuredRenderedHtml : trim((string) ($fileConfig['renderedHtml'] ?? '')),
    ]);
    if ($renderedHtml !== '') {
        $work['template'] = 'external';
        $work['layout']['section'] = 'work';
        $work['layout']['sectionTemplate'] = '';
    }
    $previewUrl = '';
    if ($linkUrl !== null) {
        $trimmedLinkUrl = trim($linkUrl);
        if ($trimmedLinkUrl !== '' && (cmsIsExternalLinkTarget($trimmedLinkUrl) || cmsIsCmsQueryLinkTarget($trimmedLinkUrl))) {
            $previewUrl = $trimmedLinkUrl;
        }
    }
    $showInlineTextPreview = false;
    $textContent = '';
    if ($type === 'text') {
        $showInlineTextPreview = MediaType::shouldUseInlineTextPreview(basename($fullPath), $mimeType);
        if ($showInlineTextPreview) {
            $contents = @file_get_contents($fullPath);
            if ($contents !== false) {
                $textContent = $contents;
            }
        }
    }

    $rawName = basename($relativePath);
    $rawSlug = $fileConfig['slug'] ?? preg_replace('/[^a-z0-9\-]+/i', '-', $rawName);
    $rawSlug = trim((string) $rawSlug, '-');

    $descriptionHtml = '';
    if (!empty($fileConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($fileConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    $bodyContent = Worktype::render($type, [
        'path' => $relativePath,
        'viewerHref' => '?view=1&file=' . rawurlencode($relativePath),
        'viewUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'workUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'rawHref' => viewerAssetHref($relativePath),
        'assetUrl' => viewerAssetHref($relativePath),
        'mimeType' => $mimeType ?? '',
        'name' => $rawName,
        'title' => $fileConfig['title'] ?? $rawName,
        'description' => $fileConfig['description'] ?? '',
        'descriptionHtml' => $descriptionHtml,
        'linkUrl' => $linkUrl ?? '',
        'previewUrl' => $previewUrl,
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
        'showInlineTextPreview' => $showInlineTextPreview,
        'textContent' => $textContent,
        'renderedHtml' => $renderedHtml,
        'work' => $work,
    ]);

    renderViewerShell([
        'type' => $type,
        'name' => $rawName,
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'bodyContent' => $bodyContent,
        'openHref' => viewerAssetHref($relativePath),
        'openLabel' => 'Open Raw',
    ]);
}
