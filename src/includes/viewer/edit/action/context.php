<?php
/**
 * Shared edit action request context.
 */

function cmsBuildEditActionContext(): array
{
    $action = (string) ($_GET['edit'] ?? '');
    $runtimeRootDir = realpath(getcwd() ?: '.') ?: '.';
    $data = cmsReadJsonBody();
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $runtimeTarget = cmsResolveTarget($runtimeRootDir, $path);
    $runtimeAllowDir = $runtimeTarget['dir'] ?? $runtimeRootDir;
    $editModeAllowed = cmsEditModeAllowedForDirectory((string) $runtimeAllowDir, $runtimeRootDir);

    if ($action === 'auth') {
        return [
            'action' => $action,
            'runtimeRootDir' => $runtimeRootDir,
            'rootDir' => $runtimeRootDir,
            'data' => $data,
            'path' => $path,
            'target' => $runtimeTarget,
            'targetDir' => is_array($runtimeTarget) ? (string) ($runtimeTarget['dir'] ?? $runtimeRootDir) : $runtimeRootDir,
            'editModeAllowed' => $editModeAllowed,
        ];
    }

    if (!$editModeAllowed) {
        cmsJsonResponse([
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
            'auth' => cmsBuildEditorAuthView($runtimeRootDir, false),
        ], 403);
    }

    cmsRequireEditorAccess($runtimeRootDir, true);

    if (!class_exists('PoffConfig')) {
        cmsJsonResponse(['allowed' => true, 'error' => 'PoffConfig unavailable.', 'auth' => cmsBuildEditorAuthView($runtimeRootDir, true)]);
    }

    $target = cmsResolveTarget($runtimeRootDir, $path);
    if ($target === null) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Invalid folder path.', 'auth' => cmsBuildEditorAuthView($runtimeRootDir, true)]);
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
        'editModeAllowed' => $editModeAllowed,
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
