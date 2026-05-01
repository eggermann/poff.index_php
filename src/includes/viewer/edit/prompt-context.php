<?php

function cmsPromptOuterWrapperReference(array $layoutValue, string $currentSection): array
{
    $layoutName = trim((string) ($layoutValue['name'] ?? Worktype::defaultLayoutName()));
    if ($layoutName === '') {
        $layoutName = Worktype::defaultLayoutName();
    }

    $storage = trim((string) ($layoutValue['storage'] ?? ''));
    if ($storage === '') {
        $storage = 'default';
    }

    $section = trim((string) ($layoutValue['section'] ?? $currentSection));
    if ($section === '') {
        $section = $currentSection;
    }

    $templateName = $layoutName;
    $templateCandidate = Worktype::template($templateName);
    if (!is_string($templateCandidate) || $templateCandidate === '') {
        $templateName = Worktype::defaultLayoutName();
        $templateCandidate = Worktype::template($templateName);
    }

    $template = '';
    if (isset($layoutValue['template']) && is_string($layoutValue['template']) && trim($layoutValue['template']) !== '') {
        $template = $layoutValue['template'];
    } else {
        $template = (string) ($templateCandidate ?? '');
    }

    $css = '';
    if (isset($layoutValue['css']) && is_string($layoutValue['css']) && trim($layoutValue['css']) !== '') {
        $css = $layoutValue['css'];
    } else {
        $css = (string) (Worktype::layoutBundleAsset($templateName, 'style.css') ?? '');
    }

    $js = '';
    if (isset($layoutValue['js']) && is_string($layoutValue['js']) && trim($layoutValue['js']) !== '') {
        $js = $layoutValue['js'];
    } else {
        $js = (string) (Worktype::layoutBundleAsset($templateName, 'script.js') ?? '');
    }

    return [
        'name' => $layoutName,
        'storage' => $storage,
        'sectionPartial' => $section,
        'source' => $storage === 'filesystem'
            ? 'resolved active wrapper'
            : ($storage === 'inline' ? 'inline wrapper config' : 'bundled default wrapper reference'),
        'template' => $template,
        'css' => $css,
        'js' => $js,
    ];
}

