<?php

function renderFileViewer(string $relativePath, string $fullPath): void
{
    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }

    $type = detectFileType($fullPath);
    $mimeType = MediaType::detectMimeType($fullPath, basename($fullPath));
    $workDefaults = Worktype::definition($type, $mimeType);
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    $work = array_merge($workDefaults, $workData);
    if (class_exists('PoffConfig')) {
        $work['layout'] = PoffConfig::prepareLayoutForView($work['layout'] ?? null, $relativePath, true, 'work');
    }

    $linkUrl = null;
    if ($type === 'link') {
        $linkUrl = extractLinkFileUrl($fullPath);
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
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
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
