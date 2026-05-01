import { escapeHtml } from '../../core/utils.js';
import { readPromptEditorDraft } from './draft.js';
import { summarizeSerializedHistory } from './history.js';
import { extractWorkFields, summarizeWorkFields } from '../work-fields.js';

function isExternalPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    if (!trimmed) {
        return false;
    }
    if (trimmed.startsWith('//')) {
        return true;
    }

    return /^[a-z][a-z0-9+.-]*:/i.test(trimmed);
}

function isCmsPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    if (!trimmed.startsWith('?')) {
        return false;
    }

    const params = new URLSearchParams(trimmed.replace(/^\?/, ''));
    return params.has('file') || params.has('path') || params.get('view') === '1';
}

function isSpecialPromptLink(value = '') {
    const trimmed = String(value || '').trim();
    return trimmed.startsWith('#') || isCmsPromptLink(trimmed) || isExternalPromptLink(trimmed);
}

function getPromptItemExplicitLink(item = {}) {
    const keys = ['pageLink', 'pageUrl', 'viewUrl', 'workUrl', 'viewerHref', 'linkUrl', 'link', 'url'];
    for (const key of keys) {
        const value = String(item?.[key] || '').trim();
        if (value) {
            return value;
        }
    }

    const rawPath = String(item?.path || item?.relativePath || '').trim();
    return isSpecialPromptLink(rawPath) ? rawPath : '';
}

function getPromptItemDisplayPath(folderBasePath = '', item = {}) {
    const rawPath = String(item?.path || item?.relativePath || '').trim();
    if (rawPath) {
        if (isCmsPromptLink(rawPath)) {
            const params = new URLSearchParams(rawPath.replace(/^\?/, ''));
            return params.get(params.has('file') ? 'file' : 'path') || '';
        }
        if (isSpecialPromptLink(rawPath)) {
            return rawPath;
        }
        return folderBasePath && !rawPath.startsWith(`${folderBasePath}/`) && rawPath !== folderBasePath
            ? `${folderBasePath}/${rawPath}`
            : rawPath;
    }

    const explicitLink = getPromptItemExplicitLink(item);
    if (isCmsPromptLink(explicitLink)) {
        const params = new URLSearchParams(explicitLink.replace(/^\?/, ''));
        return params.get(params.has('file') ? 'file' : 'path') || '';
    }
    if (explicitLink) {
        return explicitLink;
    }

    const fallbackName = String(item?.name || '').trim();
    if (!fallbackName) {
        return '';
    }
    return folderBasePath ? `${folderBasePath}/${fallbackName}` : fallbackName;
}

