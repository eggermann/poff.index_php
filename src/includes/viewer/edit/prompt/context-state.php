<?php

function cmsPromptBuildContextAssets(array $layoutValue, string $layoutBasePath): array
{
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

    return $layoutAssets;
}

function cmsPromptBuildCurrentState(
    string $relativePath,
    string $subjectType,
    array $config,
    bool $isLayoutTarget,
    string $layoutPreset
): array {
    $normalizedPath = trim($relativePath, "/\\");
    $currentIsFile = $subjectType === 'file';
    $currentName = $currentIsFile ? (string) ($config['fileName'] ?? basename($normalizedPath)) : (string) ($config['folderName'] ?? basename($normalizedPath));
    $layoutValue = is_array($config['work'] ?? null) && is_array($config['work']['layout'] ?? null) ? $config['work']['layout'] : [];
    $currentSection = $currentIsFile ? 'work' : 'works';
    $currentPageLink = cmsPromptViewerUrl($normalizedPath, $currentIsFile);
    $currentAssetUrl = cmsPromptAssetUrl($normalizedPath, $currentIsFile);
    $currentSectionTarget = cmsPromptTemplateTarget($normalizedPath, $currentIsFile, $currentSection);
    $currentLocalLayoutTarget = cmsPromptLayoutTemplateTarget($normalizedPath, $currentIsFile);
    $currentLocalLayoutDirectory = preg_replace('#/template\.hbs$#', '', $currentLocalLayoutTarget) ?: $currentLocalLayoutTarget;
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

    return [
        'normalizedPath' => $normalizedPath,
        'currentName' => $currentName,
        'currentIsFile' => $currentIsFile,
        'currentSection' => $currentSection,
        'currentPageLink' => $currentPageLink,
        'currentAssetUrl' => $currentAssetUrl,
        'currentSectionTarget' => $currentSectionTarget,
        'currentLocalLayoutTarget' => $currentLocalLayoutTarget,
        'currentLocalLayoutDirectory' => $currentLocalLayoutDirectory,
        'resolvedLayoutDirectory' => $resolvedLayoutDirectory,
        'layoutStorage' => $layoutStorage,
        'normalizedLayoutPreset' => $normalizedLayoutPreset,
        'activeLayoutDirectory' => $activeLayoutDirectory,
        'layoutBasePath' => $activeLayoutDirectory,
        'layoutSectionBasePath' => trim((string) ($layoutValue['sectionDirectory'] ?? $activeLayoutDirectory), "/\\"),
        'layoutValue' => $layoutValue,
    ];
}

function cmsPromptBuildContextRoot(array $config, string $currentName, string $currentPath, bool $currentIsFile): array
{
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

    $result = [
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
    ];

    if (is_array($workSource['templateMap'] ?? null)) {
        $result['work']['templateMap'] = $workSource['templateMap'];
    }

    return $result;
}
