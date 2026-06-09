import { loadPromptSettings } from '../prompt/storage.js';
import { layoutOverlayState, syncPromptDock } from './shared.js';
import { bindUploadDialog } from './upload.js';
import {
    bindLayoutCloseButtons,
    bindLayoutDraftInputs,
    bindLayoutPanelChrome,
    bindLayoutPresetChanges,
    bindLayoutSubmit,
    collectLayoutElements,
    createLayoutBindingContext,
    syncLayoutUi,
} from './layout-bind.js';
import { renderEditLayoutOverlayHtml, renderEditLayoutPanelHtml } from './layout-render.js';

export function renderEditLayoutPanel({
    editPanel,
    config,
    status,
    activeWorkName,
    onSubmitLayout,
    onLayoutPresetChange,
    onReturnToWork,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onCreateLink,
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
        sectionSourceLabel,
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
    const hasVirtualSource = !overlayState.wrapperWasLocal && !originalUsesLocal;
    const sharedLayouts = Array.isArray(layoutState.sharedLayouts) ? layoutState.sharedLayouts : [];
    const sharedLayoutName = String(layoutState.sharedName || layoutState.name || '').trim();
    editPanel.innerHTML = renderEditLayoutPanelHtml({
        settings,
        subjectLabel,
        status,
        activeWorkName,
        displayMode,
        layoutState,
        sectionName,
        wrapperTarget,
        sectionTarget,
        sectionSourceLabel,
        sectionWasLocal,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
        sharedLayouts,
        sharedLayoutName,
    });

    const elements = collectLayoutElements(editPanel);
    const promptRoot = elements.promptRoot;
    syncPromptDock(promptRoot);

    const bindingContext = createLayoutBindingContext({
        layoutState,
        sharedLayouts,
        sharedLayoutName,
        sectionName,
        sectionSourceLabel,
        originalTemplate,
        originalCss,
        originalJs,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        wrapperTarget,
        originalTarget,
        originalEditable,
        hasVirtualSource,
    });
    const modeController = bindingContext.createModeController(elements.presetEl, elements.sharedLayoutEl);

    bindLayoutPresetChanges({
        elements,
        modeController,
        sharedLayoutName,
        onLayoutPresetChange,
    });
    bindLayoutDraftInputs(modeController, elements);
    bindLayoutPanelChrome({
        backButton: elements.backButton,
        moreButton: elements.moreButton,
        manualDetailsEl: elements.manualDetailsEl,
        onReturnToWork,
    });
    syncLayoutUi(modeController, elements);

    bindLayoutSubmit({
        elements,
        modeController,
        currentSectionTemplate: bindingContext.currentSectionTemplate,
        sectionWasLocal,
        originalEditable,
        originalTarget,
        wrapperWasLocal,
        sharedLayoutName,
        drafts: bindingContext.drafts,
        onSubmit: onSubmitLayout,
    });

    bindUploadDialog({
        editPanel,
        statusEl: elements.statusEl,
        uploadLimits: status?.uploadLimits || null,
        onUploadFiles,
        onCreateBlankFile,
        onCreateFolder,
        onCreateLink,
        onResetFolderWork,
    });

    return { statusEl: elements.statusEl, promptRoot };
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
        sectionSourceLabel,
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

    const hasVirtualSource = !wrapperWasLocal && !originalUsesLocal;
    const sharedLayouts = Array.isArray(layoutState.sharedLayouts) ? layoutState.sharedLayouts : [];
    const sharedLayoutName = String(layoutState.sharedName || layoutState.name || '').trim();

    editLayoutOverlay.hidden = false;
    editLayoutOverlay.innerHTML = renderEditLayoutOverlayHtml({
        layoutState,
        displayMode,
        sectionName,
        wrapperTarget,
        sectionTarget,
        sectionSourceLabel,
        sectionWasLocal,
        wrapperSourceLabel,
        inheritedLayoutLabel,
        originalLabel,
        sharedLayouts,
        sharedLayoutName,
    });

    const elements = collectLayoutElements(editLayoutOverlay, {
        form: '#editLayoutOverlayForm',
        statusEl: '#editLayoutOverlayStatus',
        closeButton: '#editLayoutOverlayClose',
        cancelButton: '#editLayoutOverlayCancel',
        backButton: null,
        moreButton: null,
        manualDetailsEl: null,
        promptRoot: null,
    });
    const bindingContext = createLayoutBindingContext({
        layoutState,
        sharedLayouts,
        sharedLayoutName,
        sectionName,
        sectionSourceLabel,
        originalTemplate,
        originalCss,
        originalJs,
        localWrapperTemplate,
        localWrapperCss,
        localWrapperJs,
        wrapperTarget,
        originalTarget,
        originalEditable,
        hasVirtualSource,
    });
    const modeController = bindingContext.createModeController(elements.presetEl, elements.sharedLayoutEl);

    syncLayoutUi(modeController, elements);
    bindLayoutCloseButtons({
        closeButton: elements.closeButton,
        cancelButton: elements.cancelButton,
        onClose,
    });
    bindLayoutPresetChanges({
        elements,
        modeController,
        sharedLayoutName,
    });
    bindLayoutSubmit({
        elements,
        modeController,
        currentSectionTemplate: bindingContext.currentSectionTemplate,
        sectionWasLocal,
        originalEditable,
        originalTarget,
        wrapperWasLocal,
        sharedLayoutName,
        drafts: bindingContext.drafts,
        onSubmit,
        onAfterSubmit: onClose,
    });

    return { overlayStatus: elements.statusEl };
}
