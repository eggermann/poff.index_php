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
    array $editorDraft = [],
    array $parentPrompt = [],
    array $folderViewData = []
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
    $rootTitle = trim((string) ($config['title'] ?? $currentName));
    if ($rootTitle === '') {
        $rootTitle = $currentName;
    }
    $rootFolderName = trim((string) ($config['folderName'] ?? $currentName));
    if ($rootFolderName === '') {
        $rootFolderName = $currentName;
    }
    $rootSlug = trim((string) ($config['slug'] ?? ''));
    if ($rootSlug === '') {
        $rootSlug = PoffConfig::slugify($rootFolderName);
    }
    $rootDescription = trim((string) ($config['description'] ?? ''));
    $workSource = is_array($config['work'] ?? null) ? $config['work'] : [];
    $workTitle = trim((string) ($workSource['title'] ?? $currentName));
    if ($workTitle === '') {
        $workTitle = $currentName;
    }
    $workName = trim((string) ($workSource['name'] ?? $currentName));
    if ($workName === '') {
        $workName = $currentName;
    }
    $workSlug = trim((string) ($workSource['slug'] ?? ''));
    if ($workSlug === '') {
        $workSlug = $rootSlug;
    }
    $workDescription = trim((string) ($workSource['description'] ?? $rootDescription));
    $workType = trim((string) ($workSource['type'] ?? ($currentIsFile ? 'file' : 'folder')));
    if ($workType === '') {
        $workType = $currentIsFile ? 'file' : 'folder';
    }
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
            'title' => $rootTitle,
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
            'root' => [
                'title' => $rootTitle,
                'name' => $rootFolderName,
                'folderName' => $rootFolderName,
                'path' => $currentPath,
                'slug' => $rootSlug,
                'description' => $rootDescription,
                'type' => 'folder',
            ],
            'work' => [
                'title' => $workTitle,
                'name' => $workName,
                'path' => $currentPath,
                'slug' => $workSlug,
                'description' => $workDescription,
                'type' => $workType,
                'kind' => $workType,
            ],
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
        'siblingWorks' => [],
        'siblingImages' => [],
        'siblingVideos' => [],
        'siblingAudio' => [],
        'siblingPdfs' => [],
        'siblingTexts' => [],
        'siblingLinks' => [],
        'siblingFolders' => [],
        'siblingOther' => [],
    ];
    cmsApplyParentPromptContext($context, $parentPrompt, $currentPath);
    if ($subjectType === 'folder' && is_array($folderViewData['tree'] ?? null)) {
        $context['current']['tree'] = $folderViewData['tree'];
        $context['current']['workTree'] = $folderViewData['workTree'] ?? null;
        foreach (['allItems', 'allFiles', 'allFolders', 'allImages', 'allVideos', 'allAudio', 'allPdfs', 'allTexts', 'allLinks', 'allOther'] as $key) {
            if (array_key_exists($key, $folderViewData) && is_array($folderViewData[$key])) {
                $context['current'][$key] = $folderViewData[$key];
                $context[$key] = $folderViewData[$key];
            }
        }
        $context['items'] = $folderViewData['tree'];
        $context['allItems'] = $folderViewData['allItems'] ?? [];
        $context['allFiles'] = $folderViewData['allFiles'] ?? [];
        $context['allFolders'] = $folderViewData['allFolders'] ?? [];
        $context['allImages'] = $folderViewData['allImages'] ?? [];
        $context['allVideos'] = $folderViewData['allVideos'] ?? [];
        $context['allAudio'] = $folderViewData['allAudio'] ?? [];
        $context['allPdfs'] = $folderViewData['allPdfs'] ?? [];
        $context['allTexts'] = $folderViewData['allTexts'] ?? [];
        $context['allLinks'] = $folderViewData['allLinks'] ?? [];
        $context['allOther'] = $folderViewData['allOther'] ?? [];
    }
    if (is_array($config['work']['fields'] ?? null) && $config['work']['fields'] !== []) {
        $context['current']['workFields'] = array_values(array_filter($config['work']['fields'], static fn($field): bool => is_array($field)));
    }
    if (is_array($config['work']['templateMap'] ?? null)) {
        $context['current']['work']['templateMap'] = $config['work']['templateMap'];
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

    if (is_array($folderViewData['tree'] ?? null) && $folderViewData['tree'] !== []) {
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

function cmsPromptParentWork(array $parentConfig, string $parentRelativePath): array
{
    $parentPath = trim(str_replace('\\', '/', $parentRelativePath), '/');
    $parentName = trim((string) ($parentConfig['folderName'] ?? basename($parentPath)));
    if ($parentName === '') {
        $parentName = $parentPath !== '' ? basename($parentPath) : 'root';
    }
    $parentTitle = trim((string) ($parentConfig['title'] ?? $parentName));
    if ($parentTitle === '') {
        $parentTitle = $parentName;
    }
    $parentDescription = trim((string) ($parentConfig['description'] ?? ''));
    $parentWork = is_array($parentConfig['work'] ?? null) ? $parentConfig['work'] : [];
    $parentType = trim((string) ($parentWork['type'] ?? 'folder'));
    if ($parentType === '') {
        $parentType = 'folder';
    }
    $parentPageLink = cmsPromptViewerUrl($parentPath, false);
    $parentAssetUrl = cmsPromptAssetUrl($parentPath, false);

    return [
        'title' => $parentTitle,
        'name' => $parentName,
        'folderName' => $parentName,
        'path' => $parentPath,
        'slug' => (string) ($parentConfig['slug'] ?? PoffConfig::slugify($parentName)),
        'description' => $parentDescription,
        'type' => $parentType,
        'kind' => $parentType,
        'pageLink' => $parentPageLink,
        'pageUrl' => $parentPageLink,
        'workUrl' => $parentPageLink,
        'viewUrl' => $parentPageLink,
        'viewerHref' => $parentPageLink,
        'assetUrl' => $parentAssetUrl,
        'assetLink' => $parentAssetUrl,
        'rawHref' => $parentAssetUrl,
        'srcUrl' => $parentAssetUrl,
        'sourceUrl' => $parentAssetUrl,
    ];
}

function cmsApplyParentPromptContext(array &$context, array $parentPrompt, string $currentPath): void
{
    $parentConfig = is_array($parentPrompt['config'] ?? null) ? $parentPrompt['config'] : [];
    if ($parentConfig === []) {
        return;
    }

    $parentRelativePath = trim(str_replace('\\', '/', (string) ($parentPrompt['relativePath'] ?? '')), '/');
    $context['current']['parentWork'] = cmsPromptParentWork($parentConfig, $parentRelativePath);

    $currentNormalized = trim(str_replace('\\', '/', $currentPath), '/');
    $currentName = basename($currentNormalized);
    $tree = is_array($parentConfig['tree'] ?? null) ? $parentConfig['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ref = cmsBuildPromptRef($parentRelativePath, $item);
        if ($ref === null) {
            continue;
        }

        $refPath = trim(str_replace('\\', '/', (string) ($ref['path'] ?? '')), '/');
        $refName = trim((string) ($ref['name'] ?? ''));
        if ($refPath === $currentNormalized || ($refName !== '' && $refName === $currentName)) {
            continue;
        }

        $context['siblingWorks'][] = $ref;
        if ($ref['isFolder']) {
            $context['siblingFolders'][] = $ref;
            continue;
        }

        switch ($ref['kind']) {
            case 'image':
                $context['siblingImages'][] = $ref;
                break;
            case 'video':
                $context['siblingVideos'][] = $ref;
                break;
            case 'audio':
                $context['siblingAudio'][] = $ref;
                break;
            case 'pdf':
                $context['siblingPdfs'][] = $ref;
                break;
            case 'text':
                $context['siblingTexts'][] = $ref;
                break;
            case 'link':
                $context['siblingLinks'][] = $ref;
                break;
            default:
                $context['siblingOther'][] = $ref;
                break;
        }
    }
}
