export function getSelectionOrFallback(getActiveSelection, fallback = {}) {
    return typeof getActiveSelection === 'function'
        ? (getActiveSelection() || fallback)
        : fallback;
}

export function getLayoutPresetValue(defaultValue = 'actual') {
    const presetEl = document.getElementById('edit-layout-preset');
    if (!presetEl || typeof presetEl.value !== 'string') {
        return defaultValue;
    }
    const value = presetEl.value.trim();
    return value || defaultValue;
}
