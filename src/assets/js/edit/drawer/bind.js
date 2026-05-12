export function bindEditDrawerInteractions({ editDrawer, status, onClose, onSubmit }) {
    const drawerClose = editDrawer.querySelector('#editDrawerClose');
    if (drawerClose && typeof onClose === 'function') {
        drawerClose.addEventListener('click', () => onClose());
    }

    const drawerStatus = editDrawer.querySelector('#editDrawerStatus');
    const drawerForm = editDrawer.querySelector('#editDrawerForm');
    const treeBulkToggle = editDrawer.querySelector('#editTreeVisibleAll');
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

    return { drawerForm, drawerStatus };
}
