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

    function scrollActiveLinkIntoView(link = activeLink) {
        if (!link || typeof link.scrollIntoView !== 'function') {
            return;
        }
        link.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest',
        });
    }

    function centerActiveLinkInSidebar(link = activeLink) {
        if (!navList || !link || typeof link.getBoundingClientRect !== 'function') {
            return;
        }
        const sidebar = typeof navList.closest === 'function'
            ? navList.closest('#appSidebar')
            : navList.parentElement;
        if (!sidebar || typeof sidebar.getBoundingClientRect !== 'function') {
            return;
        }
        const sidebarRect = sidebar.getBoundingClientRect();
        const linkRect = link.getBoundingClientRect();
        if (!sidebarRect || !linkRect) {
            return;
        }
        const targetTop = sidebar.scrollTop + (linkRect.top - sidebarRect.top) - (sidebar.clientHeight / 2) + (linkRect.height / 2);
        sidebar.scrollTop = Math.max(0, targetTop);
    }

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

    function setActiveLink(nextLink) {
        clearActiveLink();
        if (!nextLink) {
            return;
        }
        nextLink.classList.add('nav-link-active');
        activeLink = nextLink;
        centerActiveLinkInSidebar(nextLink);
        scrollActiveLinkIntoView(nextLink);
    }

    function findNavLinkByPath(path = '', attribute = 'data-path') {
        if (!navList || !path) {
            return null;
        }
        const normalizedPath = String(path).trim();
        if (!normalizedPath) {
            return null;
        }
        const links = navList.querySelectorAll(`a[${attribute}]`);
        for (const link of links) {
            if ((link.getAttribute(attribute) || '').trim() === normalizedPath) {
                return link;
            }
        }
        return null;
    }

    function setActiveFileLink(path = '') {
        const nextLink = findNavLinkByPath(path, 'data-path');
        if (nextLink) {
            setActiveLink(nextLink);
            return;
        }
        if (!navList || !path) {
            return;
        }
        const fileName = String(path).split('/').pop() || '';
        const fileEls = navList.querySelectorAll('a[data-file]');
        for (const el of fileEls) {
            if (el.getAttribute('data-file') === fileName) {
                setActiveLink(el);
                return;
            }
        }
    }

    function setActiveLayoutLink(layoutPath = '') {
        const nextLink = findNavLinkByPath(layoutPath, 'data-layout-path');
        if (nextLink) {
            setActiveLink(nextLink);
        }
    }

    function showNavLoading() {
        if (typeof setLoadingVisible === 'function') {
            setLoadingVisible(sidebarLoading, true);
        }
    }

    function loadNav(relPath = '') {
        if (!navList) {
            return Promise.resolve('');
        }
        currentFolderPath = relPath || '';
        showNavLoading();
        return fetch(`?ajax=nav&path=${encodeURIComponent(relPath)}${editQuery}`)
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
        setActiveFileLink(path);
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
