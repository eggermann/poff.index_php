export function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
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
