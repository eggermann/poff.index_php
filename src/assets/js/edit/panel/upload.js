import { uploadValidationError } from './shared.js';
import { setStatusMessage } from '../status.js';

export function renderUploadSectionHtml({ isFileTarget, isEmptyFolder }) {
    if (isFileTarget) {
        return '';
    }
    return `
        <div class="edit-upload-launch ${isEmptyFolder ? 'edit-upload-launch-empty' : ''}">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Add work</div>
                <div class="small-note">${isEmptyFolder ? 'This folder is empty. Upload a file, create a blank file, or create a folder to start.' : 'Upload files, create a blank file, or create a folder in this folder.'}</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add work</button>
        </div>
        <dialog class="edit-upload-dialog" id="editUploadDialog">
            <form method="dialog" class="edit-upload-dialog-form">
                <div class="drawer-header">
                    <h4 class="drawer-title">Add work</h4>
                    <button type="button" class="drawer-close" id="editUploadClose">&times;</button>
                </div>
                <div class="edit-grid">
                    <div>
                        <label class="edit-label" for="edit-upload-source">Source</label>
                        <select class="form-select" id="edit-upload-source" name="upload_source">
                            <option value="upload" selected>Upload</option>
                            <option value="blank">Blank file</option>
                            <option value="folder">Folder</option>
                            <option value="url" disabled>From URL (disabled)</option>
                        </select>
                    </div>
                    <div id="editUploadFilesWrap">
                        <label class="edit-label" for="edit-upload-files">Files</label>
                        <input class="form-input" id="edit-upload-files" type="file" name="files" multiple>
                    </div>
                    <div id="editBlankFileWrap" hidden>
                        <label class="edit-label" for="edit-blank-file-name">Blank file name</label>
                        <input class="form-input" id="edit-blank-file-name" type="text" name="blank_file_name" placeholder="notes.txt">
                    </div>
                </div>
                <div class="small-note" id="editUploadSummary">No files selected.</div>
                <div class="edit-inline-actions">
                    <button class="btn" type="button" id="editUploadSubmit">Add</button>
                    <button class="btn btn-secondary" type="button" id="editUploadCancel">Cancel</button>
                </div>
            </form>
        </dialog>
    `;
}

export function bindUploadDialog({
    editPanel,
    statusEl,
    uploadLimits,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
}) {
    const uploadDialog = editPanel.querySelector('#editUploadDialog');
    const openUploadDialogButton = editPanel.querySelector('#editOpenUploadDialog');
    const uploadCloseButton = editPanel.querySelector('#editUploadClose');
    const uploadCancelButton = editPanel.querySelector('#editUploadCancel');
    const uploadSubmitButton = editPanel.querySelector('#editUploadSubmit');
    const uploadSourceEl = editPanel.querySelector('#edit-upload-source');
    const uploadFilesEl = editPanel.querySelector('#edit-upload-files');
    const uploadSummaryEl = editPanel.querySelector('#editUploadSummary');
    const uploadFilesWrapEl = editPanel.querySelector('#editUploadFilesWrap');
    const blankFileWrapEl = editPanel.querySelector('#editBlankFileWrap');
    const blankFileNameEl = editPanel.querySelector('#edit-blank-file-name');
    const blankFileLabelEl = blankFileWrapEl ? blankFileWrapEl.querySelector('label') : null;
    const uploadNameDrafts = {
        blank: '',
        folder: '',
    };
    let uploadMode = uploadSourceEl?.value || 'upload';

    if (!uploadDialog || !openUploadDialogButton || typeof onUploadFiles !== 'function' || typeof onCreateBlankFile !== 'function' || typeof onCreateFolder !== 'function') {
        return;
    }

    const setUploadSummary = () => {
        const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
        if (!uploadSummaryEl) {
            return;
        }
        const mode = uploadSourceEl?.value || 'upload';
        if (mode === 'blank' || mode === 'folder') {
            const name = blankFileNameEl?.value?.trim() || '';
            uploadSummaryEl.textContent = name ? `Will create: ${name}` : (mode === 'folder' ? 'Enter a folder name.' : 'Enter a file name.');
            return;
        }
        const validationError = uploadValidationError(files, uploadLimits);
        if (validationError) {
            uploadSummaryEl.textContent = validationError;
            return;
        }
        uploadSummaryEl.textContent = files.length ? files.map((file) => file.name).join(', ') : 'No files selected.';
    };

    const syncUploadMode = () => {
        const mode = uploadSourceEl?.value || 'upload';
        if ((uploadMode === 'blank' || uploadMode === 'folder') && blankFileNameEl) {
            uploadNameDrafts[uploadMode] = blankFileNameEl.value || '';
        }
        uploadMode = mode;
        if (uploadFilesWrapEl) {
            uploadFilesWrapEl.hidden = mode !== 'upload';
        }
        if (blankFileWrapEl) {
            blankFileWrapEl.hidden = mode !== 'blank' && mode !== 'folder';
        }
        if (blankFileLabelEl) {
            blankFileLabelEl.textContent = mode === 'folder' ? 'Folder name' : 'Blank file name';
        }
        if (blankFileNameEl) {
            blankFileNameEl.placeholder = mode === 'folder' ? 'new-folder' : 'notes.txt';
            if (mode === 'blank' || mode === 'folder') {
                blankFileNameEl.value = uploadNameDrafts[mode] || '';
            }
        }
        if (uploadSubmitButton) {
            uploadSubmitButton.textContent = mode === 'blank'
                ? 'Create blank file'
                : mode === 'folder'
                    ? 'Create folder'
                    : 'Upload';
        }
        setUploadSummary();
    };

    const closeUploadDialog = () => {
        if (typeof uploadDialog.close === 'function') {
            uploadDialog.close();
        } else {
            uploadDialog.removeAttribute('open');
        }
    };

    const openUploadDialog = () => {
        syncUploadMode();
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
    if (uploadSourceEl) {
        uploadSourceEl.addEventListener('change', syncUploadMode);
    }
    if (uploadFilesEl) {
        uploadFilesEl.addEventListener('change', setUploadSummary);
    }
    if (blankFileNameEl) {
        blankFileNameEl.addEventListener('input', setUploadSummary);
    }
    if (uploadSubmitButton) {
        uploadSubmitButton.addEventListener('click', async () => {
            const source = uploadSourceEl?.value || 'upload';
            try {
                uploadSubmitButton.disabled = true;
                if (source === 'blank') {
                        const fileName = blankFileNameEl?.value?.trim() || '';
                        if (!fileName) {
                            setStatusMessage(statusEl, 'Enter a file name.');
                            return;
                        }
                    await onCreateBlankFile({
                        source,
                        fileName,
                        statusEl,
                    });
                } else if (source === 'folder') {
                        const folderName = blankFileNameEl?.value?.trim() || '';
                        if (!folderName) {
                            setStatusMessage(statusEl, 'Enter a folder name.');
                            return;
                        }
                    await onCreateFolder({
                        source,
                        folderName,
                        statusEl,
                    });
                } else {
                        const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
                        if (files.length === 0) {
                            setStatusMessage(statusEl, 'Choose at least one file.');
                            return;
                        }
                        const validationError = uploadValidationError(files, uploadLimits);
                        if (validationError) {
                            setStatusMessage(statusEl, validationError);
                            return;
                        }
                    await onUploadFiles({
                        source,
                        files,
                        statusEl,
                    });
                }
                closeUploadDialog();
                } catch (err) {
                    setStatusMessage(statusEl, err.message || 'Upload failed.');
                } finally {
                    uploadSubmitButton.disabled = false;
                }
        });
    }
    syncUploadMode();
}
