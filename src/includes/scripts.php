<?php
/**
 * JavaScript functionality for the file browser
 */
?>
<script>
const navList       = document.getElementById('navList');
const contentFrame  = document.getElementById('contentFrame');
const folderMetaEl  = document.getElementById('folderMeta');
const iframeLoading = document.getElementById('iframeLoading');
let activeLink      = null;
const currentPoffConfig = <?php echo json_encode($folderPoffConfig); ?>;

function renderFolderMeta() {
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
        if (currentPoffConfig.description) {
            html += `<p>${currentPoffConfig.description}</p>`;
        }
        folderMetaEl.innerHTML = html;
        folderMetaEl.style.display = 'block';
    } else {
        folderMetaEl.innerHTML = '';
        folderMetaEl.style.display = 'none';
    }
}

function loadCurrentFolderInIframe() {
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

document.addEventListener('DOMContentLoaded', () => {
    const sidebarLoading = document.getElementById('sidebarLoading');
    // If hash is present, load iframe first, then update sidebar only if hash is for subfolder
    if (window.location.hash && window.location.hash.length > 1) {
        const hashPath = window.location.hash.replace(/^#\/?/, '');
        contentFrame.src = hashPath;
        // Only update sidebar if hash is for subfolder (not root)
        const parts = hashPath.split('/');
        let folderPath = '';
        let fileName = '';
        if (parts.length >= 2) {
            folderPath = parts.slice(0, parts.length - 1).join('/');
            fileName = parts[parts.length - 1];
            if (sidebarLoading) sidebarLoading.style.display = 'block';
            fetch(`?ajax=1&path=${encodeURIComponent(folderPath)}`)
                .then(response => response.text())
                .then(html => {
                    navList.innerHTML = html;
                    // Highlight the file if present
                    const fileEls = navList.querySelectorAll('li[data-file]');
                    fileEls.forEach(el => {
                        el.classList.remove('active');
                        if (el.getAttribute('data-file') === fileName) {
                            el.classList.add('active');
                        }
                    });
                    if (sidebarLoading) sidebarLoading.style.display = 'none';
                })
                .catch(() => {
                    navList.innerHTML = '<li>Error loading folder contents</li>';
                    if (sidebarLoading) sidebarLoading.style.display = 'none';
                });
        }
    } else {
        loadCurrentFolderInIframe();
    }
});

navList.addEventListener('click', (e) => {
    let target = e.target;
    while (target && target.tagName !== 'A') {
        target = target.parentElement;
    }
    if (!target || target.tagName !== 'A') {
        return;
    }
    // Get the href or data-src for directories/files
    let relPath = '';
    if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
        // Directory link
        relPath = decodeURIComponent(target.getAttribute('href').replace('?path=', ''));
    } else if (target.dataset.src) {
        // File link
        relPath = target.dataset.src;
    }
    if (relPath) {
        e.preventDefault();
        // Check if the URL is external (starts with http:// or https://)
        if (relPath.match(/^https?:\/\//)) {
            window.open(relPath, '_blank');
        } else if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
            // Directory link: fetch contents via AJAX and update sidebar
            fetch(`?ajax=1&path=${encodeURIComponent(relPath)}`)
                .then(response => response.text())
                .then(html => {
                    navList.innerHTML = html;
                    // Auto-load index.html or index.htm if present (never index.php)
                    const indexFiles = ['index.html', 'index.htm'];
                    let foundIndex = null;
                    indexFiles.forEach(idx => {
                        const indexEl = navList.querySelector(`li[data-file="${idx}"]`);
                        if (indexEl && !foundIndex) {
                            foundIndex = idx;
                        }
                    });
                    if (foundIndex) {
                        // Load index file in iframe (relative to folder)
                        contentFrame.src = relPath.replace(/\/$/, '') + '/' + foundIndex;
                        window.location.hash = '/' + relPath.replace(/^\/+/, '') + '/' + foundIndex;
                    } else {
                        // If no index file, load folder itself in iframe (for folder view)
                        contentFrame.src = relPath.replace(/\/$/, '') + '/';
                        window.location.hash = '/' + relPath.replace(/^\/+/, '');
                    }
                    // Optionally, re-attach event listeners if needed
                })
                .catch(err => {
                    navList.innerHTML = '<li>Error loading folder contents</li>';
                    contentFrame.src = relPath.replace(/\/$/, '') + '/';
                    window.location.hash = '/' + relPath.replace(/^\/+/, '');
                });
        } else {
            // File link: load in iframe
            // Show iframe loading indicator
            if (iframeLoading) iframeLoading.style.display = 'block';
            contentFrame.src = relPath;
            window.location.hash = '/' + relPath.replace(/^\/+/, '');
        }
        if (activeLink) {
            activeLink.classList.remove('active');
        }
        target.classList.add('active');
        activeLink = target;
    }
});
contentFrame.addEventListener('load', () => {
    if (iframeLoading) iframeLoading.style.display = 'none';
});
</script>
</body>
</html>