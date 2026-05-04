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
    let previewDisabled = false;
    let lastPreviewKey = '';
    const slugToPathAliases = new Map();
    const pathToSlugAliases = new Map();

    function normalizeHashPath(value = '') {
        return String(value || '')
            .replace(/\\/g, '/')
            .replace(/^#\/?/, '')
            .replace(/^\/+|\/+$/g, '');
    }

    function normalizeHashAlias(value = '') {
        return normalizeHashPath(value).toLowerCase();
    }

    function routeResolution(path = '', isFile = inferFilePath(path)) {
        return {
            path,
            isFile,
        };
    }

    function rememberSlugPathAlias(detail = {}) {
        const path = normalizeHashPath(detail?.routePath || detail?.path || detail?.relativePath || '');
        const slug = normalizeHashPath(detail?.routeSlug || detail?.slug || '');
        if (!path || !slug || slug.includes('/')) {
            return;
        }

        slugToPathAliases.set(normalizeHashAlias(slug), path);
        pathToSlugAliases.set(normalizeHashAlias(path), slug);
    }

    function findNavLinkByAttribute(attributeName, value = '') {
        if (!navList || !value) {
            return null;
        }
        const normalizedValue = normalizeHashAlias(value);
        for (const link of navList.querySelectorAll(`[${attributeName}]`)) {
            if (normalizeHashAlias(link.getAttribute(attributeName) || '') === normalizedValue) {
                return link;
            }
        }
        return null;
    }

    function navTargetPath(link) {
        if (!link) {
            return '';
        }
        return link.getAttribute('data-layout-path')
            || link.getAttribute('data-path')
            || link.getAttribute('data-src')
            || '';
    }

    function navTargetIsFile(link, path = '') {
        if (!link) {
            return inferFilePath(path);
        }
        if (link.hasAttribute('data-layout-path')) {
            return false;
        }
        if (link.hasAttribute('data-file') || link.hasAttribute('data-src')) {
            return true;
        }
        const href = link.getAttribute('href') || '';
        if (href.startsWith('?path=')) {
            return false;
        }
        return inferFilePath(path);
    }

    function resolveHashPath(path = '') {
        const normalizedPath = normalizeHashPath(path);
        const aliasPath = slugToPathAliases.get(normalizeHashAlias(normalizedPath));
        if (aliasPath) {
            return routeResolution(aliasPath);
        }
        if (!normalizedPath.includes('/')) {
            const link = findNavLinkByAttribute('data-slug', normalizedPath);
            const targetPath = navTargetPath(link);
            if (targetPath) {
                rememberSlugPathAlias({
                    path: targetPath,
                    slug: normalizedPath,
                });
                return routeResolution(targetPath, navTargetIsFile(link, targetPath));
            }
        }
        return routeResolution(normalizedPath);
    }

    async function resolveHashPathAsync(path = '') {
        const resolved = resolveHashPath(path);
        const normalizedPath = normalizeHashPath(path);
        if (
            !normalizedPath
            || normalizedPath.includes('/')
            || normalizedPath === '.layout'
            || normalizedPath.endsWith('/.layout')
            || inferFilePath(normalizedPath)
            || resolved.path !== normalizedPath
        ) {
            return resolved;
        }

        try {
            const response = await fetch(`?ajax=resolve&slug=${encodeURIComponent(normalizedPath)}`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) {
                return resolved;
            }
            const data = await response.json();
            if (!data?.resolved || !data.path) {
                return resolved;
            }
            rememberSlugPathAlias({
                path: data.path,
                slug: data.slug || normalizedPath,
            });
            return routeResolution(data.path, typeof data.isFile === 'boolean' ? data.isFile : data.type !== 'folder');
        } catch (err) {
            return resolved;
        }
    }

    function displayHashPath(path = '') {
        const normalizedPath = normalizeHashPath(path);
        if (!normalizedPath || normalizedPath.includes('/.layout') || normalizedPath === '.layout') {
            return normalizedPath;
        }
        const aliasSlug = pathToSlugAliases.get(normalizeHashAlias(normalizedPath));
        if (aliasSlug) {
            return aliasSlug;
        }
        const link = findNavLinkByAttribute('data-path', normalizedPath);
        const slug = link?.getAttribute('data-slug') || '';
        if (slug && !slug.includes('/')) {
            rememberSlugPathAlias({
                path: normalizedPath,
                slug,
            });
            return slug;
        }
        return normalizedPath;
    }

    window.POFF_RESOLVE_HASH_PATH = resolveHashPath;

    function previewStateFromUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            const isFile = parsed.searchParams.get('view') === '1' && parsed.searchParams.has('file');
            const path = parsed.searchParams.get(isFile ? 'file' : 'path') || '';
            return {
                key: `${isFile ? 'file' : 'path'}:${path}`,
                path,
                isFile,
            };
        } catch (err) {
            return {
                key: '',
                path: '',
                isFile: false,
            };
        }
    }

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
        if (!navList) return;
        navList.innerHTML = `
            <div id="navLoading" class="loading-row flex items-center">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
        `;
    }

    function setLoadingVisible(element, visible) {
        if (!element) {
            return;
        }
        element.classList.toggle('flex', visible);
        element.classList.toggle('items-center', visible);
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

    function syncPreviewDisabledState(disabled = false) {
        if (!contentFrame) {
            return;
        }
        previewDisabled = !!disabled;
        contentFrame.setAttribute('aria-disabled', previewDisabled ? 'true' : 'false');
        contentFrame.dataset.disabled = previewDisabled ? 'true' : 'false';

        if (!previewDisabled) {
            return;
        }

        contentFrame.querySelectorAll('a, button, input, select, textarea, summary, iframe, video, audio, [contenteditable="true"], [tabindex]').forEach((node) => {
            if (node instanceof HTMLElement) {
                node.setAttribute('tabindex', '-1');
                node.setAttribute('aria-disabled', 'true');
            }
            if ('disabled' in node) {
                try {
                    node.disabled = true;
                } catch (err) {
                    // Ignore nodes with readonly disabled semantics.
                }
            }
            if (node instanceof HTMLMediaElement) {
                node.controls = false;
            }
            if (node instanceof HTMLDetailsElement) {
                node.open = false;
            }
        });
    }

    function writeHashPath(path = '') {
        const hashPath = displayHashPath(path);
        const nextHash = hashPath ? `#/${hashPath.replace(/^\/+/, '')}` : '';
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

    function navigateToPath(path = '', options = {}) {
        const resolved = resolveHashPath(path);
        const selection = getSelectionFromPath(resolved.path, {
            isFile: typeof options?.isFile === 'boolean' ? options.isFile : resolved.isFile,
        });
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

        setLoadingVisible(iframeLoading, true);
        if (contentFrame) {
            contentFrame.classList.toggle('content-frame-layout-target', !!selection.isLayout);
            syncPreviewDisabledState(!!selection.isLayout);
            renderPreview(buildViewerUrl(previewPath, previewIsFile, forceRefresh));
        }
        if (updateHash) {
            writeHashPath(selection.path || '');
        }
        if (navList) {
            setLoadingVisible(sidebarLoading, true);
            loadNav(folderPath)
                .then(() => {
                    syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
                    setLoadingVisible(sidebarLoading, false);
                })
                .catch(() => {
                    syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
                    setLoadingVisible(sidebarLoading, false);
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
            if (previewDisabled || contentFrame.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
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

    function scopePreviewRootSelector(selector = '') {
        const trimmed = selector.trim();
        if (trimmed === 'body') {
            return '#contentFrame > .viewer';
        }
        if (trimmed === 'html') {
            return '#contentFrame';
        }
        if (trimmed === 'html body' || trimmed === 'body html') {
            return '#contentFrame > .viewer';
        }
        if (trimmed.startsWith('body.')) {
            return `#contentFrame > .viewer${trimmed.slice('body'.length)}`;
        }
        if (trimmed.startsWith('html.')) {
            return `#contentFrame${trimmed.slice('html'.length)}`;
        }
        return selector;
    }

    function scopePreviewStyleText(css = '') {
        return String(css || '').replace(/(^|})\s*([^@{}][^{]*)\{/g, (match, prefix, selectorList) => {
            const scopedSelectors = selectorList
                .split(',')
                .map(scopePreviewRootSelector)
                .join(', ');
            return `${prefix} ${scopedSelectors} {`;
        });
    }

    function normalizePreviewStyleNode(node) {
        if (!(node instanceof HTMLStyleElement)) {
            return node.outerHTML;
        }
        const clone = node.cloneNode(true);
        clone.textContent = scopePreviewStyleText(node.textContent || '');
        return clone.outerHTML;
    }

    async function renderPreview(url) {
        if (!contentFrame) {
            return;
        }

        const requestId = ++previewRequestId;
        const nextPreview = previewStateFromUrl(url);
        const shouldPreserveScroll = !!nextPreview.key && nextPreview.key === lastPreviewKey;
        const preservedScrollTop = shouldPreserveScroll ? contentFrame.scrollTop : 0;
        const preservedScrollLeft = shouldPreserveScroll ? contentFrame.scrollLeft : 0;
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
                fragments.push(normalizePreviewStyleNode(node));
            });
            doc.body?.querySelectorAll('style').forEach((node) => {
                node.textContent = scopePreviewStyleText(node.textContent || '');
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
            syncPreviewDisabledState(previewDisabled);
            bindPreviewNavigation();
            lastPreviewKey = nextPreview.key;
            if (shouldPreserveScroll) {
                const restoreScroll = () => {
                    contentFrame.scrollTop = preservedScrollTop;
                    contentFrame.scrollLeft = preservedScrollLeft;
                };
                restoreScroll();
                requestAnimationFrame(restoreScroll);
            } else {
                contentFrame.scrollTop = 0;
                contentFrame.scrollLeft = 0;
            }
        } catch (error) {
            if (requestId !== previewRequestId) {
                return;
            }
            contentFrame.innerHTML = '<div class="viewer-error">Preview failed to load.</div>';
        } finally {
            if (requestId === previewRequestId) {
                setLoadingVisible(iframeLoading, false);
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

    async function syncFromLocation(options = {}) {
        const { forceRefresh = false } = options;
        const hashPath = readHashPath();
        if (hashPath || window.location.hash) {
            const resolved = await resolveHashPathAsync(hashPath);
            navigateToSelection(getSelectionFromPath(resolved.path, { isFile: resolved.isFile }), {
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
        rememberSlugPathAlias,
        writeHashPath,
    };
}
