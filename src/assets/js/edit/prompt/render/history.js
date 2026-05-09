import { escapeHtml } from '../../../core/utils.js';
import { summarizeSerializedHistory } from '../history.js';

export function renderPromptHistory(container, history, streamState, options = {}) {
    if (!container) {
        return;
    }
    const { forceScroll = false } = options;
    const stickToBottom = container.scrollHeight - container.clientHeight - container.scrollTop < 24;
    const historyStats = summarizeSerializedHistory(history);
    if (!history || !history.length) {
        container.innerHTML = '<div class="small-note">History payload: 0 messages, 0 chars.</div><div class="small-note">No messages yet.</div>';
        return;
    }
    container.innerHTML = history.map((msg) => {
        const role = (msg.role || 'user').toLowerCase();
        const isStreaming = streamState && streamState.index === msg._index;
        const content = isStreaming ? streamState.text : msg.content;
        const safeContent = content || '';
        const snapshot = msg?.templateSnapshot && typeof msg.templateSnapshot === 'object' ? msg.templateSnapshot : null;
        const snapshotParts = [];
        if (snapshot) {
            if (typeof snapshot.targetType === 'string' && snapshot.targetType) {
                snapshotParts.push(`target: ${snapshot.targetType}`);
            }
            if (typeof snapshot.templateLength === 'number' && snapshot.templateLength > 0) {
                snapshotParts.push(`template: ${snapshot.templateLength} chars`);
            }
            if (Array.isArray(snapshot.workFieldNames) && snapshot.workFieldNames.length) {
                snapshotParts.push(`fields: ${snapshot.workFieldNames.join(', ')}`);
            }
            if (typeof snapshot.cssLength === 'number' && snapshot.cssLength > 0) {
                snapshotParts.push(`css: ${snapshot.cssLength}`);
            }
            if (typeof snapshot.jsLength === 'number' && snapshot.jsLength > 0) {
                snapshotParts.push(`js: ${snapshot.jsLength}`);
            }
        }
        return `
            <div class="prompt-message prompt-message-${role}">
                <span class="prompt-message-role">${escapeHtml(role)}:</span>
                <span class="prompt-message-content">${escapeHtml(safeContent)}${isStreaming ? '<span class="stream-cursor"></span>' : ''}</span>
                ${snapshotParts.length ? `<div class="small-note prompt-message-meta">${escapeHtml(snapshotParts.join(' | '))}</div>` : ''}
            </div>
        `;
    }).join('');
    container.innerHTML = `<div class="small-note">History payload: ${historyStats.count} messages, ${historyStats.chars} chars.</div>${container.innerHTML}`;
    if (forceScroll || stickToBottom) {
        container.scrollTop = container.scrollHeight;
    }
}
