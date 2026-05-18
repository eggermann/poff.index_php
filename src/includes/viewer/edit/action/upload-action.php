<?php

function cmsHandleEditUploadAction(array $ctx): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Upload requires POST.'], 405);
    }
    if (!$ctx['isLayoutTarget'] && $ctx['subjectType'] !== 'folder') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Uploads are only supported for folders.'], 400);
    }

    $data = $ctx['data'];
    $targetDir = $ctx['isLayoutTarget']
        ? ($ctx['subjectType'] === 'file'
            ? PoffConfig::fileLayoutDir($ctx['targetDir'], (string) $ctx['targetFile'])
            : PoffConfig::folderLayoutDir($ctx['targetDir']))
        : $ctx['targetDir'];
    $source = trim((string) ($data['source'] ?? 'upload'));
    if (!in_array($source, ['upload', 'blank', 'folder', 'url'], true)) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Unsupported add-content source.'], 400);
    }

    $result = match ($source) {
        'blank' => cmsCreateBlankFile($targetDir, (string) ($data['fileName'] ?? $data['filename'] ?? ''), (string) ($data['contents'] ?? '')),
        'folder' => cmsCreateFolder($targetDir, (string) ($data['fileName'] ?? $data['folderName'] ?? $data['filename'] ?? '')),
        'url' => cmsCreateLinkEntry(
            $targetDir,
            (string) ($data['linkName'] ?? $data['fileName'] ?? $data['filename'] ?? ''),
            (string) ($data['linkUrl'] ?? $data['url'] ?? '')
        ),
        default => function () use ($targetDir): array {
            $entries = cmsCollectUploadedEntries($_FILES['files'] ?? []);
            if ($entries === []) {
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

    $updatedConfig = $ctx['subjectType'] === 'file'
        ? PoffConfig::ensureFileConfig($ctx['targetDir'], (string) $ctx['targetFile'])
        : PoffConfig::ensure($ctx['targetDir']);

    cmsJsonResponse([
        'allowed' => true,
        'target' => $ctx['isLayoutTarget'] ? 'layout' : 'folder',
        'subjectTarget' => $ctx['subjectType'],
        'uploaded' => $result['stored'],
        'errors' => $result['errors'],
        'config' => $updatedConfig,
        'uploadLimits' => cmsUploadLimits(),
    ]);
}
