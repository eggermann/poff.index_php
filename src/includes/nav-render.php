<?php

function cmsNavSlug(string $value): string
{
    if (class_exists('PoffConfig')) {
        return PoffConfig::slugify($value);
    }

    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($value));
    return trim((string) $slug, '-') ?: 'untitled';
}

function cmsNavEntry(string $name, string $path, string $type, string $icon, array $source = [], ?string $dataSrc = null, string $editQuery = ''): array
{
    $title = (string) ($source['title'] ?? $name);
    $slug = (string) ($source['slug'] ?? cmsNavSlug($title));
    $entry = [
        'name' => $name,
        'title' => $title,
        'path' => $path,
        'slug' => $slug,
        'icon' => $icon,
        'copyText' => (string) ($source['name'] ?? $name),
    ];

    if ($type === 'folder') {
        $entry['link'] = '?path=' . urlencode($path) . $editQuery;
    } else {
        $entry['data_src'] = $dataSrc ?? $path;
    }

    return $entry;
}

function cmsNavCopyPasteAttr(string $text): string
{
    $escaped = htmlspecialchars($text);
    $js = "event.preventDefault();event.stopPropagation();var t=" . json_encode($text) . ";var input=document.getElementById('prompt-input');if(input){var start=typeof input.selectionStart==='number'?input.selectionStart:input.value.length;var end=typeof input.selectionEnd==='number'?input.selectionEnd:input.value.length;var before=input.value.slice(0,start);var after=input.value.slice(end);var sep=before&&!/\\s$/.test(before)?' ':'';input.value=before+sep+t+after;if(typeof input.setSelectionRange==='function'){var caret=(before+sep+t).length;input.setSelectionRange(caret,caret);}input.focus({preventScroll:true});input.dispatchEvent(new Event('input',{bubbles:true}));}if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).catch(function(){});}return false;";
    return ' data-copy-text="' . $escaped . '" onclick="' . htmlspecialchars($js, ENT_QUOTES) . '"';
}

function cmsNavActionButton(string $label, string $title, string $class, string $icon, string $extraAttrs = ''): string
{
    return '<button type="button" class="nav-row-action ' . htmlspecialchars($class) . '" title="' . htmlspecialchars($title) . '" aria-label="' . htmlspecialchars($title) . '"' . $extraAttrs . '>'
        . '<span class="nav-row-action__icon" aria-hidden="true">' . $icon . '</span>'
        . '<span class="nav-row-action__label">' . htmlspecialchars($label) . '</span>'
        . '</button>';
}

function cmsNavIcon(string $name): string
{
    return match ($name) {
        'copy' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M7 2a2 2 0 00-2 2v8h2V4h6V2H7zm3 4a2 2 0 00-2 2v8a2 2 0 002 2h6a2 2 0 002-2V8a2 2 0 00-2-2h-6zm0 2h6v8h-6V8z"/></svg>',
        'hide' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 4c-4.5 0-8 3.5-8 6 0 1.1.5 2.4 1.4 3.5l1.5-1.5C4.4 11 4 10.2 4 10c0-1.2 2.7-4 6-4 .7 0 1.4.1 2 .3l1.6-1.6C12.5 4.2 11.3 4 10 4zm7.2 2.2-1.5 1.5C16.5 8.8 16 9.6 16 10c0 1.2-2.7 4-6 4-.7 0-1.4-.1-2-.3l-1.6 1.6c1.1.5 2.3.7 3.6.7 4.5 0 8-3.5 8-6 0-1.1-.5-2.4-1.4-3.5zM6.7 8.3l4.9 4.9c.6-.3 1.1-.8 1.4-1.4L8.1 6.9c-.6.3-1.1.8-1.4 1.4z"/></svg>',
        'show' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 4c-4.5 0-8 3.5-8 6s3.5 6 8 6 8-3.5 8-6-3.5-6-8-6zm0 10c-3 0-5.8-2.2-6.9-4 1.1-1.8 3.9-4 6.9-4s5.8 2.2 6.9 4c-1.1 1.8-3.9 4-6.9 4zm0-6a2 2 0 100 4 2 2 0 000-4z"/></svg>',
        'pending' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm.75 4.25a.75.75 0 00-1.5 0v4.19c0 .2.08.39.22.53l2.5 2.5a.75.75 0 101.06-1.06l-2.28-2.28V6.25z"/></svg>',
        default => '',
    };
}

