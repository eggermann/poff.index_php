<?php
// Navigation list rendering. Consumes existing variables set in build output:
// $baseDir, $currentRelativePath, $currentAbsolutePath, $currentScript, $folderPoffConfig.

$editQuery = (isset($_GET['edit']) && $_GET['edit'] === 'true') ? '&edit=true' : '';
$editMode = isset($_GET['edit']) && $_GET['edit'] === 'true';
$navFolder = $currentRelativePath;

if (!function_exists('cmsNavSlug')) {
    function cmsNavSlug(string $value): string
    {
        if (class_exists('PoffConfig')) {
            return PoffConfig::slugify($value);
        }

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($value));
        return trim((string) $slug, '-') ?: 'untitled';
    }
}

if (!function_exists('cmsNavEntry')) {
    function cmsNavEntry(string $name, string $path, string $type, string $icon, array $source = [], ?string $dataSrc = null, string $editQuery = ''): array
    {
        $title = (string) ($source['title'] ?? $name);
        $slug = (string) ($source['slug'] ?? cmsNavSlug($title));
        $entry = [
            'name' => $name,
            'path' => $path,
            'slug' => $slug,
            'icon' => $icon,
        ];

        if ($type === 'folder') {
            $entry['link'] = '?path=' . urlencode($path) . $editQuery;
        } else {
            $entry['data_src'] = $dataSrc ?? $path;
        }

        return $entry;
    }
}

$folderIcon = '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>';
$fileIcon = '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L12 1.586A2 2 0 0010.586 1H4zm6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>';

// If hash contains a folder, prefer that for initial render
if (isset($_SERVER['QUERY_STRING']) && preg_match('/#\/([^\/]+)(?:\/([^\/]+))?/', $_SERVER['QUERY_STRING'], $matches)) {
    $navFolder = $matches[1];
}

if (!empty($navFolder)) {
    $parentRelativePath = dirname($navFolder);
    if ($parentRelativePath === '.') {
        $parentRelativePath = '';
    }
    $upLink = '?path=' . urlencode($parentRelativePath) . $editQuery;
    echo '<li><a class="nav-link nav-link-up" href="' . htmlspecialchars($upLink) . '"><svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.7a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg> Go Up</a></li>';
}

// Current directory link
if ($navFolder !== '') {
    //root;
}

$currentNavConfig = is_array($config ?? null) ? $config : [];
$currentNavName = (string) ($currentNavConfig['folderName'] ?? basename($navFolder));
$currentNavSlug = (string) ($currentNavConfig['slug'] ?? cmsNavSlug((string) ($currentNavConfig['title'] ?? $currentNavName)));
echo '<li><a class="nav-link nav-link-up" href="' . htmlspecialchars('?path=' . urlencode($navFolder) . $editQuery) . '" data-path="' . htmlspecialchars($navFolder) . '" data-slug="' . htmlspecialchars($currentNavSlug) . '">./ ' . htmlspecialchars($currentNavName) . '</a></li>';


$navAbsolutePath = $baseDir . ($navFolder ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $navFolder) : '');
$navKind = file_exists($navAbsolutePath) ? (is_dir($navAbsolutePath) ? 'dir' : 'file') : 'missing';
if ($navKind === 'file') {
    $navFolder = dirname($navFolder);
    if ($navFolder === '.') {
        $navFolder = '';
    }
    $navAbsolutePath = $baseDir . ($navFolder ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $navFolder) : '');
}
$directories = $files = [];
$tree = $folderPoffConfig['tree'] ?? null;

if (is_array($tree)) {
    foreach ($tree as $item) {
        $isHidden = isset($item['visible']) && $item['visible'] === false;
        if ($isHidden && !$editMode) {
            continue;
        }
        $itemName = $item['name'] ?? '';
        if ($itemName === '' || ($currentRelativePath === '' && $itemName === $currentScript)) {
            continue;
        }
        $itemType = $item['type'] ?? 'file';
        $relativePath = $navFolder ? rtrim($navFolder, "/\\") . '/' . $itemName : $itemName;
        $fullPath = $navAbsolutePath . DIRECTORY_SEPARATOR . $itemName;
        $linkUrl = ($itemType === 'folder') ? null : extractLinkFileUrl($fullPath);

        if ($itemType === 'folder') {
            $directories[] = cmsNavEntry($itemName, $relativePath, 'folder', $folderIcon, $item, null, $editQuery) + ['hidden' => $isHidden];
        } else {
            $files[] = cmsNavEntry($itemName, $relativePath, 'file', $fileIcon, $item, $linkUrl ?? $relativePath, $editQuery) + ['hidden' => $isHidden];
        }
    }
} else {
    $items = is_dir($navAbsolutePath) ? scandir($navAbsolutePath) : false;
    if ($items !== false) {
        foreach ($items as $item) {
        if (
                $item === '.' ||
                $item === '..' ||
                $item === '.works' ||
                $item === '.layout' ||
                $item === '.htaccess' ||
                $item === '.DS_Store' ||
                $item === 'Thumbs.db' ||
                $item === '.git' ||
                $item === '.idea' ||
                $item === 'node_modules' ||
                $item === '.edit.allow' ||
                ($currentRelativePath === '' && $item === $currentScript) ||
                $item === 'poff.config.json'
            ) {
                continue;
            }

            $itemFullPath = $navAbsolutePath . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($itemFullPath);
            $linkUrl = $isDir ? null : extractLinkFileUrl($itemFullPath);
            $itemRelativePath = $navFolder ? rtrim($navFolder, "/\\") . '/' . $item : $item;

            if ($isDir) {
            $directories[] = cmsNavEntry($item, $itemRelativePath, 'folder', $folderIcon, [], null, $editQuery);
            } else {
                $files[] = cmsNavEntry($item, $itemRelativePath, 'file', $fileIcon, [], $linkUrl ?? $itemRelativePath, $editQuery);
            }
        }
    } else {
        echo '<li>Error reading directory.</li>';
    }
}

