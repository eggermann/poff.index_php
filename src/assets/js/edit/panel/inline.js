import { escapeHtml } from '../../core/utils.js';
import { loadPromptSettings } from '../prompt/storage.js';
import { renderPromptWindow } from '../prompt-window.js';
import {
    applyWorkFieldTypeDefaults,
    extractWorkFields,
    normalizeWorkField,
} from '../work-fields.js';
import { bindWorkCategoryControls, normalizeWorkCategories } from './categories.js';
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
    renderRemoteWorkPresentationSection,
    renderWorkConfigFieldsSection,
} from './inline-sections.js';
import { createWorkFieldEditor } from './inline-work-fields.js';

function renderPoffRegistrationSection(config, isFileTarget) {
    if (isFileTarget) {
        return '';
    }
    const work = config?.work && typeof config.work === 'object' ? config.work : {};
    const categories = normalizeWorkCategories(work.categories ?? work.category ?? []);
    const filterLabel = categories.length ? categories.join(' · ') : 'current work type';
    return `
        <div class="edit-poff-register">
            <div>
                <div class="edit-work-fields-title">Poff network</div>
                <div class="small-note">Share work catalog and converter offers. Filters: ${escapeHtml(filterLabel)}.</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editRegisterPoff">Register with Poff</button>
        </div>
    `;
}

