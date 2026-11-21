import { requestEditConfig, requestEditDelete } from '../api/edit.js';
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
    let navRequestId = 0;
    let navAbortController = null;

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

    function showNavLoading(label = '') {
        if (typeof setLoadingVisible === 'function') {
            setLoadingVisible(sidebarLoading, true, label);
        }
    }

    function loadNav(relPath = '', options = {}) {
        if (!navList) {
            return Promise.resolve('');
        }
        const requestId = ++navRequestId;
        navAbortController?.abort();
        const requestController = typeof AbortController === 'function'
            ? new AbortController()
            : null;
        navAbortController = requestController;
        currentFolderPath = relPath || '';
        showNavLoading(options.loadingLabel || 'Loading navigation...');
        return fetch(`?ajax=nav&path=${encodeURIComponent(relPath)}${editQuery}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'text/html',
                'X-Requested-With': 'fetch-navigation',
            },
            ...(requestController ? { signal: requestController.signal } : {}),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Navigation request failed: ${response.status}`);
                }
                return response.text();
            })
            .then((html) => {
                if (requestId !== navRequestId) {
                    return '';
                }
                const extracted = extractNavHtml(html) || '';
                if (extracted.trim()) {
                    navList.innerHTML = extracted;
                    navList.dataset.loaded = '1';
                    delete navList.dataset.error;
                    delete navList.dataset.stale;
                } else {
                    navList.dataset.stale = '1';
                }
                return extracted;
            })
            .catch((error) => {
                if (requestId !== navRequestId || error?.name === 'AbortError') {
                    return '';
                }
                navList.dataset.error = '1';
                return '';
            })
            .finally(() => {
                if (navAbortController === requestController) {
                    navAbortController = null;
                }
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

    async function deleteNavEntry(link) {
        if (!link) {
            return;
        }
        const targetPath = link.getAttribute('data-path') || link.getAttribute('data-src') || '';
        if (!targetPath) {
            return;
        }
        const confirmed = window.confirm('Delete this item? This cannot be undone.');
        if (!confirmed) {
            return;
        }
        const response = await requestEditDelete({
            path: targetPath,
        });
        if (!response || response.error) {
            window.alert(response?.error || 'Delete failed.');
            return;
        }
        const nextPath = targetPath.split('/').slice(0, -1).join('/');
        if (typeof navigateToPath === 'function') {
            navigateToPath(nextPath, {
                isFile: false,
                forceRefresh: true,
            });
            return;
        }
        await loadNav(currentFolderPath);
    }

    async function moveNavEntry(link) {
        if (!link) {
            return;
        }
        const sourcePath = link.getAttribute('data-path') || link.getAttribute('data-src') || '';
        if (!sourcePath) {
            return;
        }

        const destination = window.prompt(
            'Destination folder path, relative to this PageOff node. Leave blank for the root folder:',
            currentFolderPath
        );
        if (destination === null) {
            return;
        }

        const sourceName = sourcePath.split('/').filter(Boolean).pop() || sourcePath;
        const name = window.prompt('New name. Leave blank to keep the current name:', sourceName);
        if (name === null) {
            return;
        }

        const response = await requestEditConfig('move', {
            path: sourcePath,
            destination: destination.trim(),
            name: name.trim(),
        });
        if (!response || response.error || response.allowed === false) {
            window.alert(response?.error || 'Move failed.');
            return;
        }

        if (typeof navigateToPath === 'function') {
            navigateToPath(destination.trim(), {
                isFile: false,
                forceRefresh: true,
            });
            return;
        }
        await loadNav(destination.trim());
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
        const deleteAction = event.target.closest?.('[data-nav-action="delete-target"]');
        if (deleteAction) {
            event.preventDefault();
            event.stopPropagation();
            const rowLink = deleteAction.closest('li')?.querySelector('a[data-tree-item="1"]');
            deleteNavEntry(rowLink).catch(() => {});
            return;
        }
        const moveAction = event.target.closest?.('[data-nav-action="move-target"]');
        if (moveAction) {
            event.preventDefault();
            event.stopPropagation();
            const rowLink = moveAction.closest('li')?.querySelector('a[data-tree-item="1"]');
            moveNavEntry(rowLink).catch(() => {});
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
        const hasExplicitFileFlag = Object.prototype.hasOwnProperty.call(target.dataset, 'isFile');
        const isFile = target.dataset.layoutPath
            ? false
            : (hasExplicitFileFlag
                ? target.dataset.isFile === '1'
                : !(target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')));
        const previewUrl = String(target.dataset.viewUrl || '').trim();
        navigateToPath(relPath, {
            isFile,
            previewUrl: previewUrl || undefined,
        });
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
