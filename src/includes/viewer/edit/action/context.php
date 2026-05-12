<?php
/**
 * Shared edit action request context.
 */

function cmsBuildEditActionContext(): array
{
    $action = (string) ($_GET['edit'] ?? '');
    $runtimeRootDir = realpath(getcwd() ?: '.') ?: '.';
    $data = ($action === 'save' || $action === 'prompt') ? cmsReadJsonBody() : [];
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $runtimeTarget = cmsResolveTarget($runtimeRootDir, $path);
    $runtimeAllowDir = $runtimeTarget['dir'] ?? $runtimeRootDir;

    if (!cmsEditModeAllowedForDirectory((string) $runtimeAllowDir, $runtimeRootDir)) {
        cmsJsonResponse(['allowed' => false, 'error' => 'Edit mode not enabled.']);
    }

    if (!class_exists('PoffConfig')) {
        cmsJsonResponse(['allowed' => true, 'error' => 'PoffConfig unavailable.']);
    }

    $target = cmsResolveTarget($runtimeRootDir, $path);
    if ($target === null) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Invalid folder path.']);
    }

    $targetType = (string) $target['type'];
    $isLayoutTarget = $targetType === 'layout';
    $subjectType = $isLayoutTarget ? (string) ($target['subjectType'] ?? 'folder') : $targetType;
    $subjectRelativePath = $isLayoutTarget ? (string) ($target['subjectRelativePath'] ?? '') : trim($path, "/\\");
    $targetDir = (string) $target['dir'];
    $targetFile = $target['file'] ?? null;

    $config = $subjectType === 'file'
        ? PoffConfig::ensureFileConfig($targetDir, (string) $targetFile)
        : PoffConfig::ensure($targetDir);

    $folderViewData = $subjectType === 'folder'
        ? cmsPromptFolderViewData(
            $subjectRelativePath,
            $targetDir,
            $config,
            [
                'name' => (string) ($config['folderName'] ?? basename($subjectRelativePath)),
                'title' => (string) ($config['title'] ?? $config['folderName'] ?? basename($subjectRelativePath)),
                'slug' => (string) ($config['slug'] ?? PoffConfig::slugify((string) ($config['folderName'] ?? basename($subjectRelativePath)))),
            ]
        )
        : [];

    return [
        'action' => $action,
        'runtimeRootDir' => $runtimeRootDir,
        'rootDir' => $runtimeRootDir,
        'data' => $data,
        'path' => $path,
        'target' => $target,
        'targetType' => $targetType,
        'isLayoutTarget' => $isLayoutTarget,
        'subjectType' => $subjectType,
        'subjectRelativePath' => $subjectRelativePath,
        'targetDir' => $targetDir,
        'targetFile' => $targetFile,
        'config' => $config,
        'folderViewData' => $folderViewData,
    ];
}
