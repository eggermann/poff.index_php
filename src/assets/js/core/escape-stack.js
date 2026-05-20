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

if (typeof window !== 'undefined' && window) {
    window.POFF_REGISTER_ESCAPE_CLOSE = registerEscapeClose;
}
