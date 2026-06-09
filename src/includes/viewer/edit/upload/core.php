<?php
/**
 * Upload core helpers.
 */

function cmsUploadStoredItem(string $name, array $extra = []): array
{
    return array_merge([
        'name' => $name,
        'path' => $name,
    ], $extra);
}

function cmsUploadErrorResult(string $message): array
{
    return [
        'stored' => [],
        'errors' => [$message],
    ];
}

function cmsUploadStoredResult(string $name, array $extra = []): array
{
    return [
        'stored' => [cmsUploadStoredItem($name, $extra)],
        'errors' => [],
    ];
}

function cmsEnsureUploadDirectory(string $targetDir, string $errorMessage = 'Failed to create target directory.'): ?array
{
    if (is_dir($targetDir) || (mkdir($targetDir, 0755, true) || is_dir($targetDir))) {
        return null;
    }

    return cmsUploadErrorResult($errorMessage);
}

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

    $dirError = cmsEnsureUploadDirectory($targetDir);
    if ($dirError !== null) {
        return $dirError;
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

        $ok = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $targetPath)
            : copy($tmpName, $targetPath);
        if (!$ok) {
            $errors[] = 'Failed to store ' . $name . '.';
            continue;
        }

        $stored[] = cmsUploadStoredItem($name);
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
        return cmsUploadErrorResult('Invalid file name.');
    }

    $dirError = cmsEnsureUploadDirectory($targetDir);
    if ($dirError !== null) {
        return $dirError;
    }

    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $name;
    if ($name === '.htaccess' && trim($contents) === '') {
        $contents = cmsDefaultHtaccessContents();
    }
    $written = file_put_contents($targetPath, $contents);
    if ($written === false) {
        return cmsUploadErrorResult('Failed to create blank file.');
    }

    return cmsUploadStoredResult($name);
}

function cmsDefaultHtaccessContents(): string
{
    return <<<'HTACCESS'
# Poff iframe policy
# Edit this file to control which sites may embed this CMS in an <iframe>.

<IfModule mod_headers.c>
    Header always unset X-Frame-Options
    Header always set Content-Security-Policy "frame-ancestors *;"
</IfModule>

# To restrict embedding, replace the policy above with something like:
# <IfModule mod_headers.c>
#     Header always set Content-Security-Policy "frame-ancestors 'self'"
# </IfModule>
HTACCESS;
}

function cmsCreateFolder(string $targetDir, string $folderName): array
{
    $name = cmsUploadSafeName($folderName);
    if ($name === '') {
        return cmsUploadErrorResult('Invalid folder name.');
    }

    $targetPath = $targetDir . DIRECTORY_SEPARATOR . $name;
    if (is_file($targetPath)) {
        return cmsUploadErrorResult('A file with that name already exists.');
    }
    if (is_dir($targetPath)) {
        return cmsUploadErrorResult('Folder already exists.');
    }
    $dirError = cmsEnsureUploadDirectory($targetDir);
    if ($dirError !== null) {
        return $dirError;
    }
    if (!mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
        return cmsUploadErrorResult('Failed to create folder.');
    }

    return cmsUploadStoredResult($name);
}

function cmsUploadSafeName(string $name): string
{
    $name = trim($name);
    $name = str_replace(["\0", '/', '\\'], '-', $name);
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?? '';
    $name = trim($name, '-');
    return $name;
}
