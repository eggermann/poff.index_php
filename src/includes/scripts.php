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

document.addEventListener('DOMContentLoaded', loadCurrentFolderInIframe);

navList.addEventListener('click', (e) => {
    let target = e.target;
    while (target && target.tagName !== 'A') {
        target = target.parentElement;
    }
    if (!target || target.tagName !== 'A') {
        return;
    }
    if (target.dataset.src) {
        e.preventDefault();
        // Check if the URL is external (starts with http:// or https://)
        if (target.dataset.src.match(/^https?:\/\//)) {
            window.open(target.dataset.src, '_blank');
        } else {
            contentFrame.src = target.dataset.src;
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