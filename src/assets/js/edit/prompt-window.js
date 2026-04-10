import { escapeHtml } from '../core/utils.js';

export function renderPromptWindow(settings = {}) {
    return `
        <div class="prompt-window prompt-inline" id="promptWindow">
            <div class="prompt-header">
                <div>
                    <h4 class="edit-panel-title">Prompt edit window</h4>
                    <div class="small-note">Chat + completion helper</div>
                </div>
            </div>
            <details class="prompt-system" open>
                <summary class="prompt-system-summary">Connection</summary>
                <div class="edit-grid prompt-grid">
                    <div>
                        <label class="edit-label" for="prompt-provider">Provider</label>
                        <select class="form-input" id="prompt-provider">
                            <option value="local">Local URL</option>
                            <option value="openai">OpenAI</option>
                            <option value="gemini">Gemini</option>
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
            <details class="prompt-system" open>
                <summary class="prompt-system-summary">System prompt (description &rarr; HBS component)</summary>
                <textarea class="form-textarea prompt-textarea" id="prompt-system" placeholder="Set the instruction your model should follow.">${escapeHtml(settings.systemPrompt || '')}</textarea>
                <div class="prompt-system-footer">
                    <span class="small-note">Used for chat + completions. Stored only in this browser.</span>
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
                <span class="prompt-dot"></span> Editable via prompt: <strong>title</strong>, <strong>description</strong>, <strong>work.*</strong>
            </div>
            <div class="prompt-messages" id="promptMessages"></div>
            <div class="prompt-context" id="promptContext">
                <div class="prompt-context-title">Placeholders</div>
                <div class="prompt-context-body">
                    <div>{{pageLink}}, {{pageUrl}}, {{workUrl}}, {{viewUrl}}, {{srcUrl}}, {{assetUrl}}, {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}</div>
                    <div><code>{{pageLink}}</code> is for navigation. <code>{{srcUrl}}</code> is for direct sources like <code>src=</code>, <code>poster</code>, downloads, and CSS <code>url(...)</code>.</div>
                    <div>Prompt edits the wrapped <code>{{> work}}</code> / <code>{{> works}}</code> partial. The outer layout wrapper stays active.</div>
                    <div>{{> default-layout}}, {{> works}}, {{> work}}, {{work.key}}, {{layout.sectionBaseHref}}</div>
                    <div>Theme shell: <code>.poff-default-layout</code> with <code>--poff-shell-*</code> CSS vars</div>
                </div>
            </div>
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
            <textarea class="prompt-input" id="prompt-input" placeholder="Describe the component you want..."></textarea>
            <div class="prompt-actions">
                <div class="prompt-actions-left">
                    <button class="btn" type="button" id="prompt-send">Send</button>
                    <button class="btn btn-secondary" type="button" id="prompt-attach">Attach image</button>
                    <button class="btn btn-secondary" type="button" id="prompt-clear">Clear</button>
                </div>
                <label class="prompt-inline-toggle">
                    <input class="prompt-inline-toggle-input" type="checkbox" id="prompt-stream" ${settings.streamPreview === false ? '' : 'checked'}>
                    Stream response
                </label>
            </div>
            <div class="small-note">Press <code>Enter</code> to send. Use <code>Shift+Enter</code> for a new line.</div>
            <div class="small-note">Paste an image from the clipboard directly into the prompt input to attach it.</div>
            <div class="small-note">Template responses are saved to the wrapped partial: <code>work.hbs</code> for files and <code>works.hbs</code> for folders. The current outer layout stays active.</div>
        </div>
    `;
}
