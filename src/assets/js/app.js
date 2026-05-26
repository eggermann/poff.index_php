import { createEditController } from './edit/controller.js';
import { initNavigation } from './nav/navigation.js';
import {
    bindSidebarToggle,
    createAppElements,
    isPreviewHashActive,
    redirectLegacyMcpHash,
    scrollToPreview,
} from './app/helpers.js';

redirectLegacyMcpHash();

const elements = createAppElements();

const poffContext = window.POFF_CONTEXT || {};
const currentPathForIframe = Object.prototype.hasOwnProperty.call(poffContext, 'currentPathForIframe')
    ? poffContext.currentPathForIframe
    : null;

function resolveRootDocumentTitle(context = {}) {
    const config = context && typeof context === 'object' ? context.currentPoffConfig : null;
    const title = typeof config?.title === 'string' ? config.title.trim() : '';
    if (title) {
        return title;
    }
    const folderName = typeof config?.folderName === 'string' ? config.folderName.trim() : '';
    return folderName || 'poff.io';
}

document.title = resolveRootDocumentTitle(poffContext);

const editRequested = new URLSearchParams(window.location.search).get('edit') === 'true';
const editQuery = editRequested ? '&edit=true' : '';

const editController = createEditController({
    elements,
    context: poffContext,
    editRequested,
});

const navigation = initNavigation({
    elements,
    editQuery,
    currentPathForIframe,
    renderFolderMeta: editController.renderFolderMeta,
    initEditMode: editController.initEditMode,
    getPreviewParams: editController.getPreviewParams,
});

editController.setPreviewRefreshHandler(() => {
    navigation.refreshCurrentPreviewLocation();
});

const sidebarController = bindSidebarToggle(elements);

document.addEventListener('DOMContentLoaded', async () => {
    if (sidebarController) {
        sidebarController.syncSidebarState(true);
    }
    editController.syncEditToggle();
    editController.bindEditToggle();
    editController.bindAddWorkButton();
    editController.bindSidebarFileDrop(elements.appSidebar);
    editController.bindAuthForm();

    if (isPreviewHashActive()) {
        navigation.loadCurrentFolderInIframe();
        requestAnimationFrame(() => scrollToPreview());
    } else if (window.location.hash && window.location.hash.length > 1) {
        await navigation.syncFromLocation();
    } else {
        navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
});

window.addEventListener('hashchange', async () => {
    if (isPreviewHashActive()) {
        scrollToPreview();
        if (editRequested) {
            editController.initEditMode();
        }
        return;
    }

    if (!navigation.consumeHashSync()) {
        await navigation.syncFromLocation();
    }
    if (editRequested) {
        editController.initEditMode();
    }
});

window.addEventListener('poff:content-updated', async (event) => {
    navigation.rememberSlugPathAlias(event.detail || {});
    const nextPath = event.detail?.routePath || event.detail?.path || '';
    if (nextPath) {
        navigation.writeHashPath(nextPath);
    }
    await navigation.refreshCurrentLocation();
    if (editRequested) {
        editController.initEditMode();
    }
});
