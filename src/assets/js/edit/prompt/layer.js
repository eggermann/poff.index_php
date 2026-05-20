import { registerEscapeClose } from '../../core/escape-stack.js';

export function createPromptLayerController({
    root,
    windowEl,
    closeEl,
    openEl,
    storageKey,
    storage,
}) {
    const readState = () => {
        try {
            const stored = JSON.parse(storage.getItem(storageKey) || '{}');
            return !!stored.collapsed;
        } catch (err) {
            return false;
        }
    };

    let unregisterEscapeClose = null;
    let unregisterEscapeOpen = null;

    const writeState = (collapsed) => {
        try {
            storage.setItem(storageKey, JSON.stringify({ collapsed: !!collapsed }));
        } catch (err) {
            // Ignore storage failures.
        }
    };

    const applyState = (collapsed, options = {}) => {
        const nextCollapsed = !!collapsed;
        root.classList.toggle('prompt-layer-collapsed', nextCollapsed);
        if (windowEl) {
            windowEl.hidden = nextCollapsed;
        }
        if (closeEl) {
            closeEl.hidden = nextCollapsed;
        }
        if (openEl) {
            openEl.hidden = !nextCollapsed;
        }
        if (nextCollapsed) {
            if (unregisterEscapeClose) {
                unregisterEscapeClose();
                unregisterEscapeClose = null;
            }
            if (!unregisterEscapeOpen) {
                unregisterEscapeOpen = registerEscapeClose(() => {
                    if (!root.classList.contains('prompt-layer-collapsed')) {
                        applyState(true);
                        return true;
                    }
                    return false;
                }, { label: 'prompt-layer-open' });
            }
        } else {
            if (unregisterEscapeOpen) {
                unregisterEscapeOpen();
                unregisterEscapeOpen = null;
            }
            if (!unregisterEscapeClose) {
                unregisterEscapeClose = registerEscapeClose(() => {
                    applyState(true);
                    return true;
                }, { label: 'prompt-layer-close' });
            }
        }
        if (!options.skipPersist) {
            writeState(nextCollapsed);
        }
    };

    if (closeEl) {
        closeEl.addEventListener('click', () => applyState(true));
    }
    if (openEl) {
        openEl.addEventListener('click', () => applyState(false));
    }
    return {
        readState,
        writeState,
        applyState,
    };
}
