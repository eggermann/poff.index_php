export function setItemStatus(statusEl, message, success = false) {
    if (!statusEl) {
        return;
    }
    statusEl.textContent = message;
    statusEl.className = success ? 'edit-status edit-status-success' : 'edit-status';
}
