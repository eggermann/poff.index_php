import { defaultPromptSettings, promptHistoryKey, promptSettingsKey } from './constants.js';

export function loadPromptSettings() {
    try {
        const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || '{}');
        return { ...defaultPromptSettings, ...stored };
    } catch (err) {
        return defaultPromptSettings;
    }
}

export function savePromptSettings(settings) {
    try {
        localStorage.setItem(promptSettingsKey, JSON.stringify(settings));
    } catch (err) {
        // Ignore storage failures.
    }
}

export function readStoredHistory(path) {
    if (!path) {
        return [];
    }
    try {
        const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || '{}');
        const list = stored[path] || [];
        return Array.isArray(list) ? list : [];
    } catch (err) {
        return [];
    }
}

export function writeStoredHistory(path, history) {
    if (!path) {
        return;
    }
    try {
        const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || '{}');
        stored[path] = history;
        localStorage.setItem(promptHistoryKey, JSON.stringify(stored));
    } catch (err) {
        // ignore
    }
}
