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
});

const sidebarController = bindSidebarToggle(elements);

document.addEventListener('DOMContentLoaded', () => {
    if (sidebarController) {
        sidebarController.syncSidebarState(true);
    }
    editController.syncEditToggle();
    editController.bindEditToggle();

    if (isPreviewHashActive()) {
        navigation.loadCurrentFolderInIframe();
        requestAnimationFrame(() => scrollToPreview());
    } else if (window.location.hash && window.location.hash.length > 1) {
        navigation.syncFromLocation();
    } else {
        navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
});

window.addEventListener('hashchange', () => {
    if (isPreviewHashActive()) {
        scrollToPreview();
        if (editRequested) {
            editController.initEditMode();
        }
        return;
    }

    if (!navigation.consumeHashSync()) {
        navigation.syncFromLocation();
    }
    if (editRequested) {
        editController.initEditMode();
    }
});

window.addEventListener('poff:content-updated', () => {
    navigation.refreshCurrentLocation();
    if (editRequested) {
        editController.initEditMode();
    }
});
