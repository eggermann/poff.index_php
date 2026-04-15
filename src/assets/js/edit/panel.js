import { escapeHtml, getLayoutState } from '../core/utils.js';
import { loadPromptSettings } from './prompt/storage.js';
import { renderPromptWindow } from './prompt-window.js';

function layoutOverlayState(config, status) {
    const layoutState = getLayoutState(config);
    const isFile = status?.target === 'file';
    const sectionName = layoutState.section || (isFile ? 'work' : 'works');
    const localLayoutDirectory = isFile
        ? `.works/${config.name || config.path || 'item'}.layout`
        : '.layout';
    const wrapperTarget = `${localLayoutDirectory}/template.hbs`;
    const sectionTarget = `${localLayoutDirectory}/${sectionName}.hbs`;
    const wrapperWasLocal = layoutState.directory === localLayoutDirectory;
    const sectionWasLocal = layoutState.sectionDirectory === localLayoutDirectory;
    const hasInheritedLayout = !!layoutState.inheritedDirectory;
    const originalTarget = layoutState.storage === 'filesystem'
        ? (layoutState.directory || localLayoutDirectory)
        : (layoutState.inheritedDirectory || '');
    const originalEditable = originalTarget !== '';
    const originalUsesLocal = originalTarget === localLayoutDirectory;

    const localWrapperTemplate = wrapperWasLocal
        ? (layoutState.template || '')
        : '';
    const localWrapperCss = wrapperWasLocal
        ? (layoutState.css || '')
        : '';
    const localWrapperJs = wrapperWasLocal
        ? (layoutState.js || '')
        : '';

    let originalTemplate = '';
    let originalCss = '';
    let originalJs = '';
    if (originalEditable && layoutState.storage === 'filesystem') {
        originalTemplate = layoutState.template || '';
        originalCss = layoutState.css || '';
        originalJs = layoutState.js || '';
    } else if (!originalEditable) {
        originalTemplate = layoutState.phpTemplate || '';
    }

    const wrapperSourceLabel = layoutState.storage === 'filesystem'
        ? `Filesystem: ${layoutState.directory || localLayoutDirectory}`
        : 'PHP built-in poff-layout';
    const inheritedLayoutLabel = hasInheritedLayout
        ? layoutState.inheritedDirectory
        : 'No parent .layout found';
    const originalLabel = originalEditable
        ? `Editable source: ${originalTarget}`
        : 'PHP built-in poff-layout is read-only until a parent .layout exists';

    return {
        layoutState,
        sectionName,
        localLayoutDirectory,
        wrapperTarget,
        sectionTarget,
        wrapperWasLocal,
        sectionWasLocal,
        hasInheritedLayout,
        originalTarget,
        originalEditable,
        originalUsesLocal,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        originalTemplate,
        originalCss,
        originalJs,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
    };
}

