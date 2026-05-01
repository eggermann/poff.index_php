import { escapeHtml } from '../../core/utils.js';

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
                    <label class="edit-label" for="edit-work-type">Work Type</label>
                    <input class="form-input" id="edit-work-type" type="text" name="work_type" value="${escapeHtml((config?.work || {}).type || '')}">
                </div>
                <div class="small-note">Use <strong>Change layout</strong> for layout source, inheritance, and wrapper/work template editing.</div>
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
