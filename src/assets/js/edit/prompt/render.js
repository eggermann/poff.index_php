import { escapeHtml } from '../../core/utils.js';

export function renderPromptHistory(container, history, streamState, options = {}) {
    if (!container) {
        return;
    }
    const { forceScroll = false } = options;
    const stickToBottom = (container.scrollHeight - container.clientHeight - container.scrollTop) < 24;
    if (!history || !history.length) {
        container.innerHTML = '<div class="small-note">No messages yet.</div>';
        return;
    }
    container.innerHTML = history.map((msg) => {
        const role = (msg.role || 'user').toLowerCase();
        const isStreaming = streamState && streamState.index === msg._index;
        const content = isStreaming ? streamState.text : msg.content;
        const safeContent = content || '';
        return `
            <div class="prompt-message prompt-message-${role}">
                <span class="prompt-message-role">${escapeHtml(role)}:</span>
                <span class="prompt-message-content">${escapeHtml(safeContent)}${isStreaming ? '<span class="stream-cursor"></span>' : ''}</span>
            </div>
        `;
    }).join('');
    if (forceScroll || stickToBottom) {
        container.scrollTop = container.scrollHeight;
    }
}

export function renderPromptSummary(summaryEl, content) {
    if (!summaryEl) {
        return;
    }
    const safeContent = content || 'Waiting for response...';
    const body = summaryEl.querySelector('.prompt-summary-body');
    if (!body) {
        summaryEl.innerHTML = `<div class="prompt-summary-title">Template summary</div><div class="prompt-summary-body">${escapeHtml(safeContent)}</div>`;
        return;
    }
    body.innerHTML = escapeHtml(safeContent);
}

export function buildPromptContext({ getActiveSelection, getConfig }) {
    const selection = typeof getActiveSelection === 'function' ? getActiveSelection() : { path: '', isFile: false };
    const config = typeof getConfig === 'function' ? (getConfig() || {}) : {};
    const path = selection?.path || '';
    const name = path ? path.split(/[\\/]/).pop() : '';
    const work = (config && typeof config === 'object' && config.work) ? config.work : {};
    const ellipsis = '\u2026';
    const workPreview = Object.entries(work || {}).slice(0, 6).map(([key, value]) => {
        if (typeof value === 'boolean') {
            return `${key}: ${value ? 'true' : 'false'}`;
        }
        if (value === null || value === undefined) {
            return `${key}: null`;
        }
        const str = String(value);
        return `${key}: ${str.length > 28 ? str.slice(0, 25) + ellipsis : str}`;
    }).join(', ');
    return { path, name, workPreview };
}

export function renderPromptContext(contextEl, context) {
    if (!contextEl) {
        return;
    }
    const path = context?.path || '';
    const name = context?.name || '';
    const workPreview = context?.workPreview || '';
    contextEl.innerHTML = `
        <div class="prompt-context-row"><strong>path</strong>: ${escapeHtml(path)}</div>
        <div class="prompt-context-row"><strong>name</strong>: ${escapeHtml(name)}</div>
        <div class="prompt-context-row"><strong>partials</strong>: ${escapeHtml('default-layout, works, work')}</div>
        ${workPreview ? `<div class="prompt-context-row"><strong>work.*</strong>: ${escapeHtml(workPreview)}</div>` : ''}
    `;
}