function renderEditLayoutPanel({
    editPanel,
    config,
    status,
    contentTargetLabel,
    onSubmitLayout,
    onReturnToWork,
    onUploadFiles,
    onCreateBlankFile,
}) {
    const settings = loadPromptSettings();
    const subjectStatus = {
        ...status,
        target: status?.subjectTarget || status?.target,
    };
    const overlayState = layoutOverlayState(config, subjectStatus);
    const {
        layoutState,
        sectionName,
        wrapperTarget,
        sectionTarget,
        sectionWasLocal,
        originalTarget,
        originalEditable,
        originalUsesLocal,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        originalTemplate,
        originalCss,
        originalJs,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
    } = overlayState;
    const subjectLabel = subjectStatus.target === 'file' ? 'file' : 'folder';
    const layoutPresetOptions = [
        { value: 'actual', label: 'Actual' },
        { value: 'none', label: 'None' },
        { value: 'custom', label: 'Custom' },
    ];
    const hasVirtualSource = !overlayState.wrapperWasLocal && !originalUsesLocal;
    const isFileSubject = subjectStatus.target === 'file';
    const addContentHint = isFileSubject
        ? `Add content to the containing folder: ${contentTargetLabel || '.'}`
        : 'Upload files or create a blank file in this folder.';

    editPanel.innerHTML = `
        <h3 class="edit-panel-title">Edit layout (${subjectLabel})</h3>
        <div class="small-note">Virtual <code>.layout</code> target for this ${escapeHtml(subjectLabel)}. The preview stays on the current work while you edit the wrapper.</div>
        <div class="edit-status" id="editLayoutStatus"></div>
        <form id="editLayoutPanelForm" class="edit-inline edit-layout-panel">
            <div class="edit-layout-launch edit-layout-summary">
                <div class="edit-layout-copy">
                    <div class="edit-layout-title">Layout</div>
                    <div class="edit-layout-summary-line">Editing source: <code id="edit-layout-source-preview">${escapeHtml(wrapperSourceLabel)}</code></div>
                    <div class="edit-layout-summary-line">Current mode: <code id="edit-layout-mode-preview">${escapeHtml(layoutState.mode)}</code></div>
                    <div class="edit-layout-summary-line">Inner section stays at <code>${escapeHtml(sectionTarget)}</code> unless you change it in <strong>More...</strong></div>
                </div>
                <div class="edit-inline-actions edit-layout-header-actions">
                    <button class="btn btn-secondary" type="button" id="editLayoutBack">Back to work</button>
                    <button class="btn btn-secondary" type="button" id="editLayoutMore">More...</button>
                    <button class="btn" type="submit">Save layout</button>
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-preset">Layout select</label>
                    <select class="form-select" id="edit-layout-preset" name="layout_preset">
                        ${layoutPresetOptions.map((option) => `
                            <option value="${option.value}" ${layoutState.preset === option.value ? 'selected' : ''}>${option.label}</option>
                        `).join('')}
                    </select>
                </div>
                <div class="edit-layout-copy edit-layout-section-note">
                    <div class="edit-layout-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                </div>
            </div>
        </form>
        <div class="edit-upload-launch">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Add content</div>
                <div class="small-note">${escapeHtml(addContentHint)}</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add content</button>
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
                            <option value="blank">Blank file</option>
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
        ${renderPromptWindow(settings, {
            mode: 'layout',
            subjectType: subjectLabel,
            templateTarget: wrapperTarget,
            sectionTarget,
        })}
        <details class="edit-layout-advanced edit-layout-manual" id="editLayoutManual" ${sectionWasLocal ? 'open' : ''}>
            <summary class="edit-layout-advanced-summary">More layout files</summary>
            <div class="edit-layout-overlay-grid">
                <div class="edit-layout-meta-card">
                    <div class="edit-layout-meta-title">Sources</div>
                    <div class="small-note">Inherited parent layout: <code>${escapeHtml(inheritedLayoutLabel)}</code></div>
                    <div class="small-note">PHP built-in: <code>poff-layout.hbs</code> from the bundled templates</div>
                    <div class="small-note">Layout target: <code>${escapeHtml(originalLabel)}</code></div>
                    <div class="small-note">Custom wrapper target: <code>${escapeHtml(wrapperTarget)}</code></div>
                    <div class="small-note">Inner section target: <code>${escapeHtml(sectionTarget)}</code></div>
                </div>
            </div>
            <div class="edit-layout-editor">
                <div class="edit-layout-editor-head">
                    <div>
                        <div class="edit-layout-meta-title">Layout template</div>
                        <div class="small-note">Manual editor for the outer wrapper template used by this virtual <code>.layout</code> page.</div>
                    </div>
                </div>
                <textarea class="form-textarea" id="edit-layout-primary-template" name="layout_primary_template"></textarea>
                <div class="edit-layout-overlay-grid">
                    <div>
                        <label class="edit-label" for="edit-layout-primary-css">Layout CSS</label>
                        <textarea class="form-textarea" id="edit-layout-primary-css" name="layout_primary_css"></textarea>
                    </div>
                    <div>
                        <label class="edit-label" for="edit-layout-primary-js">Layout JS</label>
                        <textarea class="form-textarea" id="edit-layout-primary-js" name="layout_primary_js"></textarea>
                    </div>
                </div>
            </div>

            <details class="edit-layout-advanced" ${sectionWasLocal ? 'open' : ''}>
                <summary class="edit-layout-advanced-summary">Inner work section (advanced)</summary>
                <div class="edit-layout-editor">
                    <div class="edit-layout-editor-head">
                        <div>
                            <div class="edit-layout-meta-title">Inner section partial</div>
                            <div class="small-note">Edit the wrapped <code>{{> ${escapeHtml(sectionName)}}</code> partial only when you need item-specific content inside the current layout.</div>
                        </div>
                    </div>
                    <textarea class="form-textarea" id="edit-content-template" name="content_template">${escapeHtml(layoutState.sectionTemplate || '')}</textarea>
                </div>
            </details>
        </details>
    `;

    const form = editPanel.querySelector('#editLayoutPanelForm');
    const statusEl = editPanel.querySelector('#editLayoutStatus');
    const backButton = editPanel.querySelector('#editLayoutBack');
    const moreButton = editPanel.querySelector('#editLayoutMore');
    const manualDetailsEl = editPanel.querySelector('#editLayoutManual');
    const presetEl = editPanel.querySelector('#edit-layout-preset');
    const modePreviewEl = editPanel.querySelector('#edit-layout-mode-preview');
    const sourcePreviewEl = editPanel.querySelector('#edit-layout-source-preview');
    const primaryTitleEl = editPanel.querySelector('#edit-layout-primary-title');
    const primaryHintEl = editPanel.querySelector('#edit-layout-primary-hint');
    const primaryTemplateEl = editPanel.querySelector('#edit-layout-primary-template');
    const primaryCssEl = editPanel.querySelector('#edit-layout-primary-css');
    const primaryJsEl = editPanel.querySelector('#edit-layout-primary-js');
    const contentTemplateEl = editPanel.querySelector('#edit-content-template');
    const promptRoot = editPanel.querySelector('#promptWindow');
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

    const currentSectionTemplate = layoutState.sectionTemplate || '';
    const drafts = {
        virtualTemplate: originalTemplate || '',
        virtualCss: originalCss || '',
        virtualJs: originalJs || '',
        localTemplate: localWrapperTemplate || '',
        localCss: localWrapperCss || '',
        localJs: localWrapperJs || '',
    };

    const currentPrimaryMode = () => {
        const preset = (presetEl?.value || 'actual').trim();
        if (preset === 'custom') {
            return 'local';
        }
        return hasVirtualSource ? 'virtual' : 'local';
    };

    const syncLayoutMode = () => {
        const preset = (presetEl?.value || 'actual').trim();
        const nextMode = preset === 'none'
            ? 'none'
            : preset === 'custom'
                ? 'custom-layout'
                : (originalEditable ? 'filesystem-layout' : 'poff-layout');
        const primaryMode = currentPrimaryMode();
        const isVirtual = primaryMode === 'virtual';
        const sourcePreview = isVirtual
            ? (originalEditable ? `Filesystem: ${originalTarget}` : 'PHP built-in poff-layout')
            : `Filesystem: ${wrapperTarget.replace(/\/template\.hbs$/, '')}`;

        if (modePreviewEl) {
            modePreviewEl.textContent = nextMode;
        }
        if (sourcePreviewEl) {
            sourcePreviewEl.textContent = sourcePreview;
        }
        if (primaryTitleEl) {
            primaryTitleEl.textContent = isVirtual ? 'Virtual layout' : 'Custom layout';
        }
        if (primaryHintEl) {
            if (isVirtual) {
                primaryHintEl.innerHTML = originalEditable
                    ? `Editing the inherited parent layout source <code>${escapeHtml(originalTarget)}</code>. Switch to <code>Custom</code> when you want to create a local <code>${escapeHtml(wrapperTarget)}</code>.`
                    : 'Showing the bundled poff-layout. It stays read-only until a parent .layout exists.';
            } else {
                primaryHintEl.innerHTML = `Editing the local wrapper override <code>${escapeHtml(wrapperTarget)}</code>.`;
            }
        }
        if (primaryTemplateEl) {
            primaryTemplateEl.value = isVirtual ? drafts.virtualTemplate : drafts.localTemplate;
            primaryTemplateEl.disabled = isVirtual && !originalEditable;
        }
        if (primaryCssEl) {
            primaryCssEl.value = isVirtual ? drafts.virtualCss : drafts.localCss;
            primaryCssEl.disabled = isVirtual && !originalEditable;
        }
        if (primaryJsEl) {
            primaryJsEl.value = isVirtual ? drafts.virtualJs : drafts.localJs;
            primaryJsEl.disabled = isVirtual && !originalEditable;
        }
    };

    const storePrimaryDraft = () => {
        const primaryMode = currentPrimaryMode();
        if (primaryMode === 'virtual') {
            drafts.virtualTemplate = primaryTemplateEl?.value ?? '';
            drafts.virtualCss = primaryCssEl?.value ?? '';
            drafts.virtualJs = primaryJsEl?.value ?? '';
            return;
        }
        drafts.localTemplate = primaryTemplateEl?.value ?? '';
        drafts.localCss = primaryCssEl?.value ?? '';
        drafts.localJs = primaryJsEl?.value ?? '';
    };

    if (presetEl) {
        presetEl.addEventListener('change', () => {
            storePrimaryDraft();
            syncLayoutMode();
        });
    }
    [primaryTemplateEl, primaryCssEl, primaryJsEl].forEach((field) => {
        if (field) {
            field.addEventListener('input', storePrimaryDraft);
        }
    });
    if (backButton && typeof onReturnToWork === 'function') {
        backButton.addEventListener('click', () => onReturnToWork());
    }
    if (moreButton && manualDetailsEl) {
        moreButton.addEventListener('click', () => {
            manualDetailsEl.open = true;
            manualDetailsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
    syncLayoutMode();

    if (form && typeof onSubmitLayout === 'function') {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            storePrimaryDraft();
            const payload = {
                layoutPreset: (presetEl?.value || 'actual').trim(),
            };
            const contentTemplateValue = contentTemplateEl?.value ?? '';
            if (sectionWasLocal || contentTemplateValue !== currentSectionTemplate) {
                payload.contentTemplate = contentTemplateValue;
            }
            if (currentPrimaryMode() === 'virtual') {
                if (originalEditable) {
                    payload.originalLayoutTarget = originalTarget;
                    payload.originalLayoutTemplate = drafts.virtualTemplate;
                    payload.originalLayoutCss = drafts.virtualCss;
                    payload.originalLayoutJs = drafts.virtualJs;
                }
            } else {
                payload.layoutTemplate = drafts.localTemplate;
                payload.layoutCss = drafts.localCss;
                payload.layoutJs = drafts.localJs;
            }

            await onSubmitLayout({
                payload,
                statusEl,
            });
        });
    }

    if (uploadDialog && openUploadDialogButton && typeof onUploadFiles === 'function' && typeof onCreateBlankFile === 'function') {
        const setUploadSummary = () => {
            const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
            if (!uploadSummaryEl) {
                return;
            }
            if ((uploadSourceEl?.value || 'upload') === 'blank') {
                const name = blankFileNameEl?.value?.trim() || '';
                uploadSummaryEl.textContent = name ? `Will create: ${name}` : 'Enter a file name.';
                return;
            }
            uploadSummaryEl.textContent = files.length ? files.map((file) => file.name).join(', ') : 'No files selected.';
        };
        const syncUploadMode = () => {
            const mode = uploadSourceEl?.value || 'upload';
            if (uploadFilesWrapEl) {
                uploadFilesWrapEl.hidden = mode !== 'upload';
            }
            if (blankFileWrapEl) {
                blankFileWrapEl.hidden = mode !== 'blank';
            }
            if (uploadSubmitButton) {
                uploadSubmitButton.textContent = mode === 'blank' ? 'Create blank file' : 'Upload';
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
                            if (statusEl) {
                                statusEl.textContent = 'Enter a file name.';
                                statusEl.className = 'edit-status';
                            }
                            return;
                        }
                        await onCreateBlankFile({ source, fileName, statusEl });
                    } else {
                        const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
                        if (files.length === 0) {
                            if (statusEl) {
                                statusEl.textContent = 'Choose at least one file.';
                                statusEl.className = 'edit-status';
                            }
                            return;
                        }
                        await onUploadFiles({ source, files, statusEl });
                    }
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
        syncUploadMode();
    }

    return { statusEl, promptRoot };
}

export function renderEditPanel({
    editPanel,
    editRequested,
    config,
    status,
    contentTargetLabel,
    onTitleInput,
    onDescriptionInput,
    onSubmit,
    onToggleDrawer,
    onOpenLayoutPage,
    onReturnToWork,
    onSubmitLayout,
    onUploadFiles,
    onCreateBlankFile,
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

    if (status?.target === 'layout') {
        return renderEditLayoutPanel({
            editPanel,
            config,
            status,
            contentTargetLabel,
            onSubmitLayout,
            onReturnToWork,
            onUploadFiles,
            onCreateBlankFile,
        });
    }

    const label = status?.target === 'file' ? 'Edit mode (file)' : 'Edit mode (folder)';
    const settings = loadPromptSettings();
    const overlayState = layoutOverlayState(config, status);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isFileTarget = status?.target === 'file';
    const isEmptyFolder = !isFileTarget && treeItems.length === 0;
    const addContentHint = isEmptyFolder
        ? 'This folder is empty. Upload a file or create a blank file to start.'
        : (isFileTarget
            ? `Add content to the containing folder: ${contentTargetLabel || '.'}`
            : 'Upload files or create a blank file in this folder.');

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
                <div class="small-note">${escapeHtml(overlayState.wrapperSourceLabel)}</div>
                <div class="small-note">Inherited parent layout: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code></div>
                <div class="small-note">Current mode: <code>${escapeHtml(overlayState.layoutState.mode)}</code></div>
            </div>
            <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
        </div>
        <div class="edit-upload-launch ${isEmptyFolder ? 'edit-upload-launch-empty' : ''}">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Add content</div>
                <div class="small-note">${escapeHtml(addContentHint)}</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add content</button>
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
                            <option value="blank">Blank file</option>
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
        <details class="prompt-template-viewer">
            <summary class="prompt-template-viewer-summary">Current template code</summary>
            <div class="prompt-template-viewer-body">
                <div class="small-note">${status?.target === 'file' ? 'Current wrapped file partial' : 'Current wrapped folder partial'}</div>
                <textarea class="form-textarea prompt-template-code" readonly spellcheck="false" placeholder="No template loaded yet.">${escapeHtml(overlayState.layoutState.sectionTemplate || '')}</textarea>
            </div>
        </details>
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
    const uploadFilesWrapEl = editPanel.querySelector('#editUploadFilesWrap');
    const blankFileWrapEl = editPanel.querySelector('#editBlankFileWrap');
    const blankFileNameEl = editPanel.querySelector('#edit-blank-file-name');

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
    if (changeLayoutButton && typeof onOpenLayoutPage === 'function') {
        changeLayoutButton.addEventListener('click', () => onOpenLayoutPage());
    }

    if (uploadDialog && openUploadDialogButton && typeof onUploadFiles === 'function' && typeof onCreateBlankFile === 'function') {
        const setUploadSummary = () => {
            const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
            if (!uploadSummaryEl) {
                return;
            }
            if ((uploadSourceEl?.value || 'upload') === 'blank') {
                const name = blankFileNameEl?.value?.trim() || '';
                uploadSummaryEl.textContent = name ? `Will create: ${name}` : 'Enter a file name.';
                return;
            }
            uploadSummaryEl.textContent = files.length ? files.map((file) => file.name).join(', ') : 'No files selected.';
        };
        const syncUploadMode = () => {
            const mode = uploadSourceEl?.value || 'upload';
            if (uploadFilesWrapEl) {
                uploadFilesWrapEl.hidden = mode !== 'upload';
            }
            if (blankFileWrapEl) {
                blankFileWrapEl.hidden = mode !== 'blank';
            }
            if (uploadSubmitButton) {
                uploadSubmitButton.textContent = mode === 'blank' ? 'Create blank file' : 'Upload';
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
                            if (statusEl) {
                                statusEl.textContent = 'Enter a file name.';
                                statusEl.className = 'edit-status';
                            }
                            return;
                        }
                        await onCreateBlankFile({
                            source,
                            fileName,
                            statusEl,
                        });
                    } else {
                        const files = uploadFilesEl?.files ? Array.from(uploadFilesEl.files) : [];
                        if (files.length === 0) {
                            if (statusEl) {
                                statusEl.textContent = 'Choose at least one file.';
                                statusEl.className = 'edit-status';
                            }
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
                    if (statusEl) {
                        statusEl.textContent = err.message || 'Upload failed.';
                        statusEl.className = 'edit-status';
                    }
                } finally {
                    uploadSubmitButton.disabled = false;
                }
            });
        }
        syncUploadMode();
    }

    return { statusEl, promptRoot };
}

