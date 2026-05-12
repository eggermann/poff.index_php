import { inferFilePath } from '../core/selection.js';

export function normalizeHashPath(value = '') {
    return String(value || '')
        .replace(/\\/g, '/')
        .replace(/^#\/?/, '')
        .replace(/^\/+|\/+$/g, '');
}

export function normalizeHashAlias(value = '') {
    return normalizeHashPath(value).toLowerCase();
}

export function routeResolution(path = '', isFile = inferFilePath(path)) {
    return {
        path,
        isFile,
    };
}

export function findNavLinkByAttribute(navList, attributeName, value = '') {
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

export function navTargetPath(link) {
    if (!link) {
        return '';
    }
    return link.getAttribute('data-layout-path')
        || link.getAttribute('data-path')
        || link.getAttribute('data-src')
        || '';
}

export function navTargetIsFile(link, path = '') {
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
