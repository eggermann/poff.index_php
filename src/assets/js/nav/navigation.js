import { extractNavHtml } from '../core/utils.js';

export function initNavigation({
    elements,
    editQuery,
    currentPathForIframe,
    renderFolderMeta,
    initEditMode,
}) {
    const { navList, contentFrame, iframeLoading, sidebarLoading } = elements;
    let activeLink = null;
    const initialQueryPath = new URLSearchParams(window.location.search).get('path') || '';

    function showNavLoading() {
        if (!navList) return;
        navList.innerHTML = `
            <div id="navLoading" class="loading-row" style="display:flex;align-items:center;">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
        `;
    }

    function loadNav(relPath = '') {
        if (!navList) return;
        showNavLoading();
        fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery}`)
            .then(response => response.text())
            .then(html => {
                const extracted = extractNavHtml(html) || '';
                if (extracted.trim()) {
                    navList.innerHTML = extracted;
                    navList.dataset.loaded = '1';
                } else {
                    navList.dataset.stale = '1';
                }
            })
            .catch(() => {
                navList.dataset.error = '1';
            });
    }

    function loadCurrentFolderInIframe() {
        if (currentPathForIframe && contentFrame) {
            contentFrame.src = currentPathForIframe;
            if (activeLink) {
                activeLink.classList.remove('nav-link-active');
                activeLink = null;
            }
        }
        if (navList && !navList.dataset.loaded) {
            loadNav(initialQueryPath);
        }
        if (renderFolderMeta) {
            renderFolderMeta();
        }
    }

    function handleInitialHash() {
        if (!contentFrame) {
            return;
        }
        const rawHashPath = window.location.hash.replace(/^#\/?/, '');
        let hashPath = rawHashPath;
        if (rawHashPath) {
            try {
                hashPath = decodeURIComponent(rawHashPath);
            } catch (err) {
                hashPath = rawHashPath;
            }
        }
        const isFile = /\.[^\\/]+$/.test(hashPath);
        contentFrame.src = isFile
            ? `?view=1&file=${encodeURIComponent(hashPath)}`
            : hashPath;

        const parts = hashPath.split('/');
        if (parts.length < 2 || !navList) {
            return;
        }
        const folderPath = parts.slice(0, parts.length - 1).join('/');
        const fileName = parts[parts.length - 1];
        if (sidebarLoading) {
            sidebarLoading.style.display = 'block';
        }
        fetch(`?ajax=1&path=${encodeURIComponent(folderPath)}${editQuery}`)
            .then(response => response.text())
            .then(html => {
                const extracted = extractNavHtml(html) || '';
                if (extracted.trim()) {
                    navList.innerHTML = extracted;
                    navList.dataset.loaded = '1';
                } else {
                    // keep existing nav when empty to avoid blank menu
                    navList.dataset.stale = '1';
                }
                const fileEls = navList.querySelectorAll('a[data-file]');
                fileEls.forEach(el => {
                    el.classList.remove('nav-link-active');
                    if (el.getAttribute('data-file') === fileName) {
                        el.classList.add('nav-link-active');
                    }
                });
                if (sidebarLoading) {
                    sidebarLoading.style.display = 'none';
                }
            })
            .catch(() => {
                navList.dataset.error = '1';
                if (sidebarLoading) {
                    sidebarLoading.style.display = 'none';
                }
            });
    }

    function handleNavClick(event) {
        if (!navList || !contentFrame) {
            return;
        }
        let target = event.target;
        while (target && target.tagName !== 'A') {
            target = target.parentElement;
        }
        if (!target || target.tagName !== 'A') {
            return;
        }
        let relPath = '';
        if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
            const href = target.getAttribute('href') || '';
            const params = new URLSearchParams(href.replace(/^\?/, ''));
            relPath = params.get('path') || '';
        } else if (target.dataset.src) {
            relPath = target.dataset.src;
        }
        if (!relPath) {
            return;
        }
        event.preventDefault();
        if (relPath.match(/^https?:\/\//)) {
            window.open(relPath, '_blank');
            return;
        }
        if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
            if (iframeLoading) {
                iframeLoading.style.display = 'block';
            }
            fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery}`)
            .then(response => response.text())
            .then(html => {
                const extracted = extractNavHtml(html) || '';
                if (extracted.trim()) {
                    navList.innerHTML = extracted;
                    navList.dataset.loaded = '1';
                } else {
                    navList.dataset.stale = '1';
                }
                const indexFiles = ['index.html', 'index.htm'];
                let foundIndex = null;
                    indexFiles.forEach(idx => {
                        const indexEl = navList.querySelector(`li[data-file="${idx}"]`);
                        if (indexEl && !foundIndex) {
                            foundIndex = idx;
                        }
                    });
                    if (foundIndex) {
                        const indexPath = relPath.replace(/\/$/, '') + '/' + foundIndex;
                        contentFrame.src = `?view=1&file=${encodeURIComponent(indexPath)}`;
                        window.location.hash = '/' + relPath.replace(/^\/+/, '') + '/' + foundIndex;
                    } else {
                        contentFrame.src = relPath.replace(/\/$/, '') + '/';
                        window.location.hash = '/' + relPath.replace(/^\/+/, '');
                    }
                    if (initEditMode) {
                        initEditMode();
                    }
                })
                .catch(() => {
                    navList.dataset.error = '1';
                    contentFrame.src = relPath.replace(/\/$/, '') + '/';
                    window.location.hash = '/' + relPath.replace(/^\/+/, '');
                    if (initEditMode) {
                        initEditMode();
                    }
                });
        } else {
            if (iframeLoading) {
                iframeLoading.style.display = 'block';
            }
            contentFrame.src = `?view=1&file=${encodeURIComponent(relPath)}`;
            window.location.hash = '/' + relPath.replace(/^\/+/, '');
            if (initEditMode) {
                initEditMode();
            }
        }
        if (activeLink) {
            activeLink.classList.remove('nav-link-active');
        }
        target.classList.add('nav-link-active');
        activeLink = target;
    }

    if (navList) {
        navList.addEventListener('click', handleNavClick);
    }
    if (contentFrame) {
        contentFrame.addEventListener('load', () => {
            if (iframeLoading) {
                iframeLoading.style.display = 'none';
            }
        });
    }

    return {
        loadCurrentFolderInIframe,
        handleInitialHash,
    };
}
