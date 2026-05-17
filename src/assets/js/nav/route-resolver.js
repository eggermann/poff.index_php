import { getSelectionFromPath, inferFilePath } from '../core/selection.js';
import {
    findNavLinkByAttribute,
    navTargetIsFile,
    navTargetPath,
    normalizeHashAlias,
    normalizeHashPath,
    routeResolution,
} from './path-helpers.js';

export function createRouteResolver({ navList } = {}) {
    const slugToPathAliases = new Map();
    const pathToSlugAliases = new Map();

    function rememberSlugPathAlias(detail = {}) {
        const path = normalizeHashPath(detail?.routePath || detail?.path || detail?.relativePath || '');
        const slug = normalizeHashPath(detail?.routeSlug || detail?.slug || '');
        if (!path || !slug || slug.includes('/')) {
            return;
        }

        slugToPathAliases.set(normalizeHashAlias(slug), path);
        pathToSlugAliases.set(normalizeHashAlias(path), slug);
    }

    function resolveHashPath(path = '') {
        const normalizedPath = normalizeHashPath(path);
        const aliasPath = slugToPathAliases.get(normalizeHashAlias(normalizedPath));
        if (aliasPath) {
            return routeResolution(aliasPath);
        }
        if (!normalizedPath.includes('/')) {
            const link = findNavLinkByAttribute(navList, 'data-route-slug', normalizedPath)
                || findNavLinkByAttribute(navList, 'data-slug', normalizedPath);
            const targetPath = navTargetPath(link);
            if (targetPath) {
                rememberSlugPathAlias({
                    path: targetPath,
                    slug: link?.getAttribute('data-route-slug') || normalizedPath,
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
        const link = findNavLinkByAttribute(navList, 'data-path', normalizedPath)
            || findNavLinkByAttribute(navList, 'data-route-path', normalizedPath);
        const slug = link?.getAttribute('data-route-slug') || link?.getAttribute('data-slug') || '';
        if (slug && !slug.includes('/')) {
            rememberSlugPathAlias({
                path: normalizedPath,
                slug,
            });
            return slug;
        }
        return normalizedPath;
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

    return {
        displayHashPath,
        readHashPath,
        rememberSlugPathAlias,
        resolveHashPath,
        resolveHashPathAsync,
    };
}
