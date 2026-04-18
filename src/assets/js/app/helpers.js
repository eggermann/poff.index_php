import { APP_ELEMENT_IDS, APP_HASHES, APP_LABELS } from './constants.js';

export function redirectLegacyMcpHash() {
    if (window.location.hash !== APP_HASHES.legacyMcp) {
        return false;
    }

    const basePath = window.location.pathname.split('#')[0];
    window.location.href = `${basePath}?mcp=1`;
    return true;
}

export function createAppElements() {
    return Object.fromEntries(
        Object.entries(APP_ELEMENT_IDS).map(([key, id]) => [key, document.getElementById(id)]),
    );
}

export function isPreviewHashActive() {
    return window.location.hash === APP_HASHES.preview;
}

export function scrollToPreview() {
    const previewEl = document.getElementById('preview');
    if (!previewEl) {
        return;
    }

    previewEl.scrollIntoView({ block: 'start' });
}

export function bindSidebarToggle({ appShell, appSidebar, sidebarToggle }) {
    if (!appShell || !appSidebar || !sidebarToggle) {
        return null;
    }

    const syncSidebarState = (isOpen) => {
        appShell.classList.toggle('sidebar-collapsed', !isOpen);
        appSidebar.hidden = !isOpen;
        appSidebar.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        sidebarToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        sidebarToggle.setAttribute('aria-label', isOpen ? APP_LABELS.closeNavigation : APP_LABELS.openNavigation);
        sidebarToggle.setAttribute('title', isOpen ? APP_LABELS.closeNavigation : APP_LABELS.openNavigation);
    };

    syncSidebarState(true);

    const onToggleClick = () => {
        const isOpen = !appShell.classList.contains('sidebar-collapsed');
        syncSidebarState(!isOpen);
    };

    sidebarToggle.addEventListener('click', onToggleClick);

    return {
        syncSidebarState,
        destroy() {
            sidebarToggle.removeEventListener('click', onToggleClick);
        },
    };
}