function cmsNavBadgeMarkup(bool $isHidden, bool $isPendingApproval, bool $isApprovedExternal): string
{
    if ($isPendingApproval) {
        return '<span class="nav-link-badge nav-link-badge-pending">new</span>';
    }
    if ($isHidden) {
        return '<span class="nav-link-badge">hidden</span>';
    }
    if ($isApprovedExternal) {
        return '<span class="nav-link-badge nav-link-badge-approved">external</span>';
    }

    return '';
}

function cmsNavPathLooksLikeConverter(string $path): bool
{
    $normalized = trim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return false;
    }

    return (bool) preg_match('~^converters/[^/]+(?:/.*)?$~', $normalized);
}

function cmsNavFolderWorkType(string $baseDir, string $relativePath): string
{
    $normalized = trim(str_replace('\\', '/', $relativePath), '/');
    if ($normalized === '') {
        return '';
    }

    $absolutePath = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (!is_dir($absolutePath)) {
        return '';
    }

    $configPath = $absolutePath . DIRECTORY_SEPARATOR . 'poff.config.json';
    if (!is_file($configPath)) {
        return '';
    }

    $decoded = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($decoded)) {
        return '';
    }

    return trim((string) (($decoded['work']['type'] ?? $decoded['type'] ?? '')));
}

function cmsNavIsConverterItem(array $item, bool $isFile, string $baseDir): bool
{
    if (!empty($item['isConverter'])) {
        return true;
    }

    if ($isFile) {
        return false;
    }

    $workType = trim((string) (($item['work']['type'] ?? $item['type'] ?? '')));
    if ($workType === 'converter') {
        return true;
    }

    $relativePath = trim((string) ($item['path'] ?? ''), "/\\");
    if ($relativePath === '') {
        return false;
    }

    if (cmsNavPathLooksLikeConverter($relativePath)) {
        return cmsNavFolderWorkType($baseDir, $relativePath) === 'converter' || !str_contains($relativePath, '/');
    }

    return cmsNavFolderWorkType($baseDir, $relativePath) === 'converter';
}

function cmsNavBadgeForItem(bool $isHidden, bool $isPendingApproval, bool $isApprovedExternal, bool $isConverter): string
{
    if ($isPendingApproval) {
        return '<span class="nav-link-badge nav-link-badge-pending">new</span>';
    }
    if ($isHidden) {
        return '<span class="nav-link-badge">hidden</span>';
    }
    if ($isApprovedExternal) {
        return '<span class="nav-link-badge nav-link-badge-approved">external</span>';
    }
    if ($isConverter) {
        return '<span class="nav-link-badge nav-link-badge-converter">converter</span>';
    }

    return '';
}

function cmsNavRenderItem(array $item, bool $editMode, bool $isFile, string $baseDir): string
{
    $isHidden = !empty($item['hidden']);
    $isPendingApproval = !empty($item['pendingApproval']);
    $isApprovedExternal = !empty($item['approvedExternal']);
    $isConverter = cmsNavIsConverterItem($item, $isFile, $baseDir);
    $displayName = (string) ($item['title'] ?? $item['name']);
    $hiddenAttrs = $isHidden
        ? ' aria-disabled="true" tabindex="-1" data-hidden="true" style="opacity:.48;filter:grayscale(1);cursor:not-allowed;pointer-events:none;"'
        : '';
    $rowStyle = $isHidden && !$isPendingApproval
        ? 'display:flex;align-items:center;gap:.25rem;opacity:.48;filter:grayscale(1);'
        : 'display:flex;align-items:center;gap:.25rem;';
    $navLinkClasses = 'nav-link'
        . ($isHidden ? ' nav-link-hidden' : '')
        . ($isPendingApproval ? ' nav-link-pending-approval' : '')
        . ($isApprovedExternal ? ' nav-link-approved-external' : '')
        . ($isConverter ? ' nav-link-converter' : '');
    $href = $isFile ? '#' : ($isHidden ? '#' : $item['link']);
    $dataAttrs = ' data-tree-item="1" data-path="' . htmlspecialchars($item['path']) . '" data-slug="' . htmlspecialchars($item['slug']) . '"';

    if ($isFile) {
        $dataAttrs .= ' data-src="' . htmlspecialchars($item['data_src']) . '" data-file="' . htmlspecialchars($item['name']) . '"';
    }

    return '<li style="' . $rowStyle . '">'
        . '<a class="' . $navLinkClasses . '" style="flex:1;" href="' . htmlspecialchars($href) . '"' . $dataAttrs . ' title="' . htmlspecialchars((string) ($item['name'] ?? $displayName)) . '"' . $hiddenAttrs . '>'
        . $item['icon'] . htmlspecialchars($displayName) . cmsNavBadgeForItem($isHidden, $isPendingApproval, $isApprovedExternal, $isConverter) . '</a>'
        . ($isPendingApproval ? cmsNavActionButton('Review', 'Review external link submission', 'nav-row-action-review', cmsNavIcon('pending'), ' data-nav-action="review-external" data-nav-path="' . htmlspecialchars($item['path']) . '"') : '')
        . ($editMode ? cmsNavActionButton('Copy', 'Copy + paste', 'nav-row-action-copy', cmsNavIcon('copy'), cmsNavCopyPasteAttr((string) ($item['copyText'] ?? $item['name']))) : '')
        . ($editMode && !$isPendingApproval ? cmsNavActionButton($isHidden ? 'Unhide' : 'Hide', $isHidden ? 'Unhide in sidebar' : 'Hide from sidebar', 'nav-row-action-toggle', $isHidden ? cmsNavIcon('show') : cmsNavIcon('hide'), ' data-nav-action="toggle-visibility" data-nav-path="' . htmlspecialchars($item['path']) . '" data-nav-hidden="' . ($isHidden ? '1' : '0') . '"') : '')
        . '</li>';
}

