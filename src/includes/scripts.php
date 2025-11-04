<?php
/**
 * JavaScript functionality for the file browser
 */
?>
<script>
const navList       = document.getElementById('navList');
const contentFrame  = document.getElementById('contentFrame');
const folderMetaEl  = document.getElementById('folderMeta');
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
    // If hash is present, load the file/directory in iframe
    if (window.location.hash && window.location.hash.length > 1) {
        const hashPath = window.location.hash.replace(/^#\/?/, '');
        if (hashPath) {
            contentFrame.src = hashPath;
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
                    // Optionally, re-attach event listeners if needed
                })
                .catch(err => {
                    navList.innerHTML = '<li>Error loading folder contents</li>';
                });
            // Update hash
            window.location.hash = '/' + relPath.replace(/^\/+/, '');
        } else {
            // File link: load in iframe
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
</script>
</body>
</html>