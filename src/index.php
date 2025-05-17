<?php
/**
 * File: php_file_browser.php
 * Desc: Simple PHP file browser with sidebar navigation and an iframe preview pane.
 *       Displays folder metadata (title, description, link) from an optional
 *       poff.config.json file in each directory, rendered as a header above the
 *       iframe. The title becomes a hyperlink (if a link/url field exists) that
 *       loads in the preview pane.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Browser</title>
    <style>
        /* -------------- Reset & Layout -------------- */
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* Prevent body scroll */
            background-color: #f0f2f5;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        /* -------------- Sidebar -------------- */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #d1d5db;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }
        .sidebar h2 {
            margin-top: 0;
            font-size: 1.3em;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            text-decoration: none;
            color: #374151;
            border-radius: 6px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar li a:hover {
            background-color: #e5e7eb;
            color: #111827;
        }
        .sidebar li a.active {
            background-color: #3b82f6;
            color: #ffffff;
            font-weight: 500;
        }
        .sidebar li a.active .item-icon {
            filter: brightness(0) invert(1);
        }
        /* Icon */
        .item-icon {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        /* Go-Up link */
        .go-up-link a {
            font-weight: 500;
            color: #10b981;
        }
        .go-up-link a:hover {
            color: #059669;
            background-color: #d1fae5;
        }

        /* -------------- Main Content -------------- */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            background-color: #ffffff;
        }
        /* Folder meta header */
        .folder-meta {
            display: none; /* Shown only when metadata exists */
            padding: 16px 20px;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .folder-meta h3 {
            margin: 0;
            font-size: 1.25em;
            color: #111827;
        }
        .folder-meta p {
            margin: 4px 0 0;
            font-size: 0.95em;
            color: #4b5563;
        }
        /* link style */
        .folder-meta a {
            text-decoration: none;
            color: #2563eb;
        }
        .folder-meta a:hover {
            text-decoration: underline;
        }

        /* Iframe */
        .content-frame {
            flex-grow: 1;
            border: none;
        }
    </style>
</head>
<body>
<?php
// ---------- PHP Logic for File Browsing ---------- //

/**
 * Extracts a URL from “link”‑type files so we can treat them like normal hyperlinks
 * in the browser sidebar. Supports macOS *.webloc*, Windows *.url*, and Linux *.desktop* files.
 *
 * @param string $filePath  Absolute path to the link file.
 * @return string|null      The extracted URL or null if none was found.
 */
function extractLinkFileUrl(string $filePath): ?string {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $content = @file_get_contents($filePath);
    if (!$content) {
        return null;
    }

    switch ($ext) {
        case 'webloc':   // macOS .webloc (plist xml)
            if (preg_match('/<key>URL<\/key>\s*<string>([^<]+)<\/string>/i', $content, $m)) {
                return trim($m[1]);
            }
            break;

        case 'url':      // Windows Internet Shortcut (.url)
        case 'desktop':  // Linux .desktop
            if (preg_match('/^URL=(.+)$/mi', $content, $m)) {
                return trim($m[1]);
            }
            break;
    }
    return null;
}

$baseDir = realpath(__DIR__);
$requestedRelativePath = isset($_GET['path']) ? trim($_GET['path'], "\\/") : '';
$currentAbsolutePath = realpath($baseDir . DIRECTORY_SEPARATOR . $requestedRelativePath);
if (!$currentAbsolutePath || strpos($currentAbsolutePath, $baseDir) !== 0 || !is_dir($currentAbsolutePath)) {
    $currentAbsolutePath = $baseDir;
    $currentRelativePath = '';
} else {
    $currentRelativePath = strlen($currentAbsolutePath) > strlen($baseDir)
        ? ltrim(substr($currentAbsolutePath, strlen($baseDir)), DIRECTORY_SEPARATOR)
        : '';
}
$currentScript = basename(__FILE__);
$folderPoffConfig = null;
$poffConfigPath = $currentAbsolutePath . DIRECTORY_SEPARATOR . 'poff.config.json';
if (is_file($poffConfigPath) && is_readable($poffConfigPath)) {
    $configJson = file_get_contents($poffConfigPath);
    $decoded = json_decode($configJson, true);
    if (json_last_error() === JSON_ERROR_NONE) $folderPoffConfig = $decoded;
}
?>
<div class="container">
    <!-- ---------- Sidebar ---------- -->
    <nav class="sidebar">
        <h2>Navigation</h2>
        <ul id="navList">
<?php
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
        if ($item === '.' || $item === '..' || ($currentRelativePath === '' && $item === $currentScript) || $item === 'poff.config.json') continue;
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
                // Use the extracted URL when we have one; fall back to the relative file path otherwise
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
        <iframe id="contentFrame" name="contentFrame" class="content-frame" src="about:blank" sandbox="allow-forms allow-modals allow-pointer-lock allow-popups allow-presentation allow-same-origin allow-scripts allow-top-navigation"></iframe>
    </div>
</div>

<script>
const navList       = document.getElementById('navList');
const contentFrame  = document.getElementById('contentFrame');
const folderMetaEl  = document.getElementById('folderMeta');
let activeLink      = null;
const currentPoffConfig = <?php echo json_encode($folderPoffConfig); ?>;

function renderFolderMeta () {
    if (currentPoffConfig && (currentPoffConfig.title || currentPoffConfig.description)) {
        let html = '';
        // Build linked title if link/url present
        if (currentPoffConfig.title) {
            if (currentPoffConfig.link || currentPoffConfig.url) {
                const lnk = currentPoffConfig.link || currentPoffConfig.url;
                html += `<h3><a href="${lnk}" target="contentFrame">${currentPoffConfig.title}</a></h3>`;
            } else {
                html += `<h3>${currentPoffConfig.title}</h3>`;
            }
        }
        if (currentPoffConfig.description) html += `<p>${currentPoffConfig.description}</p>`;
        folderMetaEl.innerHTML = html;
        folderMetaEl.style.display = 'block';
    } else {
        folderMetaEl.innerHTML = '';
        folderMetaEl.style.display = 'none';
    }
}

function loadCurrentFolderInIframe () {
    const currentPathForIframe = <?php echo !empty($currentRelativePath) ? json_encode(rtrim($currentRelativePath, "\\/") . '/') : 'null'; ?>;
    if (currentPathForIframe) {
        contentFrame.src = currentPathForIframe;
        if (activeLink) {
            activeLink.classList.remove('active');
            activeLink = null;
        }
    }
    renderFolderMeta();
}

document.addEventListener('DOMContentLoaded', loadCurrentFolderInIframe);

navList.addEventListener('click', (e) => {
    let target = e.target;
    while (target && target.tagName !== 'A') target = target.parentElement;
    if (!target || target.tagName !== 'A') return;
    if (target.dataset.src) {
        e.preventDefault();
        // Check if the URL is external (starts with http:// or https://)
        if (target.dataset.src.match(/^https?:\/\//)) {
            window.open(target.dataset.src, '_blank');
        } else {
            contentFrame.src = target.dataset.src;
        }
        if (activeLink) activeLink.classList.remove('active');
        target.classList.add('active');
        activeLink = target;
    }
});
</script>
</body>
</html>

