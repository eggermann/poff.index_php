<?php

function renderFolderViewer(string $relativePath, string $fullPath): void
{
    $folderConfig = null;
    if (class_exists('PoffConfig')) {
        $folderConfig = PoffConfig::ensure($fullPath);
    }

    $workData = (isset($folderConfig['work']) && is_array($folderConfig['work'])) ? $folderConfig['work'] : [];
    $workTemplateKey = trim((string) ($workData['template'] ?? 'folder'));
    $workDefaults = Worktype::definition($workTemplateKey !== '' ? $workTemplateKey : 'folder');
    $work = array_merge($workDefaults, $workData);
    if (class_exists('PoffConfig')) {
        $resolvedWorkState = PoffConfig::resolveWorkTemplateState($fullPath, $work, 'folder');
        if (is_array($resolvedWorkState['work'] ?? null)) {
            $work = $resolvedWorkState['work'];
        }
    }
    if (!isset($work['template']) || trim((string) $work['template']) === '') {
        $work['template'] = $workTemplateKey !== '' ? $workTemplateKey : 'folder';
    }
    $work['type'] = $work['type'] ?? 'folder';
    $isConverterWork = strtolower(trim((string) ($work['type'] ?? ''))) === 'converter'
        || strtolower(trim((string) ($work['template'] ?? ''))) === 'converter';
    if (class_exists('PoffConfig')) {
        $work['layout'] = PoffConfig::prepareLayoutForView($work['layout'] ?? null, $relativePath, false, $isConverterWork ? 'work' : 'works');
    }

    $rawName = $folderConfig['folderName'] ?? basename($fullPath);
    if ($rawName === '') {
        $rawName = basename(rtrim($fullPath, DIRECTORY_SEPARATOR));
    }
    $rawSlug = $folderConfig['slug'] ?? preg_replace('/[^a-z0-9\-]+/i', '-', $rawName);
    $rawSlug = trim((string) $rawSlug, '-');
    $folderViewData = buildFolderViewerData($relativePath, $fullPath, $folderConfig, [
        'name' => $rawName,
        'title' => $folderConfig['title'] ?? $rawName,
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
    ]);
    $tree = $folderViewData['tree'];
    $descriptionHtml = '';
    if (!empty($folderConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($folderConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    $viewerHref = '?view=1&path=' . rawurlencode($relativePath);
    $browseHref = '?path=';
    if ($relativePath !== '') {
        $browseHref .= rawurlencode($relativePath);
    }

    $converterDefinition = null;
    if ($isConverterWork && class_exists('Converter')) {
        $converterRootDir = function_exists('cmsProjectRootDir') ? cmsProjectRootDir($fullPath) : dirname($fullPath);
        $converterDefinition = Converter::definitionFromFolder($converterRootDir, $relativePath);
    }

    $context = [
        'path' => $relativePath,
        'viewerHref' => $viewerHref,
        'viewUrl' => $viewerHref,
        'workUrl' => $viewerHref,
        'rawHref' => $browseHref,
        'assetUrl' => $browseHref,
        'displayPath' => $relativePath === '' ? '.' : $relativePath,
        'name' => $rawName,
        'title' => $folderConfig['title'] ?? $rawName,
        'description' => $folderConfig['description'] ?? '',
        'descriptionHtml' => $descriptionHtml,
        'linkUrl' => $folderConfig['link'] ?? $folderConfig['url'] ?? '',
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
        'folderName' => $folderConfig['folderName'] ?? $rawName,
        'tree' => $tree,
        'items' => $tree,
        'workTree' => $folderViewData['workTree'],
        'allItems' => $folderViewData['allItems'],
        'allFiles' => $folderViewData['allFiles'],
        'allFolders' => $folderViewData['allFolders'],
        'allImages' => $folderViewData['allImages'],
        'allVideos' => $folderViewData['allVideos'],
        'allAudio' => $folderViewData['allAudio'],
        'allPdfs' => $folderViewData['allPdfs'],
        'allTexts' => $folderViewData['allTexts'],
        'allLinks' => $folderViewData['allLinks'],
        'allOther' => $folderViewData['allOther'],
        'hasItems' => $tree !== [],
        'itemCount' => count($tree),
        'allItemCount' => count($folderViewData['allItems']),
        'work' => $work,
    ];
    if ($isConverterWork) {
        $context['converter'] = [
            'id' => (string) ($converterDefinition['id'] ?? ''),
            'name' => (string) ($converterDefinition['name'] ?? basename($relativePath)),
            'label' => (string) ($converterDefinition['label'] ?? ($folderConfig['title'] ?? $rawName)),
            'accepts' => $converterDefinition['accepts'] ?? [],
            'outputs' => $converterDefinition['outputs'] ?? [],
            'formats' => $converterDefinition['formats'] ?? [],
            'engine' => (string) ($converterDefinition['engine'] ?? ''),
            'path' => (string) ($converterDefinition['path'] ?? $relativePath),
            'folder' => (string) ($converterDefinition['folder'] ?? $relativePath),
            'viewerHref' => (string) ($converterDefinition['viewerHref'] ?? $viewerHref),
            'url' => (string) ($converterDefinition['url'] ?? $viewerHref),
            'format' => (string) (($converterDefinition['defaults']['format'] ?? '')),
            'quality' => (string) (($converterDefinition['defaults']['quality'] ?? '')),
        ];
        $context['source'] = [
            'name' => '',
            'path' => '',
            'mimeType' => '',
        ];
        $context['target'] = [
            'saveAs' => '',
            'mode' => (string) (($converterDefinition['defaults']['saveMode'] ?? 'new-hidden-work')),
        ];
        $context['status'] = [
            'state' => 'ready',
        ];
    }

    $bodyContent = Worktype::render($isConverterWork ? 'converter' : 'folder', $context);

    renderViewerShell([
        'type' => $isConverterWork ? 'converter' : 'folder',
        'name' => $rawName,
        'title' => $folderConfig['title'] ?? $rawName,
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'bodyContent' => $bodyContent,
        'openHref' => $browseHref,
        'openLabel' => 'Open Folder',
    ]);
}