function renderExternalSubmissionsSection(config, isFileTarget) {
    if (isFileTarget) {
        return '';
    }
    const settings = config?.externalSubmissions && typeof config.externalSubmissions === 'object'
        ? config.externalSubmissions
        : {};
    const enabled = settings.enabled === true;
    const whitelistEnabled = settings.whitelistEnabled !== false;
    const whitelist = Array.isArray(settings.whitelist) ? settings.whitelist.join('\n') : '';
    const maxPending = Math.min(20, Math.max(1, Number(settings.maxPending || 5)));
    return `
        <fieldset class="edit-external-submissions">
            <legend class="edit-work-fields-title">External work submissions</legend>
            <label class="edit-inline-toggle" for="edit-external-submissions-enabled">
                <input id="edit-external-submissions-enabled" type="checkbox" name="external_submissions_enabled"${enabled ? ' checked' : ''}>
                <span><strong>Allow external work</strong><small>Visitors may submit a Pageoff work URL. It stays hidden until you approve it in Settings.</small></span>
            </label>
            <div class="edit-external-submissions-options"${enabled ? '' : ' hidden'}>
                <label class="edit-inline-toggle" for="edit-external-submissions-whitelist-enabled">
                    <input id="edit-external-submissions-whitelist-enabled" type="checkbox" name="external_submissions_whitelist_enabled"${whitelistEnabled ? ' checked' : ''}>
                    <span><strong>Use host whitelist</strong><small>Only accept URLs from listed Pageoff hosts.</small></span>
                </label>
                <div id="editExternalWhitelistWrap"${whitelistEnabled ? '' : ' hidden'}>
                    <label class="edit-label" for="edit-external-submissions-whitelist">Allowed hosts</label>
                    <textarea class="form-textarea" id="edit-external-submissions-whitelist" name="external_submissions_whitelist" rows="3" placeholder="pageoff.de&#10;artist.example">${escapeHtml(whitelist)}</textarea>
                    <div class="small-note">One hostname per line. No path needed.</div>
                </div>
                <div>
                    <label class="edit-label" for="edit-external-submissions-max">Maximum pending submissions</label>
                    <input class="form-input" id="edit-external-submissions-max" type="number" name="external_submissions_max" min="1" max="20" value="${maxPending}">
                </div>
            </div>
        </fieldset>
    `;
}

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
    onImportRemote,
    onRegisterPoff,
    onResetFolderWork,
    onChangePassword,
    onConvertWork,
    onCreateConverter,
    onLoadConverterNode,
    onInspectRemotePresentation,
    onApplyRemotePresentation,
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
    const canRenderPublicExternalSubmission = !status?.auth?.canEdit
        && status?.auth?.editModeAllowed !== false
        && status?.target !== 'file'
        && config?.externalSubmissions?.enabled === true;
    editPanel.hidden = false;
    if (canRenderPublicExternalSubmission) {
        const treeItems = Array.isArray(config?.tree) ? config.tree : [];
        const uploadSectionHtml = renderUploadSectionHtml({
            isFileTarget: false,
            isEmptyFolder: treeItems.length === 0,
            externalOnly: true,
            showLauncher: true,
            activeWorkName,
        });
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Submit external work</h3>
            <div class="edit-status" id="editInlineStatus">This host reviews every submission before publishing it.</div>
            ${uploadSectionHtml}
        `;
        const statusEl = editPanel.querySelector('#editInlineStatus');
        bindUploadDialog({
            editPanel,
            statusEl,
            uploadLimits: status?.uploadLimits || null,
            externalOnly: true,
            onUploadFiles,
            onCreateBlankFile,
            onCreateFolder,
            onCreateLink,
            onImportRemote,
        });
        syncPromptDock();
        return { statusEl, promptRoot: null };
    }
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
            onImportRemote,
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
            ${renderExternalSubmissionsSection(config, isFileTarget)}
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
                <button class="btn btn-secondary" type="button" id="editMoreToggle">Settings</button>
                <button class="btn btn-secondary" type="button" id="editChangeLayout">Layout</button>
                ${!isFileTarget && (overlayState.wrapperWasLocal || overlayState.sectionWasLocal) && typeof onResetFolderWork === 'function' ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editResetFolderWork">
                    Reset layout
                </button>
                ` : ''}
            </div>
            <details class="edit-inline-meta">
                <summary>Layout source</summary>
                <div class="small-note">Layout: ${escapeHtml(overlayState.wrapperSourceLabel)} · parent: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code> · mode: <code>${escapeHtml(overlayState.displayMode)}</code></div>
            </details>

        </form>
        ${renderPoffRegistrationSection(config, isFileTarget)}
        <div class="edit-panel-stack">
            ${uploadSectionHtml}
            ${status?.target === 'file' ? renderRemoteWorkPresentationSection(config) : ''}
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
    const resetFolderWorkButton = editPanel.querySelector('#editResetFolderWork');
    const changeLayoutButton = editPanel.querySelector('#editChangeLayout');
    const registerPoffButton = editPanel.querySelector('#editRegisterPoff');
    const titleInput = editPanel.querySelector('#edit-title');
    const descInput = editPanel.querySelector('#edit-description');
    const addWorkFieldButton = editPanel.querySelector('#editWorkFieldAdd');
    const convertButton = editPanel.querySelector('#editConvertWork');
    const createConverterButton = editPanel.querySelector('#editCreateConverter');
    const converterNodeUrlInput = editPanel.querySelector('#edit-converter-node-url');
    const remotePresentationUrlInput = editPanel.querySelector('#edit-remote-presentation-url');
    const remotePresentationCheckButton = editPanel.querySelector('#editCheckRemotePresentation');
    const remotePresentationApplyButton = editPanel.querySelector('#editApplyRemotePresentation');
    const remotePresentationStatus = editPanel.querySelector('#editRemotePresentationStatus');
    const remotePresentationOffer = editPanel.querySelector('#editRemotePresentationOffer');
    const remotePresentationOfferTitle = editPanel.querySelector('#editRemotePresentationOfferTitle');
    const remotePresentationOfferMeta = editPanel.querySelector('#editRemotePresentationOfferMeta');
    const remotePresentationSource = editPanel.querySelector('#editRemotePresentationSource');
    const remotePresentationConflict = editPanel.querySelector('#editRemotePresentationConflict');
    const remotePresentationHbs = editPanel.querySelector('#editRemotePresentationHbs');
    const remotePresentationCss = editPanel.querySelector('#editRemotePresentationCss');
    const remotePresentationJs = editPanel.querySelector('#editRemotePresentationJs');
    const remotePresentationHbsMeta = editPanel.querySelector('#editRemotePresentationHbsMeta');
    const remotePresentationCssMeta = editPanel.querySelector('#editRemotePresentationCssMeta');
    const remotePresentationJsMeta = editPanel.querySelector('#editRemotePresentationJsMeta');
    const externalSubmissionsEnabled = editPanel.querySelector('#edit-external-submissions-enabled');
    const externalWhitelistEnabled = editPanel.querySelector('#edit-external-submissions-whitelist-enabled');
    const externalSubmissionsOptions = editPanel.querySelector('.edit-external-submissions-options');
    const externalWhitelistWrap = editPanel.querySelector('#editExternalWhitelistWrap');
    const promptRoot = editPanel.querySelector('#promptLayer');
    syncPromptDock(promptRoot);
    let inspectedRemotePresentation = null;

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
    const syncExternalSubmissionOptions = () => {
        if (externalSubmissionsOptions) {
            externalSubmissionsOptions.hidden = !externalSubmissionsEnabled?.checked;
        }
        if (externalWhitelistWrap) {
            externalWhitelistWrap.hidden = !externalSubmissionsEnabled?.checked || !externalWhitelistEnabled?.checked;
        }
    };
    externalSubmissionsEnabled?.addEventListener('change', syncExternalSubmissionOptions);
    externalWhitelistEnabled?.addEventListener('change', syncExternalSubmissionOptions);
    syncExternalSubmissionOptions();
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
    if (registerPoffButton && typeof onRegisterPoff === 'function') {
        registerPoffButton.addEventListener('click', async () => {
            const originalLabel = registerPoffButton.textContent;
            registerPoffButton.disabled = true;
            registerPoffButton.textContent = 'Registering...';
            try {
                const result = await onRegisterPoff({ statusEl });
                registerPoffButton.textContent = result?.pendingApproval ? 'Registration pending' : 'Registered';
            } catch (error) {
                registerPoffButton.disabled = false;
                registerPoffButton.textContent = originalLabel;
                setStatusMessage(statusEl, error?.message || 'Poff registration failed.');
            }
        });
    }
    if (createConverterButton && typeof onCreateConverter === 'function') {
        createConverterButton.addEventListener('click', async () => {
            await onCreateConverter({ statusEl, form });
        });
    }
    if (converterNodeUrlInput && typeof onLoadConverterNode === 'function') {
        const loadConverterNode = async () => {
            await onLoadConverterNode({
                nodeUrl: String(converterNodeUrlInput?.value || '').trim(),
                statusEl,
            });
        };
        converterNodeUrlInput.addEventListener('change', loadConverterNode);
        const available = Array.isArray(config?.converterCatalog?.available) ? config.converterCatalog.available : [];
        const hasRemote = available.some((item) => item?.engine === 'remote-node');
        if (config?.converterCatalog?.autoLoadRemote === true && !hasRemote) {
            Promise.resolve().then(loadConverterNode);
        }
    }
    if (remotePresentationUrlInput && remotePresentationCheckButton && typeof onInspectRemotePresentation === 'function') {
        remotePresentationCheckButton.addEventListener('click', async () => {
            const sourceUrl = String(remotePresentationUrlInput.value || '').trim();
            inspectedRemotePresentation = null;
            remotePresentationCheckButton.disabled = true;
            remotePresentationCheckButton.textContent = 'Checking...';
            if (remotePresentationOffer) {
                remotePresentationOffer.hidden = true;
                remotePresentationOffer.classList.remove('is-compatible', 'is-incompatible');
            }
            if (remotePresentationApplyButton) {
                remotePresentationApplyButton.disabled = true;
            }
            try {
                const offer = await onInspectRemotePresentation({ sourceUrl, statusEl: remotePresentationStatus });
                inspectedRemotePresentation = offer;
                const compatible = offer?.compatible === true;
                const remote = offer?.remote && typeof offer.remote === 'object' ? offer.remote : {};
                const presentation = offer?.presentation && typeof offer.presentation === 'object' ? offer.presentation : {};
                const bytes = offer?.presentationBytes && typeof offer.presentationBytes === 'object' ? offer.presentationBytes : {};
                const localPresentation = offer?.localPresentation && typeof offer.localPresentation === 'object' ? offer.localPresentation : {};
                const matches = Array.isArray(offer?.matches) ? offer.matches.join(' · ') : '';
                const safetyIssues = Array.isArray(offer?.safetyIssues) ? offer.safetyIssues : [];
                if (remotePresentationOffer) {
                    remotePresentationOffer.hidden = false;
                    remotePresentationOffer.classList.add(compatible ? 'is-compatible' : 'is-incompatible');
                }
                if (remotePresentationOfferTitle) {
                    remotePresentationOfferTitle.textContent = remote.title || remote.path || 'Remote work presentation';
                }
                if (remotePresentationOfferMeta) {
                    remotePresentationOfferMeta.textContent = compatible
                        ? `Compatible: ${matches || 'matching work type'}`
                        : (safetyIssues.join(' ') || `Not compatible with this local work (${remote.mimeType || remote.kind || remote.extension || 'unknown type'}).`);
                }
                if (remotePresentationSource) {
                    remotePresentationSource.href = remote.url || sourceUrl;
                }
                if (remotePresentationHbs) remotePresentationHbs.textContent = String(presentation.workHbs || '');
                if (remotePresentationCss) remotePresentationCss.textContent = String(presentation.styleCss || '');
                if (remotePresentationJs) remotePresentationJs.textContent = String(presentation.scriptJs || '');
                if (remotePresentationHbsMeta) remotePresentationHbsMeta.textContent = `work.hbs · ${Number(bytes['work.hbs'] || 0)} bytes`;
                if (remotePresentationCssMeta) remotePresentationCssMeta.textContent = `style.css · ${Number(bytes['style.css'] || 0)} bytes`;
                if (remotePresentationJsMeta) remotePresentationJsMeta.textContent = `script.js · ${Number(bytes['script.js'] || 0)} bytes`;
                if (remotePresentationConflict) {
                    const existingFiles = Object.entries(localPresentation.files || {})
                        .filter(([, state]) => state?.exists === true)
                        .map(([name]) => name);
                    remotePresentationConflict.hidden = existingFiles.length === 0;
                    remotePresentationConflict.textContent = existingFiles.length
                        ? `Conflict: ${existingFiles.join(', ')} will be backed up, then all three presentation files will be replaced together.`
                        : '';
                }
                if (remotePresentationApplyButton) {
                    remotePresentationApplyButton.disabled = !compatible;
                    remotePresentationApplyButton.textContent = localPresentation.hasPresentation
                        ? 'Replace complete presentation'
                        : 'Copy complete presentation';
                }
                setStatusMessage(
                    remotePresentationStatus,
                    compatible ? 'Complete presentation ready: HBS + CSS + JS.' : 'Presentation cannot be copied.',
                    compatible
                );
            } catch (error) {
                setStatusMessage(remotePresentationStatus, error?.message || 'Remote work presentation unavailable.');
            } finally {
                remotePresentationCheckButton.disabled = false;
                remotePresentationCheckButton.textContent = 'Check presentation';
            }
        });
    }
    if (remotePresentationApplyButton && typeof onApplyRemotePresentation === 'function') {
        remotePresentationApplyButton.addEventListener('click', async () => {
            if (!inspectedRemotePresentation?.compatible) {
                return;
            }
            remotePresentationApplyButton.disabled = true;
            remotePresentationApplyButton.textContent = 'Copying complete presentation...';
            try {
                const result = await onApplyRemotePresentation({ offer: inspectedRemotePresentation, statusEl: remotePresentationStatus });
                remotePresentationApplyButton.textContent = 'Presentation copied';
                setStatusMessage(
                    remotePresentationStatus,
                    result?.backupPath ? `Presentation replaced. Backup: ${result.backupPath}` : 'Complete presentation copied.',
                    true
                );
            } catch (error) {
                remotePresentationApplyButton.disabled = false;
                remotePresentationApplyButton.textContent = inspectedRemotePresentation?.localPresentation?.hasPresentation
                    ? 'Replace complete presentation'
                    : 'Copy complete presentation';
                setStatusMessage(remotePresentationStatus, error?.message || 'Presentation copy failed.');
            }
        });
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
        onImportRemote,
    });

    return { statusEl, promptRoot };
}