export function renderEditLayoutOverlay({
    editLayoutOverlay,
    editRequested,
    open,
    config,
    status,
    onClose,
    onSubmit,
}) {
    if (!editLayoutOverlay) {
        return { overlayStatus: null };
    }
    if (!editRequested || !open) {
        editLayoutOverlay.hidden = true;
        editLayoutOverlay.innerHTML = '';
        return { overlayStatus: null };
    }
    if (!config || status?.error || !status?.allowed) {
        editLayoutOverlay.hidden = true;
        editLayoutOverlay.innerHTML = '';
        return { overlayStatus: null };
    }

    const overlayState = layoutOverlayState(config, status);
    const {
        layoutState,
        sectionName,
        localLayoutDirectory,
        wrapperTarget,
        sectionTarget,
        wrapperWasLocal,
        sectionWasLocal,
        originalTarget,
        originalEditable,
        originalUsesLocal,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        originalTemplate,
        originalCss,
        originalJs,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
    } = overlayState;

    const layoutPresetOptions = [
        { value: 'actual', label: 'Actual' },
        { value: 'none', label: 'None' },
        { value: 'custom', label: 'Custom' },
    ];
    const hasVirtualSource = !wrapperWasLocal && !originalUsesLocal;

    editLayoutOverlay.hidden = false;
    editLayoutOverlay.innerHTML = `
        <div class="edit-layout-overlay-shell">
            <div class="drawer-header">
                <div>
                    <h4 class="drawer-title">Layout overlay</h4>
                    <div class="small-note">Inherited layouts open as one virtual layout target until a real local <code>.layout</code> exists.</div>
                </div>
                <button type="button" class="drawer-close" id="editLayoutOverlayClose">&times;</button>
            </div>
            <div class="edit-status" id="editLayoutOverlayStatus"></div>
            <form id="editLayoutOverlayForm" class="edit-layout-overlay-form">
                <div class="edit-layout-overlay-grid">
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Mode</div>
                        <label class="edit-label" for="edit-layout-preset">Layout select</label>
                        <select class="form-select" id="edit-layout-preset" name="layout_preset">
                            ${layoutPresetOptions.map((option) => `
                                <option value="${option.value}" ${layoutState.preset === option.value ? 'selected' : ''}>${option.label}</option>
                            `).join('')}
                        </select>
                        <div class="small-note">Resolved mode: <code id="edit-layout-mode-preview">${escapeHtml(layoutState.mode)}</code></div>
                        <div class="small-note">Resolved wrapper: <code>${escapeHtml(wrapperSourceLabel)}</code></div>
                    </div>
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Inheritance</div>
                        <div class="small-note">Inherited parent layout: <code>${escapeHtml(inheritedLayoutLabel)}</code></div>
                        <div class="small-note">PHP built-in: <code>poff-layout.hbs</code> from the bundled templates</div>
                        <div class="small-note">Wrapped inner partial: <code>${escapeHtml(layoutState.sectionDirectory ? `${layoutState.sectionDirectory}/${sectionName}.hbs` : `built-in ${sectionName}.hbs`)}</code></div>
                    </div>
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Targets</div>
                        <div class="small-note">Virtual/original target: <code>${escapeHtml(originalLabel)}</code></div>
                        <div class="small-note">Custom layout target: <code>${escapeHtml(wrapperTarget)}</code></div>
                        <div class="small-note">Advanced inner partial target: <code>${escapeHtml(sectionTarget)}</code></div>
                    </div>
                </div>

                <div class="edit-layout-editor">
                    <div class="edit-layout-editor-head">
                        <div>
                            <div class="edit-layout-meta-title" id="edit-layout-primary-title"></div>
                            <div class="small-note" id="edit-layout-primary-hint"></div>
                        </div>
                    </div>
                    <textarea class="form-textarea" id="edit-layout-primary-template" name="layout_primary_template"></textarea>
                    <div class="edit-layout-overlay-grid">
                        <div>
                            <label class="edit-label" for="edit-layout-primary-css">Layout CSS</label>
                            <textarea class="form-textarea" id="edit-layout-primary-css" name="layout_primary_css"></textarea>
                        </div>
                        <div>
                            <label class="edit-label" for="edit-layout-primary-js">Layout JS</label>
                            <textarea class="form-textarea" id="edit-layout-primary-js" name="layout_primary_js"></textarea>
                        </div>
                    </div>
                </div>

                <details class="edit-layout-advanced" ${sectionWasLocal ? 'open' : ''}>
                    <summary class="edit-layout-advanced-summary">Inner work section (advanced)</summary>
                    <div class="edit-layout-editor">
                        <div class="edit-layout-editor-head">
                            <div>
                                <div class="edit-layout-meta-title">Inner section partial</div>
                                <div class="small-note">Edit the wrapped <code>{{> ${escapeHtml(sectionName)}}</code> partial only when you need item-specific content inside the current layout.</div>
                            </div>
                        </div>
                        <textarea class="form-textarea" id="edit-content-template" name="content_template">${escapeHtml(layoutState.sectionTemplate || '')}</textarea>
                    </div>
                </details>

                <div class="edit-inline-actions">
                    <button class="btn" type="submit">Save layout</button>
                    <button class="btn btn-secondary" type="button" id="editLayoutOverlayCancel">Close</button>
                </div>
            </form>
        </div>
    `;

    const form = editLayoutOverlay.querySelector('#editLayoutOverlayForm');
    const statusEl = editLayoutOverlay.querySelector('#editLayoutOverlayStatus');
    const closeButton = editLayoutOverlay.querySelector('#editLayoutOverlayClose');
    const cancelButton = editLayoutOverlay.querySelector('#editLayoutOverlayCancel');
    const presetEl = editLayoutOverlay.querySelector('#edit-layout-preset');
    const modePreviewEl = editLayoutOverlay.querySelector('#edit-layout-mode-preview');
    const primaryTitleEl = editLayoutOverlay.querySelector('#edit-layout-primary-title');
    const primaryHintEl = editLayoutOverlay.querySelector('#edit-layout-primary-hint');
    const primaryTemplateEl = editLayoutOverlay.querySelector('#edit-layout-primary-template');
    const primaryCssEl = editLayoutOverlay.querySelector('#edit-layout-primary-css');
    const primaryJsEl = editLayoutOverlay.querySelector('#edit-layout-primary-js');
    const contentTemplateEl = editLayoutOverlay.querySelector('#edit-content-template');

    const currentSectionTemplate = layoutState.sectionTemplate || '';
    const drafts = {
        virtualTemplate: originalTemplate || '',
        virtualCss: originalCss || '',
        virtualJs: originalJs || '',
        localTemplate: localWrapperTemplate || '',
        localCss: localWrapperCss || '',
        localJs: localWrapperJs || '',
    };

    const currentPrimaryMode = () => {
        const preset = (presetEl?.value || 'actual').trim();
        if (preset === 'custom') {
            return 'local';
        }
        return hasVirtualSource ? 'virtual' : 'local';
    };

    const syncLayoutMode = () => {
        const preset = (presetEl?.value || 'actual').trim();
        const nextMode = preset === 'none'
            ? 'none'
            : preset === 'custom'
                ? 'custom-layout'
                : (originalEditable ? 'filesystem-layout' : 'poff-layout');
        const primaryMode = currentPrimaryMode();
        const isVirtual = primaryMode === 'virtual';

        if (modePreviewEl) {
            modePreviewEl.textContent = nextMode;
        }
        if (primaryTitleEl) {
            primaryTitleEl.textContent = isVirtual ? 'Virtual layout' : 'Custom layout';
        }
        if (primaryHintEl) {
            if (isVirtual) {
                primaryHintEl.innerHTML = originalEditable
                    ? `Editing the inherited parent layout source <code>${escapeHtml(originalTarget)}</code>. Switch to <code>Custom</code> when you want to create a local <code>${escapeHtml(wrapperTarget)}</code>.`
                    : 'Showing the bundled poff-layout. It stays read-only until a parent .layout exists.';
            } else {
                primaryHintEl.innerHTML = `Editing the local wrapper override <code>${escapeHtml(wrapperTarget)}</code>.`;
            }
        }
        if (primaryTemplateEl) {
            primaryTemplateEl.value = isVirtual ? drafts.virtualTemplate : drafts.localTemplate;
            primaryTemplateEl.disabled = isVirtual && !originalEditable;
        }
        if (primaryCssEl) {
            primaryCssEl.value = isVirtual ? drafts.virtualCss : drafts.localCss;
            primaryCssEl.disabled = isVirtual && !originalEditable;
        }
        if (primaryJsEl) {
            primaryJsEl.value = isVirtual ? drafts.virtualJs : drafts.localJs;
            primaryJsEl.disabled = isVirtual && !originalEditable;
        }
    };

    const storePrimaryDraft = () => {
        const primaryMode = currentPrimaryMode();
        if (primaryMode === 'virtual') {
            drafts.virtualTemplate = primaryTemplateEl?.value ?? '';
            drafts.virtualCss = primaryCssEl?.value ?? '';
            drafts.virtualJs = primaryJsEl?.value ?? '';
            return;
        }
        drafts.localTemplate = primaryTemplateEl?.value ?? '';
        drafts.localCss = primaryCssEl?.value ?? '';
        drafts.localJs = primaryJsEl?.value ?? '';
    };

    if (presetEl) {
        presetEl.addEventListener('change', () => {
            storePrimaryDraft();
            syncLayoutMode();
        });
    }
    [primaryTemplateEl, primaryCssEl, primaryJsEl].forEach((field) => {
        if (field) {
            field.addEventListener('input', storePrimaryDraft);
        }
    });
    syncLayoutMode();

    if (closeButton && typeof onClose === 'function') {
        closeButton.addEventListener('click', () => onClose());
    }
    if (cancelButton && typeof onClose === 'function') {
        cancelButton.addEventListener('click', () => onClose());
    }

    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            storePrimaryDraft();
            const preset = (presetEl?.value || 'actual').trim();
            const payload = {
                layoutPreset: preset,
            };

            const contentTemplateValue = contentTemplateEl?.value ?? '';
            if (sectionWasLocal || contentTemplateValue !== currentSectionTemplate) {
                payload.contentTemplate = contentTemplateValue;
            }

            if (currentPrimaryMode() === 'virtual') {
                if (originalEditable) {
                    payload.originalLayoutTarget = originalTarget;
                    payload.originalLayoutTemplate = drafts.virtualTemplate;
                    payload.originalLayoutCss = drafts.virtualCss;
                    payload.originalLayoutJs = drafts.virtualJs;
                }
            } else {
                payload.layoutTemplate = drafts.localTemplate;
                payload.layoutCss = drafts.localCss;
                payload.layoutJs = drafts.localJs;
            }

            await onSubmit({
                payload,
                statusEl,
            });
            if (typeof onClose === 'function') {
                onClose();
            }
        });
    }

    return { overlayStatus: statusEl };
}
