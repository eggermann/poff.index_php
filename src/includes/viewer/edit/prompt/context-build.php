<?php

require_once __DIR__ . '/context-state.php';
require_once __DIR__ . '/../core/parent.php';
require_once __DIR__ . '/../prompt-context/parent.php';

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
    $state = cmsPromptBuildCurrentState($relativePath, $subjectType, $config, $isLayoutTarget, $layoutPreset);
    if ($subjectType === 'folder' && $folderViewData === []) {
        $fallbackFullPath = rtrim(getcwd() ?: '.', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $state['normalizedPath']);
        $folderViewData = buildFolderViewerData($state['normalizedPath'], $fallbackFullPath, $config, [
            'name' => (string) ($config['folderName'] ?? basename($state['normalizedPath'])),
            'title' => (string) ($config['title'] ?? $config['folderName'] ?? basename($state['normalizedPath'])),
            'slug' => (string) ($config['slug'] ?? PoffConfig::slugify((string) ($config['folderName'] ?? basename($state['normalizedPath'])))),
        ]);
    }
    $layoutAssets = cmsPromptBuildContextAssets($state['layoutValue'], $state['layoutBasePath']);
    $rootAndWork = cmsPromptBuildContextRoot($config, $state['currentName'], $state['normalizedPath'], $state['currentIsFile']);
    $workFields = is_array($config['work']['fields'] ?? null) ? $config['work']['fields'] : [];

    $context = [
        'current' => [
            'targetType' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectType' => $subjectType,
            'layoutPreset' => $state['normalizedLayoutPreset'],
            'title' => $rootAndWork['root']['title'],
            'sectionPartial' => $state['currentSection'],
            'name' => $state['currentName'],
            'path' => $state['normalizedPath'],
            'virtualPath' => $isLayoutTarget ? PoffConfig::relativeLayoutPath($state['normalizedPath'], $state['currentIsFile']) : '',
            'pageLink' => $state['currentPageLink'],
            'pageUrl' => $state['currentPageLink'],
            'workUrl' => $state['currentPageLink'],
            'viewUrl' => $state['currentPageLink'],
            'viewerHref' => $state['currentPageLink'],
            'assetUrl' => $state['currentAssetUrl'],
            'assetLink' => $state['currentAssetUrl'],
            'rawHref' => $state['currentAssetUrl'],
            'srcUrl' => $state['currentAssetUrl'],
            'sourceUrl' => $state['currentAssetUrl'],
            'templateTarget' => $isLayoutTarget ? trim($state['activeLayoutDirectory'], "/\\") . '/template.hbs' : $state['currentSectionTarget'],
            'layoutTemplateTarget' => $state['currentLocalLayoutTarget'],
            'sectionTemplateTarget' => $state['currentSectionTarget'],
            'layoutBaseHref' => cmsPromptEncodeRelativePath($state['layoutBasePath']),
            'inheritedLayoutDirectory' => $state['resolvedLayoutDirectory'],
            'layoutSectionBaseHref' => cmsPromptEncodeRelativePath($state['layoutSectionBasePath']),
            'layoutAssets' => $layoutAssets,
            'outerWrapper' => cmsPromptOuterWrapperReference($state['layoutValue'], $state['currentSection']),
            'root' => $rootAndWork['root'],
            'work' => $rootAndWork['work'],
            'workFields' => $workFields,
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
    cmsApplyParentPromptContext($context, $parentPrompt, $state['normalizedPath']);
    if ($subjectType === 'folder' && is_array($folderViewData['tree'] ?? null)) {
        $context['current']['tree'] = $folderViewData['tree'];
        $context['current']['workTree'] = $folderViewData['workTree'] ?? null;
        foreach (['allItems', 'allFiles', 'allFolders', 'allImages', 'allVideos', 'allAudio', 'allPdfs', 'allTexts', 'allLinks', 'allOther'] as $key) {
            if (isset($folderViewData[$key]) && is_array($folderViewData[$key])) {
                $context[$key] = $folderViewData[$key];
            }
        }
        $context['items'] = $folderViewData['tree'];
    }
    if (is_array($editorDraft) && $editorDraft !== []) {
        $context['current']['editorDraft'] = $editorDraft;
    }
    return $context;
}
