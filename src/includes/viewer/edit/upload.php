<?php
/**
 * Upload and blank-file helpers for edit actions.
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

        if (is_uploaded_file($tmpName)) {
            $ok = move_uploaded_file($tmpName, $targetPath);
        } else {
            $ok = copy($tmpName, $targetPath);
        }

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

function cmsPendingExternalLinkLimit(): int
{
    return 5;
}

function cmsCountPendingExternalLinks(array $tree): int
{
    $count = 0;
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['type'] ?? '') !== 'link') {
            continue;
        }
        if ((string) ($item['approvalStatus'] ?? '') !== 'pending') {
            continue;
        }
        $count++;
    }

    return $count;
}

function cmsIsAllowedExternalPoffLink(string $url): bool
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return false;
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts)) {
        return false;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    parse_str((string) ($parts['query'] ?? ''), $query);
    $view = (string) ($query['view'] ?? '');
    $path = trim((string) ($query['path'] ?? ''));
    $file = trim((string) ($query['file'] ?? ''));

    if ($view === '1' && ($path !== '' || $file !== '')) {
        return true;
    }

    $fragment = trim((string) ($parts['fragment'] ?? ''));
    if ($fragment === '') {
        return false;
    }

    $hashPath = trim(preg_replace('/^[!\\/]+/', '', $fragment) ?? '', "/\\ \t\n\r\0\x0B");
    return $hashPath !== '';
}

function cmsCreateLinkEntry(string $targetDir, string $label, string $linkUrl, array $options = []): array
{
    $target = trim($linkUrl);
    if ($target === '') {
        return cmsUploadErrorResult('Enter a link URL.');
    }

    $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return cmsUploadErrorResult('Link URLs must start with http:// or https://.');
    }

    $pendingApproval = !empty($options['pendingApproval']);
    if ($pendingApproval && !cmsIsAllowedExternalPoffLink($target)) {
        return cmsUploadErrorResult('Only links from another Poff system are allowed. Use a viewer URL with ?view=1&path=..., ?view=1&file=..., or a hash route like #/work-name.');
    }

    $baseUrl = cmsNormalizeRemoteBaseUrl($target);

    $dirError = cmsEnsureUploadDirectory($targetDir);
    if ($dirError !== null) {
        return $dirError;
    }

    $config = PoffConfig::ensure($targetDir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    if ($pendingApproval && cmsCountPendingExternalLinks($tree) >= cmsPendingExternalLinkLimit()) {
        return cmsUploadErrorResult('Too many external links are waiting for approval in this folder.');
    }

    $baseName = cmsUploadSafeName($label);
    if ($baseName === '') {
        $host = (string) (parse_url($target, PHP_URL_HOST) ?? '');
        $path = (string) (parse_url($target, PHP_URL_PATH) ?? '');
        $baseName = cmsUploadSafeName(trim($host . ' ' . basename($path)));
    }
    if ($baseName === '') {
        $baseName = 'poff-link';
    }

    $existingNames = [];
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string) ($item['name'] ?? ''));
        if ($name !== '') {
            $existingNames[$name] = true;
        }
    }

    $name = $baseName;
    $suffix = 2;
    while (isset($existingNames[$name])) {
        $name = $baseName . '-' . $suffix;
        $suffix++;
    }

    $now = date('c');
    $treeItem = [
        'name' => $name,
        'title' => trim($label) !== '' ? trim($label) : $name,
        'slug' => class_exists('PoffConfig') ? PoffConfig::slugify($name) : $name,
        'type' => 'link',
        'kind' => 'link',
        'path' => $name,
        'linkUrl' => $target,
        'baseUrl' => $baseUrl !== '' ? $baseUrl : $target,
        'visible' => !$pendingApproval,
        'modifiedAt' => $now,
    ];
    if ($pendingApproval) {
        $treeItem['externalSubmission'] = true;
        $treeItem['approvalStatus'] = 'pending';
        $treeItem['submittedAt'] = $now;
    }
    $tree[] = $treeItem;

    $config['tree'] = $tree;
    $config['treeHash'] = hash('sha256', json_encode($tree));
    $config['updatedAt'] = $now;

    $configPath = PoffConfig::configPath($targetDir);
    $dirPath = dirname($configPath);
    $dirError = cmsEnsureUploadDirectory($dirPath, 'Failed to create config directory.');
    if ($dirError !== null) {
        return $dirError;
    }

    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false || file_put_contents($configPath, $encoded) === false) {
        return cmsUploadErrorResult('Failed to write config file.');
    }

    if (!$pendingApproval) {
        $htaccessDir = trim((string) ($options['htaccessDir'] ?? ''));
        cmsEnsureHtaccessFrameAncestorForUrl($htaccessDir !== '' ? $htaccessDir : $targetDir, $target);
    }

    return cmsUploadStoredResult($name, [
        'linkUrl' => $target,
        'baseUrl' => $baseUrl !== '' ? $baseUrl : $target,
        'pendingApproval' => $pendingApproval,
    ]);
}

function cmsSetFolderVisibleState(string $parentDir, string $folderName, bool $visible): void
{
    $name = cmsUploadSafeName($folderName);
    if ($name === '' || !is_dir($parentDir)) {
        return;
    }

    $config = PoffConfig::ensure($parentDir);
    if (!is_array($config['tree'] ?? null)) {
        return;
    }

    $changed = false;
    foreach ($config['tree'] as &$item) {
        if (!is_array($item) || (string) ($item['name'] ?? '') !== $name) {
            continue;
        }
        if (!array_key_exists('visible', $item) || (bool) $item['visible'] !== $visible) {
            $item['visible'] = $visible;
            $changed = true;
        }
        break;
    }
    unset($item);

    if (!$changed) {
        return;
    }

    $config['treeHash'] = hash('sha256', json_encode($config['tree']));
    $config['updatedAt'] = date('c');
    file_put_contents(PoffConfig::configPath($parentDir), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cmsNormalizeRemoteBaseUrl(string $url): string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return rtrim($trimmed, '/');
    }

    $normalized = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }
    $path = trim((string) ($parts['path'] ?? ''), '/');
    if ($path !== '') {
        $normalized .= '/' . $path;
    }

    return rtrim($normalized, '/');
}

function cmsExternalFrameAncestorOrigin(string $url): string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }

    $parts = parse_url($trimmed);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }

    $origin = $scheme . '://' . $parts['host'];
    if (isset($parts['port'])) {
        $origin .= ':' . $parts['port'];
    }

    return $origin;
}

function cmsAppendFrameAncestorToPolicy(string $policyLine, string $origin): string
{
    if ($origin === '' || strpos($policyLine, 'frame-ancestors') === false) {
        return $policyLine;
    }
    if (preg_match('/\bframe-ancestors\s+\*/i', $policyLine) === 1) {
        return $policyLine;
    }
    if (strpos($policyLine, $origin) !== false) {
        return $policyLine;
    }

    return preg_replace_callback(
        '/(frame-ancestors\s+)([^"]+)/i',
        static function (array $matches) use ($origin): string {
            $existing = trim((string) ($matches[2] ?? ''));
            $updated = $existing === '' ? $origin : ($existing . ' ' . $origin);
            return (string) ($matches[1] ?? 'frame-ancestors ') . $updated;
        },
        $policyLine,
        1
    ) ?? $policyLine;
}

