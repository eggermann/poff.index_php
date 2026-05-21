import { bindStoredDetailsState } from '../panel/shared.js';

export function bindEditDrawerInteractions({ editDrawer, status, onClose, onSubmit, onDeleteTarget }) {
    const drawerClose = editDrawer.querySelector('#editDrawerClose');
    if (drawerClose && typeof onClose === 'function') {
        drawerClose.addEventListener('click', () => onClose());
    }

    const drawerStatus = editDrawer.querySelector('#editDrawerStatus');
    const drawerForm = editDrawer.querySelector('#editDrawerForm');
    const deleteTargetButton = editDrawer.querySelector('#editDrawerDeleteTarget');
    const templateDefaultsDetails = editDrawer.querySelector('#editTemplateDefaultsDetails');
    const treeBulkToggle = editDrawer.querySelector('#editTreeVisibleAll');
    if (templateDefaultsDetails) {
        bindStoredDetailsState(templateDefaultsDetails, 'template-defaults-details');
    }
    const treeVisibleInputs = () => Array.from(editDrawer.querySelectorAll('input[name="tree_visible"]'));
    const syncTreeBulkToggle = () => {
        if (!treeBulkToggle) {
            return;
        }
        const inputs = treeVisibleInputs();
        const checkedCount = inputs.filter((input) => input.checked).length;
        treeBulkToggle.checked = inputs.length > 0 && checkedCount === inputs.length;
        treeBulkToggle.indeterminate = checkedCount > 0 && checkedCount < inputs.length;
    };
    if (treeBulkToggle) {
        treeBulkToggle.addEventListener('change', () => {
            const inputs = treeVisibleInputs();
            inputs.forEach((input) => {
                input.checked = treeBulkToggle.checked;
            });
            syncTreeBulkToggle();
        });
        treeVisibleInputs().forEach((input) => {
            input.addEventListener('change', syncTreeBulkToggle);
        });
        syncTreeBulkToggle();
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
                drawerForm,
                statusEl: drawerStatus,
                treeVisible,
            });
        });
    }
    if (deleteTargetButton && drawerStatus && typeof onDeleteTarget === 'function') {
        deleteTargetButton.addEventListener('click', async () => {
            const confirmed = window.confirm('Delete this item? This cannot be undone.');
            if (!confirmed) {
                return;
            }
            await onDeleteTarget({ statusEl: drawerStatus });
        });
    }

    function focusTreeItem(path = '') {
        if (!path) {
            return false;
        }
        const row = Array.from(editDrawer.querySelectorAll('[data-tree-item-path]'))
            .find((candidate) => candidate.getAttribute('data-tree-item-path') === path);
        if (!row) {
            return false;
        }
        row.classList.add('edit-tree-item-focused');
        window.setTimeout(() => {
            row.classList.remove('edit-tree-item-focused');
        }, 1800);
        row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const checkbox = row.querySelector('input[name="tree_visible"]');
        if (checkbox && typeof checkbox.focus === 'function') {
            checkbox.focus({ preventScroll: true });
        }
        return true;
    }

    return { drawerForm, drawerStatus, focusTreeItem };
}
