import { renderDrawerTreeHtml, renderEditDrawerMarkup } from './drawer/render.js';
import { bindEditDrawerInteractions } from './drawer/bind.js';

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
    if (!config || status?.error || !status?.allowed) {
        editDrawer.innerHTML = '';
        return { drawerForm: null, drawerStatus: null };
    }

    const treeHtml = renderDrawerTreeHtml(config, status);
    editDrawer.innerHTML = renderEditDrawerMarkup({ config, status, treeHtml, treeItems: config?.tree || [] });

    return bindEditDrawerInteractions({
        editDrawer,
        status,
        onClose,
        onSubmit,
    });
}
