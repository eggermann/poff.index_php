export function getPromptMode(selection = null) {
    if (selection?.isLayout) {
        return 'layout';
    }
    return selection?.previewIsFile ? 'file' : 'folder';
}

export function getDefaultSystemPromptForMode(mode, prompts = {}) {
    if (mode === 'layout') {
        return prompts.layout || '';
    }
    if (mode === 'folder') {
        return prompts.folder || '';
    }
    return prompts.file || '';
}

export function getSystemPromptSettingKeyForMode(mode) {
    if (mode === 'layout') {
        return 'systemPromptLayout';
    }
    if (mode === 'folder') {
        return 'systemPromptFolder';
    }
    return 'systemPromptFile';
}

export function getPromptPlaceholderForMode(mode, defaultPlaceholder = 'Describe the component you want...') {
    if (mode === 'layout') {
        return 'Describe the layout you want...';
    }
    if (mode === 'folder') {
        return 'Describe the folder component you want...';
    }
    return defaultPlaceholder;
}
