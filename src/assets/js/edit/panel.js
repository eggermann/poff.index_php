import { escapeHtml, getLayoutState } from '../core/utils.js';
import { loadPromptSettings } from './prompt/storage.js';
import { renderPromptWindow } from './prompt-window.js';

export function renderEditPanel({
    editPanel,
    editRequested,
    config,
    status,
    onTitleInput,
    onDescriptionInput,
    onSubmit,
    onToggleDrawer,
    onUploadFiles,
}) {
    if (!editPanel) {
        return { statusEl: null, promptRoot: null };
    }
    if (!editRequested) {
        editPanel.hidden = true;
        return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!config || status?.error) {
        const message = status?.error || 'Edit mode is unavailable.';
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(message)}</div>
        `;
        return { statusEl: null, promptRoot: null };
    }
    if (!status?.allowed) {
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create a file named <code>.edit.allow</code> in the site root to enable edit mode.</div>
        `;
        return { statusEl: null, promptRoot: null };
    }

    const label = status?.target === 'file' ? 'Edit mode (file)' : 'Edit mode (folder)';
    const settings = loadPromptSettings();
    const layoutState = getLayoutState(config);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isEmptyFolder = status?.target !== 'file' && treeItems.length === 0;
    editPanel.innerHTML = `
        <h3 class="edit-panel-title">${label}</h3>
        <div class="edit-status" id="editInlineStatus"></div>
        <form id="inlineEditForm" class="edit-inline">
            <div>
                <label class="edit-label" for="edit-title">Title</label>
                <input class="form-input" id="edit-title" type="text" name="title" value="${escapeHtml(config.title || '')}">
            </div>
            <div>
                <label class="edit-label" for="edit-description">Description</label>
                <textarea class="form-textarea" id="edit-description" name="description">${escapeHtml(config.description || '')}</textarea>
            </div>
            <div class="edit-inline-actions">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" id="editMoreToggle">More...</button>
            </div>
        </form>
        <div class="edit-layout-launch">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="small-note">${escapeHtml(layoutState.sourceLabel)}</div>
                <div class="small-note">Current mode: <code>${escapeHtml(layoutState.mode)}</code></div>
            </div>
            <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
        </div>
        ${status?.target !== 'file' ? `
            <div class="edit-upload-launch ${isEmptyFolder ? 'edit-upload-launch-empty' : ''}">
                <div class="edit-layout-copy">
                    <div class="edit-layout-title">Add content</div>
                    <div class="small-note">${isEmptyFolder ? 'This folder is empty. Upload a file to start.' : 'Upload files into this folder.'}</div>
                </div>
                <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add files</button>
            </div>
            <dialog class="edit-upload-dialog" id="editUploadDialog">
                <form method="dialog" class="edit-upload-dialog-form">
                    <div class="drawer-header">
                        <h4 class="drawer-title">Add content</h4>
                        <button type="button" class="drawer-close" id="editUploadClose">&times;</button>
                    </div>
                    <div class="edit-grid">
                        <div>
                            <label class="edit-label" for="edit-upload-source">Source</label>
                            <select class="form-select" id="edit-upload-source" name="upload_source">
                                <option value="upload" selected>Upload</option>
                                <option value="url" disabled>From URL (disabled)</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label" for="edit-upload-files">Files</label>
                            <input class="form-input" id="edit-upload-files" type="file" name="files" multiple>
                        </div>
                    </div>
                    <div class="small-note" id="editUploadSummary">No files selected.</div>
                    <div class="edit-inline-actions">
                        <button class="btn" type="button" id="editUploadSubmit">Upload</button>
                        <button class="btn btn-secondary" type="button" id="editUploadCancel">Cancel</button>
                    </div>
                </form>
            </dialog>
        ` : ''}
        ${renderPromptWindow(settings)}
    `;

    const form = editPanel.querySelector('#inlineEditForm');
    const statusEl = editPanel.querySelector('#editInlineStatus');
    const moreToggle = editPanel.querySelector('#editMoreToggle');
    const changeLayoutButton = editPanel.querySelector('#editChangeLayout');
    const titleInput = editPanel.querySelector('#edit-title');
    const descInput = editPanel.querySelector('#edit-description');
    const promptRoot = editPanel.querySelector('#promptWindow');
    const uploadDialog = editPanel.querySelector('#editUploadDialog');
    const openUploadDialogButton = editPanel.querySelector('#editOpenUploadDialog');
    const uploadCloseButton = editPanel.querySelector('#editUploadClose');
    const uploadCancelButton = editPanel.querySelector('#editUploadCancel');
    const uploadSubmitButton = editPanel.querySelector('#editUploadSubmit');
    const uploadSourceEl = editPanel.querySelector('#edit-upload-source');
    const uploadFilesEl = editPanel.querySelector('#edit-upload-files');
    const uploadSummaryEl = editPanel.querySelector('#editUploadSummary');

    if (titleInput && typeof onTitleInput === 'function') {
        titleInput.addEventListener('input', () => {
            onTitleInput(titleInput.value);
        });
    }
    if (descInput && typeof onDescriptionInput === 'function') {
        descInput.addEventListener('input', () => {
            onDescriptionInput(descInput.value);
        });
    }
    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            onSubmit({
                elements: form.elements,
                statusEl,
            });
        });
    }
    if (moreToggle && typeof onToggleDrawer === 'function') {
        moreToggle.addEventListener('click', () => onToggleDrawer());
    }
    if (changeLayoutButton && typeof onToggleDrawer === 'function') {
        changeLayoutButton.addEventListener('click', () => onToggleDrawer());
    }
    if (uploadDialog && openUploadDialogButton && typeof onUploadFiles === 'function') {
        const setUploadSummary = () => {
            const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
            if (!uploadSummaryEl) {
                return;
            }
            uploadSummaryEl.textContent = files.length
                ? files.map((file) => file.name).join(', ')
                : 'No files selected.';
        };
        const closeUploadDialog = () => {
            if (typeof uploadDialog.close === 'function') {
                uploadDialog.close();
            } else {
                uploadDialog.removeAttribute('open');
            }
        };
        const openUploadDialog = () => {
            setUploadSummary();
            if (typeof uploadDialog.showModal === 'function') {
                uploadDialog.showModal();
            } else {
                uploadDialog.setAttribute('open', 'open');
            }
        };

        openUploadDialogButton.addEventListener('click', openUploadDialog);
        if (uploadCloseButton) {
            uploadCloseButton.addEventListener('click', closeUploadDialog);
        }
        if (uploadCancelButton) {
            uploadCancelButton.addEventListener('click', closeUploadDialog);
        }
        if (uploadFilesEl) {
            uploadFilesEl.addEventListener('change', setUploadSummary);
        }
        if (uploadSubmitButton) {
            uploadSubmitButton.addEventListener('click', async () => {
                const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
                if (files.length === 0) {
                    if (statusEl) {
                        statusEl.textContent = 'Choose at least one file.';
                        statusEl.className = 'edit-status';
                    }
                    return;
                }
                try {
                    uploadSubmitButton.disabled = true;
                    await onUploadFiles({
                        source: uploadSourceEl?.value || 'upload',
                        files,
                        statusEl,
                    });
                    closeUploadDialog();
                } catch (err) {
                    if (statusEl) {
                        statusEl.textContent = err.message || 'Upload failed.';
                        statusEl.className = 'edit-status';
                    }
                } finally {
                    uploadSubmitButton.disabled = false;
                }
            });
        }
    }

    return { statusEl, promptRoot };
}
