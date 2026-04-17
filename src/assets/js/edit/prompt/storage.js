import { defaultPromptSettings, promptHistoryKey, promptSettingsKey } from './constants.js';

export function loadPromptSettings() {
    try {
        const rawStored = JSON.parse(localStorage.getItem(promptSettingsKey) || '{}');
        const stored = {
            provider: rawStored.provider,
            model: rawStored.model,
            endpoint: rawStored.endpoint,
            apiKey: rawStored.apiKey,
            streamPreview: rawStored.streamPreview,
        };
        const looksLikeLegacyProviderDefault = (!stored.provider || stored.provider === 'openai')
            && (!stored.model || stored.model === 'gpt-4o-mini')
            && !stored.endpoint;
        if (looksLikeLegacyProviderDefault) {
            stored.provider = defaultPromptSettings.provider;
            stored.model = defaultPromptSettings.model;
            stored.endpoint = defaultPromptSettings.endpoint;
        }
        if ((stored.provider === 'local' || !stored.provider) && !stored.endpoint) {
            stored.endpoint = defaultPromptSettings.endpoint;
        }
        return { ...defaultPromptSettings, ...stored };
    } catch (err) {
        return defaultPromptSettings;
    }
}

export function savePromptSettings(settings) {
    try {
        const persisted = {
            provider: settings?.provider || defaultPromptSettings.provider,
            model: settings?.model || '',
            endpoint: settings?.endpoint || '',
            apiKey: settings?.apiKey || '',
            streamPreview: settings?.streamPreview !== false,
        };
        localStorage.setItem(promptSettingsKey, JSON.stringify(persisted));
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
