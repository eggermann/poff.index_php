import { escapeHtml } from '../../../core/utils.js';

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
