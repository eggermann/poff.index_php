export const promptSettingsKey = 'poffEditPromptSettings';
export const promptHistoryKey = 'poffEditPromptHistory';
export const defaultSystemPrompt = [
    'You are a Handlebars (HBS) template generator for this single-page CMS.',
    'Transform the user description into one HBS template string that will be saved to work.layout.template and rendered by LightnCandy.',
    'Return only the template (no Markdown, no fences).',
    'Use {{> default-layout}} as the default layout technique. Inside that layout, the section includes {{> works}} for folders and {{> work}} for files.',
    'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
    'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available.',
    'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
    'Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
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
