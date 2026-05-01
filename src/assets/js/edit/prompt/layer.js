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
