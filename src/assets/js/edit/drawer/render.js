import { escapeHtml } from '../../core/utils.js';

function groupWorktypeChoices(choices = []) {
    return Array.isArray(choices)
        ? choices.reduce((groups, choice) => {
            const group = String(choice?.kind || 'other').trim() || 'other';
            if (!groups[group]) {
                groups[group] = [];
            }
            groups[group].push(choice);
            return groups;
        }, {})
        : {};
}

function renderGroupedSelectOptions(choices = [], selectedValue = '', includeInherit = false) {
    const groups = groupWorktypeChoices(choices);
    const groupEntries = Object.entries(groups);
    if (!groupEntries.length) {
        return includeInherit ? `<option value="" ${selectedValue === '' ? 'selected' : ''}>Inherit default</option>` : '';
    }

    const inheritOption = includeInherit
        ? `<option value="" ${selectedValue === '' ? 'selected' : ''}>Inherit default</option>`
        : '';

    return `
        ${inheritOption}
        ${groupEntries.map(([group, groupChoices]) => `
            <optgroup label="${escapeHtml(group)}">
                ${groupChoices.map((choice) => `
                    <option value="${escapeHtml(choice.value || '')}" data-kind="${escapeHtml(choice.kind || group)}" ${String(choice.value || '') === selectedValue ? 'selected' : ''}>
                        ${escapeHtml(choice.label || choice.value || group)}
                    </option>
                `).join('')}
            </optgroup>
        `).join('')}
    `;
}

function renderWorktypeSelect(config = {}) {
    const catalog = config?.workTemplateCatalog && typeof config.workTemplateCatalog === 'object'
        ? config.workTemplateCatalog
        : null;
    const choices = Array.isArray(catalog?.choices) ? catalog.choices : [];
    const selectedValue = String(config?.work?.template || catalog?.selected || config?.work?.type || '').trim();
    if (!choices.length) {
        return `<input class="form-input" id="edit-work-type" type="text" name="work_template" value="${escapeHtml(selectedValue)}">`;
    }

    return `
        <select class="form-select" id="edit-work-type" name="work_template">
            ${renderGroupedSelectOptions(choices, selectedValue, false)}
        </select>
        <div class="small-note">
            ${catalog?.detectedMime
                ? `Detected ${escapeHtml(catalog.detectedMime)}${catalog.detectedExtension ? ` · .${escapeHtml(catalog.detectedExtension)}` : ''} · showing ${escapeHtml(catalog.detectedKind || 'current')} templates`
                : 'Template is picked from the available registry.'}
        </div>
    `;
}

function renderTemplateMapSelect(row = {}) {
    const catalog = row?.catalog && typeof row.catalog === 'object' ? row.catalog : null;
    const choices = Array.isArray(catalog?.choices) ? catalog.choices : [];
    const selectedValue = String(row?.selected || '').trim();
    const mime = String(row?.mime || '').trim();
    const safeMimeId = mime
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '') || 'mime';
    return `
        <label class="edit-label" for="template-map-${escapeHtml(safeMimeId)}">
            ${escapeHtml(mime || 'mime')}
        </label>
        <select
            class="form-select edit-template-map-select"
            id="template-map-${escapeHtml(safeMimeId)}"
            name="work_template_map[${escapeHtml(mime)}]"
            data-template-map-mime="${escapeHtml(mime)}"
            data-template-map-selected="${escapeHtml(selectedValue)}"
        >
            ${renderGroupedSelectOptions(choices, selectedValue, true)}
        </select>
        <div class="small-note">
            ${escapeHtml(row?.kind || 'other')} · ${escapeHtml(row?.count ? `${row.count} item${row.count === 1 ? '' : 's'}` : 'no items')}
            ${row?.sampleName ? `· ${escapeHtml(row.sampleName)}` : ''}
        </div>
    `;
}

export function renderDrawerTreeHtml(config, status) {
    if (status?.target === 'file') {
        return '';
    }
    const treeItems = Array.isArray(config?.tree) ? config.tree : [];
    return treeItems.length
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

export function renderEditDrawerMarkup({ config, status, treeHtml }) {
    return `
        <div class="drawer-header">
            <h4 class="drawer-title">More settings</h4>
            <button type="button" class="drawer-close" id="editDrawerClose">&times;</button>
        </div>
        <div class="edit-status" id="editDrawerStatus"></div>
        <form id="editDrawerForm" class="edit-form">
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-link">Link</label>
                    <input class="form-input" id="edit-link" type="text" name="link" value="${escapeHtml(config?.link || '')}">
                </div>
                <div>
                    <label class="edit-label" for="edit-url">URL</label>
                    <input class="form-input" id="edit-url" type="text" name="url" value="${escapeHtml(config?.url || '')}">
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work Template</label>
                    ${renderWorktypeSelect(config)}
                </div>
                <div class="small-note">Use <strong>Change layout</strong> for wrapper editing. This selector chooses the active work template for the current item.</div>
            </div>
            ${Array.isArray(config?.workTemplateMapCatalog?.rows) && config.workTemplateMapCatalog.rows.length ? `
            <div class="edit-fieldset">
                <div class="edit-fieldset-title">Template defaults by MIME</div>
                <div class="small-note">Set the inherited default template for each MIME family in this folder or layout. Leave the entry on <em>Inherit default</em> to use the parent value.</div>
                <div class="edit-template-map-list">
                    ${config.workTemplateMapCatalog.rows.map((row) => `
                        <div class="edit-template-map-row">
                            ${renderTemplateMapSelect(row)}
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}
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
}
