import { requestEditConfig } from '../api/edit.js';
import { defaultPromptSettings, getDefaultModelForProvider } from './prompt/constants.js';
import { loadPromptSettings, savePromptSettings } from './prompt/storage.js';
import { bindPromptSettings } from './prompt/settings.js';
import { createPromptLayerController } from './prompt/layer.js';
import { bindPromptActions } from './prompt/actions.js';
import { createPromptRuntime } from './prompt/runtime.js';
import { createPromptWindowWorkflows } from './prompt/workflows.js';

const PROMPT_LAYER_STATE_KEY = 'poffEditPromptLayerState';

export function bindPromptWindow({
    root,
    statusEl,
    drawerForm,
    getActiveSelection,
    getConfig,
    requestPromptTemplate,
    saveConfig,
}) {
    if (!root) {
        return;
    }

    const providerEl = root.querySelector('#prompt-provider');
    const modelEl = root.querySelector('#prompt-model');
    const endpointRow = root.querySelector('#prompt-endpoint-row');
    const endpointEl = root.querySelector('#prompt-endpoint');
    const apiKeyEl = root.querySelector('#prompt-api-key');
    const systemPromptEl = root.querySelector('#prompt-system');
    const systemResetEl = root.querySelector('#prompt-system-reset');
    const settingsResetEl = root.querySelector('#prompt-settings-reset');
    const streamToggleEl = root.querySelector('#prompt-stream');
    const promptMessagesEl = root.querySelector('#promptMessages');
    const promptContextEl = root.querySelector('#promptContext');
    const promptSummaryEl = root.querySelector('#promptSummary');
    const promptGenerationEl = root.querySelector('#promptGeneration');
    const promptGenerationLabelEl = root.querySelector('#promptGenerationLabel');
    const promptTemplateLabelEl = root.querySelector('#promptTemplateLabel');
    const promptTemplateResetEl = root.querySelector('#prompt-template-reset');
    const promptTemplateCodeEl = root.querySelector('#promptTemplateCode');
    const promptAttachmentEl = root.querySelector('#promptAttachment');
    const promptWindowEl = root.querySelector('#promptWindow');
    const promptLayerCloseEl = root.querySelector('#promptLayerClose');
    const promptLayerOpenEl = root.querySelector('#promptLayerOpen');
    const promptInputEl = root.querySelector('#prompt-input');
    const promptSendEl = root.querySelector('#prompt-send');
    const promptAttachEl = root.querySelector('#prompt-attach');
    const promptInsertNameEl = root.querySelector('#prompt-insert-name');
    const promptClearEl = root.querySelector('#prompt-clear');
    const promptImageInputEl = root.querySelector('#prompt-image-input');
    const promptAttachmentPreviewEl = root.querySelector('#promptAttachmentPreview');
    const promptAttachmentNameEl = root.querySelector('#promptAttachmentName');
    const promptAttachmentRemoveEl = root.querySelector('#prompt-attachment-remove');
    const runtime = createPromptRuntime({
        root,
        statusEl,
        drawerForm,
        getActiveSelection,
        getConfig,
        requestEditConfig,
        promptInputEl,
        promptMessagesEl,
        promptContextEl,
        promptSummaryEl,
        promptGenerationEl,
        promptGenerationLabelEl,
        promptTemplateLabelEl,
        promptTemplateCodeEl,
        promptAttachmentEl,
        promptAttachEl,
        promptAttachmentPreviewEl,
        promptAttachmentNameEl,
        promptImageInputEl,
        promptSendEl,
        promptClearEl,
        promptAttachmentRemoveEl,
    });
    const {
        stream,
        state,
        setPromptStatus,
        currentSelection,
        currentHasImageContext,
        currentSelectionName,
        insertPromptText,
        setHistory,
        readHistoryForSelection,
        writeHistoryForSelection,
        renderHistory,
        getCurrentTemplateText,
        renderTemplatePreview,
        renderContext,
        renderSummary,
        updateAttachmentUi,
        clearAttachment,
        clearPromptHistory,
        attachImageFile,
        setGeneratingState,
        reloadViewer,
        syncHistoryForPath,
        currentDefaultSystemPrompt,
        currentPromptMode,
        currentSystemPromptSettingKey,
        getLayoutPreset,
        forceLayoutPromptToCustom,
        updatePromptEditorFields,
        focusPromptTemplateField,
        syncWorkFieldEditors,
    } = runtime;

    const workflows = createPromptWindowWorkflows({
        root,
        statusEl,
        drawerForm,
        getConfig,
        saveConfig,
        promptInputEl,
        providerEl,
        modelEl,
        endpointEl,
        apiKeyEl,
        systemPromptEl,
        streamToggleEl,
        currentSelection,
        currentHasImageContext,
        currentSelectionName,
        currentPromptPlaceholder: runtime.currentPromptPlaceholder,
        currentDefaultSystemPrompt: runtime.currentDefaultSystemPrompt,
        setPromptStatus,
        setHistory,
        writeHistoryForSelection,
        readHistoryForSelection,
        renderHistory,
        renderContext,
        renderSummary,
        setGeneratingState,
        clearAttachment,
        attachImageFile,
        currentPromptMode: runtime.currentPromptMode,
        getCurrentTemplateText,
        getLayoutPreset,
        forceLayoutPromptToCustom,
        updatePromptEditorFields,
        syncWorkFieldEditors,
        focusPromptTemplateField,
        reloadViewer,
        state,
        stream,
    });

    bindPromptActions({
        promptClearEl,
        promptTemplateResetEl,
        promptSendEl,
        promptInputEl,
        promptAttachEl,
        promptInsertNameEl,
        promptImageInputEl,
        promptAttachmentRemoveEl,
        layoutPresetEl: document.getElementById('edit-layout-preset'),
        onClearChat: () => {
            syncHistoryForPath();
            workflows.onClearChat();
            clearAttachment();
            setPromptStatus('Chat cleared.');
        },
        onResetTemplate: workflows.onResetTemplate,
        onSendPrompt: workflows.onSendPrompt,
        onAttachImage: workflows.onAttachImage,
        onInsertName: workflows.onInsertName,
        onRemoveImage: workflows.onRemoveImage,
        onTemplateInput: renderTemplatePreview,
        onLayoutPresetChange: renderContext,
    });

    const layerController = createPromptLayerController({
        root,
        windowEl: promptWindowEl,
        closeEl: promptLayerCloseEl,
        openEl: promptLayerOpenEl,
        storageKey: PROMPT_LAYER_STATE_KEY,
        storage: localStorage,
    });
    layerController.applyState(layerController.readState(), { skipPersist: true });

    const settingsController = bindPromptSettings({
        providerEl,
        modelEl,
        endpointRow,
        endpointEl,
        apiKeyEl,
        systemPromptEl,
        systemResetEl,
        settingsResetEl,
        streamToggleEl,
        defaultPromptSettings,
        currentDefaultSystemPrompt,
        currentPromptMode,
        currentSystemPromptSettingKey,
        getDefaultModelForProvider,
        loadPromptSettings,
        savePromptSettings,
        onRenderContext: renderContext,
    });
    const { readSettings, updateProviderUi, syncModeAwareSystemPrompt } = settingsController;
    updateProviderUi({ resetModel: false });
    syncModeAwareSystemPrompt();
    setHistory(readHistoryForSelection(currentSelection(null)));
    renderHistory();
    renderContext();
    renderSummary('Waiting for response...');
    updateAttachmentUi();
    window.addEventListener('hashchange', syncHistoryForPath);
    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLTextAreaElement || target instanceof HTMLInputElement)) {
            return;
        }
        if (target.matches('#edit-content-template, #edit-layout-primary-template')) {
            renderTemplatePreview();
        }
    });

    if (promptMessagesEl) {
        promptMessagesEl.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            const button = target.closest('[data-history-reset-index]');
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }
            const index = Number.parseInt(button.dataset.historyResetIndex || '', 10);
            if (!Number.isInteger(index)) {
                return;
            }
            const historyEntry = state.promptHistory.find((item) => item && item._index === index);
            if (!historyEntry || !historyEntry.templateSnapshot) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            void workflows.restoreHistorySnapshot(historyEntry.templateSnapshot);
        });
    }
}
