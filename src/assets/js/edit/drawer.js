import { escapeHtml, getLayoutState } from '../core/utils.js';

export function renderEditDrawer({
    editDrawer,
    editRequested,
    config,
    status,
    onClose,
    onSubmit,
}) {
    if (!editDrawer) {
        return { drawerForm: null, drawerStatus: null };
    }
    if (!editRequested) {
        editDrawer.hidden = true;
        editDrawer.classList.remove('edit-drawer-open');
        return { drawerForm: null, drawerStatus: null };
    }
    if (!config || status?.error) {
        editDrawer.innerHTML = '';
        return { drawerForm: null, drawerStatus: null };
    }
    if (!status?.allowed) {
        editDrawer.innerHTML = '';
        return { drawerForm: null, drawerStatus: null };
    }

    const layoutState = getLayoutState(config);
    const layoutDirectory = layoutState.directory || '.layout';
    const sectionName = layoutState.section || (status?.target === 'file' ? 'work' : 'works');
    const sectionFileName = `${sectionName}.hbs`;
    const localContentDirectory = status?.target === 'file'
        ? `.works/${config.name || config.path || 'item'}.layout`
        : '.layout';
    const contentTemplatePath = `${localContentDirectory}/${sectionFileName}`;
    const layoutAssets = Array.isArray(layoutState.assets) ? layoutState.assets : [];
    const layoutPresetOptions = [
        { value: 'actual', label: 'Actual' },
        { value: 'none', label: 'None' },
        { value: 'custom', label: 'Custom' },
    ];
    let treeHtml = '';
    if (status?.target !== 'file') {
        const treeItems = Array.isArray(config.tree) ? config.tree : [];
        treeHtml = treeItems.length
            ? treeItems.map((item) => {
                const label = escapeHtml(item.name || '');
                const key = escapeHtml(item.path || item.name || '');
                const visible = item.visible !== false ? 'checked' : '';
                const type = escapeHtml(item.type || 'item');
                return `
                    <label class="edit-tree-item">
                        <input type="checkbox" name="tree_visible" value="${key}" ${visible}>
                        <span>${label} <span class="opacity-60">(${type})</span></span>
                    </label>
                `;
            }).join('')
            : '<div class="edit-tree-item">No items found.</div>';
    }
    const layoutAssetsHtml = layoutAssets.length
        ? `
            <div>
                <label class="edit-label">Layout assets</label>
                <div class="edit-tree">
                    ${layoutAssets.map((asset) => `
                        <div class="edit-tree-item">
                            <span>${escapeHtml(asset.path || asset.name || '')}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `
        : '';

    editDrawer.innerHTML = `
        <div class="drawer-header">
            <h4 class="drawer-title">More settings</h4>
            <button type="button" class="drawer-close" id="editDrawerClose">&times;</button>
        </div>
        <div class="edit-status" id="editDrawerStatus"></div>
        <form id="editDrawerForm" class="edit-form">
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-link">Link</label>
                    <input class="form-input" id="edit-link" type="text" name="link" value="${escapeHtml(config.link || '')}">
                </div>
                <div>
                    <label class="edit-label" for="edit-url">URL</label>
                    <input class="form-input" id="edit-url" type="text" name="url" value="${escapeHtml(config.url || '')}">
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work Type</label>
                    <input class="form-input" id="edit-work-type" type="text" name="work_type" value="${escapeHtml((config.work || {}).type || '')}">
                </div>
                <div>
                    <label class="edit-label" for="edit-layout-preset">Layout preset</label>
                    <select class="form-select" id="edit-layout-preset" name="layout_preset">
                        ${layoutPresetOptions.map((option) => `
                            <option value="${option.value}" ${layoutState.preset === option.value ? 'selected' : ''}>${option.label}</option>
                        `).join('')}
                    </select>
                </div>
            </div>
            <div class="small-note">Actual layout: <code>${escapeHtml(layoutState.mode)}</code> · ${escapeHtml(layoutState.sourceLabel)}</div>
            <input type="hidden" id="edit-work-layout" name="work_layout" value="${escapeHtml(layoutState.mode)}">
            <div>
                <label class="edit-label" for="edit-content-template">Wrapped content template (HBS)</label>
                <textarea class="form-textarea" id="edit-content-template" name="content_template">${escapeHtml(layoutState.sectionTemplate)}</textarea>
            </div>
            <div class="small-note">Prompt/content edits save to <code>${escapeHtml(contentTemplatePath)}</code>. This partial is wrapped by the active layout chain.</div>
            <div>
                <label class="edit-label" for="edit-layout-template">Outer layout wrapper (HBS)</label>
                <textarea class="form-textarea" id="edit-layout-template" name="layout_template">${escapeHtml(layoutState.template)}</textarea>
            </div>
            <div class="small-note">Wrapper layout files live in <code>${escapeHtml(layoutDirectory)}</code>. Put shared layout assets there.</div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-css">Layout CSS</label>
                    <textarea class="form-textarea" id="edit-layout-css" name="layout_css">${escapeHtml(layoutState.css)}</textarea>
                </div>
                <div>
                    <label class="edit-label" for="edit-layout-js">Layout JS</label>
                    <textarea class="form-textarea" id="edit-layout-js" name="layout_js">${escapeHtml(layoutState.js)}</textarea>
                </div>
            </div>
            ${layoutAssetsHtml}
            ${status?.target !== 'file' ? `
            <div>
                <label class="edit-label">Visible items</label>
                <div class="edit-tree">${treeHtml}</div>
            </div>
            ` : ''}
            <div class="edit-actions">
                <button class="btn" type="submit">Save advanced</button>
            </div>
        </form>
    `;

    const drawerClose = editDrawer.querySelector('#editDrawerClose');
    if (drawerClose && typeof onClose === 'function') {
        drawerClose.addEventListener('click', () => onClose());
    }

    const drawerStatus = editDrawer.querySelector('#editDrawerStatus');
    const drawerForm = editDrawer.querySelector('#editDrawerForm');
    const layoutPresetEl = editDrawer.querySelector('#edit-layout-preset');
    const layoutNameEl = editDrawer.querySelector('#edit-work-layout');
    const contentTemplateEl = editDrawer.querySelector('#edit-content-template');
    const layoutTemplateEl = editDrawer.querySelector('#edit-layout-template');
    const cssEl = editDrawer.querySelector('#edit-layout-css');
    const jsEl = editDrawer.querySelector('#edit-layout-js');
    const currentLayoutMode = layoutState.mode || 'default-layout';
    const currentSectionTemplate = layoutState.sectionTemplate || '';
    const currentTemplate = layoutState.template || '';
    const currentCss = layoutState.css || '';
    const currentJs = layoutState.js || '';

    if (layoutPresetEl && layoutNameEl) {
        const syncLayoutPreset = () => {
            if (layoutPresetEl.value === 'none') {
                layoutNameEl.value = 'none';
                return;
            }
            if (layoutPresetEl.value === 'custom') {
                layoutNameEl.value = currentLayoutMode === 'none' ? 'custom-layout' : currentLayoutMode;
                if (contentTemplateEl && !contentTemplateEl.value.trim()) {
                    contentTemplateEl.value = currentSectionTemplate;
                }
                if (layoutTemplateEl && !layoutTemplateEl.value.trim()) {
                    layoutTemplateEl.value = currentTemplate;
                }
                if (cssEl && !cssEl.value.trim()) {
                    cssEl.value = currentCss;
                }
                if (jsEl && !jsEl.value.trim()) {
                    jsEl.value = currentJs;
                }
                return;
            }

            layoutNameEl.value = 'default-layout';
            if (layoutState.preset === 'custom') {
                if (layoutTemplateEl) layoutTemplateEl.value = '';
                if (cssEl) cssEl.value = '';
                if (jsEl) jsEl.value = '';
            }
        };
        layoutPresetEl.addEventListener('change', syncLayoutPreset);
        syncLayoutPreset();
    }
    if (drawerForm && drawerStatus && typeof onSubmit === 'function') {
        drawerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const treeVisible = status?.target !== 'file'
                ? Array.from(editDrawer.querySelectorAll('input[name="tree_visible"]:checked'))
                    .map((input) => input.value)
                : [];
            onSubmit({
                elements: drawerForm.elements,
                statusEl: drawerStatus,
                treeVisible,
            });
        });
    }

    return { drawerForm, drawerStatus };
}