function cmsBuildPromptContext(
    string $relativePath,
    string $subjectType,
    array $config,
    ?string $targetFile = null,
    bool $isLayoutTarget = false,
    string $layoutPreset = '',
    array $editorDraft = []
): array {
    $normalizedPath = trim($relativePath, "/\\");
    $currentName = $subjectType === 'file'
        ? (string) ($targetFile ?? basename($normalizedPath))
        : (string) ($config['folderName'] ?? basename($normalizedPath));
    $currentPath = $normalizedPath;
    $currentIsFile = $subjectType === 'file';
    $currentSection = $currentIsFile ? 'work' : 'works';
    $currentPageLink = cmsPromptViewerUrl($currentPath, $currentIsFile);
    $currentAssetUrl = cmsPromptAssetUrl($currentPath, $currentIsFile);
    $currentSectionTarget = cmsPromptTemplateTarget($currentPath, $currentIsFile, $currentSection);
    $currentLocalLayoutTarget = cmsPromptLayoutTemplateTarget($currentPath, $currentIsFile);
    $currentLocalLayoutDirectory = preg_replace('#/template\.hbs$#', '', $currentLocalLayoutTarget) ?: $currentLocalLayoutTarget;
    $layoutValue = is_array($config['work'] ?? null) && is_array($config['work']['layout'] ?? null)
        ? $config['work']['layout']
        : [];
    $resolvedLayoutDirectory = trim((string) ($layoutValue['directory'] ?? ''), "/\\");
    $layoutStorage = trim((string) ($layoutValue['storage'] ?? ''));
    $normalizedLayoutPreset = trim($layoutPreset);
    $activeLayoutDirectory = $currentLocalLayoutDirectory;
    if ($normalizedLayoutPreset === 'custom') {
        $activeLayoutDirectory = $currentLocalLayoutDirectory;
    } elseif ($isLayoutTarget && $layoutStorage === 'filesystem' && $resolvedLayoutDirectory !== '') {
        $activeLayoutDirectory = $resolvedLayoutDirectory;
    } elseif (!$isLayoutTarget && $resolvedLayoutDirectory !== '') {
        $activeLayoutDirectory = $resolvedLayoutDirectory;
    }
    $currentLayoutTarget = trim($activeLayoutDirectory, "/\\") . '/template.hbs';
    $layoutBasePath = $activeLayoutDirectory;
    $layoutSectionBasePath = trim((string) ($layoutValue['sectionDirectory'] ?? $layoutBasePath), "/\\");
    $layoutAssets = [];
    foreach (($layoutValue['assets'] ?? []) as $asset) {
        if (!is_array($asset) || !isset($asset['path'])) {
            continue;
        }
        $assetPath = trim((string) $asset['path'], "/\\");
        if ($assetPath === '') {
            continue;
        }
        $layoutAssets[] = [
            'name' => (string) ($asset['name'] ?? basename($assetPath)),
            'path' => $assetPath,
            'href' => cmsPromptEncodeRelativePath($layoutBasePath . '/' . $assetPath),
        ];
    }

    $context = [
        'current' => [
            'targetType' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectType' => $subjectType,
            'layoutPreset' => $normalizedLayoutPreset,
            'sectionPartial' => $currentSection,
            'name' => $currentName,
            'path' => $currentPath,
            'virtualPath' => $isLayoutTarget ? PoffConfig::relativeLayoutPath($currentPath, $currentIsFile) : '',
            'pageLink' => $currentPageLink,
            'pageUrl' => $currentPageLink,
            'workUrl' => $currentPageLink,
            'viewUrl' => $currentPageLink,
            'viewerHref' => $currentPageLink,
            'assetUrl' => $currentAssetUrl,
            'assetLink' => $currentAssetUrl,
            'rawHref' => $currentAssetUrl,
            'srcUrl' => $currentAssetUrl,
            'sourceUrl' => $currentAssetUrl,
            'templateTarget' => $isLayoutTarget ? $currentLayoutTarget : $currentSectionTarget,
            'layoutTemplateTarget' => $currentLocalLayoutTarget,
            'sectionTemplateTarget' => $currentSectionTarget,
            'layoutBaseHref' => cmsPromptEncodeRelativePath($layoutBasePath),
            'inheritedLayoutDirectory' => trim((string) ($layoutValue['inheritedDirectory'] ?? ''), "/\\"),
            'layoutSectionBaseHref' => cmsPromptEncodeRelativePath($layoutSectionBasePath),
            'layoutAssets' => $layoutAssets,
            'outerWrapper' => cmsPromptOuterWrapperReference($layoutValue, $currentSection),
        ],
        'items' => [],
        'allItems' => [],
        'allFiles' => [],
        'allFolders' => [],
        'allImages' => [],
        'allVideos' => [],
        'allAudio' => [],
        'allPdfs' => [],
        'allTexts' => [],
        'allLinks' => [],
        'allOther' => [],
    ];
    if (is_array($config['work']['fields'] ?? null) && $config['work']['fields'] !== []) {
        $context['current']['workFields'] = array_values(array_filter($config['work']['fields'], static fn($field): bool => is_array($field)));
    }

    $draftSummary = [];
    foreach (['template', 'sectionTemplate', 'css', 'js'] as $key) {
        if (!array_key_exists($key, $editorDraft) || !is_string($editorDraft[$key])) {
            continue;
        }
        $draftSummary[$key] = $editorDraft[$key];
    }
    if ($draftSummary !== []) {
        $context['current']['editorDraft'] = $draftSummary;
    }

    if ($subjectType !== 'folder') {
        return $context;
    }

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ref = cmsBuildPromptRef($normalizedPath, $item);
        if ($ref === null) {
            continue;
        }
        $context['items'][] = $ref;
        $context['allItems'][] = $ref;
        if ($ref['isFolder']) {
            $context['allFolders'][] = $ref;
            continue;
        }

        $context['allFiles'][] = $ref;
        switch ($ref['kind']) {
            case 'image':
                $context['allImages'][] = $ref;
                break;
            case 'video':
                $context['allVideos'][] = $ref;
                break;
            case 'audio':
                $context['allAudio'][] = $ref;
                break;
            case 'pdf':
                $context['allPdfs'][] = $ref;
                break;
            case 'text':
                $context['allTexts'][] = $ref;
                break;
            case 'link':
                $context['allLinks'][] = $ref;
                break;
            default:
                $context['allOther'][] = $ref;
                break;
        }
    }

    return $context;
}
