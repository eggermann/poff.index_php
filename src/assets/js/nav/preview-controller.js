import { getSelectionFromPath, inferFilePath } from '../core/selection.js';
import {
    normalizeCmsRelativePath,
    normalizePreviewStyleNode,
    previewStateFromUrl,
    scopePreviewStyleText,
} from './preview-helpers.js';

export function createPreviewController({
    contentFrame,
    iframeLoading,
    initialQueryPath = '',
    navigateToPath,
    setLoadingVisible,
    getCurrentSelection,
}) {
    let previewRequestId = 0;
    let previewClickBound = false;
    let previewDisabled = false;
    let lastPreviewKey = '';

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
            const scripts = Array.from(doc.querySelectorAll('script'));
            doc.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
                fragments.push(normalizePreviewStyleNode(node));
            });
            doc.body?.querySelectorAll('style').forEach((node) => {
                node.textContent = scopePreviewStyleText(node.textContent || '');
            });
            scripts.forEach((node) => node.remove());
            const bodyHtml = doc.body ? doc.body.innerHTML : html;
            contentFrame.innerHTML = `${fragments.join('')}${bodyHtml}`;

            scripts.forEach((oldScript) => {
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

    return {
        bindPreviewNavigation,
        buildViewerUrl,
        renderPreview,
        syncPreviewDisabledState,
    };
}
