import { getSelectionFromPath, inferFilePath } from '../core/selection.js';
import {
    normalizeCmsRelativePath,
    normalizePreviewStyleNode,
    previewStateFromUrl,
} from './preview-helpers.js';

export function createPreviewController({
    contentFrame,
    iframeLoading,
    initialQueryPath = '',
    navigateToPath,
    setLoadingVisible,
    getCurrentSelection,
    getPreviewParams,
}) {
    let previewRequestId = 0;
    let previewClickBound = false;
    let previewDisabled = false;
    let lastPreviewKey = '';
    let previewMountRoot = null;

    function stripPreviewChrome(root) {
        if (!root || typeof root.querySelectorAll !== 'function') {
            return root;
        }

        root.querySelectorAll('script, #appShell, #preview, #appSidebar, #sidebarToggle, #editPanel, #editDrawer, #promptDock, #iframeLoading, #sidebarLoading, #editActionsMenu, .app-edit-toggle-wrap').forEach((node) => {
            if (node && typeof node.remove === 'function') {
                node.remove();
            }
        });

        return root;
    }

    function escapePreviewMarkup(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function rewritePreviewMarkupUrls(markup, baseUrl) {
        const html = String(markup || '');
        const trimmedBaseUrl = String(baseUrl || '').trim();
        if (html === '' || trimmedBaseUrl === '') {
            return html;
        }

        let base;
        try {
            base = new URL(trimmedBaseUrl);
        } catch (err) {
            return html;
        }

        const isKeepRelative = (value) => {
            const trimmed = String(value || '').trim();
            return (
                trimmed === ''
                || trimmed.startsWith('#')
                || trimmed.startsWith('//')
                || /^(?:data|blob|mailto|tel|javascript):/i.test(trimmed)
                || /^[a-z][a-z0-9+.-]*:/i.test(trimmed)
            );
        };

        const resolveUrl = (value) => {
            const trimmed = String(value || '').trim();
            if (isKeepRelative(trimmed)) {
                return trimmed;
            }
            try {
                return new URL(trimmed, base).href;
            } catch (err) {
                return trimmed;
            }
        };

        let rewritten = html.replace(/\b(src|href|poster|action|data)=("|')([^"']+)\2/gi, (match, attribute, quote, value) => {
            const nextValue = resolveUrl(value);
            return nextValue === value ? match : `${attribute}=${quote}${nextValue}${quote}`;
        });

        rewritten = rewritten.replace(/url\((['"]?)([^'")]+)\1\)/gi, (match, quote, value) => {
            const nextValue = resolveUrl(value);
            return nextValue === value ? match : `url("${nextValue}")`;
        });

        return rewritten;
    }

    function isExternalPreviewUrl(url) {
        const trimmed = String(url || '').trim();
        if (!trimmed) {
            return false;
        }
        try {
            const resolved = new URL(trimmed, window.location.href);
            return resolved.origin !== window.location.origin;
        } catch (err) {
            return false;
        }
    }

    function extractPreferredPreviewMarkup(previewDocument, previewRoot, options = {}) {
        const candidateRoot = previewRoot && typeof previewRoot.cloneNode === 'function'
            ? previewRoot.cloneNode(true)
            : previewDocument.body;
        if (!candidateRoot) {
            return '';
        }

        if (typeof candidateRoot.querySelector === 'function') {
            if (options.converterPreview) {
                const converterRoot = candidateRoot.querySelector('[data-poff-text-editor-converter], .poff-text-editor-converter, [data-poff-converter], .converter-app');
                if (converterRoot && typeof converterRoot.outerHTML === 'string') {
                    return converterRoot.outerHTML.trim();
                }
            }

            const layout = candidateRoot.querySelector('.poff-default-layout');
            if (layout && typeof layout.outerHTML === 'string') {
                return layout.outerHTML.trim();
            }

            const main = candidateRoot.querySelector('.poff-default-layout__main');
            if (main && typeof main.innerHTML === 'string') {
                return main.innerHTML.trim();
            }

            const appShell = candidateRoot.querySelector('#appShell');
            if (appShell && typeof appShell.querySelector === 'function') {
                const appShellLayout = appShell.querySelector('.poff-default-layout');
                if (appShellLayout && typeof appShellLayout.outerHTML === 'string') {
                    return appShellLayout.outerHTML.trim();
                }

                const appShellMain = appShell.querySelector('.poff-default-layout__main');
                if (appShellMain && typeof appShellMain.innerHTML === 'string') {
                    return appShellMain.innerHTML.trim();
                }
            }
        }

        stripPreviewChrome(candidateRoot);
        const renderedHtml = typeof candidateRoot.innerHTML === 'string' ? candidateRoot.innerHTML.trim() : '';
        if (renderedHtml === '' || renderedHtml.includes('currentPoffConfig') || renderedHtml.includes('POFF_CONTEXT')) {
            return '';
        }
        if (renderedHtml !== '') {
            return renderedHtml;
        }
        if (typeof candidateRoot.outerHTML === 'string') {
            return candidateRoot.outerHTML.trim();
        }
        return '';
    }

    function isConverterPreviewUrl(url) {
        try {
            const parsed = new URL(url, window.location.href);
            return parsed.searchParams.get('converter_preview') === '1';
        } catch (err) {
            return false;
        }
    }

    function collectPreviewScripts(previewDocument, baseUrl, enabled = false) {
        if (!enabled || !previewDocument || typeof previewDocument.querySelectorAll !== 'function') {
            return [];
        }

        return Array.from(previewDocument.querySelectorAll('script'))
            .map((node) => {
                const src = node.getAttribute('src') || '';
                const text = node.textContent || '';
                if (src) {
                    try {
                        return {
                            src: new URL(src, baseUrl).href,
                            text: '',
                        };
                    } catch (err) {
                        return null;
                    }
                }
                const trimmed = String(text || '').trim();
                if (trimmed === '' || trimmed.includes('POFF_CONTEXT') || trimmed.includes('currentPoffConfig')) {
                    return null;
                }
                return {
                    src: '',
                    text: trimmed,
                };
            })
            .filter(Boolean);
    }

    function executePreviewScripts(previewContainer, scripts = []) {
        if (!previewContainer || !scripts.length) {
            return;
        }
        scripts.forEach((scriptData) => {
            try {
                const script = document.createElement('script');
                if (scriptData.src) {
                    script.src = scriptData.src;
                    script.async = false;
                } else {
                    script.textContent = scriptData.text || '';
                }
                previewContainer.appendChild(script);
            } catch (err) {
                // Converter previews still render through their HTML/CSS fallback if a script cannot be mounted.
            }
        });
    }

    function buildViewerUrl(path, isFile = false, forceRefresh = false) {
        const url = new URL(window.location.href);
        url.search = '';
        url.hash = '';
        url.searchParams.set('view', '1');
        url.searchParams.set(isFile ? 'file' : 'path', path);
        const previewParams = typeof getPreviewParams === 'function' ? getPreviewParams({
            path,
            isFile,
        }) : null;
        if (previewParams && typeof previewParams === 'object') {
            Object.entries(previewParams).forEach(([key, value]) => {
                const normalizedKey = String(key || '').trim();
                if (!normalizedKey || value === undefined || value === null || value === '') {
                    return;
                }
                url.searchParams.set(normalizedKey, String(value));
            });
        }
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

        const previewRoot = previewMountRoot || contentFrame;
        previewRoot.querySelectorAll('a, button, input, select, textarea, summary, iframe, video, audio, [contenteditable="true"], [tabindex]').forEach((node) => {
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

    function extractFallbackAnchorPath(anchor) {
        if (!anchor) {
            return null;
        }
        const currentSelection = getCurrentSelection?.() || getSelectionFromPath(initialQueryPath || '');
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
            const path = typeof event.composedPath === 'function' ? event.composedPath() : [];
            let target = path.find((node) => node && node.tagName === 'A') || event.target;
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
        const nextPreview = previewStateFromUrl(url);
        const shouldPreserveScroll = !!nextPreview.key && nextPreview.key === lastPreviewKey;
        const preservedScrollTop = shouldPreserveScroll ? contentFrame.scrollTop : 0;
        const preservedScrollLeft = shouldPreserveScroll ? contentFrame.scrollLeft : 0;
        try {
            previewMountRoot = null;
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'text/html,application/xhtml+xml',
                    'X-Requested-With': 'fetch-preview',
                },
            });
            if (!response.ok) {
                throw new Error(`Preview request failed: ${response.status}`);
            }
            const html = await response.text();
            const parser = new DOMParser();
            const previewDocument = parser.parseFromString(html, 'text/html');
            const previewRoot = previewDocument.querySelector('#contentFrame > .viewer') || previewDocument.querySelector('.viewer') || previewDocument.body;
            const converterPreview = isConverterPreviewUrl(url);
            const previewContainer = contentFrame.shadowRoot || contentFrame;
            previewMountRoot = previewContainer;
            previewContainer.innerHTML = '';
            const headNodes = previewDocument.head ? Array.from(previewDocument.head.children) : [];
            const previewStyles = headNodes
                .filter((node) => node instanceof HTMLStyleElement || (node instanceof HTMLLinkElement && (node.getAttribute('rel') || '').toLowerCase() === 'stylesheet'))
                .map((node) => {
                    if (node instanceof HTMLStyleElement) {
                        return normalizePreviewStyleNode(node);
                    }
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    const href = node.getAttribute('href') || '';
                    if (!href) {
                        return '';
                    }
                    try {
                        link.href = new URL(href, response.url).href;
                    } catch (err) {
                        return '';
                    }
                    return link.outerHTML;
                })
                .filter(Boolean)
                .join('');
            const previewMarkup = rewritePreviewMarkupUrls(
                extractPreferredPreviewMarkup(previewDocument, previewRoot, { converterPreview }),
                response.url
            );
            let previewScripts = [];
            try {
                previewScripts = collectPreviewScripts(previewDocument, response.url, converterPreview);
            } catch (err) {
                previewScripts = [];
            }
            const fallbackMarkup = previewMarkup || (
                isExternalPreviewUrl(response.url)
                    ? `<div class="viewer-error"><div>Remote snapshot unavailable.</div><a href="${escapePreviewMarkup(response.url)}" target="_blank" rel="noopener">Open original</a></div>`
                    : '<div class="viewer-error">Preview unavailable.</div>'
            );
            previewContainer.innerHTML = `${previewStyles}${fallbackMarkup}`;
            executePreviewScripts(previewContainer, previewScripts);
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
            previewMountRoot = null;
            contentFrame.innerHTML = '<div class="viewer-error">Preview failed to load.</div>';
        } finally {
            if (requestId === previewRequestId) {
                setLoadingVisible(iframeLoading, false);
            }
        }
    }

    return {
        bindPreviewNavigation,
        buildViewerUrl,
        renderPreview,
        syncPreviewDisabledState,
    };
}
