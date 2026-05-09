import { extractWorkFields, summarizeWorkFields } from '../../work-fields.js';
import { readPromptEditorDraft } from '../draft.js';

function isExternalPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
        return false;
    }
    if (trimmed.startsWith('//')) {
        return true;
    }
    return /^[a-z][a-z0-9+.-]*:/i.test(trimmed);
}

function isCmsPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    if (!trimmed.startsWith('?')) {
        return false;
    }
    const params = new URLSearchParams(trimmed.replace(/^\?/, ''));
    return params.has('file') || params.has('path') || params.get('view') === '1';
}

function isSpecialPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    return trimmed.startsWith('#') || isCmsPromptLink(trimmed) || isExternalPromptLink(trimmed);
}

function getPromptItemExplicitLink(item = {}) {
    const keys = ['pageLink', 'pageUrl', 'viewUrl', 'workUrl', 'viewerHref', 'linkUrl', 'link', 'url'];
    for (const key of keys) {
        const value = String(item?.[key] || '').trim();
        if (value) {
            return value;
        }
    }
    const rawPath = String(item?.path || item?.relativePath || '').trim();
    return isSpecialPromptLink(rawPath) ? rawPath : '';
}

function getPromptItemDisplayPath(folderBasePath = '', item = {}) {
    const rawPath = String(item?.path || item?.relativePath || '').trim();
    if (rawPath) {
        if (isCmsPromptLink(rawPath)) {
            const params = new URLSearchParams(rawPath.replace(/^\?/, ''));
            return params.get(params.has('file') ? 'file' : 'path') || '';
        }
        if (isSpecialPromptLink(rawPath)) {
            return rawPath;
        }
        return folderBasePath && !rawPath.startsWith(`${folderBasePath}/`) && rawPath !== folderBasePath
            ? `${folderBasePath}/${rawPath}`
            : rawPath;
    }
    const explicitLink = getPromptItemExplicitLink(item);
    if (isCmsPromptLink(explicitLink)) {
        const params = new URLSearchParams(explicitLink.replace(/^\?/, ''));
        return params.get(params.has('file') ? 'file' : 'path') || '';
    }
    if (explicitLink) {
        return explicitLink;
    }
    const fallbackName = String(item?.name || '').trim();
    if (!fallbackName) {
        return '';
    }
    return folderBasePath ? `${folderBasePath}/${fallbackName}` : fallbackName;
}

function getDefaultWorkCategories(type = '') {
    const normalizedType = String(type || '').trim().toLowerCase();
    if (normalizedType === 'image') {
        return ['image', 'media', 'visual'];
    }
    if (normalizedType === 'video') {
        return ['video', 'media', 'motion'];
    }
    if (normalizedType === 'audio') {
        return ['audio', 'media', 'sound'];
    }
    if (normalizedType === 'pdf') {
        return ['pdf', 'document'];
    }
    if (normalizedType === 'text') {
        return ['text', 'document'];
    }
    if (normalizedType === 'link') {
        return ['link', 'reference'];
    }
    if (normalizedType === 'folder') {
        return ['folder', 'collection'];
    }
    return ['other'];
}

function normalizeWorkCategories(work = {}) {
    const rawValue = Array.isArray(work?.categories)
        ? work.categories
        : Array.isArray(work?.category)
            ? work.category
            : work?.categories ?? work?.category ?? [];
    const sourceValues = Array.isArray(rawValue)
        ? rawValue
        : String(rawValue || '').trim()
            ? String(rawValue).split(/\r?\n|,/)
            : [];
    const categories = [];
    const append = (value) => {
        const normalized = String(value || '').trim().toLowerCase();
        if (!normalized || categories.includes(normalized)) {
            return;
        }
        categories.push(normalized);
    };
    getDefaultWorkCategories(work?.type).forEach(append);
    sourceValues.forEach(append);
    return categories;
}