function cmsRenderNavListMarkup(array $context): string
{
    $baseDir = (string) ($context['baseDir'] ?? '');
    $currentRelativePath = (string) ($context['currentRelativePath'] ?? '');
    $currentAbsolutePath = (string) ($context['currentAbsolutePath'] ?? '');
    $currentScript = (string) ($context['currentScript'] ?? '');
    $folderPoffConfig = $context['folderPoffConfig'] ?? null;
    $editQuery = (isset($_GET['edit']) && $_GET['edit'] === 'true') ? '&edit=true' : '';
    $editMode = isset($_GET['edit']) && $_GET['edit'] === 'true';
    $navEditorAuthenticated = cmsIsEditorAuthenticated($baseDir);
    $navFolder = $currentRelativePath;
    $folderIcon = '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>';
    $fileIcon = '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L12 1.586A2 2 0 0010.586 1H4zm6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>';

    ob_start();
    if (isset($_SERVER['QUERY_STRING']) && preg_match('/#\/([^\/]+)(?:\/([^\/]+))?/', $_SERVER['QUERY_STRING'], $matches)) {
        $navFolder = $matches[1];
    }

    if ($navFolder !== '') {
        $parentRelativePath = dirname($navFolder);
        if ($parentRelativePath === '.') {
            $parentRelativePath = '';
        }
        $upLink = '?path=' . urlencode($parentRelativePath) . $editQuery;
        echo '<li style="display:flex;align-items:center;gap:.25rem;">'
            . '<a class="nav-link nav-link-up" style="flex:1;" href="' . htmlspecialchars($upLink) . '"><svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.7a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg> Go Up</a>'
            . ($editMode ? cmsNavActionButton('Copy', 'Copy + paste', 'nav-row-action-copy', cmsNavIcon('copy'), cmsNavCopyPasteAttr($parentRelativePath !== '' ? $parentRelativePath : '..')) : '')
            . '</li>';
    }

    $currentNavConfig = is_array($folderPoffConfig ?? null) ? $folderPoffConfig : [];
    $currentNavName = (string) ($currentNavConfig['title'] ?? $currentNavConfig['folderName'] ?? basename($navFolder));
    $currentNavSlug = (string) ($currentNavConfig['slug'] ?? cmsNavSlug((string) ($currentNavConfig['title'] ?? $currentNavConfig['folderName'] ?? $currentNavName)));
    echo '<li style="display:flex;align-items:center;gap:.25rem;">'
        . '<a class="nav-link nav-link-up" style="flex:1;" href="' . htmlspecialchars('?path=' . urlencode($navFolder) . $editQuery) . '" data-path="' . htmlspecialchars($navFolder) . '" data-slug="' . htmlspecialchars($currentNavSlug) . '" title="' . htmlspecialchars((string) ($currentNavConfig['folderName'] ?? $currentNavName)) . '">./ ' . htmlspecialchars($currentNavName) . '</a>'
        . ($editMode ? cmsNavActionButton('Copy', 'Copy + paste', 'nav-row-action-copy', cmsNavIcon('copy'), cmsNavCopyPasteAttr($currentNavName)) : '')
        . '</li>';

    $navAbsolutePath = $currentAbsolutePath !== '' ? $currentAbsolutePath : $baseDir . ($navFolder ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $navFolder) : '');
    $navKind = file_exists($navAbsolutePath) ? (is_dir($navAbsolutePath) ? 'dir' : 'file') : 'missing';
    if ($navKind === 'file') {
        $navFolder = dirname($navFolder);
        if ($navFolder === '.') {
            $navFolder = '';
        }
        $navAbsolutePath = $baseDir . ($navFolder ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $navFolder) : '');
    }

    $directories = [];
    $files = [];
    $tree = $folderPoffConfig['tree'] ?? null;

    if (is_array($tree)) {
        foreach ($tree as $item) {
            $isHidden = isset($item['visible']) && $item['visible'] === false;
            $approvalStatus = trim((string) ($item['approvalStatus'] ?? ''));
            $isPendingApproval = $approvalStatus === 'pending';
            $isApprovedExternal = !empty($item['externalSubmission']) && $approvalStatus === 'approved';
            if ($isPendingApproval && !$navEditorAuthenticated) {
                continue;
            }
            if ($isHidden && !$editMode && !$isPendingApproval) {
                continue;
            }

            $itemName = $item['name'] ?? '';
            if ($itemName === '' || ($currentRelativePath === '' && $itemName === $currentScript)) {
                continue;
            }
            $itemType = $item['type'] ?? 'file';
            $itemStoredPath = trim((string) ($item['path'] ?? $itemName), "/\\");
            $relativePath = $navFolder
                ? rtrim($navFolder, "/\\") . '/' . ($itemStoredPath !== '' ? $itemStoredPath : $itemName)
                : ($itemStoredPath !== '' ? $itemStoredPath : $itemName);

            $entry = [
                'hidden' => $isHidden,
                'pendingApproval' => $isPendingApproval,
                'approvedExternal' => $isApprovedExternal,
            ];

            if ($itemType === 'folder') {
                $directories[] = cmsNavEntry($itemName, $relativePath, 'folder', $folderIcon, $item, null, $editQuery) + $entry;
            } else {
                $files[] = cmsNavEntry($itemName, $relativePath, 'file', $fileIcon, $item, $relativePath, $editQuery) + $entry;
            }
        }
    } else {
        $items = is_dir($navAbsolutePath) ? scandir($navAbsolutePath) : false;
        if ($items !== false) {
            foreach ($items as $item) {
                if (
                    $item === '.' ||
                    $item === '..' ||
                    cmsIsHiddenSystemEntry($item) ||
                    ($currentRelativePath === '' && $item === $currentScript)
                ) {
                    continue;
                }

                $itemFullPath = $navAbsolutePath . DIRECTORY_SEPARATOR . $item;
                $isDir = is_dir($itemFullPath);
                $itemRelativePath = $navFolder ? rtrim($navFolder, "/\\") . '/' . $item : $item;

                if ($isDir) {
                    $directories[] = cmsNavEntry($item, $itemRelativePath, 'folder', $folderIcon, [], null, $editQuery);
                } else {
                    $files[] = cmsNavEntry($item, $itemRelativePath, 'file', $fileIcon, [], $itemRelativePath, $editQuery);
                }
            }
        } else {
            echo '<li>Error reading directory.</li>';
        }
    }

    usort($directories, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

    foreach ($directories as $dir) {
        echo cmsNavRenderItem($dir, $editMode, false, $baseDir);
    }

    if ($editMode) {
        $layoutVirtualPath = $navFolder !== '' ? rtrim($navFolder, "/\\") . '/.layout' : '.layout';
        $layoutHash = '#/' . str_replace('%2F', '/', rawurlencode($layoutVirtualPath));
        echo '<li style="display:flex;align-items:center;gap:.25rem;">'
            . '<a class="nav-link nav-link-layout" style="flex:1;" href="' . htmlspecialchars($layoutHash) . '" data-layout-path="' . htmlspecialchars($layoutVirtualPath) . '">'
            . '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.75A1.75 1.75 0 014.75 3h10.5A1.75 1.75 0 0117 4.75v10.5A1.75 1.75 0 0115.25 17H4.75A1.75 1.75 0 013 15.25V4.75zm2 1a.75.75 0 000 1.5h10a.75.75 0 000-1.5H5zm0 3.5a.75.75 0 000 1.5h4.5a.75.75 0 000-1.5H5zm0 3.5a.75.75 0 000 1.5h7a.75.75 0 000-1.5H5z" clip-rule="evenodd"/></svg>'
            . '.layout</a>'
            . '<button type="button" title="Copy + paste" aria-label="Copy + paste"' . cmsNavCopyPasteAttr($layoutVirtualPath) . ' style="flex:0 0 auto;border:0;background:transparent;padding:.25rem .35rem;cursor:pointer;font-size:.78rem;line-height:1;opacity:.6;">Copy</button>'
            . '</li>';
    }

    foreach ($files as $file) {
        echo cmsNavRenderItem($file, $editMode, true, $baseDir);
    }

    return (string) ob_get_clean();
}
