import { requestEditConfig } from '../api/edit.js';
import { extractNavHtml } from '../core/utils.js';

export function createSidebarController({
    navList,
    sidebarLoading,
    editQuery,
    navigateToPath,
    getCurrentSelection,
    setLoadingVisible,
}) {
    let activeLink = null;
    let currentFolderPath = '';

    function clearActiveLink() {
        if (activeLink) {
            activeLink.classList.remove('nav-link-active');
            activeLink = null;
        }
        if (!navList) {
            return;
        }
        navList.querySelectorAll('.nav-link-active').forEach((link) => {
            if (link !== activeLink) {
                link.classList.remove('nav-link-active');
            }
        });
    }

    function setActiveFileLink(fileName = '') {
        clearActiveLink();
        if (!navList || !fileName) {
            return;
        }
        const fileEls = navList.querySelectorAll('a[data-file]');
        fileEls.forEach((el) => {
            if (el.getAttribute('data-file') === fileName) {
                el.classList.add('nav-link-active');
                activeLink = el;
            }
        });
    }

    function setActiveLayoutLink(layoutPath = '') {
        clearActiveLink();
        if (!navList || !layoutPath) {
            return;
        }
        const layoutEls = navList.querySelectorAll('a[data-layout-path]');
        layoutEls.forEach((el) => {
            if (el.getAttribute('data-layout-path') === layoutPath) {
                el.classList.add('nav-link-active');
                activeLink = el;
            }
        });
    }

    function showNavLoading() {
        if (!navList) {
            return;
        }
        navList.innerHTML = `
            <div id="navLoading" class="loading-row flex items-center">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
        `;
    }

    function loadNav(relPath = '') {
        if (!navList) {
            return Promise.resolve('');
        }
        currentFolderPath = relPath || '';
        showNavLoading();
        return fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery}`)
            .then((response) => response.text())
            .then((html) => {
                const extracted = extractNavHtml(html) || '';
                if (extracted.trim()) {
                    navList.innerHTML = extracted;
                    navList.dataset.loaded = '1';
                } else {
                    navList.dataset.stale = '1';
                }
                return extracted;
            })
            .catch(() => {
                navList.dataset.error = '1';
                return '';
            });
    }

    function collectVisibleTreePaths() {
        if (!navList) {
            return [];
        }
        const visiblePaths = [];
        navList.querySelectorAll('a[data-tree-item="1"]').forEach((link) => {
            if (link.hasAttribute('data-hidden')) {
                return;
            }
            const path = link.getAttribute('data-path') || '';
            if (!path) {
                return;
            }
            visiblePaths.push(path);
        });
        return visiblePaths;
    }

    async function toggleNavEntry(link) {
        if (!link) {
            return;
        }
        const targetPath = link.getAttribute('data-path') || '';
        if (!targetPath) {
            return;
        }
        const isHidden = link.hasAttribute('data-hidden');
        const visiblePaths = collectVisibleTreePaths();
        const nextVisiblePaths = isHidden
            ? Array.from(new Set([...visiblePaths, targetPath]))
            : visiblePaths.filter((path) => path !== targetPath);
        const response = await requestEditConfig('save', {
            path: currentFolderPath,
            treeVisible: nextVisiblePaths,
        });
        if (response && response.allowed === false) {
            return;
        }
        const selection = typeof getCurrentSelection === 'function' ? getCurrentSelection() : null;
        if (typeof navigateToPath === 'function') {
            navigateToPath(selection?.path || currentFolderPath || '', {
                isFile: !!selection?.previewIsFile,
                forceRefresh: true,
                updateHash: false,
            });
        } else {
            await loadNav(currentFolderPath);
        }
    }

    function syncSidebarSelection(path = '', isFile = false, isLayout = false) {
        if (isLayout) {
            setActiveLayoutLink(path);
            return;
        }
        if (!isFile) {
            clearActiveLink();
            return;
        }
        const parts = path.split('/');
        const fileName = parts[parts.length - 1] || '';
        setActiveFileLink(fileName);
    }

    function handleNavClick(event) {
        if (!navList) {
            return;
        }
        const reviewAction = event.target.closest?.('[data-nav-action="review-external"]');
        if (reviewAction) {
            event.preventDefault();
            event.stopPropagation();
            const reviewPath = reviewAction.getAttribute('data-nav-path') || '';
            window.dispatchEvent(new CustomEvent('poff:review-external-link', {
                detail: {
                    path: reviewPath,
                    folderPath: currentFolderPath,
                },
            }));
            return;
        }
        const fastAction = event.target.closest?.('[data-nav-action="toggle-visibility"]');
        if (fastAction) {
            event.preventDefault();
            event.stopPropagation();
            const rowLink = fastAction.closest('li')?.querySelector('a[data-tree-item="1"]');
            toggleNavEntry(rowLink).catch(() => {});
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
        let resolvedPath = false;
        if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
            const href = target.getAttribute('href') || '';
            const params = new URLSearchParams(href.replace(/^\?/, ''));
            relPath = params.get('path') || '';
            resolvedPath = true;
        } else if (target.dataset.path) {
            relPath = target.dataset.path;
            resolvedPath = true;
        } else if (target.dataset.src) {
            relPath = target.dataset.src;
            resolvedPath = true;
        } else if (target.dataset.layoutPath) {
            relPath = target.dataset.layoutPath;
            resolvedPath = true;
        }
        if (!resolvedPath) {
            return;
        }
        event.preventDefault();
        if (relPath.match(/^https?:\/\//)) {
            window.open(relPath, '_blank');
            return;
        }
        const isFile = target.dataset.layoutPath
            ? false
            : !(target.hasAttribute('href') && target.getAttribute('href').startsWith('?path='));
        navigateToPath(relPath, { isFile });
    }

    function bindNavClick() {
        if (!navList) {
            return;
        }
        navList.addEventListener('click', handleNavClick);
    }

    return {
        bindNavClick,
        loadNav,
        syncSidebarSelection,
    };
}
