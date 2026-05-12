export function previewStateFromUrl(url) {
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

export function parseCmsLinkValue(value = '') {
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

export function normalizeCmsRelativePath(value = '') {
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

export function scopePreviewRootSelector(selector = '') {
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

export function scopePreviewStyleText(css = '') {
    return String(css || '').replace(/(^|})\s*([^@{}][^{]*)\{/g, (match, prefix, selectorList) => {
        const scopedSelectors = selectorList
            .split(',')
            .map(scopePreviewRootSelector)
            .join(', ');
        return `${prefix} ${scopedSelectors} {`;
    });
}

export function normalizePreviewStyleNode(node) {
    if (!(node instanceof HTMLStyleElement)) {
        return node.outerHTML;
    }
    const clone = node.cloneNode(true);
    clone.textContent = scopePreviewStyleText(node.textContent || '');
    return clone.outerHTML;
}