export function buildPromptContext({ getActiveSelection, getConfig }) {
    const selection = typeof getActiveSelection === 'function' ? getActiveSelection() : { path: '', isFile: false };
    const config = typeof getConfig === 'function' ? getConfig() || {} : {};
    const isLayout = !!selection?.isLayout;
    const path = selection?.previewPath ?? selection?.path ?? '';
    const virtualPath = selection?.path || '';
    const name = path ? path.split(/[\\/]/).pop() : '';
    const isFile = isLayout ? !!selection?.layoutIsFile : selection?.isFile ?? /\.[^\\/]+$/.test(path);
    const viewUrl = isFile ? `?view=1&file=${encodeURIComponent(path)}` : `?view=1&path=${encodeURIComponent(path)}`;
    const localLayoutDirectory = isFile ? `.works/${name || 'item'}.layout` : '.layout';
    const sectionTemplateTarget = isFile ? `${localLayoutDirectory}/work.hbs` : `${localLayoutDirectory}/works.hbs`;
    const layoutTemplateTarget = `${localLayoutDirectory}/template.hbs`;
    const work = config && typeof config === 'object' && config.work ? config.work : {};
    const layout = work && typeof work.layout === 'object' ? work.layout : {};
    const layoutStorage = typeof layout?.storage === 'string' ? String(layout.storage) : '';
    const resolvedLayoutDirectory = layout?.directory ? String(layout.directory) : '';
    const inheritedLayoutDirectory = layout?.inheritedDirectory ? String(layout.inheritedDirectory) : '';
    const presetEl = isLayout ? document.getElementById('edit-layout-preset') : null;
    const sharedPresetEl = isLayout ? document.getElementById('edit-layout-shared') : null;
    const layoutPreset = isLayout && presetEl ? String(presetEl.value || '').trim() : '';
    const layoutSharedName = isLayout && sharedPresetEl ? String(sharedPresetEl.value || '').trim() : '';
    const activeLayoutDirectory = (() => {
        if (!isLayout) {
            return resolvedLayoutDirectory || localLayoutDirectory;
        }
        if (layoutPreset === 'custom') {
            return localLayoutDirectory;
        }
        if (layoutStorage === 'shared' && resolvedLayoutDirectory) {
            return resolvedLayoutDirectory;
        }
        if (layoutStorage === 'filesystem' && resolvedLayoutDirectory) {
            return resolvedLayoutDirectory;
        }
        return localLayoutDirectory;
    })();
    const templateTarget = isLayout ? `${activeLayoutDirectory}/template.hbs` : sectionTemplateTarget;
    const tree = Array.isArray(config?.tree) ? config.tree : [];
    const folderBasePath = (selection?.isFile ? path.split('/').slice(0, -1).join('/') : path).replace(/^\/+|\/+$/g, '');
    const ellipsis = '…';
    const workConfig = config && typeof config === 'object' && config.work && typeof config.work === 'object' ? config.work : {};
    const rootTitle = String(config?.title || name || '').trim();
    const rootDescription = String(config?.description || '').trim();
    const rootFolderName = String(config?.folderName || name || '').trim();
    const rootSlug = String(config?.slug || '').trim();
    const workFields = extractWorkFields(workConfig);
    const workWithCategories = {
        ...workConfig,
        categories: normalizeWorkCategories(workConfig),
    };
    const workPreview = Object.entries(workConfig || {}).slice(0, 6).map(([key, value]) => {
        if (key === 'fields' && Array.isArray(value)) {
            const summary = summarizeWorkFields(value);
            return summary ? `fields: ${summary}` : `fields: ${value.length} item(s)`;
        }
        if (typeof value === 'boolean') {
            return `${key}: ${value ? 'true' : 'false'}`;
        }
        if (Array.isArray(value)) {
            return `${key}: [${value.length} item(s)]`;
        }
        if (value && typeof value === 'object') {
            const keys = Object.keys(value).slice(0, 4);
            return `${key}: {${keys.join(', ')}}`;
        }
        if (value === null || value === undefined) {
            return `${key}: null`;
        }
        const str = String(value);
        return `${key}: ${str.length > 28 ? `${str.slice(0, 25)}${ellipsis}` : str}`;
    }).join(', ');
    const workFieldsPreview = summarizeWorkFields(workFields);
    const refPreview = tree.slice(0, 4).map((item) => {
        const itemName = item?.name || item?.path || '';
        if (!itemName) {
            return '';
        }
        const itemPath = getPromptItemDisplayPath(folderBasePath, item);
        const isItemFile = (item?.type || 'file') !== 'folder';
        const itemPageLink = getPromptItemExplicitLink(item) || (isItemFile ? `?view=1&file=${encodeURIComponent(itemPath)}` : `?view=1&path=${encodeURIComponent(itemPath)}`);
        const itemAssetUrl = getPromptItemExplicitLink(item) || (isItemFile ? itemPath.split('/').map((part) => encodeURIComponent(part)).join('/') : `?path=${encodeURIComponent(itemPath)}`);
        return `${itemName} -> pageLink: ${itemPageLink}, srcUrl: ${itemAssetUrl}`;
    }).filter(Boolean).join(' | ');
    const layoutBaseHref = activeLayoutDirectory;
    const layoutAssetsPreview = Array.isArray(layout?.assets) ? layout.assets.slice(0, 4).map((asset) => {
        const assetPath = asset?.path ? String(asset.path) : '';
        if (!assetPath) {
            return '';
        }
        return `${assetPath} -> ${layoutBaseHref}/${assetPath}`;
    }).filter(Boolean).join(' | ') : '';
    const editorDraft = readPromptEditorDraft(selection);

    return {
        path,
        virtualPath,
        isLayout,
        layoutPreset,
        layoutSharedName,
        name,
        title: rootTitle,
        pageLink: viewUrl,
        viewUrl,
        templateTarget,
        layoutTemplateTarget,
        sectionTemplateTarget,
        layoutBaseHref,
        inheritedLayoutDirectory,
        layoutAssetsPreview,
        editorDraft,
        root: {
            title: rootTitle,
            name: rootFolderName,
            folderName: rootFolderName,
            path,
            slug: rootSlug,
            description: rootDescription,
            type: selection?.isFile ? 'file' : 'folder',
            sectionPartial: isLayout ? 'layout' : isFile ? 'work' : 'works',
        },
        work: {
            title: String(workConfig?.title || name || rootTitle || '').trim(),
            name: String(workConfig?.name || name || '').trim(),
            path,
            slug: String(workConfig?.slug || rootSlug || '').trim(),
            description: String(workConfig?.description || rootDescription || '').trim(),
            type: String(workConfig?.type || (selection?.isFile ? 'file' : 'folder') || '').trim(),
            kind: String(workConfig?.kind || (selection?.isFile ? 'file' : 'folder') || '').trim(),
            categories: normalizeWorkCategories(workConfig),
            fields: workFields,
            layout: workWithCategories.layout ?? null,
        },
        workData: workWithCategories,
        workFields,
        workFieldsPreview,
        workPreview,
        refPreview,
    };
}
