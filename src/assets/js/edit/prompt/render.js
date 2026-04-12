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
    const defaultLayoutDirectory = layout?.defaultDirectory ? String(layout.defaultDirectory) : '';
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
    const layoutBaseHref = activeLayoutDirectory;
    const layoutDefaultBaseHref = defaultLayoutDirectory || resolvedLayoutDirectory || layoutBaseHref;
    const layoutAssetsPreview = Array.isArray(layout?.assets)
        ? layout.assets.slice(0, 4).map((asset) => {
            const assetPath = asset?.path ? String(asset.path) : '';
            if (!assetPath) {
                return '';
            }
            return `${assetPath} -> ${layoutBaseHref}/${assetPath}`;
        }).filter(Boolean).join(' | ')
        : '';
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
        layoutDefaultBaseHref,
        layoutAssetsPreview,
        workPreview,
        refPreview,
    };
}

export function renderPromptContext(contextEl, context) {
    if (!contextEl) {
        return;
    }
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
    const layoutDefaultBaseHref = context?.layoutDefaultBaseHref || '';
    const layoutAssetsPreview = context?.layoutAssetsPreview || '';
    const workPreview = context?.workPreview || '';
    const refPreview = context?.refPreview || '';
    contextEl.innerHTML = `
        ${context?.isLayout ? `<div class="prompt-context-row"><strong>virtualPath</strong>: ${escapeHtml(virtualPath)}</div>` : ''}
        ${context?.isLayout && layoutPreset ? `<div class="prompt-context-row"><strong>layoutPreset</strong>: ${escapeHtml(layoutPreset)}</div>` : ''}
        <div class="prompt-context-row"><strong>pageLink</strong>: ${escapeHtml(pageLink)}</div>
        <div class="prompt-context-row"><strong>path</strong>: ${escapeHtml(path)}</div>
        <div class="prompt-context-row"><strong>name</strong>: ${escapeHtml(name)}</div>
        <div class="prompt-context-row"><strong>viewUrl</strong>: ${escapeHtml(viewUrl)}</div>
        ${templateTarget ? `<div class="prompt-context-row"><strong>templateTarget</strong>: ${escapeHtml(templateTarget)}</div>` : ''}
        ${layoutTemplateTarget ? `<div class="prompt-context-row"><strong>layoutTemplateTarget (custom)</strong>: ${escapeHtml(layoutTemplateTarget)}</div>` : ''}
        ${sectionTemplateTarget ? `<div class="prompt-context-row"><strong>sectionTemplateTarget</strong>: ${escapeHtml(sectionTemplateTarget)}</div>` : ''}
        ${layoutBaseHref ? `<div class="prompt-context-row"><strong>layoutBaseHref</strong>: ${escapeHtml(layoutBaseHref)}</div>` : ''}
        ${layoutDefaultBaseHref ? `<div class="prompt-context-row"><strong>layoutDefaultBaseHref</strong>: ${escapeHtml(layoutDefaultBaseHref)}</div>` : ''}
        <div class="prompt-context-row"><strong>partials</strong>: ${escapeHtml('default-layout, works, work')}</div>
        ${layoutAssetsPreview ? `<div class="prompt-context-row"><strong>layoutAssets</strong>: ${escapeHtml(layoutAssetsPreview)}</div>` : ''}
        ${refPreview ? `<div class="prompt-context-row"><strong>refs</strong>: ${escapeHtml(refPreview)}</div>` : ''}
        ${workPreview ? `<div class="prompt-context-row"><strong>work.*</strong>: ${escapeHtml(workPreview)}</div>` : ''}
    `;
}
