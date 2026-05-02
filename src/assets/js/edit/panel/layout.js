import { escapeHtml } from '../../core/utils.js';
import { loadPromptSettings } from '../prompt/storage.js';
import { renderPromptWindow } from '../prompt-window.js';
import { layoutOverlayState, syncPromptDock } from './shared.js';
import { bindUploadDialog, renderUploadSectionHtml } from './upload.js';
import {
    buildLayoutSubmitPayload,
    createLayoutDraftState,
    createLayoutModeController,
} from './layout-shared.js';

function renderLayoutModeSummary({ subjectLabel, displayMode, wrapperSourceLabel, inheritedLayoutLabel, sectionTarget }) {
    return `
        <div class="edit-layout-launch edit-layout-summary">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="edit-layout-summary-line">Editing source: <code id="edit-layout-source-preview">${escapeHtml(wrapperSourceLabel)}</code></div>
                <div class="edit-layout-summary-line">Current mode: <code id="edit-layout-mode-preview">${escapeHtml(displayMode)}</code></div>
                <div class="edit-layout-summary-line">Inner section stays at <code>${escapeHtml(sectionTarget)}</code> unless you change it in <strong>More...</strong></div>
            </div>
            <div class="edit-inline-actions edit-layout-header-actions">
                <button class="btn btn-secondary" type="button" id="editLayoutBack">Back to work</button>
                <button class="btn btn-secondary" type="button" id="editLayoutMore">More...</button>
            </div>
        </div>
    `;
}

function renderSharedLayoutOptions(sharedLayouts = [], selectedName = '') {
    if (!Array.isArray(sharedLayouts) || sharedLayouts.length === 0) {
        return '<div class="small-note">No shared layouts available for this worktype.</div>';
    }

    return `
        <label class="edit-label" for="edit-layout-shared">Shared layout</label>
        <select class="form-select" id="edit-layout-shared" name="layout_shared">
            ${sharedLayouts.map((option) => `
                <option value="${escapeHtml(option.name || '')}" ${String(selectedName || '') === String(option.name || '') ? 'selected' : ''}>
                    ${escapeHtml(option.label || option.name || 'shared')}
                </option>
            `).join('')}
        </select>
        <div class="small-note">Choose a marketplace layout from the same worktype.</div>
    `;
}

function bindLayoutForm({
    form,
    presetEl,
    sharedLayoutEl,
    contentTemplateEl,
    currentSectionTemplate,
    sectionWasLocal,
    currentPrimaryMode,
    storePrimaryDraft,
    drafts,
    originalEditable,
    originalTarget,
    wrapperWasLocal,
    statusEl,
    onSubmitLayout,
    primaryTemplateEl,
    primaryCssEl,
    primaryJsEl,
}) {
    if (!form || typeof onSubmitLayout !== 'function') {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl });
        const payload = buildLayoutSubmitPayload({
            preset: (presetEl?.value || 'actual').trim(),
            sharedLayoutName: sharedLayoutEl?.value || '',
            currentSectionTemplate,
            sectionWasLocal,
            contentTemplateEl,
            currentPrimaryMode,
            drafts,
            originalEditable,
            originalTarget,
            wrapperWasLocal,
        });

        await onSubmitLayout({
            payload,
            statusEl,
        });
    });
}

