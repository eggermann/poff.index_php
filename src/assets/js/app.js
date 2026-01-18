import { createEditController } from './edit/controller.js';
import { initNavigation } from './nav/navigation.js';



if (window.location.hash === '#mcp') {
    const basePath = window.location.pathname.split('#')[0];
    window.location.href = `${basePath}?mcp=1`;
}

const elements = {
    navList: document.getElementById('navList'),
    contentFrame: document.getElementById('contentFrame'),
    folderMetaEl: document.getElementById('folderMeta'),
    editPanel: document.getElementById('editPanel'),
    editDrawer: document.getElementById('editDrawer'),
    editToggle: document.getElementById('editToggle'),
    iframeLoading: document.getElementById('iframeLoading'),
    sidebarLoading: document.getElementById('sidebarLoading'),
};

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

document.addEventListener('DOMContentLoaded', () => {
    editController.syncEditToggle();
    editController.bindEditToggle();

    if (window.location.hash && window.location.hash.length > 1) {
        navigation.handleInitialHash();
    } else {
        navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
});

window.addEventListener('hashchange', () => {
    if (editRequested) {
        editController.initEditMode();
    }
});
