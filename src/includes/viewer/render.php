<?php
/**
 * Viewer rendering: HTML output for files and folders.
 */

require_once __DIR__ . '/utils.php';

function renderViewer(string $baseDir, string $requestedPath): void
{
    $relativePath = sanitizeRelativePath($requestedPath);

    if (strpos($relativePath, '..') !== false) {
        http_response_code(400);
        echo 'Invalid path.';
        return;
    }

    $fullPath = rtrim($baseDir, DIRECTORY_SEPARATOR);
    if ($relativePath !== '') {
        $fullPath .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo 'Path not found.';
        return;
    }

    if (is_dir($fullPath)) {
        renderFolderViewer($relativePath, $fullPath);
        return;
    }

    renderFileViewer($relativePath, $fullPath);
}

function renderFileViewer(string $relativePath, string $fullPath): void
{
    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }

    $type = detectFileType($fullPath);
    $mimeType = MediaType::detectMimeType($fullPath, basename($fullPath));
    $workDefaults = Worktype::definition($type, $mimeType);
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    $work = array_merge($workDefaults, $workData);

    $linkUrl = null;
    if ($type === 'link') {
        $linkUrl = extractLinkFileUrl($fullPath);
    }

    $rawName = basename($relativePath);
    $rawSlug = $fileConfig['slug'] ?? preg_replace('/[^a-z0-9\\-]+/i', '-', $rawName);
    $rawSlug = trim((string) $rawSlug, '-');

    $descriptionHtml = '';
    if (!empty($fileConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($fileConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    $bodyContent = Worktype::render($type, [
        'path' => $relativePath,
        'mimeType' => $mimeType ?? '',
        'name' => $rawName,
        'title' => $fileConfig['title'] ?? $rawName,
        'description' => $fileConfig['description'] ?? '',
        'descriptionHtml' => $descriptionHtml,
        'linkUrl' => $linkUrl ?? '',
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
        'work' => $work,
    ]);

    renderViewerShell([
        'type' => $type,
        'name' => $rawName,
        'path' => $relativePath,
        'bodyContent' => $bodyContent,
        'openHref' => viewerAssetHref($relativePath),
        'openLabel' => 'Open Raw',
    ]);
}

function renderFolderViewer(string $relativePath, string $fullPath): void
{
    $folderConfig = null;
    if (class_exists('PoffConfig')) {
        $folderConfig = PoffConfig::ensure($fullPath);
    }

    $workDefaults = Worktype::definition('folder');
    $workData = (isset($folderConfig['work']) && is_array($folderConfig['work'])) ? $folderConfig['work'] : [];
    $work = array_merge($workDefaults, $workData);

    $rawName = $folderConfig['folderName'] ?? basename($fullPath);
    if ($rawName === '') {
        $rawName = basename(rtrim($fullPath, DIRECTORY_SEPARATOR));
    }
    $rawSlug = $folderConfig['slug'] ?? preg_replace('/[^a-z0-9\\-]+/i', '-', $rawName);
    $rawSlug = trim((string) $rawSlug, '-');
    $tree = buildFolderViewerItems($relativePath, $fullPath, $folderConfig);
    $descriptionHtml = '';
    if (!empty($folderConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($folderConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    $bodyContent = Worktype::render('folder', [
        'path' => $relativePath,
        'displayPath' => $relativePath === '' ? '.' : $relativePath,
        'name' => $rawName,
        'title' => $folderConfig['title'] ?? $rawName,
        'description' => $folderConfig['description'] ?? '',
        'descriptionHtml' => $descriptionHtml,
        'linkUrl' => $folderConfig['link'] ?? $folderConfig['url'] ?? '',
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
        'folderName' => $folderConfig['folderName'] ?? $rawName,
        'tree' => $tree,
        'items' => $tree,
        'hasItems' => $tree !== [],
        'itemCount' => count($tree),
        'work' => $work,
    ]);

    $browseHref = '?path=';
    if ($relativePath !== '') {
        $browseHref .= rawurlencode($relativePath);
    }

    renderViewerShell([
        'type' => 'folder',
        'name' => $rawName,
        'path' => $relativePath,
        'bodyContent' => $bodyContent,
        'openHref' => $browseHref,
        'openLabel' => 'Open Folder',
    ]);
}

function buildFolderViewerItems(string $relativePath, string $fullPath, ?array $folderConfig): array
{
    $tree = [];
    if (is_array($folderConfig) && isset($folderConfig['tree']) && is_array($folderConfig['tree'])) {
        $tree = $folderConfig['tree'];
    } elseif (class_exists('PoffConfig')) {
        $tree = PoffConfig::buildFirstLevelTree($fullPath);
    }

    $items = [];
    foreach ($tree as $entry) {
        if (!is_array($entry) || (($entry['visible'] ?? true) === false)) {
            continue;
        }
        $entryName = trim((string) ($entry['name'] ?? ''));
        if ($entryName === '') {
            continue;
        }

        $entryType = (($entry['type'] ?? 'file') === 'folder') ? 'folder' : 'file';
        $entryRelativePath = $relativePath === '' ? $entryName : $relativePath . '/' . $entryName;
        $entryFullPath = $fullPath . DIRECTORY_SEPARATOR . $entryName;
        $entryKind = $entryType === 'folder' ? 'folder' : detectFileType($entryFullPath);

        $items[] = array_merge($entry, [
            'name' => $entryName,
            'title' => $entry['title'] ?? $entryName,
            'type' => $entryType,
            'kind' => $entryKind,
            'path' => $entryRelativePath,
            'viewerHref' => '?view=1&path=' . rawurlencode($entryRelativePath),
            'isFolder' => $entryType === 'folder',
            'isFile' => $entryType !== 'folder',
        ]);
    }

    return $items;
}

function viewerAssetHref(string $relativePath): string
{
    if ($relativePath === '') {
        return '';
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}

function renderViewerShell(array $payload): void
{
    $rawType = (string) ($payload['type'] ?? 'file');
    $rawName = (string) ($payload['name'] ?? '');
    $rawPath = (string) ($payload['path'] ?? '');
    $bodyContent = (string) ($payload['bodyContent'] ?? '');
    $openHref = isset($payload['openHref']) ? (string) $payload['openHref'] : '';
    $openLabel = isset($payload['openLabel']) ? (string) $payload['openLabel'] : 'Open';

    $safeName = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
    $safePath = htmlspecialchars($rawPath === '' ? '.' : $rawPath, ENT_QUOTES, 'UTF-8');
    $safeOpenHref = htmlspecialchars($openHref, ENT_QUOTES, 'UTF-8');
    $safeOpenLabel = htmlspecialchars($openLabel, ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($rawType, ENT_QUOTES, 'UTF-8');

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer - <?= $safeName ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #0b1021;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        header {
            padding: 12px 16px;
            background: #111827;
            border-bottom: 1px solid #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        header .meta { display: flex; flex-direction: column; }
        header .meta .name { font-weight: 600; }
        header .meta .path { font-size: 12px; color: #9ca3af; }
        header a { color: #93c5fd; text-decoration: none; font-size: 14px; }
        header a:hover { text-decoration: underline; }
        .viewer {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0b1021;
            overflow: hidden;
        }
        .viewer img, .viewer video, .viewer iframe {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,0.45);
            border-radius: 6px;
            background: #111827;
        }
        .viewer video, .viewer iframe { width: 100%; height: 100%; }
        .viewer iframe { background: #c3cddbff; }
        .viewer .viewer-template {
            width: 100%;
            min-height: 100%;
            box-sizing: border-box;
        }
        .viewer .viewer-template--folder {
            padding: 24px;
        }
        .folder-view {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .folder-view-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            color: #9ca3af;
            font-size: 13px;
        }
        .folder-view-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .folder-view-card {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(17, 24, 39, 0.72);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.28);
        }
        .folder-view-card-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.16);
            color: #93c5fd;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .folder-view-card--folder .folder-view-card-label {
            background: rgba(34, 197, 94, 0.16);
            color: #86efac;
        }
        .folder-view-card-name {
            display: block;
            margin-top: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #f9fafb;
            word-break: break-word;
        }
        .folder-view-card-path {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #94a3b8;
            word-break: break-word;
        }
        .work-description {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            padding: 12px 14px;
            background: rgba(17, 24, 39, 0.6);
            color: #e5e7eb;
            border-radius: 16px;
            backdrop-filter: blur(8px);
            margin: 0;
            max-width: 70%;
            line-height: 1.4;
            box-shadow: 0 20px 40px rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .message { padding: 24px; text-align: center; color: #d1d5db; }
    </style>
</head>
<body>
    <header>
        <div class="meta">
            <div class="name"><?= $safeName ?> <span style="opacity:0.7;font-size:12px;">(<?= $safeType ?>)</span></div>
            <div class="path"><?= $safePath ?></div>
        </div>
        <div>
            <a href="<?= $safeOpenHref ?>" target="_blank" rel="noopener"><?= $safeOpenLabel ?></a>
        </div>
    </header>
    <div class="viewer">
        <?= $bodyContent ?>
    </div>
</body>
</html>
<?php
}
