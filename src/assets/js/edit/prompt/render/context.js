import { escapeHtml } from '../../../core/utils.js';

function renderValue(value = '', depth = 0) {
    if (value === null || value === undefined || value === '') {
        return '<code class="prompt-context-code">-</code>';
    }
    if (Array.isArray(value)) {
        const filtered = value.filter((item) => item !== null && item !== undefined && item !== '');
        if (!filtered.length) {
            return '<code class="prompt-context-code">[]</code>';
        }
        return `
            <div class="prompt-context-list prompt-context-list--nested">
                ${filtered.map((item) => `<div class="prompt-context-list-item">${renderValue(item, depth + 1)}</div>`).join('')}
            </div>
        `;
    }
    if (typeof value === 'object') {
        const entries = Object.entries(value).filter(([, entryValue]) => entryValue !== undefined);
        if (!entries.length) {
            return '<code class="prompt-context-code">{}</code>';
        }
        return `
            <div class="prompt-context-object${depth > 0 ? ' prompt-context-object--nested' : ''}">
                ${entries.map(([entryKey, entryValue]) => `
                    <div class="prompt-context-object-row">
                        <div class="prompt-context-object-key">${escapeHtml(entryKey)}</div>
                        <div class="prompt-context-object-value">${renderValue(entryValue, depth + 1)}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    return `<code class="prompt-context-code">${escapeHtml(String(value))}</code>`;
}

function renderRow(label, value, className = '') {
    return `
        <div class="prompt-context-item${className ? ` ${className}` : ''}">
            <div class="prompt-context-key">${escapeHtml(label)}</div>
            <div class="prompt-context-value">${renderValue(value)}</div>
        </div>
    `;
}

function renderList(label, values = []) {
    const filtered = Array.isArray(values) ? values.filter(Boolean) : [];
    if (!filtered.length) {
        return '';
    }
    return `
        <div class="prompt-context-item">
            <div class="prompt-context-key">${escapeHtml(label)}</div>
            <div class="prompt-context-value">
                <div class="prompt-context-list">
                    ${filtered.map((value) => `<div class="prompt-context-list-item">${renderValue(value)}</div>`).join('')}
                </div>
            </div>
        </div>
    `;
}

export function renderPromptContext(contextEl, context) {
    if (!contextEl) {
        return;
    }
    const path = context?.path || '';
    const virtualPath = context?.virtualPath || '';
    const layoutPreset = context?.layoutPreset || '';
    const layoutSharedName = context?.layoutSharedName || '';
    const name = context?.name || '';
    const pageLink = context?.pageLink || context?.viewUrl || '';
    const viewUrl = context?.viewUrl || '';
    const templateTarget = context?.templateTarget || '';
    const layoutTemplateTarget = context?.layoutTemplateTarget || '';
    const sectionTemplateTarget = context?.sectionTemplateTarget || '';
    const layoutBaseHref = context?.layoutBaseHref || '';
    const inheritedLayoutDirectory = context?.inheritedLayoutDirectory || '';
    const layoutAssetsPreview = context?.layoutAssetsPreview || '';
    const editorDraft = context?.editorDraft && typeof context.editorDraft === 'object' ? context.editorDraft : null;
    const rootData = context?.root && typeof context.root === 'object' ? context.root : {};
    const workData = context?.work && typeof context.work === 'object' ? context.work : (context?.workData && typeof context.workData === 'object' ? context.workData : {});
    const workFields = Array.isArray(context?.workFields) ? context.workFields : [];
    const workFieldsPreview = context?.workFieldsPreview || '';
    const refPreview = context?.refPreview || '';
    const partials = ['poff-layout', 'filesystem-layout', 'works', 'work'];
    const refItems = refPreview ? refPreview.split(' | ').filter(Boolean) : [];
    const layoutAssetItems = layoutAssetsPreview ? layoutAssetsPreview.split(' | ').filter(Boolean) : [];

    contextEl.innerHTML = `
        <div class="prompt-context-grid">
            ${Object.keys(rootData).length ? renderRow('root', rootData, 'prompt-context-item--accent') : ''}
            ${Object.keys(workData).length ? renderRow('work', workData, 'prompt-context-item--accent') : ''}
            ${context?.isLayout ? renderRow('virtualPath', virtualPath) : ''}
            ${context?.isLayout && layoutPreset ? renderRow('layoutPreset', layoutPreset) : ''}
            ${context?.isLayout && layoutSharedName ? renderRow('layoutSharedName', layoutSharedName) : ''}
            ${renderRow('pageLink', pageLink)}
            ${renderRow('path', path)}
            ${renderRow('name', name)}
            ${context?.title ? renderRow('title', context.title) : ''}
            ${renderRow('viewUrl', viewUrl)}
            ${templateTarget ? renderRow('templateTarget', templateTarget) : ''}
            ${layoutTemplateTarget ? renderRow('layoutTemplateTarget', layoutTemplateTarget) : ''}
            ${sectionTemplateTarget ? renderRow('sectionTemplateTarget', sectionTemplateTarget) : ''}
            ${layoutBaseHref ? renderRow('layoutBaseHref', layoutBaseHref) : ''}
            ${inheritedLayoutDirectory ? renderRow('inheritedLayoutDirectory', inheritedLayoutDirectory) : ''}
            ${editorDraft ? renderRow('editorDraft', editorDraft) : ''}
        </div>
        ${renderList('partials', partials)}
        ${renderList('layoutAssets', layoutAssetItems)}
        ${renderList('refs', refItems)}
        ${workFieldsPreview ? renderRow('work.fields', workFields) : ''}
        ${Object.keys(workData).length ? renderRow('work.*', workData) : ''}
    `;
}
