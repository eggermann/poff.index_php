export function setItemStatus(statusEl, message, success = false) {
    if (!statusEl) {
        return;
    }
    statusEl.textContent = message;
    statusEl.className = success ? 'edit-status edit-status-success' : 'edit-status';
}

export function updateFolderConfig({ editConfig, folderConfig, renderFolderMeta, statusTarget }) {
    if (statusTarget === 'file') {
        return folderConfig;
    }
    renderFolderMeta();
    return editConfig;
}

export function updateAfterSuccessfulMutation({ statusEl, message, success = true }) {
    setItemStatus(statusEl, message, success);
}