export function renderPromptHistory(container, history, streamState, options = {}) {
    if (!container) {
        return;
    }
    const { forceScroll = false } = options;
    const stickToBottom = (container.scrollHeight - container.clientHeight - container.scrollTop) < 24;
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
        const snapshot = msg?.templateSnapshot && typeof msg.templateSnapshot === 'object'
            ? msg.templateSnapshot
            : null;
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
    const isLayout = !!selection?.isLayout;
    const path = selection?.previewPath ?? selection?.path ?? '';
    const virtualPath = selection?.path || '';
    const name = path ? path.split(/[\\/]/).pop() : '';
    const isFile = isLayout
        ? !!selection?.layoutIsFile
        : (selection?.isFile ?? /\.[^\\/]+$/.test(path));
    const viewUrl = isFile
        ? `?view=1&file=${encodeURIComponent(path)}`
        : `?view=1&path=${encodeURIComponent(path)}`;
    const localLayoutDirectory = isFile
        ? `.works/${name || 'item'}.layout`
        : '.layout';
    const sectionTemplateTarget = isFile
        ? `${localLayoutDirectory}/work.hbs`
        : `${localLayoutDirectory}/works.hbs`;
    const layoutTemplateTarget = `${localLayoutDirectory}/template.hbs`;
    const work = (config && typeof config === 'object' && config.work) ? config.work : {};
    const layout = work && typeof work.layout === 'object' ? work.layout : {};
    const layoutStorage = typeof layout?.storage === 'string' ? String(layout.storage) : '';
    const resolvedLayoutDirectory = layout?.directory ? String(layout.directory) : '';
    const inheritedLayoutDirectory = layout?.inheritedDirectory ? String(layout.inheritedDirectory) : '';
    const presetEl = isLayout ? document.getElementById('edit-layout-preset') : null;
    const layoutPreset = isLayout && presetEl ? String(presetEl.value || '').trim() : '';
    const activeLayoutDirectory = (() => {
        if (!isLayout) {
            return resolvedLayoutDirectory || localLayoutDirectory;
        }
        if (layoutPreset === 'custom') {
            return localLayoutDirectory;
        }
        if (layoutStorage === 'filesystem' && resolvedLayoutDirectory) {
            return resolvedLayoutDirectory;
        }
        return localLayoutDirectory;
    })();
    const templateTarget = isLayout
        ? `${activeLayoutDirectory}/template.hbs`
        : sectionTemplateTarget;
    const tree = Array.isArray(config?.tree) ? config.tree : [];
    const folderBasePath = (selection?.isFile ? path.split('/').slice(0, -1).join('/') : path).replace(/^\/+|\/+$/g, '');
    const ellipsis = '\u2026';
    const workFields = extractWorkFields(work);
    const workPreview = Object.entries(work || {}).slice(0, 6).map(([key, value]) => {
        if (key === 'fields' && Array.isArray(value)) {
            const summary = summarizeWorkFields(value);
            return summary ? `fields: ${summary}` : `fields: ${value.length} item(s)`;
        }
        if (typeof value === 'boolean') {
            return `${key}: ${value ? 'true' : 'false'}`;
        }
        if (Array.isArray(value)) {
            return `${key}: [${value.length} item(s)]`;
        }
        if (value && typeof value === 'object') {
            const keys = Object.keys(value).slice(0, 4);
            return `${key}: {${keys.join(', ')}}`;
        }
        if (value === null || value === undefined) {
            return `${key}: null`;
        }
        const str = String(value);
        return `${key}: ${str.length > 28 ? str.slice(0, 25) + ellipsis : str}`;
    }).join(', ');
    const workFieldsPreview = summarizeWorkFields(workFields);
    const refPreview = tree.slice(0, 4).map((item) => {
        const itemName = item?.name || item?.path || '';
        if (!itemName) {
            return '';
        }
        const itemPath = getPromptItemDisplayPath(folderBasePath, item);
        const isItemFile = (item?.type || 'file') !== 'folder';
        const itemPageLink = getPromptItemExplicitLink(item) || (isItemFile
            ? `?view=1&file=${encodeURIComponent(itemPath)}`
            : `?view=1&path=${encodeURIComponent(itemPath)}`);
        const itemAssetUrl = getPromptItemExplicitLink(item) || (isItemFile
            ? itemPath.split('/').map((part) => encodeURIComponent(part)).join('/')
            : `?path=${encodeURIComponent(itemPath)}`);
        return `${itemName} -> pageLink: ${itemPageLink}, srcUrl: ${itemAssetUrl}`;
    }).filter(Boolean).join(' | ');
    const layoutBaseHref = activeLayoutDirectory;
    const layoutAssetsPreview = Array.isArray(layout?.assets)
        ? layout.assets.slice(0, 4).map((asset) => {
            const assetPath = asset?.path ? String(asset.path) : '';
            if (!assetPath) {
                return '';
            }
            return `${assetPath} -> ${layoutBaseHref}/${assetPath}`;
        }).filter(Boolean).join(' | ')
        : '';
    const editorDraft = readPromptEditorDraft(selection);
    return {
        path,
        virtualPath,
        isLayout,
        layoutPreset,
        name,
        pageLink: viewUrl,
        viewUrl,
        templateTarget,
        layoutTemplateTarget,
        sectionTemplateTarget,
        layoutBaseHref,
        inheritedLayoutDirectory,
        layoutAssetsPreview,
        editorDraft,
        workData: work,
        workFields,
        workFieldsPreview,
        workPreview,
        refPreview,
    };
}

export function renderPromptContext(contextEl, context) {
    if (!contextEl) {
        return;
    }
    const renderValue = (value = '', depth = 0) => {
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
    };
    const renderRow = (label, value) => `
        <div class="prompt-context-item">
            <div class="prompt-context-key">${escapeHtml(label)}</div>
            <div class="prompt-context-value">${renderValue(value)}</div>
        </div>
    `;
    const renderList = (label, values = []) => {
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
    };

    const path = context?.path || '';
    const virtualPath = context?.virtualPath || '';
    const layoutPreset = context?.layoutPreset || '';
    const name = context?.name || '';
    const pageLink = context?.pageLink || context?.viewUrl || '';
    const viewUrl = context?.viewUrl || '';
    const templateTarget = context?.templateTarget || '';
    const layoutTemplateTarget = context?.layoutTemplateTarget || '';
    const sectionTemplateTarget = context?.sectionTemplateTarget || '';
    const layoutBaseHref = context?.layoutBaseHref || '';
    const inheritedLayoutDirectory = context?.inheritedLayoutDirectory || '';
    const layoutAssetsPreview = context?.layoutAssetsPreview || '';
    const editorDraft = (context?.editorDraft && typeof context.editorDraft === 'object') ? context.editorDraft : null;
    const workData = (context?.workData && typeof context.workData === 'object') ? context.workData : {};
    const workFields = Array.isArray(context?.workFields) ? context.workFields : [];
    const workFieldsPreview = context?.workFieldsPreview || '';
    const refPreview = context?.refPreview || '';
    const partials = ['poff-layout', 'filesystem-layout', 'works', 'work'];
    const refItems = refPreview ? refPreview.split(' | ').filter(Boolean) : [];
    const layoutAssetItems = layoutAssetsPreview ? layoutAssetsPreview.split(' | ').filter(Boolean) : [];
    contextEl.innerHTML = `
        <div class="prompt-context-grid">
            ${context?.isLayout ? renderRow('virtualPath', virtualPath) : ''}
            ${context?.isLayout && layoutPreset ? renderRow('layoutPreset', layoutPreset) : ''}
            ${renderRow('pageLink', pageLink)}
            ${renderRow('path', path)}
            ${renderRow('name', name)}
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
