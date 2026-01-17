<?php
/**
 * HTML layout structure for the file browser
 */
?>
<div class="container">
    <!-- ---------- Sidebar ---------- -->
    <nav class="sidebar">
        <div class="sidebar-tools">
            <button id="editToggle" class="edit-toggle" type="button">Edit mode</button>
        </div>
        <ul id="navList">
            <div id="navLoading" style="display:none;text-align:center;padding:10px;">
                <span class="loader" style="display:inline-block;width:24px;height:24px;border:4px solid #3b82f6;border-top:4px solid #e5e7eb;border-radius:50%;animation:spin 1s linear infinite;"></span>
                <span style="margin-left:8px;">Loading...</span>
            </div>
            <style>
            @keyframes spin { 100% { transform: rotate(360deg); } }
            </style>
            <?php
            // Generate navigation list directly here instead of including browse.php
            $editQuery = (isset($_GET['edit']) && $_GET['edit'] === 'true') ? '&edit=true' : '';
            $navFolder = $currentRelativePath;
            if (isset($_SERVER['QUERY_STRING']) && preg_match('/#\/([^\/]+)(?:\/([^\/]+))?/', $_SERVER['QUERY_STRING'], $matches)) {
                $navFolder = $matches[1];
            }
            if (!empty($navFolder)) {
                $parentRelativePath = dirname($navFolder);
                if ($parentRelativePath === '.') $parentRelativePath = '';
                $upLink = '?path=' . urlencode($parentRelativePath) . $editQuery;
                echo '<li class="go-up-link"><a href="' . htmlspecialchars($upLink) . '"><svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.7a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg> Go Up</a></li>';
            }
            // Current directory link
            echo '<li class="go-up-link"><a href="' . htmlspecialchars($navFolder ? '?path=' . urlencode($navFolder) . $editQuery : '?path=' . $editQuery) . '">./</a></li>';

            $navAbsolutePath = $baseDir . ($navFolder ? DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $navFolder) : '');
            $directories = $files = [];
            $tree = $folderPoffConfig['tree'] ?? null;
            if (is_array($tree)) {
                foreach ($tree as $item) {
                    if (isset($item['visible']) && $item['visible'] === false) {
                        continue;
                    }
                    $itemName = $item['name'] ?? '';
                    if ($itemName === '' || ($currentRelativePath === '' && $itemName === $currentScript)) {
                        continue;
                    }
                    $itemType = $item['type'] ?? 'file';
                    $relativePath = $currentRelativePath ? rtrim($currentRelativePath, '/\\') . '/' . $itemName : $itemName;
                    $fullPath = $currentAbsolutePath . DIRECTORY_SEPARATOR . $itemName;
                    $linkUrl = ($itemType === 'folder') ? null : extractLinkFileUrl($fullPath);

                    if ($itemType === 'folder') {
                        $directories[] = [
                            'name' => $itemName,
                            'link' => '?path=' . urlencode($relativePath) . $editQuery,
                            'icon' => '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>'
                        ];
                    } else {
                        $files[] = [
                            'name'     => $itemName,
                            'data_src' => $linkUrl ?? $relativePath,
                            'icon'     => '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L12 1.586A2 2 0 0010.586 1H4zm6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>'
                        ];
                    }
                }
            } else {
                $items = scandir($navAbsolutePath);
                if ($items !== false) {
                    foreach ($items as $item) {
                        if (
                            $item === '.' ||
                            $item === '..' ||
                            $item === '.works' ||
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

                        $itemFullPath = $currentAbsolutePath . DIRECTORY_SEPARATOR . $item;
                        $isDir = is_dir($itemFullPath);
                        $linkUrl = $isDir ? null : extractLinkFileUrl($itemFullPath);
                        $itemRelativePath = $currentRelativePath ? rtrim($currentRelativePath, '/\\') . '/' . $item : $item;

                        if ($isDir) {
                            $directories[] = [
                                'name' => $item,
                                'link' => '?path=' . urlencode($itemRelativePath) . $editQuery,
                                'icon' => '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/></svg>'
                            ];
                        } else {
                            $files[] = [
                                'name'     => $item,
                                'data_src' => $linkUrl ?? $itemRelativePath,
                                'icon'     => '<svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L12 1.586A2 2 0 0010.586 1H4zm6 10a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>'
                            ];
                        }
                    }
                } else {
                    echo '<li>Error reading directory.</li>';
                }
            }

            usort($directories, fn($a,$b)=>strcasecmp($a['name'],$b['name']));
            usort($files, fn($a,$b)=>strcasecmp($a['name'],$b['name']));

            foreach ($directories as $dir) {
                echo '<li><a href="' . htmlspecialchars($dir['link']) . '">' . $dir['icon'] . htmlspecialchars($dir['name']) . '</a></li>';
            }
            foreach ($files as $file) {
                echo '<li><a href="#" data-src="' . htmlspecialchars($file['data_src']) . '">' . $file['icon'] . htmlspecialchars($file['name']) . '</a></li>';
            }
            ?>
        </ul>
    </nav>

    <!-- ---------- Main Content (header + iframe) ---------- -->
    <div class="main-content">
        <div id="editPanel" class="edit-panel" hidden></div>
        <aside id="editDrawer" class="edit-drawer" hidden></aside>
        <div id="folderMeta" class="folder-meta"></div>
        <div id="iframeLoading" style="display:none;text-align:center;padding:10px;">
            <span class="loader" style="display:inline-block;width:24px;height:24px;border:4px solid #3b82f6;border-top:4px solid #e5e7eb;border-radius:50%;animation:spin 1s linear infinite;"></span>
            <span style="margin-left:8px;">Loading content...</span>
        </div>
        <iframe id="contentFrame" name="contentFrame" class="content-frame" src="about:blank"></iframe>
        <style>
        @keyframes spin { 100% { transform: rotate(360deg); } }
        </style>
    </div>
</div>
