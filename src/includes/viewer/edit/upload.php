<?php
/**
 * Upload and blank-file helpers for edit actions.
 */

function cmsCollectUploadedEntries(array $files): array
{
    $entries = [];
    $names = $files['name'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    $types = $files['type'] ?? [];

    if (!is_array($names) || !is_array($tmpNames) || !is_array($errors)) {
        return [];
    }

    foreach ($names as $index => $name) {
        $tmpName = (string) ($tmpNames[$index] ?? '');
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($tmpName === '' || $error !== UPLOAD_ERR_OK) {
            continue;
        }

        $entries[] = [
            'name' => (string) $name,
            'tmp_name' => $tmpName,
            'error' => $error,
            'size' => (int) ($sizes[$index] ?? 0),
            'type' => (string) ($types[$index] ?? ''),
        ];
    }

    return $entries;
}

function cmsStoreUploadEntries(string $targetDir, array $entries): array
{
    $stored = [];
    $errors = [];

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return [
            'stored' => [],
            'errors' => ['Failed to create target directory.'],
        ];
    }

    foreach ($entries as $entry) {
        $name = cmsUploadSafeName((string) ($entry['name'] ?? ''));
        $tmpName = (string) ($entry['tmp_name'] ?? '');
        if ($name === '' || $tmpName === '') {
            $errors[] = 'Invalid upload entry.';
            continue;
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $name;
        if (!is_uploaded_file($tmpName) && !is_file($tmpName)) {
            $errors[] = 'Upload source missing for ' . $name . '.';
            continue;
        }

        if (is_uploaded_file($tmpName)) {
            $ok = move_uploaded_file($tmpName, $targetPath);
        } else {
            $ok = copy($tmpName, $targetPath);
        }

        if (!$ok) {
            $errors[] = 'Failed to store ' . $name . '.';
            continue;
        }

        $stored[] = [
            'name' => $name,
            'path' => $name,
        ];
    }

    return [
        'stored' => $stored,
        'errors' => $errors,
    ];
}

function cmsCreateBlankFile(string $targetDir, string $fileName, string $contents = ''): array
{
    $name = cmsUploadSafeName($fileName);
    if ($name === '') {
        return [
            'stored' => [],
            'errors' => ['Invalid file name.'],
        ];
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return [
            'stored' => [],
            'errors' => ['Failed to create target directory.'],
        ];
    }

    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $name;
    $written = file_put_contents($targetPath, $contents);
    if ($written === false) {
        return [
            'stored' => [],
            'errors' => ['Failed to create blank file.'],
        ];
    }

    return [
        'stored' => [[
            'name' => $name,
            'path' => $name,
        ]],
        'errors' => [],
    ];
}

function cmsCreateFolder(string $targetDir, string $folderName): array
{
    $name = cmsUploadSafeName($folderName);
    if ($name === '') {
        return [
            'stored' => [],
            'errors' => ['Invalid folder name.'],
        ];
    }

    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $name;
    if (is_file($targetPath)) {
        return [
            'stored' => [],
            'errors' => ['A file with that name already exists.'],
        ];
    }
    if (is_dir($targetPath)) {
        return [
            'stored' => [],
            'errors' => ['Folder already exists.'],
        ];
    }
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return [
            'stored' => [],
            'errors' => ['Failed to create target directory.'],
        ];
    }
    if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
        return [
            'stored' => [],
            'errors' => ['Failed to create folder.'],
        ];
    }

    return [
        'stored' => [[
            'name' => $name,
            'path' => $name,
        ]],
        'errors' => [],
    ];
}

function cmsUploadSafeName(string $name): string
{
    $name = trim($name);
    $name = str_replace(["\0", '/', '\\'], '-', $name);
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?? '';
    $name = trim($name, '-');
    return $name;
}
