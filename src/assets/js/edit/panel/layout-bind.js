import { buildLayoutSubmitPayload, createLayoutDraftState, createLayoutModeController } from './layout-shared.js';

export function collectLayoutElements(root, selectors = {}) {
    const resolvedSelectors = {
        form: '#editLayoutPanelForm',
        statusEl: '#editLayoutStatus',
        closeButton: null,
        cancelButton: null,
        backButton: '#editLayoutBack',
        moreButton: '#editLayoutMore',
        manualDetailsEl: '#editLayoutManual',
        presetEl: '#edit-layout-preset',
        sharedLayoutWrapEl: '#edit-layout-shared-wrap',
        sharedLayoutEl: '#edit-layout-shared',
        modePreviewEl: '#edit-layout-mode-preview',
        sourcePreviewEl: '#edit-layout-source-preview',
        sectionSourcePreviewEl: '#edit-layout-section-source-preview',
        primaryTitleEl: '#edit-layout-primary-title',
        primaryHintEl: '#edit-layout-primary-hint',
        primaryTemplateEl: '#edit-layout-primary-template',
        primaryCssEl: '#edit-layout-primary-css',
        primaryJsEl: '#edit-layout-primary-js',
        contentTemplateEl: '#edit-content-template',
        promptRoot: '#promptLayer',
        ...selectors,
    };

    return Object.entries(resolvedSelectors).reduce((elements, [key, selector]) => {
        elements[key] = selector ? root.querySelector(selector) : null;
        return elements;
    }, {});
}

export function createLayoutBindingContext({
    layoutState,
    sharedLayouts,
    sharedLayoutName,
    originalTemplate,
    originalCss,
    originalJs,
    localWrapperTemplate,
    localWrapperCss,
    localWrapperJs,
    wrapperTarget,
    sectionName,
    sectionSourceLabel,
    originalTarget,
    originalEditable,
    hasVirtualSource,
}) {
    const drafts = createLayoutDraftState({
        originalTemplate,
        originalCss,
        originalJs,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
    });

    return {
        currentSectionTemplate: layoutState.sectionTemplate || '',
        drafts,
        createModeController(presetEl, sharedLayoutEl) {
            return createLayoutModeController({
                presetEl,
                getSharedLayoutName: () => sharedLayoutEl?.value || sharedLayoutName,
                getSharedLayoutPackage: () => (sharedLayouts || []).find((option) => String(option.name || '') === String(sharedLayoutEl?.value || sharedLayoutName)) || null,
                wrapperTarget,
                sectionName,
                sectionSourceLabel,
                originalTarget,
                originalEditable,
                hasVirtualSource,
                drafts,
            });
        },
    };
}

export function syncLayoutUi(modeController, elements) {
    modeController.syncLayoutMode({
        modePreviewEl: elements.modePreviewEl,
        sourcePreviewEl: elements.sourcePreviewEl,
        sectionSourcePreviewEl: elements.sectionSourcePreviewEl,
        primaryTitleEl: elements.primaryTitleEl,
        primaryHintEl: elements.primaryHintEl,
        primaryTemplateEl: elements.primaryTemplateEl,
        primaryCssEl: elements.primaryCssEl,
        primaryJsEl: elements.primaryJsEl,
    });
}

export function bindLayoutDraftInputs(modeController, elements) {
    [elements.primaryTemplateEl, elements.primaryCssEl, elements.primaryJsEl].forEach((field) => {
        if (field) {
            field.addEventListener('input', () => modeController.storePrimaryDraft(elements));
        }
    });
}

export function bindLayoutPresetChanges({
    elements,
    modeController,
    sharedLayoutName,
    onLayoutPresetChange,
}) {
    if (elements.presetEl) {
        elements.presetEl.addEventListener('change', async () => {
            if (elements.sharedLayoutWrapEl) {
                elements.sharedLayoutWrapEl.classList.toggle('hidden', elements.presetEl.value !== 'shared');
            }
            modeController.storePrimaryDraft(elements);
            syncLayoutUi(modeController, elements);
            if (typeof onLayoutPresetChange === 'function') {
                await onLayoutPresetChange({
                    payload: {
                        layoutPreset: (elements.presetEl.value || 'actual').trim(),
                        layoutSharedName: elements.sharedLayoutEl?.value || sharedLayoutName,
                    },
                    statusEl: elements.statusEl,
                });
            }
        });
    }

    if (elements.sharedLayoutEl) {
        elements.sharedLayoutEl.addEventListener('change', async () => {
            if (typeof onLayoutPresetChange === 'function') {
                await onLayoutPresetChange({
                    payload: {
                        layoutPreset: (elements.presetEl?.value || 'actual').trim(),
                        layoutSharedName: elements.sharedLayoutEl.value,
                    },
                    statusEl: elements.statusEl,
                });
            }
            syncLayoutUi(modeController, elements);
        });
    }
}

export function bindLayoutSubmit({
    elements,
    modeController,
    currentSectionTemplate,
    sectionWasLocal,
    originalEditable,
    originalTarget,
    wrapperWasLocal,
    sharedLayoutName,
    drafts,
    onSubmit,
    onAfterSubmit,
}) {
    if (!elements.form || typeof onSubmit !== 'function') {
        return;
    }

    elements.form.addEventListener('submit', async (event) => {
        event.preventDefault();
        modeController.storePrimaryDraft(elements);
        const payload = buildLayoutSubmitPayload({
            preset: (elements.presetEl?.value || 'actual').trim(),
            sharedLayoutName: elements.sharedLayoutEl?.value || sharedLayoutName,
            currentSectionTemplate,
            sectionWasLocal,
            contentTemplateEl: elements.contentTemplateEl,
            currentPrimaryMode: modeController.currentPrimaryMode,
            drafts,
            originalEditable,
            originalTarget,
            wrapperWasLocal,
        });

        await onSubmit({
            payload,
            statusEl: elements.statusEl,
        });

        if (typeof onAfterSubmit === 'function') {
            onAfterSubmit();
        }
    });
}

export function bindLayoutCloseButtons({ closeButton, cancelButton, onClose }) {
    if (typeof onClose !== 'function') {
        return;
    }
    if (closeButton) {
        closeButton.addEventListener('click', () => onClose());
    }
    if (cancelButton) {
        cancelButton.addEventListener('click', () => onClose());
    }
}

export function bindLayoutPanelChrome({ backButton, moreButton, manualDetailsEl, onReturnToWork }) {
    if (backButton && typeof onReturnToWork === 'function') {
        backButton.addEventListener('click', () => onReturnToWork());
    }
    if (moreButton && manualDetailsEl) {
        moreButton.addEventListener('click', () => {
            manualDetailsEl.open = true;
            manualDetailsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }
}
