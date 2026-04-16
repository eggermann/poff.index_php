import { createEditController } from './edit/controller.js';
import { initNavigation } from './nav/navigation.js';


if (window.location.hash === '#mcp') {
    const basePath = window.location.pathname.split('#')[0];
    window.location.href = `${basePath}?mcp=1`;
}

const elements = {
    appShell: document.getElementById('appShell'),
    appSidebar: document.getElementById('appSidebar'),
    navList: document.getElementById('navList'),
    contentFrame: document.getElementById('contentFrame'),
    editPanel: document.getElementById('editPanel'),
    editDrawer: document.getElementById('editDrawer'),
    editToggle: document.getElementById('editToggle'),
    sidebarToggle: document.getElementById('sidebarToggle'),
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

function previewHashActive() {
    return window.location.hash === '#preview';
}

function scrollToPreview() {
    const previewEl = document.getElementById('preview');
    if (!previewEl) {
        return;
    }

    previewEl.scrollIntoView({ block: 'start' });
}

function bindSidebarToggle() {
    const { appShell, appSidebar, sidebarToggle } = elements;
    if (!appShell || !appSidebar || !sidebarToggle) {
        return;
    }

    const syncSidebarState = (isOpen) => {
        appShell.classList.toggle('sidebar-collapsed', !isOpen);
        appSidebar.hidden = !isOpen;
        appSidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        sidebarToggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
        sidebarToggle.setAttribute('title', isOpen ? 'Close navigation' : 'Open navigation');
    };

    syncSidebarState(true);

    sidebarToggle.addEventListener('click', () => {
        const isOpen = !appShell.classList.contains('sidebar-collapsed');
        syncSidebarState(!isOpen);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    bindSidebarToggle();
    editController.syncEditToggle();
    editController.bindEditToggle();

    if (previewHashActive()) {
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
    if (previewHashActive()) {
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
