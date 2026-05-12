import { escapeHtml } from '../core/utils.js';
import { defaultFileSystemPrompt, defaultFolderSystemPrompt, defaultLayoutSystemPrompt } from './prompt/constants.js';

function resolvePromptWindowMode(mode = '') {
    if (mode === 'layout') {
        return 'layout';
    }
    if (mode === 'folder') {
        return 'folder';
    }
    return 'file';
}

function buildPromptWindowModeConfig(mode, settings, sectionTarget) {
    const isLayout = mode === 'layout';
    const isFolder = mode === 'folder';
    const currentSystemPrompt = isLayout
        ? (settings.systemPromptLayout || settings.systemPrompt || defaultLayoutSystemPrompt)
        : isFolder
            ? (settings.systemPromptFolder || settings.systemPrompt || defaultFolderSystemPrompt)
            : (settings.systemPromptFile || settings.systemPrompt || defaultFileSystemPrompt);

    const promptTargetCopy = isLayout
        ? 'Prompt edits the outer layout wrapper target for this virtual .layout page.'
        : isFolder
            ? 'Prompt edits the wrapped works.hbs partial for the current folder.'
            : 'Prompt edits the wrapped work.hbs partial for the current file.';

    const footerCopy = isLayout
        ? `Template responses are saved to the current active layout wrapper target shown in Prompt context. The wrapped inner partial stays separate at <code>${escapeHtml(sectionTarget || 'work.hbs')}</code>.`
        : isFolder
            ? 'Template responses are saved to the wrapped partial: <code>works.hbs</code> for folders.'
            : 'Template responses are saved to the wrapped partial: <code>work.hbs</code> for files.';

    const insertNameLabel = isLayout
        ? 'Insert layout name'
        : isFolder
            ? 'Insert item name'
            : 'Insert file name';

    const contextCopy = isLayout
        ? `<div>Prompt edits the outer layout wrapper. <code>root.*</code> is the shell-level layout data and <code>work.*</code> is the inner item data. Use <code>root.title</code> for the wrapper title and <code>work.title</code> for the item title.</div><div><code>current.templateTarget</code> is the active wrapper target. <code>current.layoutTemplateTarget</code> is the local custom wrapper path if you switch to <code>Custom</code>. <code>current.sectionTemplateTarget</code> is the advanced inner partial.</div><div>For wrapper-owned images/assets, do not use <code>{{path}}</code>. Use <code>{{layout.baseHref}}</code> in the HBS and use <code>current.layoutBaseHref</code> plus <code>current.inheritedLayoutDirectory</code> in the prompt context to understand whether the wrapper came from a parent folder.</div>`
        : isFolder
            ? '<div>Prompt edits the wrapped <code>{{> works}}</code> partial and can use folder tree data, helper lists, and item refs.</div>'
            : '<div>Prompt edits the wrapped <code>{{> work}}</code> partial for one file view.</div>';

    const editableCopy = isLayout
        ? '<span class="prompt-dot"></span> Editable via prompt: <strong>layout.template</strong>, optional <strong>work.&lt;name&gt;</strong>'
        : '<span class="prompt-dot"></span> Editable via prompt: <strong>title</strong>, <strong>description</strong>, <strong>work.&lt;name&gt;</strong>';

    const placeholderCopy = isLayout
        ? `<div>{{pageLink}}, {{pageUrl}}, {{workUrl}}, {{viewUrl}}, {{srcUrl}}, {{assetUrl}}, {{path}}, {{name}}, {{title}}, {{root.title}}, {{root.folderName}}, {{work.title}}, {{work.name}}, {{linkUrl}}, {{slug}}</div>
                        <div><code>{{pageLink}}</code> is for navigation. <code>{{srcUrl}}</code> is for direct sources like <code>src=</code>, <code>poster</code>, downloads, and CSS <code>url(...)</code>.</div>
                        <div>{{> poff-layout}}, {{> filesystem-layout}}, {{> works}}, {{> work}}, {{work.key}}, {{layout.baseHref}}, {{layout.sectionBaseHref}}</div>
                        <div>Example context JSON: <code>{"root":{"title":"dominikeggermann.com"},"work":{"title":"tests"}}</code></div>
                        <div>Theme shell: <code>.poff-default-layout</code> with <code>--poff-shell-*</code> CSS vars</div>`
        : isFolder
            ? `<div>{{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, {{pageLink}}, {{srcUrl}}, {{assetUrl}}</div>
                        <div>{{> works}}, {{work.key}}, tree/items, workTree, allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, allOther</div>
                        <div><code>work.templateMap</code> is the inherited MIME => template defaults. <code>work.template</code> is the exact override for the current item.</div>
                        <div>Parent/sibling prompt refs: current.parentWork, siblingWorks, siblingImages, siblingVideos, siblingLinks. Sibling refs are same-folder only.</div>`
            : `<div>{{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}</div>
                        <div>{{> work}}, {{work.key}}, layout.*, current.parentWork, siblingWorks, siblingImages, siblingVideos, siblingLinks</div>
                        <div><code>work.templateMap</code> is the inherited MIME => template defaults. <code>work.template</code> is the exact override for the current item.</div>`;

    const inputPlaceholder = isLayout
        ? 'Describe the layout you want...'
        : isFolder
            ? 'Describe the folder component you want...'
            : 'Describe the file component you want...';

    return {
        systemPrompt: currentSystemPrompt,
        promptTargetCopy,
        footerCopy,
        insertNameLabel,
        contextCopy,
        editableCopy,
        placeholderCopy,
        inputPlaceholder,
    };
}

