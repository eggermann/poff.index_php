import { uploadValidationError } from './shared.js';
import { setStatusMessage } from '../status.js';
import { registerEscapeClose } from '../../core/escape-stack.js';

export function renderUploadSectionHtml({ isFileTarget, isEmptyFolder, externalOnly = false }) {
    if (isFileTarget) {
        return '';
    }
    const launcherCopy = externalOnly
        ? 'Submit a Poff link from another system. It stays hidden until an editor confirms it.'
        : (isEmptyFolder
            ? 'This folder is empty. Upload a file, create a blank file, create a folder, or add a link to start.'
            : 'Upload files, create a blank file, create a folder, or add a link in this folder.');
    const sourceFieldHtml = externalOnly
        ? '<input type="hidden" name="upload_source" value="url">'
        : `
                    <div>
                        <label class="edit-label" for="edit-upload-source">Source</label>
                        <select class="form-select" id="edit-upload-source" name="upload_source">
                            <option value="upload" selected>Upload</option>
                            <option value="blank">Blank file</option>
                            <option value="folder">Folder</option>
                            <option value="url">Poff link</option>
                        </select>
                    </div>
        `;
    return `
        <div class="edit-upload-launch ${isEmptyFolder ? 'edit-upload-launch-empty' : ''}">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Add work</div>
                <div class="small-note">${launcherCopy}</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">${externalOnly ? 'Submit link' : 'Add work'}</button>
        </div>
        <dialog class="edit-upload-dialog" id="editUploadDialog">
            <form method="dialog" class="edit-upload-dialog-form">
                <div class="drawer-header">
                    <h4 class="drawer-title">${externalOnly ? 'Submit external link' : 'Add work'}</h4>
                    <button type="button" class="drawer-close" id="editUploadClose">&times;</button>
                </div>
                <div class="edit-grid">
                    ${sourceFieldHtml}
                    <div id="editUploadFilesWrap">
                        <label class="edit-label" for="edit-upload-files">Files</label>
                        <input class="form-input" id="edit-upload-files" type="file" name="files" multiple>
                    </div>
                    <div id="editBlankFileWrap" hidden>
                        <label class="edit-label" for="edit-blank-file-name">Blank file name</label>
                        <input class="form-input" id="edit-blank-file-name" type="text" name="blank_file_name" placeholder="notes.txt">
                    </div>
                    <div id="editLinkWrap" hidden>
                        <label class="edit-label" for="edit-link-url">Link URL</label>
                        <input class="form-input" id="edit-link-url" type="url" name="link_url" placeholder="https://other.example/index.php?view=1&path=folder">
                    </div>
                </div>
                <div class="small-note" id="editUploadSummary">${externalOnly ? 'Use a Poff viewer URL from another system. Max 5 pending links per folder.' : 'No files selected.'}</div>
                <div class="edit-inline-actions">
                    <button class="btn" type="button" id="editUploadSubmit">${externalOnly ? 'Submit for review' : 'Add'}</button>
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
    externalOnly = false,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onCreateLink,
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
    const linkWrapEl = editPanel.querySelector('#editLinkWrap');
    const linkUrlEl = editPanel.querySelector('#edit-link-url');
    const blankFileLabelEl = blankFileWrapEl ? blankFileWrapEl.querySelector('label') : null;
    const uploadNameDrafts = {
        blank: '',
        folder: '',
        url: '',
    };
    const uploadUrlDrafts = {
        url: '',
    };
    const readUploadMode = () => (externalOnly ? 'url' : (uploadSourceEl?.value || 'upload'));
    let uploadMode = readUploadMode();
    let unregisterEscapeClose = null;

    if (!uploadDialog || !openUploadDialogButton || typeof onUploadFiles !== 'function' || typeof onCreateBlankFile !== 'function' || typeof onCreateFolder !== 'function' || typeof onCreateLink !== 'function') {
        return;
    }

    const setUploadSummary = () => {
        const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
        if (!uploadSummaryEl) {
            return;
        }
        const mode = readUploadMode();
        if (mode === 'blank' || mode === 'folder') {
            const name = blankFileNameEl?.value?.trim() || '';
            uploadSummaryEl.textContent = name ? `Will create: ${name}` : (mode === 'folder' ? 'Enter a folder name.' : 'Enter a file name.');
            return;
        }
        if (mode === 'url') {
            const linkName = blankFileNameEl?.value?.trim() || '';
            const linkUrl = linkUrlEl?.value?.trim() || '';
            uploadSummaryEl.textContent = linkUrl
                ? (externalOnly ? `Will submit for review: ${linkName || linkUrl}` : `Will add link: ${linkName || linkUrl}`)
                : 'Enter a link URL.';
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
        const mode = readUploadMode();
        if ((uploadMode === 'blank' || uploadMode === 'folder') && blankFileNameEl) {
            uploadNameDrafts[uploadMode] = blankFileNameEl.value || '';
        }
        if (uploadMode === 'url' && blankFileNameEl) {
            uploadNameDrafts.url = blankFileNameEl.value || '';
        }
        if (uploadMode === 'url' && linkUrlEl) {
            uploadUrlDrafts.url = linkUrlEl.value || '';
        }
        uploadMode = mode;
        if (uploadFilesWrapEl) {
            uploadFilesWrapEl.hidden = mode !== 'upload';
        }
        if (blankFileWrapEl) {
            blankFileWrapEl.hidden = mode !== 'blank' && mode !== 'folder' && mode !== 'url';
        }
        if (linkWrapEl) {
            linkWrapEl.hidden = mode !== 'url';
        }
        if (blankFileLabelEl) {
            blankFileLabelEl.textContent = mode === 'folder'
                ? 'Folder name'
                : mode === 'url'
                    ? 'Link label'
                    : 'Blank file name';
        }
        if (blankFileNameEl) {
            blankFileNameEl.placeholder = mode === 'folder'
                ? 'new-folder'
                : mode === 'url'
                    ? 'my-link'
                    : 'notes.txt';
            if (mode === 'blank' || mode === 'folder' || mode === 'url') {
                blankFileNameEl.value = uploadNameDrafts[mode] || '';
            }
        }
        if (linkUrlEl) {
            linkUrlEl.value = mode === 'url' ? (uploadUrlDrafts.url || linkUrlEl.value || '') : linkUrlEl.value || '';
        }
        if (uploadSubmitButton) {
            uploadSubmitButton.textContent = mode === 'blank'
                ? 'Create blank file'
                : mode === 'folder'
                    ? 'Create folder'
                    : mode === 'url'
                        ? (externalOnly ? 'Submit for review' : 'Add link')
                    : 'Upload';
        }
        setUploadSummary();
    };

    const closeUploadDialog = () => {
        if (typeof unregisterEscapeClose === 'function') {
            unregisterEscapeClose();
            unregisterEscapeClose = null;
        }
        uploadDialog.classList.remove('edit-upload-dialog-open');
        if (typeof uploadDialog.close === 'function') {
            uploadDialog.close();
        } else {
            uploadDialog.removeAttribute('open');
        }
    };

    const openUploadDialog = () => {
        if (!uploadDialog.isConnected) {
            return false;
        }
        syncUploadMode();
        setUploadSummary();
        try {
            if (typeof uploadDialog.showModal === 'function') {
                uploadDialog.showModal();
            } else {
                uploadDialog.setAttribute('open', 'open');
            }
        } catch (err) {
            uploadDialog.setAttribute('open', 'open');
        }
        uploadDialog.classList.add('edit-upload-dialog-open');
        if (typeof unregisterEscapeClose !== 'function') {
            unregisterEscapeClose = registerEscapeClose(() => {
                closeUploadDialog();
                return true;
            }, { label: 'upload-dialog' });
        }
        const firstFocusable = uploadDialog.querySelector('select, input, button');
        if (firstFocusable && typeof firstFocusable.focus === 'function') {
            firstFocusable.focus();
        }
        return true;
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
            const source = readUploadMode();
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
                } else if (source === 'url') {
                        const linkName = blankFileNameEl?.value?.trim() || '';
                        const linkUrl = linkUrlEl?.value?.trim() || '';
                        if (!linkUrl) {
                            setStatusMessage(statusEl, 'Enter a link URL.');
                            return;
                        }
                    await onCreateLink({
                        source,
                        linkName,
                        linkUrl,
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
    const addIntent = new URL(window.location.href).searchParams.get('add') || '';
    if ((externalOnly && addIntent === 'url') || (!externalOnly && addIntent === 'work')) {
        const openWhenReady = () => {
            if (!uploadDialog.isConnected) {
                window.requestAnimationFrame(openWhenReady);
                return;
            }
            if (!openUploadDialog()) {
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.delete('add');
            window.history.replaceState(null, '', url.toString());
        };
        window.requestAnimationFrame(openWhenReady);
    }
}
