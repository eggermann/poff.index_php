export function bindEditDrawerInteractions({ editDrawer, status, onClose, onSubmit }) {
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
                drawerForm,
                statusEl: drawerStatus,
                treeVisible,
            });
        });
    }

    return { drawerForm, drawerStatus };
}