function cmsEnsureHtaccessFrameAncestorForUrl(string $targetDir, string $url): void
{
    $origin = cmsExternalFrameAncestorOrigin($url);
    if ($origin === '') {
        return;
    }

    $dirError = cmsEnsureUploadDirectory($targetDir);
    if ($dirError !== null) {
        return;
    }

    $htaccessPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
    $contents = is_file($htaccessPath) ? (string) file_get_contents($htaccessPath) : '';
    if (trim($contents) === '') {
        $contents = cmsDefaultHtaccessContents();
    }

    $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
    $updated = false;
    foreach ($lines as $index => $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        if (stripos($line, 'Header always set Content-Security-Policy') === false || stripos($line, 'frame-ancestors') === false) {
            continue;
        }
        $nextLine = cmsAppendFrameAncestorToPolicy($line, $origin);
        if ($nextLine !== $line) {
            $lines[$index] = $nextLine;
        }
        $updated = true;
        break;
    }

    if (!$updated) {
        $block = [
            '',
            '# Allow embedded preview for linked external client origins',
            '<IfModule mod_headers.c>',
            '    Header always set Content-Security-Policy "frame-ancestors \'self\' ' . $origin . '"',
            '</IfModule>',
        ];
        $contents = rtrim($contents);
        $contents .= ($contents === '' ? '' : PHP_EOL) . implode(PHP_EOL, $block) . PHP_EOL;
    } else {
        $contents = implode(PHP_EOL, $lines);
        if ($contents !== '' && !str_ends_with($contents, PHP_EOL)) {
            $contents .= PHP_EOL;
        }
    }

    @file_put_contents($htaccessPath, $contents);
}

function cmsUploadSafeName(string $name): string
{
    $name = trim($name);
    $name = str_replace(["\0", '/', '\\'], '-', $name);
    $name = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $name) ?? '';
    $name = trim($name, '-');
    return $name;
}
