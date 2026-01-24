import { allowedWorkKeys } from './constants.js';

export function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
}

export function filterAllowedWork(work) {
    if (!work || typeof work !== 'object') {
        return null;
    }
    const filtered = {};
    Object.entries(work).forEach(([key, value]) => {
        if (allowedWorkKeys.includes(key)) {
            filtered[key] = value;
        }
    });
    return filtered;
}
