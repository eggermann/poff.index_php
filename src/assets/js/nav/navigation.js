import { getSelectionFromPath } from '../core/selection.js';
import { createPreviewController } from './preview-controller.js';
import { createRouteResolver } from './route-resolver.js';
import { createSidebarController } from './sidebar-controller.js';

export function initNavigation({
    elements,
    editQuery,
    currentPathForIframe,
    renderFolderMeta,
    initEditMode,
}) {
    const { navList, contentFrame, iframeLoading, sidebarLoading } = elements;
    let ignoreNextHashSync = false;
    const initialQueryPath = new URLSearchParams(window.location.search).get('path') || '';
    const routeResolver = createRouteResolver({ navList });

    function setLoadingVisible(element, visible) {
        if (!element) {
            return;
        }
        element.classList.toggle('flex', visible);
        element.classList.toggle('items-center', visible);
    }

    function readHashPath() {
        return routeResolver.readHashPath();
    }

    function getCurrentSelection() {
        return getSelectionFromPath(readHashPath() || initialQueryPath || '');
    }

    const previewController = createPreviewController({
        contentFrame,
        iframeLoading,
        initialQueryPath,
        navigateToPath,
        setLoadingVisible,
        getCurrentSelection,
    });

    const sidebarController = createSidebarController({
        navList,
        sidebarLoading,
        editQuery,
        navigateToPath,
        getCurrentSelection,
        setLoadingVisible,
    });

    function writeHashPath(path = '') {
        const hashPath = routeResolver.displayHashPath(path);
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

    function navigateToPath(path = '', options = {}) {
        const resolved = routeResolver.resolveHashPath(path);
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
            previewController.syncPreviewDisabledState(!!selection.isLayout);
            previewController.renderPreview(previewController.buildViewerUrl(previewPath, previewIsFile, forceRefresh));
        }
        if (updateHash) {
            writeHashPath(selection.path || '');
        }
        if (navList) {
            setLoadingVisible(sidebarLoading, true);
            sidebarController.loadNav(folderPath)
                .then(() => {
                    sidebarController.syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
                    setLoadingVisible(sidebarLoading, false);
                })
                .catch(() => {
                    sidebarController.syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
                    setLoadingVisible(sidebarLoading, false);
                });
        }
        if (initEditMode) {
            initEditMode();
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
            const resolved = await routeResolver.resolveHashPathAsync(hashPath);
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

    if (navList) {
        sidebarController.bindNavClick();
    }
    if (contentFrame) {
        previewController.bindPreviewNavigation();
    }
    window.POFF_RESOLVE_HASH_PATH = routeResolver.resolveHashPath;

    return {
        consumeHashSync() {
            if (!ignoreNextHashSync) {
                return false;
            }
            ignoreNextHashSync = false;
            return true;
        },
        loadCurrentFolderInIframe,
        refreshCurrentLocation,
        rememberSlugPathAlias: routeResolver.rememberSlugPathAlias,
        syncFromLocation,
        writeHashPath,
    };
}
