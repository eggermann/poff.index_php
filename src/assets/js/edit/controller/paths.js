import { getActiveSelection } from '../../core/selection.js';

function normalizeViewerPath(path = '') {
    return String(path || '')
        .replace(/\\/g, '/')
        .replace(/^\/+|\/+$/g, '');
}

function parentViewerPath(path = '') {
    const normalized = normalizeViewerPath(path);
    if (!normalized) {
        return '';
    }

    return normalized.split('/').slice(0, -1).join('/');
}

function findConfiguredSelectionItem(items = [], targetPath = '', prefix = '') {
    const normalizedTarget = normalizeViewerPath(targetPath);
    if (!normalizedTarget || !Array.isArray(items)) {
        return null;
    }

    for (const item of items) {
        if (!item || typeof item !== 'object') {
            continue;
        }

        const name = normalizeViewerPath(item.name || '');
        const rawPath = normalizeViewerPath(item.path || item.relativePath || name);
        const resolvedPath = normalizeViewerPath(prefix ? `${prefix}/${rawPath}` : rawPath);
        const slug = normalizeViewerPath(item.slug || '');
        const candidates = [resolvedPath, rawPath, name, slug].filter(Boolean);

        if (candidates.includes(normalizedTarget)) {
            return item;
        }

        if (Array.isArray(item.children)) {
            const found = findConfiguredSelectionItem(item.children, normalizedTarget, resolvedPath);
            if (found) {
                return found;
            }
        }
    }

    return null;
}

function resolveConfiguredSelectionItem(selection = getActiveSelection()) {
    if (typeof window === 'undefined' || !window?.POFF_CONTEXT || typeof window.POFF_CONTEXT !== 'object') {
        return null;
    }

    const tree = window.POFF_CONTEXT.currentPoffConfig?.tree;
    if (!Array.isArray(tree)) {
        return null;
    }

    const targetPath = normalizeViewerPath(selection?.previewPath || selection?.path || '');
    if (!targetPath) {
        return null;
    }

    return findConfiguredSelectionItem(tree, targetPath);
}

export function getContentTargetPath(selection = getActiveSelection()) {
    if (selection?.isLayout) {
        return selection.path || '';
    }

    const configuredItem = resolveConfiguredSelectionItem(selection);
    const previewPath = selection?.previewPath || selection?.path || '';
    if (selection?.previewIsFile || (configuredItem && configuredItem.type && configuredItem.type !== 'folder')) {
        return parentViewerPath(previewPath);
    }
    return previewPath;
}

export function getEditTargetPath(selection = getActiveSelection()) {
    if (selection?.isLayout) {
        return selection.path || '';
    }
    const activeFileLink = document.querySelector('#navList a.nav-link-active[data-path]');
    const navPath = (activeFileLink?.getAttribute('data-path') || '').trim();
    const navLooksLikeFile = !!activeFileLink?.hasAttribute?.('data-file')
        || !!activeFileLink?.hasAttribute?.('data-src')
        || /\.[^\\/]+$/.test(navPath);
    if (navPath && (selection?.previewIsFile || navLooksLikeFile)) {
        return navPath;
    }
    return selection?.previewPath || selection?.path || '';
}
