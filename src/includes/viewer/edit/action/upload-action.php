<?php

function cmsHandleEditUploadAction(array $ctx): void
{
    cmsEditRequirePost('Upload');
    if (!$ctx['isLayoutTarget'] && $ctx['subjectType'] !== 'folder') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Uploads are only supported for folders.'], 400);
    }

    $data = $ctx['data'];
    $rootDir = (string) ($ctx['rootDir'] ?? $ctx['targetDir'] ?? getcwd() ?: '.');
    $targetDir = $ctx['isLayoutTarget']
        ? ($ctx['subjectType'] === 'file'
            ? PoffConfig::fileLayoutDir($ctx['targetDir'], (string) $ctx['targetFile'])
            : PoffConfig::folderLayoutDir($ctx['targetDir']))
        : $ctx['targetDir'];
    $source = trim((string) ($data['source'] ?? 'upload'));
    if (!in_array($source, ['upload', 'blank', 'folder', 'url'], true)) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Unsupported add-content source.'], 400);
    }

    $editorAuthenticated = !empty($ctx['editorAuthenticated']);
    $publicExternalLinkSubmission = !empty($ctx['publicExternalLinkSubmission']);
    if ($publicExternalLinkSubmission && ($ctx['isLayoutTarget'] || $ctx['subjectType'] !== 'folder' || $source !== 'url')) {
        cmsJsonResponse(['allowed' => false, 'error' => 'External link submissions are only supported for folders.', 'auth' => cmsBuildEditorAuthView($rootDir, (bool) ($ctx['editModeAllowed'] ?? true))], 403);
    }

    $uploadTarget = cmsResolveUploadTargetDir($targetDir, $data, $source);
    if (is_array($uploadTarget['error'] ?? null)) {
        cmsJsonResponse(['allowed' => true, 'error' => $uploadTarget['error']['errors'][0] ?? 'Failed to prepare upload target.'], 400);
    }
    $targetDir = (string) ($uploadTarget['dir'] ?? $targetDir);

    $result = match ($source) {
        'blank' => cmsCreateBlankFile($targetDir, (string) ($data['fileName'] ?? $data['filename'] ?? ''), (string) ($data['contents'] ?? '')),
        'folder' => cmsCreateFolder($targetDir, (string) ($data['fileName'] ?? $data['folderName'] ?? $data['filename'] ?? '')),
        'url' => cmsCreateLinkEntry(
            $targetDir,
            (string) ($data['linkName'] ?? $data['fileName'] ?? $data['filename'] ?? ''),
            (string) ($data['linkUrl'] ?? $data['url'] ?? ''),
            [
                'pendingApproval' => !$editorAuthenticated,
                'htaccessDir' => $rootDir,
                'categories' => $data['categories'] ?? [],
                'poffNode' => $data['poffNode'] ?? [],
            ]
        ),
        default => function () use ($targetDir, $uploadTarget): array {
            $entries = cmsCollectUploadedEntries($_FILES['files'] ?? []);
            if ($entries === []) {
                if (!empty($uploadTarget['createdWorkFolder']) && (string) ($uploadTarget['folderName'] ?? '') !== '') {
                    return cmsUploadStoredResult((string) $uploadTarget['folderName'], [
                        'path' => (string) $uploadTarget['folderName'],
                        'type' => 'folder',
                    ]);
                }
                cmsJsonResponse(['allowed' => true, 'error' => 'No files selected.'], 400);
            }
            return cmsStoreUploadEntries($targetDir, $entries);
        },
    };
    if (is_callable($result)) {
        $result = $result();
    }

    if (($result['stored'] ?? []) === []) {
        cmsJsonResponse(['allowed' => true, 'error' => $result['errors'][0] ?? 'Upload failed.'], 400);
    }

    if (!empty($data['hideTargetFolder'])) {
        cmsSetFolderVisibleState(dirname($targetDir), basename($targetDir), false);
    }

    $updatedConfig = $ctx['subjectType'] === 'file'
        ? PoffConfig::ensureFileConfig($ctx['targetDir'], (string) $ctx['targetFile'])
        : PoffConfig::read($ctx['targetDir']);

    cmsJsonResponse([
        'allowed' => true,
        'target' => $ctx['isLayoutTarget'] ? 'layout' : 'folder',
        'subjectTarget' => $ctx['subjectType'],
        'uploaded' => $result['stored'],
        'errors' => $result['errors'],
        'config' => $updatedConfig,
        'createdWorkFolder' => !empty($uploadTarget['createdWorkFolder']),
        'workFolderName' => (string) ($uploadTarget['folderName'] ?? ''),
        'uploadLimits' => cmsUploadLimits(),
        'pendingApproval' => !empty($result['stored'][0]['pendingApproval']),
        'auth' => cmsBuildEditorAuthView($rootDir, (bool) ($ctx['editModeAllowed'] ?? true)),
    ]);
}
