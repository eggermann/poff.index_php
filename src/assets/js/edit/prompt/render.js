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
    const isFile = selection?.isFile ?? /\.[^\\/]+$/.test(path);
    const viewUrl = isFile
        ? `?view=1&file=${encodeURIComponent(path)}`
        : `?view=1&path=${encodeURIComponent(path)}`;
    const templateTarget = isFile
        ? `.works/${name || 'item'}.layout/work.hbs`
        : '.layout/works.hbs';
    const layoutTemplateTarget = isFile
        ? `.works/${name || 'item'}.layout/template.hbs`
        : '.layout/template.hbs';
    const work = (config && typeof config === 'object' && config.work) ? config.work : {};
    const tree = Array.isArray(config?.tree) ? config.tree : [];
    const folderBasePath = (selection?.isFile ? path.split('/').slice(0, -1).join('/') : path).replace(/^\/+|\/+$/g, '');
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
    const refPreview = tree.slice(0, 4).map((item) => {
        const itemName = item?.name || item?.path || '';
        if (!itemName) {
            return '';
        }
        const rawItemPath = item?.path || itemName;
        const itemPath = folderBasePath
            ? (String(rawItemPath).startsWith(`${folderBasePath}/`) ? String(rawItemPath) : `${folderBasePath}/${rawItemPath}`)
            : String(rawItemPath);
        const isItemFile = (item?.type || 'file') !== 'folder';
        const itemPageLink = isItemFile
            ? `?view=1&file=${encodeURIComponent(itemPath)}`
            : `?view=1&path=${encodeURIComponent(itemPath)}`;
        const itemAssetUrl = isItemFile
            ? itemPath.split('/').map((part) => encodeURIComponent(part)).join('/')
            : `?path=${encodeURIComponent(itemPath)}`;
        return `${itemName} -> pageLink: ${itemPageLink}, srcUrl: ${itemAssetUrl}`;
    }).filter(Boolean).join(' | ');
    return { path, name, pageLink: viewUrl, viewUrl, templateTarget, layoutTemplateTarget, workPreview, refPreview };
}

export function renderPromptContext(contextEl, context) {
    if (!contextEl) {
        return;
    }
    const path = context?.path || '';
    const name = context?.name || '';
    const pageLink = context?.pageLink || context?.viewUrl || '';
    const viewUrl = context?.viewUrl || '';
    const templateTarget = context?.templateTarget || '';
    const layoutTemplateTarget = context?.layoutTemplateTarget || '';
    const workPreview = context?.workPreview || '';
    const refPreview = context?.refPreview || '';
    contextEl.innerHTML = `
        <div class="prompt-context-row"><strong>pageLink</strong>: ${escapeHtml(pageLink)}</div>
        <div class="prompt-context-row"><strong>path</strong>: ${escapeHtml(path)}</div>
        <div class="prompt-context-row"><strong>name</strong>: ${escapeHtml(name)}</div>
        <div class="prompt-context-row"><strong>viewUrl</strong>: ${escapeHtml(viewUrl)}</div>
        ${templateTarget ? `<div class="prompt-context-row"><strong>templateTarget</strong>: ${escapeHtml(templateTarget)}</div>` : ''}
        ${layoutTemplateTarget ? `<div class="prompt-context-row"><strong>layoutTemplateTarget</strong>: ${escapeHtml(layoutTemplateTarget)}</div>` : ''}
        <div class="prompt-context-row"><strong>partials</strong>: ${escapeHtml('default-layout, works, work')}</div>
        ${refPreview ? `<div class="prompt-context-row"><strong>refs</strong>: ${escapeHtml(refPreview)}</div>` : ''}
        ${workPreview ? `<div class="prompt-context-row"><strong>work.*</strong>: ${escapeHtml(workPreview)}</div>` : ''}
    `;
}
