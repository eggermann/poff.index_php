export const promptSettingsKey = 'poffEditPromptSettings';
export const promptHistoryKey = 'poffEditPromptHistory';
export const allowedWorkKeys = [
    'type',
    'fit',
    'background',
    'caption',
    'autoplay',
    'loop',
    'muted',
    'poster',
    'url',
    'link',
    'layout',
    'model',
];
export const defaultSystemPrompt = [
    'You are a web component/template generator for this single-page CMS.',
    'Transform the user description into one HTML template string that will be saved to work.layout.template.',
    'Return only the template (no Markdown, no fences).',
    'Inputs available: {{path}}, {{name}}, {{linkUrl}}, and work.* values from config/work; booleans also expose {{keyAttr}} (e.g., autoplayAttr).',
    'Use config/title/description, layout mode/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
    'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
    'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
].join('\n');
export const defaultPromptSettings = {
    provider: 'openai',
    model: 'gpt-4o-mini',
    endpoint: '',
    apiKey: '',
    systemPrompt: defaultSystemPrompt,
    streamPreview: true,
};
