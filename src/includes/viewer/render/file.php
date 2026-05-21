<?php

function renderFileViewer(string $relativePath, string $fullPath, ?array $fileConfigOverride = null, ?array $treeConfigOverride = null): void
{
    $hasPhysicalFile = is_file($fullPath);
    $fileConfig = $fileConfigOverride;
    if ($fileConfig === null && $hasPhysicalFile && class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }
    $treeConfig = $treeConfigOverride;
    if ($treeConfig === null && class_exists('PoffConfig')) {
        $treeConfig = cmsResolveConfiguredTreeItem(dirname($fullPath), $relativePath);
    }

    $detectedType = detectFileType($fullPath);
    $configuredType = strtolower(trim((string) ($treeConfig['kind'] ?? ($treeConfig['type'] ?? ($fileConfig['kind'] ?? ($fileConfig['type'] ?? ''))))));
    $type = $configuredType === 'link' ? 'link' : $detectedType;
    $mimeType = $hasPhysicalFile ? MediaType::detectMimeType($fullPath, basename($fullPath)) : null;
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    if ($workData === [] && isset($treeConfig['work']) && is_array($treeConfig['work'])) {
        $workData = $treeConfig['work'];
    }
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
    if ($type === 'link' && $hasPhysicalFile) {
        $linkUrl = extractLinkFileUrl($fullPath);
    }
    if ($linkUrl === null && $configuredLinkUrl !== '') {
        $linkUrl = $configuredLinkUrl;
    }
    if ($type === 'htaccess') {
        $linkUrl = null;
    }
    $renderedHtml = cmsResolveRemoteRenderedHtml([
        'linkUrl' => $linkUrl ?? '',
        'pageLink' => $fileConfig['pageLink'] ?? '',
        'pageUrl' => $fileConfig['pageUrl'] ?? '',
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'renderedHtml' => $configuredRenderedHtml !== '' ? $configuredRenderedHtml : trim((string) ($fileConfig['renderedHtml'] ?? '')),
    ]);
    $previewUrl = '';
    if ($linkUrl !== null) {
        $trimmedLinkUrl = trim($linkUrl);
        if ($trimmedLinkUrl !== '' && (cmsIsExternalLinkTarget($trimmedLinkUrl) || cmsIsCmsQueryLinkTarget($trimmedLinkUrl))) {
            $previewUrl = $trimmedLinkUrl;
        }
    }
    if ($type !== 'htaccess' && ($renderedHtml !== '' || $previewUrl !== '' || ($type === 'link' && trim((string) ($linkUrl ?? '')) !== ''))) {
        $work['template'] = 'external';
        $work['layout']['section'] = 'work';
        $work['layout']['sectionTemplate'] = '';
    }
    $showInlineTextPreview = false;
    $textContent = '';
    if (in_array($type, ['text', 'htaccess'], true) && $hasPhysicalFile) {
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
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'description' => $treeConfig['description'] ?? ($fileConfig['description'] ?? ''),
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
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'bodyContent' => $bodyContent,
        'openHref' => ($linkUrl !== null && trim($linkUrl) !== '') ? $linkUrl : viewerAssetHref($relativePath),
        'openLabel' => ($linkUrl !== null && trim($linkUrl) !== '') ? 'Open Source' : 'Open Raw',
    ]);
}

function buildFileViewerSnapshotPayload(string $relativePath, string $fullPath, ?array $fileConfigOverride = null, ?array $treeConfigOverride = null): array
{
    $hasPhysicalFile = is_file($fullPath);
    $fileConfig = $fileConfigOverride;
    if ($fileConfig === null && $hasPhysicalFile && class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }
    $treeConfig = $treeConfigOverride;
    if ($treeConfig === null && class_exists('PoffConfig')) {
        $treeConfig = cmsResolveConfiguredTreeItem(dirname($fullPath), $relativePath);
    }

    $detectedType = detectFileType($fullPath);
    $configuredType = strtolower(trim((string) ($treeConfig['kind'] ?? ($treeConfig['type'] ?? ($fileConfig['kind'] ?? ($fileConfig['type'] ?? ''))))));
    $type = $configuredType === 'link' ? 'link' : $detectedType;
    $mimeType = $hasPhysicalFile ? MediaType::detectMimeType($fullPath, basename($fullPath)) : null;
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    if ($workData === [] && isset($treeConfig['work']) && is_array($treeConfig['work'])) {
        $workData = $treeConfig['work'];
    }
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
    if ($type === 'link' && $hasPhysicalFile) {
        $linkUrl = extractLinkFileUrl($fullPath);
    }
    if ($linkUrl === null && $configuredLinkUrl !== '') {
        $linkUrl = $configuredLinkUrl;
    }
    if ($type === 'htaccess') {
        $linkUrl = null;
    }

    $snapshotContext = [
        'path' => $relativePath,
        'viewerHref' => '?view=1&file=' . rawurlencode($relativePath),
        'viewUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'workUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'rawHref' => viewerAssetHref($relativePath),
        'assetUrl' => viewerAssetHref($relativePath),
        'mimeType' => $mimeType ?? '',
        'name' => basename($relativePath),
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? basename($relativePath)),
        'description' => $treeConfig['description'] ?? ($fileConfig['description'] ?? ''),
        'descriptionHtml' => !empty($fileConfig['description'])
            ? '<div class="work-description">' . nl2br(htmlspecialchars($fileConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>'
            : '',
        'linkUrl' => $linkUrl ?? '',
        'previewUrl' => '',
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'slug' => trim((string) ($fileConfig['slug'] ?? preg_replace('/[^a-z0-9\-]+/i', '-', basename($relativePath))), '-') ?: 'item',
        'showInlineTextPreview' => false,
        'textContent' => '',
        'renderedHtml' => $configuredRenderedHtml !== '' ? $configuredRenderedHtml : trim((string) ($fileConfig['renderedHtml'] ?? '')),
        'work' => $work,
    ];

    if (in_array($type, ['text', 'htaccess'], true) && $hasPhysicalFile) {
        $snapshotContext['showInlineTextPreview'] = MediaType::shouldUseInlineTextPreview(basename($fullPath), $mimeType);
        if ($snapshotContext['showInlineTextPreview']) {
            $contents = @file_get_contents($fullPath);
            if ($contents !== false) {
                $snapshotContext['textContent'] = $contents;
            }
        }
    }

    $previewUrl = '';
    if ($linkUrl !== null) {
        $trimmedLinkUrl = trim($linkUrl);
        if ($trimmedLinkUrl !== '' && (cmsIsExternalLinkTarget($trimmedLinkUrl) || cmsIsCmsQueryLinkTarget($trimmedLinkUrl))) {
            $previewUrl = $trimmedLinkUrl;
        }
    }

    $rawName = basename($relativePath);
    $renderedHtml = Worktype::render($type, $snapshotContext);
    return [
        'type' => $type,
        'name' => $rawName,
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'openHref' => ($linkUrl !== null && trim($linkUrl) !== '') ? $linkUrl : viewerAssetHref($relativePath),
        'openLabel' => ($linkUrl !== null && trim($linkUrl) !== '') ? 'Open Source' : 'Open Raw',
        'snapshotContext' => $snapshotContext,
        'renderedHtml' => $renderedHtml,
    ];
}
