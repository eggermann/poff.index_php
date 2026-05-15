import { getLayoutState } from '../../core/utils.js';

const DETAILS_STORAGE_PREFIX = 'poff.edit.details';

function resolveStorage(storage = null) {
    if (storage) {
        return storage;
    }
    if (typeof localStorage !== 'undefined') {
        return localStorage;
    }
    return null;
}

function normalizeDetailsStorageKey(storageKey) {
    const key = String(storageKey || '').trim();
    if (!key) {
        return '';
    }
    return key.startsWith(DETAILS_STORAGE_PREFIX) ? key : `${DETAILS_STORAGE_PREFIX}:${key}`;
}

export function readStoredDetailsState(storageKey, storage = null) {
    const resolvedStorage = resolveStorage(storage);
    const key = normalizeDetailsStorageKey(storageKey);
    if (!resolvedStorage || !key) {
        return null;
    }
    try {
        const raw = resolvedStorage.getItem(key);
        if (raw === null) {
            return null;
        }
        const stored = JSON.parse(raw);
        if (typeof stored === 'boolean') {
            return stored;
        }
        if (stored && typeof stored === 'object' && Object.prototype.hasOwnProperty.call(stored, 'open')) {
            return !!stored.open;
        }
        return null;
    } catch (err) {
        return null;
    }
}

export function writeStoredDetailsState(storageKey, open, storage = null) {
    const resolvedStorage = resolveStorage(storage);
    const key = normalizeDetailsStorageKey(storageKey);
    if (!resolvedStorage || !key) {
        return;
    }
    try {
        resolvedStorage.setItem(key, JSON.stringify({ open: !!open }));
    } catch (err) {
        // Ignore storage failures.
    }
}

export function bindStoredDetailsState(detailsEl, storageKey, storage = null) {
    if (!detailsEl) {
        return () => {};
    }
    const key = normalizeDetailsStorageKey(storageKey);
    const onToggle = () => {
        writeStoredDetailsState(key, !!detailsEl.open, storage);
    };
    detailsEl.addEventListener('toggle', onToggle);
    return () => {
        detailsEl.removeEventListener('toggle', onToggle);
    };
}

export function formatUploadBytes(value = 0) {
    const bytes = Number(value) || 0;
    if (bytes <= 0) {
        return '0 B';
    }
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let index = 0;
    while (size >= 1024 && index < units.length - 1) {
        size /= 1024;
        index += 1;
    }
    const rounded = size >= 10 || index === 0 ? Math.round(size) : Math.round(size * 10) / 10;
    return `${rounded} ${units[index]}`;
}

export function uploadValidationError(files = [], uploadLimits = null) {
    if (!Array.isArray(files) || files.length === 0) {
        return null;
    }

    const perFileLimit = Number(uploadLimits?.uploadMaxBytes || 0);
    const postLimit = Number(uploadLimits?.postMaxBytes || 0);
    const totalSize = files.reduce((sum, file) => sum + (Number(file?.size) || 0), 0);

    if (perFileLimit > 0) {
        const oversizedFile = files.find((file) => (Number(file?.size) || 0) > perFileLimit);
        if (oversizedFile) {
            return `${oversizedFile.name} is too large. Max file size is ${uploadLimits?.uploadMax || formatUploadBytes(perFileLimit)}.`;
        }
    }

    if (postLimit > 0 && totalSize > postLimit) {
        return `Selected files are too large together. Max upload payload is ${uploadLimits?.postMax || formatUploadBytes(postLimit)}.`;
    }

    return null;
}

export function syncPromptDock(promptRoot = null) {
    const promptDock = document.querySelector('#promptDock');
    if (!promptDock) {
        return;
    }

    if (promptRoot) {
        promptDock.replaceChildren(promptRoot);
        return;
    }

    promptDock.replaceChildren();
}

