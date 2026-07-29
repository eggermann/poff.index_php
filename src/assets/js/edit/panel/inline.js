import { escapeHtml } from '../../core/utils.js';
import { loadPromptSettings } from '../prompt/storage.js';
import { renderPromptWindow } from '../prompt-window.js';
import {
    applyWorkFieldTypeDefaults,
    extractWorkFields,
    normalizeWorkField,
} from '../work-fields.js';
import { bindWorkCategoryControls } from './categories.js';
import {
    bindStoredDetailsState,
    layoutOverlayState,
    syncPromptDock,
} from './shared.js';
import { bindUploadDialog, renderUploadSectionHtml } from './upload.js';
import { renderEditLayoutPanel } from './layout.js';
import {
    PASSWORD_DETAILS_STORAGE_KEY,
    RESERVED_WORK_CONFIG_KEYS,
    readMediaConfigFromForm,
    renderConverterSection,
    renderDirectFileLink,
    renderPasswordChangeSection,
    renderWorkConfigFieldsSection,
} from './inline-sections.js';
import { createWorkFieldEditor } from './inline-work-fields.js';

export function renderEditPanel({
    editPanel,
    editRequested,
    config,
    status,
    contentTargetLabel,
    activeWorkName,
    onTitleInput,
    onDescriptionInput,
    onWorkFieldsInput,
    onMediaInput,
    onSubmit,
    onToggleDrawer,
    onOpenLayoutPage,
    onReturnToWork,
    onSubmitLayout,
    onLayoutPresetChange,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onCreateLink,
    onResetFolderWork,
    onDeleteTarget,
    onChangePassword,
    onConvertWork,
    onCreateConverter,
    onLoadConverterNode,
}) {
    if (!editPanel) {
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    if (!editRequested) {
        editPanel.hidden = true;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!status?.auth?.canEdit && status?.auth?.editModeAllowed !== false && status?.target !== 'file') {
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status" id="editInlineStatus">${escapeHtml(status?.error || '')}</div>
            <div class="small-note">Enter the editor password to unlock editing.</div>
        `;
        const statusEl = editPanel.querySelector('#editInlineStatus');
        syncPromptDock();
        return { statusEl, promptRoot: null };
    }
    if (!config || status?.error) {
        const authMessage = status?.auth && !status.auth.canEdit
            ? (status?.error || 'Enter the editor password to unlock editing.')
            : null;
        const message = status?.error || 'Edit mode is unavailable.';
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(authMessage || message)}</div>
        `;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    if (!status?.allowed) {
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create <code>.poff-auth.php</code> with a password hash to enable editing. Add <code>edit.not-allow</code> to stop inheritance in a subtree.</div>
        `;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }

    if (status?.target === 'layout') {
        return renderEditLayoutPanel({
            editPanel,
            config,
            status,
            contentTargetLabel,
            activeWorkName,
            onSubmitLayout,
            onLayoutPresetChange,
            onReturnToWork,
            onUploadFiles,
            onCreateBlankFile,
            onCreateFolder,
            onCreateLink,
        });
    }

    const label = status?.target === 'file' ? 'Edit mode (file)' : 'Edit mode (folder)';
    const settings = loadPromptSettings();
    const overlayState = layoutOverlayState(config, status);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isFileTarget = status?.target === 'file';
    const isEmptyFolder = !isFileTarget && treeItems.length === 0;
    const initialWorkFields = extractWorkFields(config?.work || {}).map((field, index) => applyWorkFieldTypeDefaults(normalizeWorkField(field, index), field.type));
    const workFieldEditor = createWorkFieldEditor({
        editPanel,
        onWorkFieldsInput,
        initialState: initialWorkFields,
    });
    const uploadSectionHtml = renderUploadSectionHtml({
        isFileTarget,
        isEmptyFolder,
        externalOnly: false,
        showLauncher: false,
        activeWorkName,
    });
    const relativePath = String(config?.__poffRelativePath || '').trim();

    editPanel.innerHTML = `
        <h3 class="edit-panel-title">${label}</h3>
        ${renderDirectFileLink(config, status)}
        <div class="edit-status" id="editInlineStatus"></div>
        ${renderPasswordChangeSection({
            ...status,
            relativePath,
        })}
        <form id="inlineEditForm" class="edit-inline">
            <div>
                <label class="edit-label" for="edit-title">Title</label>
                <input class="form-input" id="edit-title" type="text" name="title" value="${escapeHtml(config.title || '')}">
            </div>
            <div>
                <label class="edit-label" for="edit-description">Description</label>
                <textarea class="form-textarea" id="edit-description" name="description">${escapeHtml(config.description || '')}</textarea>
            </div>
            ${status?.target === 'file' || status?.target === 'folder' || (config?.work && typeof config.work === 'object' && Object.keys(config.work).some((key) => !RESERVED_WORK_CONFIG_KEYS.has(key) || key === 'type'))
                ? renderWorkConfigFieldsSection(config)
                : ''}
            <div class="edit-work-fields">
                <div class="edit-work-fields-header">
                    <div>
                        <div class="edit-work-fields-title">Work fields</div>
                        <div class="small-note">Add extra values below Description. Saved as <code>work.&lt;name&gt;</code> and shown in prompt context.</div>
                    </div>
                    <button class="btn btn-secondary edit-work-fields-add" type="button" id="editWorkFieldAdd" aria-label="Add work field">+</button>
                </div>
                <div class="edit-work-fields-list" id="editWorkFieldsList">
                    ${workFieldEditor.renderRows()}
                </div>
            </div>
       
            <div class="edit-inline-actions">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" id="editMoreToggle">More...</button>
                <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
                ${!isFileTarget && typeof onResetFolderWork === 'function' ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editResetFolderWork">
                    Reset
                </button>
                ` : ''}
                ${typeof onDeleteTarget === 'function' ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editDeleteTarget">
                    <img class="block" src="https://cdn.jsdelivr.net/npm/heroicons@2.2.0/24/outline/trash.svg" alt="" width="16" height="16">
                    Delete
                </button>
                ` : ''}
            </div>
            <div class="small-note">Layout: ${escapeHtml(overlayState.wrapperSourceLabel)} · parent: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code> · mode: <code>${escapeHtml(overlayState.displayMode)}</code></div>

        </form>
        <div class="edit-panel-stack">
            ${uploadSectionHtml}
            ${status?.target === 'file' ? renderConverterSection(config) : ''}
            ${renderPromptWindow(settings, { mode: isFileTarget ? 'file' : 'folder' })}
        </div>
    `;

    const form = editPanel.querySelector('#inlineEditForm');
    const passwordForm = editPanel.querySelector('#editPasswordForm');
    const passwordStatusEl = editPanel.querySelector('#editPasswordStatus');
    const statusEl = editPanel.querySelector('#editInlineStatus');
    const moreToggle = editPanel.querySelector('#editMoreToggle');
    const passwordDetailsEl = editPanel.querySelector('#editPasswordDetails');
    const deleteTargetButton = editPanel.querySelector('#editDeleteTarget');
    const resetFolderWorkButton = editPanel.querySelector('#editResetFolderWork');
    const changeLayoutButton = editPanel.querySelector('#editChangeLayout');
    const titleInput = editPanel.querySelector('#edit-title');
    const descInput = editPanel.querySelector('#edit-description');
    const addWorkFieldButton = editPanel.querySelector('#editWorkFieldAdd');
    const convertButton = editPanel.querySelector('#editConvertWork');
    const createConverterButton = editPanel.querySelector('#editCreateConverter');
    const loadConverterNodeButton = editPanel.querySelector('#editLoadConverterNode');
    const converterNodeUrlInput = editPanel.querySelector('#edit-converter-node-url');
    const promptRoot = editPanel.querySelector('#promptLayer');
    syncPromptDock(promptRoot);

    if (passwordDetailsEl) {
        bindStoredDetailsState(passwordDetailsEl, PASSWORD_DETAILS_STORAGE_KEY);
    }

    const syncMediaState = () => {
        if (typeof onMediaInput !== 'function' || !form) {
            return;
        }
        onMediaInput(readMediaConfigFromForm(form, config?.work || {}));
    };
    const syncConverterDependentOptions = ({ preserveCurrentValues = false } = {}) => {
        const converterSelect = form?.elements?.converter_id;
        const formatSelect = form?.elements?.converter_format;
        const qualitySelect = form?.elements?.converter_quality;
        const saveModeSelect = form?.elements?.converter_save_mode;
        if (!(converterSelect instanceof HTMLSelectElement)) {
            return;
        }
        const selectedOption = converterSelect.selectedOptions && converterSelect.selectedOptions[0] ? converterSelect.selectedOptions[0] : null;
        let ui = {};
        let defaults = {};
        try {
            ui = JSON.parse(selectedOption?.dataset?.converterUi || '{}');
        } catch {
            ui = {};
        }
        try {
            defaults = JSON.parse(selectedOption?.dataset?.converterDefaults || '{}');
        } catch {
            defaults = {};
        }
        const formats = Array.isArray(ui?.format?.options) && ui.format.options.length
            ? ui.format.options
            : String(selectedOption?.dataset?.converterFormats || '')
                .split(',')
                .map((item) => item.trim())
                .filter(Boolean);
        const qualityOptions = Array.isArray(ui?.quality?.options) && ui.quality.options.length
            ? ui.quality.options
            : ['preview', 'default', 'archival', 'small-web'];
        const saveModeOptions = Array.isArray(ui?.saveMode?.options) && ui.saveMode.options.length
            ? ui.saveMode.options
            : ['new-hidden-work', 'replace-generated-work', 'temporary-preview-only'];
        const syncSelectOptions = (select, options, preferredValue, labeler = null) => {
            if (!(select instanceof HTMLSelectElement)) {
                return;
            }
            const nextOptions = Array.isArray(options) && options.length ? options : [''];
            const currentValue = String(select.value || '').trim();
            const normalizedPreferred = String(preferredValue || '').trim();
            const selected = preserveCurrentValues && nextOptions.includes(currentValue)
                ? currentValue
                : (nextOptions.includes(normalizedPreferred) ? normalizedPreferred : String(nextOptions[0] || ''));
            select.innerHTML = nextOptions
                .map((item) => {
                    const value = String(item || '');
                    const label = typeof labeler === 'function' ? labeler(value) : value;
                    return `<option value="${escapeHtml(value)}"${value === selected ? ' selected' : ''}>${escapeHtml(label)}</option>`;
                })
                .join('');
        };

        syncSelectOptions(
            formatSelect,
            formats.length ? formats : ['source'],
            defaults.format || 'source',
            (item) => item === 'source' ? 'same as source' : item
        );
        syncSelectOptions(
            qualitySelect,
            qualityOptions,
            defaults.quality || 'default'
        );
        syncSelectOptions(
            saveModeSelect,
            saveModeOptions,
            defaults.saveMode || 'new-hidden-work'
        );
    };
    const syncConvertButtonState = () => {
        const converterSelect = form?.elements?.converter_id;
        if (!(convertButton instanceof HTMLButtonElement) || !(converterSelect instanceof HTMLSelectElement)) {
            return;
        }
        convertButton.disabled = String(converterSelect.value || '').trim() === '';
    };

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
    if (addWorkFieldButton) {
        addWorkFieldButton.addEventListener('click', () => {
            workFieldEditor.addWorkField();
        });
    }
    editPanel.querySelectorAll('[data-work-config-field], #edit-work-type, #edit-converter-id, #edit-converter-quality, #edit-converter-format, #edit-converter-save-mode').forEach((input) => {
        if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
            return;
        }
        input.addEventListener('input', syncMediaState);
        input.addEventListener('change', () => {
            if (input.id === 'edit-converter-id') {
                syncConverterDependentOptions({ preserveCurrentValues: false });
                syncConvertButtonState();
            }
            syncMediaState();
        });
    });
    bindWorkCategoryControls({
        editPanel,
        onMediaInput,
    });
    syncConverterDependentOptions({ preserveCurrentValues: true });
    syncConvertButtonState();
    syncMediaState();
    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            workFieldEditor.syncWorkFieldsFromDom();
            onSubmit({
                elements: form.elements,
                form,
                statusEl,
            });
        });
    }
    if (passwordForm && typeof onChangePassword === 'function') {
        passwordForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            await onChangePassword({
                elements: passwordForm.elements,
                form: passwordForm,
                statusEl: passwordStatusEl,
            });
        });
    }
    if (moreToggle && typeof onToggleDrawer === 'function') {
        moreToggle.addEventListener('click', () => onToggleDrawer());
    }
    if (changeLayoutButton && typeof onOpenLayoutPage === 'function') {
        changeLayoutButton.addEventListener('click', () => onOpenLayoutPage());
    }
    if (createConverterButton && typeof onCreateConverter === 'function') {
        createConverterButton.addEventListener('click', async () => {
            await onCreateConverter({ statusEl, form });
        });
    }
    if (loadConverterNodeButton && typeof onLoadConverterNode === 'function') {
        loadConverterNodeButton.addEventListener('click', async () => {
            await onLoadConverterNode({
                nodeUrl: String(converterNodeUrlInput?.value || '').trim(),
                statusEl,
            });
        });
        const available = Array.isArray(config?.converterCatalog?.available) ? config.converterCatalog.available : [];
        const hasRemote = available.some((item) => item?.engine === 'remote-node');
        if (config?.converterCatalog?.autoLoadRemote === true && !hasRemote) {
            Promise.resolve().then(() => loadConverterNodeButton.click());
        }
    }
    if (resetFolderWorkButton && typeof onResetFolderWork === 'function') {
        resetFolderWorkButton.addEventListener('click', async () => {
            const confirmed = window.confirm('Reset this folder work to the default inherited layout? This removes the local .layout override.');
            if (!confirmed) {
                return;
            }
            await onResetFolderWork({ statusEl });
        });
    }
    if (deleteTargetButton && typeof onDeleteTarget === 'function') {
        deleteTargetButton.addEventListener('click', async () => {
            const confirmed = window.confirm('Delete this item? This cannot be undone.');
            if (!confirmed) {
                return;
            }
            await onDeleteTarget({ statusEl });
        });
    }
    if (convertButton && typeof onConvertWork === 'function') {
        convertButton.addEventListener('click', async () => {
            syncMediaState();
            await onConvertWork({
                statusEl,
                form,
                converter: readMediaConfigFromForm(form, config?.work || {}).converter || null,
                convertAllOfType: editPanel.querySelector('#edit-converter-all-of-type')?.checked === true,
                visibilityMode: editPanel.querySelector('#edit-converter-hide-originals')?.checked === false ? 'both' : 'converted-only',
                hideThumbnails: editPanel.querySelector('#edit-converter-hide-thumbnails')?.checked !== false,
                mimeType: String(config?.conversionScope?.mimeType || config?.mimeType || '').trim(),
            });
        });
    }

    bindUploadDialog({
        editPanel,
        statusEl,
        uploadLimits: status?.uploadLimits || null,
        externalOnly: false,
        onUploadFiles,
        onCreateBlankFile,
        onCreateFolder,
        onCreateLink,
    });

    return { statusEl, promptRoot };
}