export function renderEditLayoutPanel({
    editPanel,
    config,
    status,
    contentTargetLabel,
    onSubmitLayout,
    onLayoutPresetChange,
    onReturnToWork,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onResetFolderWork,
}) {
    const settings = loadPromptSettings();
    const subjectStatus = {
        ...status,
        target: status?.subjectTarget || status?.target,
    };
    const overlayState = layoutOverlayState(config, subjectStatus);
    const {
        layoutState,
        displayMode,
        sectionName,
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
    const subjectLabel = subjectStatus.target === 'file' ? 'file' : 'folder';
    const layoutPresetOptions = [
        { value: 'actual', label: 'Inherit' },
        { value: 'none', label: 'None' },
        { value: 'custom', label: 'Custom' },
        { value: 'shared', label: 'Shared' },
    ];
    const hasVirtualSource = !overlayState.wrapperWasLocal && !originalUsesLocal;
    const isFileSubject = subjectStatus.target === 'file';
    const sharedLayouts = Array.isArray(layoutState.sharedLayouts) ? layoutState.sharedLayouts : [];
    const sharedLayoutName = String(layoutState.sharedName || layoutState.name || '').trim();
    const uploadSectionHtml = renderUploadSectionHtml({
        isFileTarget: isFileSubject,
        isEmptyFolder: false,
    });

    editPanel.innerHTML = `
        <h3 class="edit-panel-title">Edit layout (${subjectLabel})</h3>
        <div class="small-note">Virtual <code>.layout</code> target for this ${escapeHtml(subjectLabel)}. The preview stays on the current work while you edit the wrapper.</div>
        <div class="edit-status" id="editLayoutStatus"></div>
        <form id="editLayoutPanelForm" class="edit-inline edit-layout-panel">
            ${renderLayoutModeSummary({ subjectLabel, displayMode, wrapperSourceLabel, inheritedLayoutLabel, sectionTarget })}
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-preset">Layout select</label>
                    <select class="form-select" id="edit-layout-preset" name="layout_preset">
                        ${layoutPresetOptions.map((option) => `
                            <option value="${option.value}" ${layoutState.preset === option.value ? 'selected' : ''}>${option.label}</option>
                        `).join('')}
                    </select>
                    <div class="mt-3${layoutState.preset === 'shared' ? '' : ' hidden'}" id="edit-layout-shared-wrap">
                        ${renderSharedLayoutOptions(sharedLayouts, sharedLayoutName)}
                    </div>
                </div>
                <div class="edit-layout-copy edit-layout-section-note">
                    <div class="edit-layout-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                    <div class="edit-inline-actions edit-layout-select-actions">
                        <button class="btn" type="submit">Save layout</button>
                    </div>
                </div>
            </div>
        </form>
        ${uploadSectionHtml}
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
    const sharedLayoutWrapEl = editPanel.querySelector('#edit-layout-shared-wrap');
    const sharedLayoutEl = editPanel.querySelector('#edit-layout-shared');
    const modePreviewEl = editPanel.querySelector('#edit-layout-mode-preview');
    const sourcePreviewEl = editPanel.querySelector('#edit-layout-source-preview');
    const primaryTitleEl = editPanel.querySelector('#edit-layout-primary-title');
    const primaryHintEl = editPanel.querySelector('#edit-layout-primary-hint');
    const primaryTemplateEl = editPanel.querySelector('#edit-layout-primary-template');
    const primaryCssEl = editPanel.querySelector('#edit-layout-primary-css');
    const primaryJsEl = editPanel.querySelector('#edit-layout-primary-js');
    const contentTemplateEl = editPanel.querySelector('#edit-content-template');
    const promptRoot = editPanel.querySelector('#promptLayer');
    syncPromptDock(promptRoot);

    const currentSectionTemplate = layoutState.sectionTemplate || '';
    const drafts = createLayoutDraftState({
        originalTemplate,
        originalCss,
        originalJs,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
    });

    const { currentPrimaryMode, syncLayoutMode, storePrimaryDraft } = createLayoutModeController({
        presetEl,
        getSharedLayoutName: () => sharedLayoutEl?.value || sharedLayoutName,
        getSharedLayoutPackage: () => (sharedLayouts || []).find((option) => String(option.name || '') === String(sharedLayoutEl?.value || sharedLayoutName)) || null,
        wrapperTarget,
        originalTarget,
        originalEditable,
        hasVirtualSource,
        drafts,
    });

    if (presetEl) {
        presetEl.addEventListener('change', async () => {
            if (sharedLayoutWrapEl) {
                sharedLayoutWrapEl.classList.toggle('hidden', presetEl.value !== 'shared');
            }
            storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl });
            syncLayoutMode({
                modePreviewEl,
                sourcePreviewEl,
                primaryTitleEl,
                primaryHintEl,
                primaryTemplateEl,
                primaryCssEl,
                primaryJsEl,
            });
            if (typeof onLayoutPresetChange === 'function') {
                await onLayoutPresetChange({
                    payload: {
                        layoutPreset: (presetEl.value || 'actual').trim(),
                        layoutSharedName: sharedLayoutEl?.value || sharedLayoutName,
                    },
                    statusEl,
                });
            }
        });
    }
    if (sharedLayoutEl) {
        sharedLayoutEl.addEventListener('change', async () => {
            if (typeof onLayoutPresetChange === 'function') {
                await onLayoutPresetChange({
                    payload: {
                        layoutPreset: (presetEl?.value || 'actual').trim(),
                        layoutSharedName: sharedLayoutEl.value,
                    },
                    statusEl,
                });
            }
            syncLayoutMode({
                modePreviewEl,
                sourcePreviewEl,
                primaryTitleEl,
                primaryHintEl,
                primaryTemplateEl,
                primaryCssEl,
                primaryJsEl,
            });
        });
    }
    [primaryTemplateEl, primaryCssEl, primaryJsEl].forEach((field) => {
        if (field) {
            field.addEventListener('input', () => storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl }));
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
    syncLayoutMode({
        modePreviewEl,
        sourcePreviewEl,
        primaryTitleEl,
        primaryHintEl,
        primaryTemplateEl,
        primaryCssEl,
        primaryJsEl,
    });

    bindLayoutForm({
        form,
        presetEl,
        sharedLayoutEl,
        contentTemplateEl,
        currentSectionTemplate,
        sectionWasLocal,
        currentPrimaryMode,
        storePrimaryDraft,
        drafts,
        originalEditable,
        originalTarget,
        wrapperWasLocal,
        statusEl,
        onSubmitLayout,
        primaryTemplateEl,
        primaryCssEl,
        primaryJsEl,
    });

    bindUploadDialog({
        editPanel,
        statusEl,
        uploadLimits: status?.uploadLimits || null,
        onUploadFiles,
        onCreateBlankFile,
        onCreateFolder,
        onResetFolderWork,
    });

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
        displayMode,
        sectionName,
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
        { value: 'actual', label: 'Inherit' },
        { value: 'none', label: 'None' },
        { value: 'custom', label: 'Custom' },
        { value: 'shared', label: 'Shared' },
    ];
    const hasVirtualSource = !wrapperWasLocal && !originalUsesLocal;
    const sharedLayouts = Array.isArray(layoutState.sharedLayouts) ? layoutState.sharedLayouts : [];
    const sharedLayoutName = String(layoutState.sharedName || layoutState.name || '').trim();

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
                        <div class="mt-3${layoutState.preset === 'shared' ? '' : ' hidden'}" id="edit-layout-shared-wrap">
                            ${renderSharedLayoutOptions(sharedLayouts, sharedLayoutName)}
                        </div>
                        <div class="small-note">Resolved mode: <code id="edit-layout-mode-preview">${escapeHtml(displayMode)}</code></div>
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
    const sharedLayoutWrapEl = editLayoutOverlay.querySelector('#edit-layout-shared-wrap');
    const sharedLayoutEl = editLayoutOverlay.querySelector('#edit-layout-shared');
    const modePreviewEl = editLayoutOverlay.querySelector('#edit-layout-mode-preview');
    const primaryTitleEl = editLayoutOverlay.querySelector('#edit-layout-primary-title');
    const primaryHintEl = editLayoutOverlay.querySelector('#edit-layout-primary-hint');
    const primaryTemplateEl = editLayoutOverlay.querySelector('#edit-layout-primary-template');
    const primaryCssEl = editLayoutOverlay.querySelector('#edit-layout-primary-css');
    const primaryJsEl = editLayoutOverlay.querySelector('#edit-layout-primary-js');
    const contentTemplateEl = editLayoutOverlay.querySelector('#edit-content-template');

    const currentSectionTemplate = layoutState.sectionTemplate || '';
    const drafts = createLayoutDraftState({
        originalTemplate,
        originalCss,
        originalJs,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
    });

    const { currentPrimaryMode, syncLayoutMode, storePrimaryDraft } = createLayoutModeController({
        presetEl,
        getSharedLayoutName: () => sharedLayoutEl?.value || sharedLayoutName,
        getSharedLayoutPackage: () => (sharedLayouts || []).find((option) => String(option.name || '') === String(sharedLayoutEl?.value || sharedLayoutName)) || null,
        wrapperTarget,
        originalTarget,
        originalEditable,
        hasVirtualSource,
        drafts,
    });

    syncLayoutMode({
        modePreviewEl,
        primaryTitleEl,
        primaryHintEl,
        primaryTemplateEl,
        primaryCssEl,
        primaryJsEl,
    });

    if (closeButton && typeof onClose === 'function') {
        closeButton.addEventListener('click', () => onClose());
    }
    if (cancelButton && typeof onClose === 'function') {
        cancelButton.addEventListener('click', () => onClose());
    }

    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl });
            const payload = buildLayoutSubmitPayload({
                preset: (presetEl?.value || 'actual').trim(),
                sharedLayoutName: sharedLayoutEl?.value || sharedLayoutName,
                currentSectionTemplate,
                sectionWasLocal,
                contentTemplateEl,
                currentPrimaryMode,
                drafts,
                originalEditable,
                originalTarget,
                wrapperWasLocal,
            });

            await onSubmit({
                payload,
                statusEl,
            });
            if (typeof onClose === 'function') {
                onClose();
            }
        });
    }
    if (presetEl) {
        presetEl.addEventListener('change', () => {
            if (sharedLayoutWrapEl) {
                sharedLayoutWrapEl.classList.toggle('hidden', presetEl.value !== 'shared');
            }
            syncLayoutMode({
                modePreviewEl,
                primaryTitleEl,
                primaryHintEl,
                primaryTemplateEl,
                primaryCssEl,
                primaryJsEl,
            });
        });
    }
    if (sharedLayoutEl) {
        sharedLayoutEl.addEventListener('change', () => {
            syncLayoutMode({
                modePreviewEl,
                primaryTitleEl,
                primaryHintEl,
                primaryTemplateEl,
                primaryCssEl,
                primaryJsEl,
            });
        });
    }

    return { overlayStatus: statusEl };
}
