import sharedWorkPrompt from './shared-work-prompt.json';

export const promptSettingsKey = 'poffEditPromptSettings';
export const promptHistoryKey = 'poffEditPromptHistory';
export const defaultLocalPromptEndpoint = 'http://127.0.0.1:1234/v1/chat/completions';

export function getDefaultModelForProvider(provider = 'local') {
    if (provider === 'openai') {
        return 'gpt-4o-mini';
    }
    if (provider === 'gemini') {
        return 'gemini-1.5-flash';
    }
    return 'gemma4';
}

export const sharedWorkSystemPromptLead = sharedWorkPrompt.lead;

export const sharedWorkSystemPrompt = [
    'You are a Handlebars (HBS) template generator for this single-page CMS.',
    sharedWorkSystemPromptLead,
    ...sharedWorkPrompt.lines,
].join('\n');

export const defaultFileSystemPrompt = [
    sharedWorkSystemPrompt,
    ...sharedWorkPrompt.fileLines,
].join('\n');

export const defaultFolderSystemPrompt = [
    sharedWorkSystemPrompt,
    ...sharedWorkPrompt.folderLines,
].join('\n');

export const defaultLayoutSystemPrompt = [
    'You are a Handlebars (HBS) layout generator for this single-page CMS.',
    'Transform the user description into an updated outer layout wrapper rendered by LightnCandy.',
    ...sharedWorkPrompt.layoutLines,
].join('\n');
export const defaultPromptSettings = {
    provider: 'local',
    model: getDefaultModelForProvider('local'),
    endpoint: defaultLocalPromptEndpoint,
    apiKey: '',
    systemPrompt: defaultFileSystemPrompt,
    systemPromptFile: defaultFileSystemPrompt,
    systemPromptFolder: defaultFolderSystemPrompt,
    systemPromptLayout: defaultLayoutSystemPrompt,
    streamPreview: true,
};