export function renderPromptWindow(settings = {}, options = {}) {
    const mode = resolvePromptWindowMode(options.mode);
    const {
        systemPrompt,
        promptTargetCopy,
        footerCopy,
        insertNameLabel,
        contextCopy,
        editableCopy,
        placeholderCopy,
        inputPlaceholder,
    } = buildPromptWindowModeConfig(mode, settings, options.sectionTarget || 'work.hbs');
    const provider = ['openai', 'gemini'].includes(settings.provider) ? settings.provider : 'local';

    return `
        <div class="prompt-layer" id="promptLayer">
            <button class="prompt-layer-toggle prompt-layer-toggle-close" type="button" id="promptLayerClose" aria-label="Hide prompt window" title="Hide prompt window">&times;</button>
            <button class="prompt-layer-toggle prompt-layer-toggle-open" type="button" id="promptLayerOpen" aria-label="Show prompt window" title="Show prompt window" hidden>poff</button>
            <div class="prompt-window prompt-inline" id="promptWindow">
                <div class="prompt-header">
                    <div>
                        <h4 class="edit-panel-title">Prompt edit window</h4>
                        <div class="small-note">Chat + completion helper</div>
                    </div>
                </div>
                <details class="prompt-system">
                    <summary class="prompt-system-summary">Connection</summary>
                    <div class="edit-grid prompt-grid">
                        <div>
                            <label class="edit-label" for="prompt-provider">Provider</label>
                            <select class="form-input" id="prompt-provider">
                                <option value="local" ${provider === 'local' ? 'selected' : ''}>LM Studio</option>
                                <option value="openai" ${provider === 'openai' ? 'selected' : ''}>OpenAI</option>
                                <option value="gemini" ${provider === 'gemini' ? 'selected' : ''}>Gemini</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label" for="prompt-model">Model</label>
                            <input class="form-input" id="prompt-model" type="text" value="${escapeHtml(settings.model || '')}" placeholder="optional">
                        </div>
                        <div>
                            <label class="edit-label" for="prompt-api-key">API key (stored in localStorage)</label>
                            <input class="form-input" id="prompt-api-key" type="password" value="${escapeHtml(settings.apiKey || '')}">
                        </div>
                    </div>
                    <div class="prompt-settings-actions">
                        <button class="btn btn-secondary" type="button" id="prompt-settings-reset">Reset settings</button>
                    </div>
                    <div id="prompt-endpoint-row">
                        <label class="edit-label" for="prompt-endpoint">Local endpoint URL</label>
                        <input class="form-input" id="prompt-endpoint" type="text" value="${escapeHtml(settings.endpoint || '')}" placeholder="http://localhost:1234/generate">
                    </div>
                </details>
                <details class="prompt-system">
                    <summary class="prompt-system-summary">System prompt (description &rarr; HBS component)</summary>
                    <textarea class="form-textarea prompt-textarea" id="prompt-system" placeholder="Set the instruction your model should follow.">${escapeHtml(systemPrompt)}</textarea>
                    <div class="prompt-system-footer">
                        <span class="small-note">Used for chat + completions. Not saved across reloads.</span>
                        <button class="btn btn-secondary" type="button" id="prompt-system-reset">Reset default</button>
                    </div>
                </details>
                <div class="prompt-summary" id="promptSummary">
                    <div class="prompt-summary-title">Template summary</div>
                    <div class="prompt-summary-body">Waiting for response...</div>
                </div>
                <div class="prompt-generation" id="promptGeneration" hidden>
                    <span class="prompt-generation-pulse" aria-hidden="true"></span>
                    <span class="prompt-generation-label" id="promptGenerationLabel">Generating answer...</span>
                </div>
                <div class="prompt-allowed">
                    ${editableCopy}
                </div>
                <details class="prompt-section prompt-section-context">
                    <summary>Prompt context</summary>
                    <div class="prompt-context" id="promptContext">
                        <div class="prompt-context-title">Placeholders</div>
                        <div class="prompt-context-body">
                        ${placeholderCopy}
                        ${contextCopy}
                    </div>
                    </div>
                </details>
                <details class="prompt-template-viewer" id="promptTemplateViewer">
                    <summary class="prompt-template-viewer-summary">Current template code</summary>
                    <div class="prompt-template-viewer-body">
                        <div class="prompt-template-viewer-head">
                            <div class="small-note" id="promptTemplateLabel">Current target template</div>
                            <button class="btn btn-secondary" type="button" id="prompt-template-reset">Reset to default template</button>
                        </div>
                        <textarea class="form-textarea prompt-template-code" id="promptTemplateCode" readonly spellcheck="false" placeholder="No template loaded yet."></textarea>
                    </div>
                </details>
                <details class="prompt-section prompt-section-messages">
                    <summary>Messages</summary>
                    <div class="prompt-messages" id="promptMessages"></div>
                </details>
                <input id="prompt-image-input" type="file" accept="image/*" hidden>
                <div class="prompt-attachment" id="promptAttachment" hidden>
                    <div class="prompt-attachment-preview-wrap">
                        <img class="prompt-attachment-preview" id="promptAttachmentPreview" alt="Prompt attachment preview">
                    </div>
                    <div class="prompt-attachment-meta">
                        <div class="prompt-attachment-name" id="promptAttachmentName">Image attached</div>
                        <div class="small-note">Clipboard paste and image uploads are supported.</div>
                    </div>
                    <button class="btn btn-secondary" type="button" id="prompt-attachment-remove">Remove image</button>
                </div>
                <textarea class="prompt-input" id="prompt-input" placeholder="${escapeHtml(inputPlaceholder)}"></textarea>
                <div class="prompt-actions">
                    <div class="prompt-actions-left">
                        <button class="btn" type="button" id="prompt-send">Send</button>
                        <button class="btn btn-secondary" type="button" id="prompt-attach">Attach image</button>
                        <button class="btn btn-secondary" type="button" id="prompt-insert-name">${escapeHtml(insertNameLabel)}</button>
                        <button class="btn btn-secondary" type="button" id="prompt-clear">Clear</button>
                    </div>
                    <label class="prompt-inline-toggle">
                        <input class="prompt-inline-toggle-input" type="checkbox" id="prompt-stream" ${settings.streamPreview === false ? '' : 'checked'}>
                        Stream response
                    </label>
                </div>
                <div class="small-note">Press <code>Enter</code> to send. Use <code>Shift+Enter</code> for a new line.</div>
                <div class="small-note">Paste an image from the clipboard directly into the prompt input to attach it.</div>
                <div class="small-note">${promptTargetCopy}</div>
                <div class="small-note">${footerCopy}</div>
            </div>
        </div>
    `;
}
