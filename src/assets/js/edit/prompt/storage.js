import { defaultPromptSettings, getDefaultSystemPrompt, promptHistoryKey, promptSettingsKey } from './constants.js';

export function loadPromptSettings() {
    try {
        const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || '{}');
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
        if (typeof stored.systemPrompt === 'string') {
            const looksLikeLegacyDefault = stored.systemPrompt.includes('saved to .layout/template.hbs')
                || stored.systemPrompt.includes('Built-in wrapper partials are {{> poff-layout}} and {{> filesystem-layout}}');
            if (looksLikeLegacyDefault) {
                stored.systemPrompt = defaultPromptSettings.systemPrompt;
            }
        }
        if (typeof stored.systemPromptWork !== 'string' || stored.systemPromptWork.trim() === '') {
            stored.systemPromptWork = getDefaultSystemPrompt('work');
        }
        if (typeof stored.systemPromptLayout !== 'string' || stored.systemPromptLayout.trim() === '') {
            stored.systemPromptLayout = getDefaultSystemPrompt('layout');
        }
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
