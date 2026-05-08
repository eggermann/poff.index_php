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

function renderWorktypeSelect(config = {}) {
    const catalog = config?.workTemplateCatalog && typeof config.workTemplateCatalog === 'object'
        ? config.workTemplateCatalog
        : null;
    const choices = Array.isArray(catalog?.choices) ? catalog.choices : [];
    const selectedValue = String(config?.work?.template || catalog?.selected || config?.work?.type || '').trim();
    const groups = groupWorktypeChoices(choices);
    const groupEntries = Object.entries(groups);
    if (!groupEntries.length) {
        return `<input class="form-input" id="edit-work-type" type="text" name="work_template" value="${escapeHtml(selectedValue)}">`;
    }

    return `
        <select class="form-select" id="edit-work-type" name="work_template">
            ${groupEntries.map(([group, groupChoices]) => `
                <optgroup label="${escapeHtml(group)}">
                    ${groupChoices.map((choice) => `
                        <option value="${escapeHtml(choice.value || '')}" data-kind="${escapeHtml(choice.kind || group)}" ${String(choice.value || '') === selectedValue ? 'selected' : ''}>
                            ${escapeHtml(choice.label || choice.value || group)}
                        </option>
                    `).join('')}
                </optgroup>
            `).join('')}
        </select>
        <div class="small-note">
            ${catalog?.detectedMime
                ? `Detected ${escapeHtml(catalog.detectedMime)}${catalog.detectedExtension ? ` · .${escapeHtml(catalog.detectedExtension)}` : ''} · showing ${escapeHtml(catalog.detectedKind || 'current')} templates`
                : 'Template is picked from the available registry.'}
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