usort($directories, fn($a, $b) => strcasecmp($a['name'], $b['name']));
usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

foreach ($directories as $dir) {
    $isHidden = !empty($dir['hidden']);
    $hiddenAttrs = $isHidden
        ? ' aria-disabled="true" tabindex="-1" data-hidden="true" style="opacity:.48;filter:grayscale(1);cursor:not-allowed;pointer-events:none;"'
        : '';
    $hiddenBadge = $isHidden
        ? '<span style="margin-left:auto;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">hidden</span>'
        : '';
    echo '<li><a class="nav-link' . ($isHidden ? ' nav-link-hidden' : '') . '" href="' . htmlspecialchars($isHidden ? '#' : $dir['link']) . '" data-path="' . htmlspecialchars($dir['path']) . '" data-slug="' . htmlspecialchars($dir['slug']) . '"' . $hiddenAttrs . '>' . $dir['icon'] . htmlspecialchars($dir['name']) . $hiddenBadge . '</a></li>';
}

if (isset($_GET['edit']) && $_GET['edit'] === 'true') {
    $layoutVirtualPath = $navFolder !== '' ? rtrim($navFolder, "/\\") . '/.layout' : '.layout';
    $layoutHash = '#/' . str_replace('%2F', '/', rawurlencode($layoutVirtualPath));
    echo '<li><a class="nav-link nav-link-layout" href="' . htmlspecialchars($layoutHash) . '" data-layout-path="' . htmlspecialchars($layoutVirtualPath) . '">'
        . '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.75A1.75 1.75 0 014.75 3h10.5A1.75 1.75 0 0117 4.75v10.5A1.75 1.75 0 0115.25 17H4.75A1.75 1.75 0 013 15.25V4.75zm2 1a.75.75 0 000 1.5h10a.75.75 0 000-1.5H5zm0 3.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5H5zm0 3.5a.75.75 0 000 1.5h7a.75.75 0 000-1.5H5z" clip-rule="evenodd"/></svg>'
        . '.layout</a></li>';
    $htaccessPath = $navFolder !== '' ? rtrim($navFolder, "/\\") . '/.htaccess' : '.htaccess';
    $htaccessFullPath = $navAbsolutePath . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($htaccessFullPath)) {
        echo '<li><a class="nav-link nav-link-htaccess" href="' . htmlspecialchars('?path=' . urlencode($htaccessPath) . $editQuery) . '" data-path="' . htmlspecialchars($htaccessPath) . '" data-file=".htaccess">'
            . '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L12 1.586A2 2 0 0010.586 1H4zm6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>'
            . '.htaccess</a></li>';
    }
}

foreach ($files as $file) {
    $isHidden = !empty($file['hidden']);
    $hiddenAttrs = $isHidden
        ? ' aria-disabled="true" tabindex="-1" data-hidden="true" style="opacity:.48;filter:grayscale(1);cursor:not-allowed;pointer-events:none;"'
        : '';
    $hiddenBadge = $isHidden
        ? '<span style="margin-left:auto;font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">hidden</span>'
        : '';
    echo '<li><a class="nav-link' . ($isHidden ? ' nav-link-hidden' : '') . '" href="#" data-path="' . htmlspecialchars($file['path']) . '" data-src="' . htmlspecialchars($file['data_src']) . '" data-file="' . htmlspecialchars($file['name']) . '" data-slug="' . htmlspecialchars($file['slug']) . '"' . $hiddenAttrs . '>' . $file['icon'] . htmlspecialchars($file['name']) . $hiddenBadge . '</a></li>';
}
