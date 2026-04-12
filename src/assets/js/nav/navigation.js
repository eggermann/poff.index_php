import { getSelectionFromPath, inferFilePath } from '../core/selection.js';
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
    let ignoreNextHashSync = false;
    let previewRequestId = 0;
    const initialQueryPath = new URLSearchParams(window.location.search).get('path') || '';
    let previewClickBound = false;

    function parseCmsLinkValue(value = '') {
        const trimmed = (value || '').trim();
        if (!trimmed) {
            return null;
        }
        if (trimmed.startsWith('?')) {
            const params = new URLSearchParams(trimmed.replace(/^\?/, ''));
            if (params.get('view') === '1') {
                if (params.has('file')) {
                    return {
                        path: params.get('file') || '',
                        isFile: true,
                    };
                }
                if (params.has('path')) {
                    return {
                        path: params.get('path') || '',
                        isFile: false,
                    };
                }
            }
            if (params.has('path')) {
                return {
                    path: params.get('path') || '',
                    isFile: false,
                };
            }
            return null;
        }
        return null;
    }

    function normalizeCmsRelativePath(value = '') {
        const trimmed = (value || '').trim();
        if (!trimmed || trimmed.startsWith('#')) {
            return '';
        }
        if (/^(data|blob|mailto|tel):/i.test(trimmed)) {
            return '';
        }
        const parsedLink = parseCmsLinkValue(trimmed);
        if (parsedLink?.path) {
            return parsedLink.path;
        }
        if (/^[a-z]+:\/\//i.test(trimmed)) {
            try {
                const url = new URL(trimmed, window.location.href);
                if (url.origin !== window.location.origin) {
                    return '';
                }
                const rootPath = window.location.pathname.replace(/\/?$/, '/');
                if (!url.pathname.startsWith(rootPath)) {
                    return '';
                }
                return decodeURIComponent(url.pathname.slice(rootPath.length));
            } catch (err) {
                return '';
            }
        }
        return decodeURIComponent(trimmed.replace(/^\.\//, '').replace(/^\/+/, ''));
    }

    function extractFallbackAnchorPath(anchor) {
        if (!anchor) {
            return null;
        }
        const currentSelection = getSelectionFromPath(readHashPath() || initialQueryPath || '');
        const currentFolderPath = currentSelection.previewIsFile
            ? currentSelection.previewPath.split('/').slice(0, -1).join('/')
            : currentSelection.previewPath;
        const candidates = [
            anchor.getAttribute('data-page-link'),
            anchor.getAttribute('data-work-url'),
            anchor.getAttribute('data-view-url'),
            anchor.getAttribute('data-path'),
            anchor.getAttribute('data-src'),
        ];
        anchor.querySelectorAll('[data-page-link],[data-work-url],[data-view-url],[data-path],[data-src],[src],[poster]').forEach((node) => {
            candidates.push(
                node.getAttribute('data-page-link'),
                node.getAttribute('data-work-url'),
                node.getAttribute('data-view-url'),
                node.getAttribute('data-path'),
                node.getAttribute('data-src'),
                node.getAttribute('src'),
                node.getAttribute('poster'),
            );
        });

        for (const candidate of candidates) {
            const normalized = normalizeCmsRelativePath(candidate || '');
            if (!normalized) {
                continue;
            }
            if (inferFilePath(normalized)) {
                return {
                    path: normalized,
                    isFile: true,
                };
            }
            if (currentFolderPath && inferFilePath(`${currentFolderPath}/${normalized}`)) {
                return {
                    path: `${currentFolderPath}/${normalized}`.replace(/^\/+/, ''),
                    isFile: true,
                };
            }
        }
        return null;
    }

    function readHashPath() {
        const rawHashPath = window.location.hash.replace(/^#\/?/, '');
        if (!rawHashPath) {
            return '';
        }
        try {
            return decodeURIComponent(rawHashPath);
        } catch (err) {
            return rawHashPath;
        }
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
        return fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery}`)
            .then(response => response.text())
            .then(html => {
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

    function buildViewerUrl(path, isFile = false, forceRefresh = false) {
        const url = new URL(window.location.href);
        url.search = '';
        url.hash = '';
        url.searchParams.set('view', '1');
        url.searchParams.set(isFile ? 'file' : 'path', path);
        if (forceRefresh) {
            url.searchParams.set('_refresh', String(Date.now()));
        }
        return url.pathname + url.search;
    }

    function writeHashPath(path = '') {
        const nextHash = path ? `#/${path.replace(/^\/+/, '')}` : '';
        if (window.location.hash === nextHash) {
            return;
        }
        if (!nextHash) {
            const nextUrl = window.location.pathname + window.location.search;
            ignoreNextHashSync = true;
            window.history.replaceState(null, '', nextUrl);
            return;
        }
        ignoreNextHashSync = true;
        window.location.hash = nextHash;
    }

    function syncSidebarSelection(path = '', isFile = false) {
        if (!isFile) {
            clearActiveLink();
            return;
        }
        const parts = path.split('/');
        const fileName = parts[parts.length - 1] || '';
        setActiveFileLink(fileName);
    }

    function navigateToPath(path = '', options = {}) {
        const selection = getSelectionFromPath(path);
        navigateToSelection(selection, options);
    }

    function navigateToSelection(selectionInput, options = {}) {
        const selection = selectionInput && typeof selectionInput === 'object' && Object.prototype.hasOwnProperty.call(selectionInput, 'path')
            ? selectionInput
            : getSelectionFromPath(selectionInput || '');
        const {
            updateHash = true,
            forceRefresh = false,
        } = options;
        const previewPath = selection.previewPath || '';
        const previewIsFile = !!selection.previewIsFile;
        const folderPath = previewIsFile ? previewPath.split('/').slice(0, -1).join('/') : previewPath;

        if (iframeLoading) {
            iframeLoading.style.display = 'block';
        }
        if (contentFrame) {
            renderPreview(buildViewerUrl(previewPath, previewIsFile, forceRefresh));
        }
        if (updateHash) {
            writeHashPath(selection.path || '');
        }
        if (navList) {
            if (sidebarLoading) {
                sidebarLoading.style.display = 'block';
            }
            loadNav(folderPath)
                .then(() => {
                    syncSidebarSelection(previewPath, previewIsFile);
                    if (sidebarLoading) {
                        sidebarLoading.style.display = 'none';
                    }
                })
                .catch(() => {
                    syncSidebarSelection(previewPath, previewIsFile);
                    if (sidebarLoading) {
                        sidebarLoading.style.display = 'none';
                    }
                });
        }
        if (initEditMode) {
            initEditMode();
        }
    }

    function resolvePreviewTarget(anchor) {
        if (!anchor) {
            return null;
        }
        const targetAttr = (anchor.getAttribute('target') || '').trim();
        if (targetAttr && targetAttr !== '_self') {
            return null;
        }
        let url;
        try {
            url = new URL(anchor.getAttribute('href') || anchor.href, window.location.href);
        } catch (err) {
            return null;
        }
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
            return null;
        }
        if (url.searchParams.get('view') === '1') {
            if (url.searchParams.has('file')) {
                return {
                    path: url.searchParams.get('file') || '',
                    isFile: true,
                };
            }
            if (url.searchParams.has('path')) {
                const path = url.searchParams.get('path') || '';
                const target = {
                    path,
                    isFile: false,
                };
                if (path && !path.includes('/') && !inferFilePath(path)) {
                    return extractFallbackAnchorPath(anchor) || target;
                }
                return target;
            }
        }
        if (url.searchParams.has('path')) {
            const path = url.searchParams.get('path') || '';
            const target = {
                path,
                isFile: false,
            };
            if (path && !path.includes('/') && !inferFilePath(path)) {
                return extractFallbackAnchorPath(anchor) || target;
            }
            return target;
        }
        const fallback = extractFallbackAnchorPath(anchor);
        if (fallback) {
            return fallback;
        }
        return null;
    }

    function bindPreviewNavigation() {
        if (!contentFrame || previewClickBound) {
            return;
        }
        previewClickBound = true;
        contentFrame.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }
            let target = event.target;
            while (target && target.tagName !== 'A') {
                target = target.parentElement;
            }
            if (!target || target.tagName !== 'A') {
                return;
            }
            const nextTarget = resolvePreviewTarget(target);
            if (!nextTarget) {
                return;
            }
            event.preventDefault();
            navigateToPath(nextTarget.path, { isFile: nextTarget.isFile });
        });
    }

    async function renderPreview(url) {
        if (!contentFrame) {
            return;
        }

        const requestId = ++previewRequestId;
        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'fetch-preview',
                },
            });
            const html = await response.text();
            if (requestId !== previewRequestId) {
                return;
            }

            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fragments = [];
            doc.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
                fragments.push(node.outerHTML);
            });
            doc.querySelectorAll('script').forEach((node) => node.remove());
            const bodyHtml = doc.body ? doc.body.innerHTML : html;
            contentFrame.innerHTML = `${fragments.join('')}${bodyHtml}`;

            doc.querySelectorAll('script').forEach((oldScript) => {
                const script = document.createElement('script');
                for (const attribute of oldScript.attributes) {
                    script.setAttribute(attribute.name, attribute.value);
                }
                script.textContent = oldScript.textContent || '';
                contentFrame.appendChild(script);
            });
            bindPreviewNavigation();
        } catch (error) {
            if (requestId !== previewRequestId) {
                return;
            }
            contentFrame.innerHTML = '<div class="viewer-error">Preview failed to load.</div>';
        } finally {
            if (requestId === previewRequestId && iframeLoading) {
                iframeLoading.style.display = 'none';
            }
        }
    }

    function loadCurrentFolderInIframe() {
        const selection = getSelectionFromPath(currentPathForIframe ?? initialQueryPath ?? '');
        navigateToSelection(selection, { updateHash: false });
        if (renderFolderMeta) {
            renderFolderMeta();
        }
    }

    function syncFromLocation(options = {}) {
        const { forceRefresh = false } = options;
        const hashPath = readHashPath();
        if (hashPath || window.location.hash) {
            navigateToSelection(getSelectionFromPath(hashPath), {
                updateHash: false,
                forceRefresh,
            });
            return;
        }
        navigateToSelection(getSelectionFromPath(currentPathForIframe ?? initialQueryPath ?? ''), {
            updateHash: false,
            forceRefresh,
        });
    }

    function refreshCurrentLocation() {
        syncFromLocation({ forceRefresh: true });
    }

    function handleNavClick(event) {
        if (!navList) {
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
        } else if (target.dataset.src) {
            relPath = target.dataset.src;
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
        const isFile = !(target.hasAttribute('href') && target.getAttribute('href').startsWith('?path='));
        navigateToPath(relPath, { isFile });
    }

    if (navList) {
        navList.addEventListener('click', handleNavClick);
    }
    if (contentFrame) {
        bindPreviewNavigation();
    }

    return {
        consumeHashSync() {
            if (!ignoreNextHashSync) {
                return false;
            }
            ignoreNextHashSync = false;
            return true;
        },
        loadCurrentFolderInIframe,
        syncFromLocation,
        refreshCurrentLocation,
    };
}
