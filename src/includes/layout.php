<?php
/**
 * HTML layout structure for the file browser
 */
?>
<div class="container">
    <!-- ---------- Sidebar ---------- -->
    <nav class="sidebar">
        <h2>Navigation</h2>
        <ul id="navList">
            <?php 
            // Generate navigation list directly here instead of including browse.php
            if (!empty($currentRelativePath)) {
                $parentRelativePath = dirname($currentRelativePath);
                if ($parentRelativePath === '.') $parentRelativePath = '';
                $upLink = '?path=' . urlencode($parentRelativePath);
                echo '<li class="go-up-link"><a href="' . htmlspecialchars($upLink) . '"><svg class="item-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.7a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd" transform="rotate(180 10 10)"/></svg> Go Up</a></li>';
            }

            $items = scandir($currentAbsolutePath);
            if ($items !== false) {
                $directories = $files = [];
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..' || ($currentRelativePath === '' && $item === $currentScript) || $item === 'poff.config.json') {
                        continue;
                    }

                    $itemFullPath = $currentAbsolutePath . DIRECTORY_SEPARATOR . $item;
                    $isDir = is_dir($itemFullPath);
                    $linkUrl = $isDir ? null : extractLinkFileUrl($itemFullPath);
                    $itemRelativePath = $currentRelativePath ? rtrim($currentRelativePath, '/\\') . '/' . $item : $item;

                    if ($isDir) {
                        $directories[] = [
                            'name' => $item,
                            'link' => '?path=' . urlencode($itemRelativePath),
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

                usort($directories, fn($a,$b)=>strcasecmp($a['name'],$b['name']));
                usort($files, fn($a,$b)=>strcasecmp($a['name'],$b['name']));

                foreach ($directories as $dir) {
                    echo '<li><a href="' . htmlspecialchars($dir['link']) . '">' . $dir['icon'] . htmlspecialchars($dir['name']) . '</a></li>';
                }
                foreach ($files as $file) {
                    echo '<li><a href="#" data-src="' . htmlspecialchars($file['data_src']) . '">' . $file['icon'] . htmlspecialchars($file['name']) . '</a></li>';
                }
            } else {
                echo '<li>Error reading directory.</li>';
            }
            ?>
        </ul>
    </nav>

    <!-- ---------- Main Content (header + iframe) ---------- -->
    <div class="main-content">
        <div id="folderMeta" class="folder-meta"></div>
        <iframe id="contentFrame" name="contentFrame" class="content-frame" src="about:blank" 
                sandbox="allow-forms allow-modals allow-pointer-lock allow-popups allow-presentation allow-same-origin allow-scripts allow-top-navigation">
        </iframe>
    </div>
</div>