export function layoutOverlayState(config, status) {
    const layoutState = getLayoutState(config);
    const isFile = status?.target === 'file';
    const sectionName = layoutState.section || (isFile ? 'work' : 'works');
    const localLayoutDirectory = layoutState.localLayoutDirectory || (isFile
        ? `.works/${config.name || config.path || 'item'}.layout`
        : '.layout');
    const wrapperTarget = `${localLayoutDirectory}/template.hbs`;
    const localSectionTarget = `${localLayoutDirectory}/${sectionName}.hbs`;
    const activeSectionDirectory = String(layoutState.sectionDirectory || '').trim();
    const sectionTarget = activeSectionDirectory
        ? `${activeSectionDirectory}/${sectionName}.hbs`
        : (isFile && layoutState.storage === 'filesystem' && layoutState.directory !== localLayoutDirectory
            ? `built-in ${sectionName}.hbs`
            : localSectionTarget);
    const wrapperWasLocal = layoutState.directory === localLayoutDirectory;
    const sectionWasLocal = layoutState.sectionDirectory === localLayoutDirectory;
    const hasInheritedLayout = !!layoutState.inheritedDirectory;
    const originalTarget = layoutState.storage === 'filesystem'
        ? (layoutState.directory || localLayoutDirectory)
        : (layoutState.inheritedDirectory || '');
    const originalEditable = originalTarget !== '';
    const originalUsesLocal = originalTarget === localLayoutDirectory;

    const localWrapperTemplate = wrapperWasLocal
        ? (layoutState.template || '')
        : '';
    const localWrapperCss = wrapperWasLocal
        ? (layoutState.css || '')
        : '';
    const localWrapperJs = wrapperWasLocal
        ? (layoutState.js || '')
        : '';

    let originalTemplate = '';
    let originalCss = '';
    let originalJs = '';
    if (originalEditable && layoutState.storage === 'filesystem') {
        originalTemplate = layoutState.template || '';
        originalCss = layoutState.css || '';
        originalJs = layoutState.js || '';
    } else if (layoutState.storage === 'shared') {
        originalTemplate = layoutState.template || '';
        originalCss = layoutState.css || '';
        originalJs = layoutState.js || '';
    } else if (!originalEditable) {
        originalTemplate = layoutState.phpTemplate || '';
    }

    const resolvedDirectory = layoutState.directory || localLayoutDirectory;
    const wrapperSourceLabel = layoutState.mode === 'none' || layoutState.storage === 'none'
        ? 'No outer layout'
        : layoutState.storage === 'filesystem'
        ? (isFile && resolvedDirectory !== localLayoutDirectory
            ? `Folder layout: ${resolvedDirectory}`
            : `${isFile ? 'File layout' : 'Folder layout'}: ${resolvedDirectory}`)
        : layoutState.storage === 'shared'
            ? (layoutState.sourceLabel || `Collection: ${layoutState.sharedName || layoutState.name || 'shared'}`)
        : 'PHP built-in poff-layout';
    const inheritedLayoutLabel = hasInheritedLayout
        ? layoutState.inheritedDirectory
        : 'No parent .layout found';
    const originalLabel = originalEditable
        ? `Editable source: ${originalTarget}`
        : layoutState.storage === 'shared'
            ? `Collection layout source: ${layoutState.directory || layoutState.sharedName || layoutState.name || 'shared'}`
        : 'PHP built-in poff-layout is read-only until a parent .layout exists';
    const displayMode = layoutState.mode === 'filesystem-layout'
        ? (layoutState.directory === localLayoutDirectory ? 'custom-layout' : 'inherit-layout')
        : layoutState.mode;

    return {
        layoutState,
        displayMode,
        sectionName,
        localLayoutDirectory,
        wrapperTarget,
        localSectionTarget,
        sectionTarget,
        wrapperWasLocal,
        sectionWasLocal,
        hasInheritedLayout,
        originalTarget,
        originalEditable,
        originalUsesLocal,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        originalTemplate,
        originalCss,
        originalJs,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
    };
}
