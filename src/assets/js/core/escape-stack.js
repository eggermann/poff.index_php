const escapeHandlers = [];

function getRootScope() {
    if (typeof window !== 'undefined' && window) {
        return window;
    }
    if (typeof globalThis !== 'undefined' && globalThis) {
        return globalThis;
    }
    return null;
}

function handleEscapeKeydown(event) {
    if (!event || event.key !== 'Escape' || event.defaultPrevented) {
        return;
    }

    for (let index = escapeHandlers.length - 1; index >= 0; index -= 1) {
        const entry = escapeHandlers[index];
        if (!entry || typeof entry.close !== 'function') {
            continue;
        }
        const shouldStop = entry.close(event);
        if (shouldStop === false) {
            continue;
        }
        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }
        if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }
        return;
    }
}

function ensureEscapeListener() {
    const root = getRootScope();
    if (!root || typeof document === 'undefined' || !document || typeof document.addEventListener !== 'function') {
        return;
    }
    if (root.__POFF_ESCAPE_STACK_BOUND__) {
        return;
    }
    root.__POFF_ESCAPE_STACK_BOUND__ = true;
    document.addEventListener('keydown', handleEscapeKeydown, true);
}

function closeOpenDetailsTree(rootDetails) {
    if (!rootDetails || typeof rootDetails !== 'object') {
        return;
    }

    if (typeof rootDetails.querySelectorAll === 'function') {
        const openDetails = Array.from(rootDetails.querySelectorAll('details[open]')).reverse();
        openDetails.forEach((details) => {
            if (details && typeof details === 'object') {
                details.open = false;
            }
        });
    }
    rootDetails.open = false;
}

export function registerEscapeClose(close, options = {}) {
    if (typeof close !== 'function') {
        return () => {};
    }

    ensureEscapeListener();

    const entry = {
        close,
        label: typeof options.label === 'string' ? options.label : '',
    };
    escapeHandlers.push(entry);

    return () => {
        const index = escapeHandlers.indexOf(entry);
        if (index >= 0) {
            escapeHandlers.splice(index, 1);
        }
    };
}

export function registerEscapeCloseDetails(detailsEl, options = {}) {
    if (!detailsEl || typeof detailsEl.addEventListener !== 'function') {
        return () => {};
    }

    let unregisterEscapeClose = null;
    const label = typeof options.label === 'string' ? options.label : '';

    const syncEscapeState = () => {
        if (detailsEl.open) {
            if (typeof unregisterEscapeClose !== 'function') {
                unregisterEscapeClose = registerEscapeClose(() => {
                    closeOpenDetailsTree(detailsEl);
                    return true;
                }, { label });
            }
            return;
        }

        closeOpenDetailsTree(detailsEl);
        if (typeof unregisterEscapeClose === 'function') {
            unregisterEscapeClose();
            unregisterEscapeClose = null;
        }
    };

    detailsEl.addEventListener('toggle', syncEscapeState);
    syncEscapeState();

    return () => {
        detailsEl.removeEventListener('toggle', syncEscapeState);
        if (typeof unregisterEscapeClose === 'function') {
            unregisterEscapeClose();
            unregisterEscapeClose = null;
        }
    };
}

if (typeof window !== 'undefined' && window) {
    window.POFF_REGISTER_ESCAPE_CLOSE = registerEscapeClose;
}
