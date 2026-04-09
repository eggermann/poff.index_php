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
                    <label class="edit-label" for="edit-work-layout">Work Layout Name</label>
                    <input class="form-input" id="edit-work-layout" type="text" name="work_layout" value="${escapeHtml(layoutState.mode)}">
                </div>
            </div>
            <div>
                <label class="edit-label" for="edit-work-template">Work Layout Template (HBS)</label>
                <textarea class="form-textarea" id="edit-work-template" name="work_template">${escapeHtml(layoutState.template)}</textarea>
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

    const drawerClose = editDrawer.querySelector('#editDrawerClose');
    if (drawerClose && typeof onClose === 'function') {
        drawerClose.addEventListener('click', () => onClose());
    }

    const drawerStatus = editDrawer.querySelector('#editDrawerStatus');
    const drawerForm = editDrawer.querySelector('#editDrawerForm');
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
