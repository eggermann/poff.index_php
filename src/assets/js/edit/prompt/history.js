export function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
}

function compactValue(value) {
    return String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function parseBooleanToken(token) {
    if (['true', 'on', 'yes', '1'].includes(token)) {
        return true;
    }
    if (['false', 'off', 'no', '0'].includes(token)) {
        return false;
    }
    return null;
}

export function inferWorkChangesFromPrompt(prompt, config) {
    const work = (config && typeof config === 'object' && config.work && typeof config.work === 'object')
        ? config.work
        : {};
    const compactPrompt = compactValue(prompt);
    if (!compactPrompt) {
        return null;
    }

    const nextWork = {};
    Object.entries(work).forEach(([key, value]) => {
        if (typeof value !== 'boolean') {
            return;
        }
        const compactKey = compactValue(key);
        if (!compactKey) {
            return;
        }

        const tokenPatterns = [
            new RegExp(`set${compactKey}(?:to|=)?(true|false|on|off|yes|no|1|0)`),
            new RegExp(`(?:make|set)?${compactKey}(true|false|on|off|yes|no|1|0)`),
            new RegExp(`turn${compactKey}(on|off)`),
        ];
        for (const pattern of tokenPatterns) {
            const match = compactPrompt.match(pattern);
            if (match) {
                const parsed = parseBooleanToken(match[1]);
                if (parsed !== null) {
                    nextWork[key] = parsed;
                    return;
                }
            }
        }

        if (compactPrompt.includes(`enable${compactKey}`)) {
            nextWork[key] = true;
            return;
        }
        if (compactPrompt.includes(`disable${compactKey}`)) {
            nextWork[key] = false;
        }
    });

    return Object.keys(nextWork).length ? nextWork : null;
}

export function filterAllowedWork(work, config) {
    if (!work || typeof work !== 'object') {
        return null;
    }
    const baseWork = (config && typeof config === 'object' && config.work && typeof config.work === 'object')
        ? config.work
        : {};
    const allowedKeys = new Set([
        ...Object.keys(baseWork),
        'type',
        'layout',
        'model',
    ]);
    const filtered = {};
    Object.entries(work).forEach(([key, value]) => {
        if (allowedKeys.has(key)) {
            filtered[key] = value;
        }
    });
    return filtered;
}
