import { getLayoutState } from '../core/utils.js';
import { getDefaultSystemPromptForMode, getPromptMode, getPromptPlaceholderForMode, getSystemPromptSettingKeyForMode } from './prompt/mode.js';
import { defaultFileSystemPrompt, defaultFolderSystemPrompt, defaultLayoutSystemPrompt, defaultPromptSettings, getDefaultModelForProvider } from './prompt/constants.js';
import { loadPromptSettings, savePromptSettings, readStoredHistory, writeStoredHistory } from './prompt/storage.js';
import { tagHistory, filterAllowedWork, inferWorkChangesFromPrompt, buildTemplateHistorySnapshot, serializeHistoryForRequest } from './prompt/history.js';
import { buildPromptContext, renderPromptContext, renderPromptHistory, renderPromptSummary } from './prompt/render.js';
import { appendStreamingChunk, beginStreaming, createStreamState, finishStreaming, stopStreaming } from './prompt/stream.js';
import { requestPromptTemplateStream } from '../api/edit.js';
import { focusPromptTemplateField, syncWorkFieldEditors, updatePromptEditorFields } from './prompt/editor-fields.js';
import { bindPromptActions } from './prompt/actions.js';
import { debugPromptLog } from './prompt/log.js';
import { readImageFile } from './prompt/image.js';
import { readPromptEditorDraft } from './prompt/draft.js';
import { materializeWorkFields } from './work-fields.js';
import { summarizePromptError, summarizePromptRequest, summarizePromptResponse } from './prompt/summary.js';
import { bindPromptSettings } from './prompt/settings.js';
import { createPromptLayerController } from './prompt/layer.js';
import { buildPromptLayoutPayload } from './prompt/layout-payload.js';
import { getLayoutPresetValue, getSelectionOrFallback } from './selection.js';
import { setStatusMessage } from './status.js';

const PROMPT_FALLBACK_TIMEOUT_MS = 305000;
const PROMPT_LAYER_STATE_KEY = 'poffEditPromptLayerState';

let promptHistory = [];
const stream = createStreamState();

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
    const promptClearEl = root.querySelector('#prompt-clear');
    const promptImageInputEl = root.querySelector('#prompt-image-input');
    const promptAttachmentPreviewEl = root.querySelector('#promptAttachmentPreview');
    const promptAttachmentNameEl = root.querySelector('#promptAttachmentName');
    const promptAttachmentRemoveEl = root.querySelector('#prompt-attachment-remove');
    let isSending = false;
    const currentSelection = (fallback = {}) => getSelectionOrFallback(getActiveSelection, fallback);
    const setPromptStatus = (message, success = false) => setStatusMessage(statusEl, message, success);
    const getLayoutPreset = () => getLayoutPresetValue();
    let activePath = currentSelection({ path: '' }).path;
    let activePromptMode = currentSelection().isLayout ? 'layout' : (currentSelection().previewIsFile ? 'file' : 'folder');
    let imageAttachment = null;
    const imageContextPattern = /\.(avif|bmp|gif|heic|heif|jpe?g|png|svg|webp)$/i;
    const defaultPromptPlaceholder = promptInputEl?.getAttribute('placeholder') || 'Describe the component you want...';
    const currentPromptMode = () => getPromptMode(currentSelection());
    const currentPromptPlaceholder = () => getPromptPlaceholderForMode(currentPromptMode(), defaultPromptPlaceholder);
    const currentDefaultSystemPrompt = () => getDefaultSystemPromptForMode(currentPromptMode(), {
        file: defaultFileSystemPrompt,
        folder: defaultFolderSystemPrompt,
        layout: defaultLayoutSystemPrompt,
    });
    const currentSystemPromptSettingKey = () => getSystemPromptSettingKeyForMode(currentPromptMode());
    const dropPendingAssistantHistory = (pendingAssistantIndex) => {
        if (pendingAssistantIndex === null || pendingAssistantIndex < 0 || pendingAssistantIndex >= promptHistory.length) {
            return promptHistory;
        }
        const nextHistory = promptHistory.slice();
        nextHistory.splice(pendingAssistantIndex, 1);
        return nextHistory;
    };
    const currentHasImageContext = () => {
        const selection = currentSelection(null);
        const selectionPath = typeof selection?.previewPath === 'string' && selection.previewPath.trim() !== ''
            ? selection.previewPath
            : (typeof selection?.path === 'string' ? selection.path : '');

        return imageContextPattern.test(selectionPath);
    };

    const setHistory = (nextHistory) => {
        const list = Array.isArray(nextHistory) ? nextHistory : [];
        promptHistory = tagHistory(list);
    };

    const getHistoryScope = (selection = null) => {
        const selected = selection || currentSelection({ path: '' });
        return {
            path: selected?.path || '',
            mode: selected?.isLayout ? 'layout' : (selected?.previewIsFile ? 'file' : 'folder'),
        };
    };

    const readHistoryForSelection = (selection = null) => {
        const scope = getHistoryScope(selection);
        return readStoredHistory(scope.path, scope.mode);
    };

    const writeHistoryForSelection = (history, selection = null) => {
        const scope = getHistoryScope(selection);
        writeStoredHistory(scope.path, history, scope.mode);
    };

    const renderHistory = (options = {}) => {
        renderPromptHistory(promptMessagesEl, promptHistory, stream.state, options);
    };

    const getCurrentTemplateField = () => {
        const selection = currentSelection(null);
        const selector = selection?.isLayout ? '#edit-layout-primary-template' : '#edit-content-template';
        return document.querySelector(selector);
    };

    const renderTemplatePreview = () => {
        if (!promptTemplateCodeEl) {
            return;
        }
        const selection = currentSelection(null);
        const templateField = getCurrentTemplateField();
        const currentConfig = getConfig ? (getConfig() || {}) : {};
        const layout = currentConfig?.work?.layout && typeof currentConfig.work.layout === 'object'
            ? currentConfig.work.layout
            : {};
        const explicitTemplate = templateField && typeof templateField.value === 'string'
            ? templateField.value
            : '';
        const fallbackTemplate = selection?.isLayout
            ? ((typeof layout.template === 'string' && layout.template) || (typeof layout.phpTemplate === 'string' ? layout.phpTemplate : ''))
            : ((typeof layout.sectionTemplate === 'string' && layout.sectionTemplate) || (typeof layout.defaultSectionTemplate === 'string' ? layout.defaultSectionTemplate : ''));
        promptTemplateCodeEl.value = explicitTemplate || fallbackTemplate || '';
        if (promptTemplateLabelEl) {
            promptTemplateLabelEl.textContent = selection?.isLayout
                ? 'Current layout wrapper template'
                : 'Current wrapped partial template';
        }
    };

    const renderContext = () => {
        const context = buildPromptContext({ getActiveSelection, getConfig });
        activePath = context.isLayout ? (context.virtualPath || context.path) : context.path;
        activePromptMode = currentPromptMode();
        syncModeAwareSystemPrompt();
        if (promptInputEl && !isSending) {
            promptInputEl.placeholder = currentPromptPlaceholder();
        }
        renderPromptContext(promptContextEl, context);
        renderTemplatePreview();
    };

    const renderSummary = (content) => {
        renderPromptSummary(promptSummaryEl, content);
    };

    const updateAttachmentUi = () => {
        if (!promptAttachmentEl || !promptAttachmentPreviewEl || !promptAttachmentNameEl || !promptInputEl) {
            return;
        }
        const hasImageContext = currentHasImageContext() || !!imageAttachment;
        const hasAttachment = !!imageAttachment;
        root.classList.toggle('prompt-has-image-context', hasImageContext);
        promptAttachmentEl.hidden = !hasAttachment;
        if (promptAttachEl) {
            promptAttachEl.hidden = !hasImageContext;
        }
        promptInputEl.classList.toggle('prompt-input-has-attachment', hasAttachment);
        if (!hasAttachment) {
            promptAttachmentPreviewEl.removeAttribute('src');
            promptAttachmentNameEl.textContent = 'Image attached';
            return;
        }
        promptAttachmentPreviewEl.src = imageAttachment.dataUrl;
        promptAttachmentNameEl.textContent = imageAttachment.name || 'clipboard-image.png';
    };

    const clearAttachment = () => {
        imageAttachment = null;
        if (promptImageInputEl) {
            promptImageInputEl.value = '';
        }
        updateAttachmentUi();
    };

    const clearPromptHistory = () => {
        stopStreaming(stream);
        setHistory([]);
        writeHistoryForSelection(promptHistory);
        renderHistory();
    };

    const attachImageFile = async (file) => {
        try {
            imageAttachment = await readImageFile(file);
            updateAttachmentUi();
            setPromptStatus(`Attached image: ${imageAttachment.name}`, true);
        } catch (err) {
            setPromptStatus(err.message || 'Failed to attach image.');
        }
    };

    const setGeneratingState = (active, label = 'Generating answer...') => {
        root.classList.toggle('prompt-window-generating', active);
        root.setAttribute('aria-busy', active ? 'true' : 'false');
        if (promptSummaryEl) {
            promptSummaryEl.classList.toggle('prompt-summary-generating', active);
        }
        if (promptGenerationEl) {
            promptGenerationEl.hidden = !active;
        }
        if (promptGenerationLabelEl) {
            promptGenerationLabelEl.textContent = label;
        }
        if (promptSendEl) {
            promptSendEl.disabled = active;
            promptSendEl.textContent = active ? 'Generating...' : 'Send';
        }
        if (promptAttachEl) {
            promptAttachEl.disabled = active;
        }
        if (promptClearEl) {
            promptClearEl.disabled = active;
        }
        if (promptAttachmentRemoveEl) {
            promptAttachmentRemoveEl.disabled = active;
        }
        if (promptInputEl) {
            promptInputEl.disabled = active;
            promptInputEl.placeholder = active ? 'Generating answer...' : defaultPromptPlaceholder;
        }
    };

    bindPromptActions({
        promptClearEl,
        promptTemplateResetEl,
        promptSendEl,
        promptInputEl,
        promptAttachEl,
        promptImageInputEl,
        promptAttachmentRemoveEl,
        layoutPresetEl: document.getElementById('edit-layout-preset'),
        onClearChat: () => {
            syncHistoryForPath();
            clearPromptHistory();
            clearAttachment();
            setPromptStatus('Chat cleared.');
        },
        onResetTemplate: async () => {
            if (isSending) {
                return;
            }

            const selection = currentSelection({ path: activePath, isLayout: false, previewPath: activePath, layoutIsFile: false });
            const isLayoutTarget = !!selection?.isLayout;
            const resetLabel = isLayoutTarget ? 'current layout wrapper template' : 'current wrapped partial template';
            if (!window.confirm(`Reset the ${resetLabel} to the inherited/default version?`)) {
                return;
            }

            try {
                isSending = true;
                setGeneratingState(true, 'Resetting template...');
                setPromptStatus('Resetting template...');

                const currentConfig = getConfig ? (getConfig() || {}) : {};
                const layoutState = getLayoutState(currentConfig || {});
                const layoutPayload = {
                    name: currentConfig?.work?.layout?.name || 'poff-layout',
                    engine: currentConfig?.work?.layout?.engine || 'lightncandy',
                };

                if (isLayoutTarget) {
                    const preset = (getLayoutPreset() || layoutState.preset || 'actual').trim();
                    const layoutPathName = (selection.previewPath || '').split('/').pop() || 'item';
                    const localLayoutDirectory = selection.layoutIsFile
                        ? `.works/${layoutPathName}.layout`
                        : '.layout';
                    const resolvedLayoutDirectory = typeof layoutState.directory === 'string'
                        ? layoutState.directory.trim()
                        : '';
                    const canEditResolvedFilesystemTarget = layoutState.storage === 'filesystem' && resolvedLayoutDirectory !== '';
                    const shouldPersistToLocalWrapper = preset === 'custom'
                        || !canEditResolvedFilesystemTarget
                        || resolvedLayoutDirectory === localLayoutDirectory;

                    layoutPayload.preset = preset;
                    layoutPayload.name = preset === 'none'
                        ? 'none'
                        : preset === 'custom'
                            ? 'custom-layout'
                            : (canEditResolvedFilesystemTarget ? 'filesystem-layout' : 'poff-layout');

                    if (shouldPersistToLocalWrapper) {
                        layoutPayload.template = '';
                    } else if (canEditResolvedFilesystemTarget) {
                        layoutPayload.originalTarget = resolvedLayoutDirectory;
                        layoutPayload.originalTemplate = '';
                    } else {
                        layoutPayload.template = '';
                    }
                    updatePromptEditorFields({
                        templateText: '',
                        nextTitle: null,
                        nextDescription: null,
                        nextWork: null,
                        isLayoutTarget: true,
                    });
                } else {
                    layoutPayload.sectionTemplate = '';
                    updatePromptEditorFields({
                        templateText: '',
                        nextTitle: null,
                        nextDescription: null,
                        nextWork: null,
                        isLayoutTarget: false,
                    });
                }

                await saveConfig({
                    path: activePath,
                    layout: layoutPayload,
                }, statusEl);

                clearPromptHistory();
                renderContext();
                renderSummary(`Reset ${isLayoutTarget ? 'layout wrapper' : 'wrapped partial'} to inherited/default template.`);
                reloadViewer();
                setPromptStatus(`${isLayoutTarget ? 'Layout wrapper' : 'Wrapped partial'} reset to inherited/default template.`, true);
            } catch (err) {
                setPromptStatus(err?.message || 'Template reset failed.');
                renderSummary('Template reset failed.');
            } finally {
                setGeneratingState(false);
                isSending = false;
            }
        },
        onSendPrompt: async () => {
            if (isSending || (!promptInputEl.value.trim() && !imageAttachment)) {
                return;
            }
            isSending = true;
            setGeneratingState(true, 'Generating answer...');
            stopStreaming(stream);
            let pendingAssistantIndex = null;
            let settled = false;
            let requestSummary = null;
            const fallbackTimer = window.setTimeout(() => {
                if (settled) {
                    return;
                }
                stopStreaming(stream);
                setGeneratingState(false);
                const errMsg = 'Prompt timed out after 5 minutes.';
                setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
                renderHistory({ forceScroll: true });
                setPromptStatus(errMsg);
                isSending = false;
            }, PROMPT_FALLBACK_TIMEOUT_MS);
            try {
                const userPrompt = promptInputEl.value.trim();
                const providerValue = providerEl ? providerEl.value : 'local';
                const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : '';
                const selection = currentSelection({ path: activePath, previewPath: activePath, previewIsFile: false, isLayout: false });
                if ((providerValue === 'openai' || providerValue === 'gemini') && apiKeyValue === '') {
                    setGeneratingState(false);
                    setPromptStatus(providerValue === 'openai'
                        ? 'Add an OpenAI API key to send prompts.'
                        : 'Add a Gemini API key to send prompts.');
                    isSending = false;
                    return;
                }
                setHistory([...promptHistory, { role: 'user', content: userPrompt }].slice(-12));
                // Add a temporary assistant placeholder
                setHistory([...promptHistory, { role: 'assistant', content: 'Generating answer...' }].slice(-12));
                pendingAssistantIndex = promptHistory.length - 1;
                writeHistoryForSelection(promptHistory, selection);
                renderHistory({ forceScroll: true });
                renderContext();
                promptInputEl.value = '';
                setPromptStatus('Generating answer...');
                renderSummary('Generating answer...');
                const historyForRequest = serializeHistoryForRequest(promptHistory.slice(0, -1));
                const systemPromptValue = (systemPromptEl?.value || '').trim();
                const payload = {
                    path: activePath,
                    provider: providerEl ? providerEl.value : 'local',
                    model: modelEl ? modelEl.value.trim() : '',
                    endpoint: endpointEl ? endpointEl.value.trim() : '',
                    apiKey: apiKeyEl ? apiKeyEl.value.trim() : '',
                    prompt: userPrompt,
                    history: historyForRequest,
                    systemPrompt: systemPromptValue,
                };
                const editorDraft = readPromptEditorDraft(selection);
                if (editorDraft) {
                    payload.draft = editorDraft;
                }
                if (selection?.isLayout) {
                    const layoutPreset = getLayoutPreset();
                    if (layoutPreset) {
                        payload.layoutPreset = layoutPreset;
                    }
                }
                if (imageAttachment) {
                    payload.image = { ...imageAttachment };
                }
                requestSummary = summarizePromptRequest(payload);
                debugPromptLog('request', requestSummary);
                const useStreaming = !!(streamToggleEl && streamToggleEl.checked);
                if (useStreaming) {
                    beginStreaming({
                        stream,
                        targetIndex: pendingAssistantIndex,
                        history: promptHistory,
                        renderHistory: () => renderHistory({ forceScroll: true }),
                    });
                }
                const response = useStreaming
                    ? await requestPromptTemplateStream(payload, {
                        onDelta: (chunk) => appendStreamingChunk({
                            stream,
                            chunk,
                            history: promptHistory,
                            renderHistory: () => renderHistory({ forceScroll: true }),
                        }),
                    })
                    : await requestPromptTemplate(payload);
                settled = true;
                debugPromptLog('response', summarizePromptResponse(response, requestSummary));
                const templateText = (response && typeof response.template === 'string') ? response.template.trim() : '';
                const nextTitle = typeof response.title === 'string' ? response.title.trim() : null;
                const nextDescription = typeof response.description === 'string' ? response.description.trim() : null;
                const nextCss = typeof response.css === 'string' ? response.css : null;
                const nextJs = typeof response.js === 'string' ? response.js : null;
                const isLayoutTarget = !!selection.isLayout;
                const currentConfig = getConfig ? getConfig() : null;
                const layoutSectionKey = isLayoutTarget
                    ? ((selection?.previewIsFile || selection?.layoutIsFile) ? 'work.hbs' : 'works.hbs')
                    : '';
                const rawResponseWork = (response && response.work && typeof response.work === 'object')
                    ? response.work
                    : null;
                const responseSectionTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork[layoutSectionKey] === 'string'
                    ? rawResponseWork[layoutSectionKey]
                    : null;
                const responseWorkTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork['work.hbs'] === 'string'
                    ? rawResponseWork['work.hbs']
                    : null;
                const responseWorksTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork['works.hbs'] === 'string'
                    ? rawResponseWork['works.hbs']
                    : null;
                const inferredWork = inferWorkChangesFromPrompt(userPrompt, currentConfig);
                const mergedWork = {
                    ...(inferredWork || {}),
                    ...(rawResponseWork || {}),
                };
                const nextWork = filterAllowedWork(mergedWork, currentConfig);
                const nextLayoutValue = nextWork && Object.prototype.hasOwnProperty.call(nextWork, 'layout')
                    ? nextWork.layout
                    : null;
                const persistedWork = nextWork && typeof nextWork === 'object'
                    ? materializeWorkFields(nextWork)
                    : null;
                if (persistedWork && Object.prototype.hasOwnProperty.call(persistedWork, 'layout')) {
                    delete persistedWork.layout;
                }
                if (response.error || !templateText) {
                    stopStreaming(stream);
                    setGeneratingState(false);
                    const errMsg = response.error || 'Prompt returned no content.';
                    setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
                    writeHistoryForSelection(promptHistory, selection);
                    renderHistory({ forceScroll: true });
                    setPromptStatus(errMsg);
                    renderSummary(errMsg);
                    return;
                }
                const templateSnapshot = buildTemplateHistorySnapshot({
                    templateText,
                    nextCss,
                    nextJs,
                    nextTitle,
                    nextDescription,
                    nextWork: persistedWork || nextWork,
                    isLayoutTarget,
                });
                if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                    promptHistory[pendingAssistantIndex].content = templateText;
                    promptHistory[pendingAssistantIndex].templateSnapshot = templateSnapshot;
                    setHistory(promptHistory);
                } else {
                    setHistory([...promptHistory, {
                        role: 'assistant',
                        content: templateText,
                        templateSnapshot,
                    }].slice(-12));
                    pendingAssistantIndex = promptHistory.length - 1;
                }
                if (useStreaming) {
                    finishStreaming({
                        stream,
                        history: promptHistory,
                        fullText: templateText,
                        renderHistory: () => renderHistory({ forceScroll: true }),
                    });
                }
                writeHistoryForSelection(promptHistory, selection);
                renderHistory({ forceScroll: true });
                renderContext();
                if (response.systemPrompt && systemPromptEl && !systemPromptEl.value.trim()) {
                    systemPromptEl.value = response.systemPrompt;
                    savePromptSettings(readSettings());
                }
                updatePromptEditorFields({
                    templateText,
                    nextTitle,
                    nextDescription,
                    nextWork,
                    isLayoutTarget,
                    nextCss,
                    nextJs,
                });
                syncWorkFieldEditors(nextWork);
                focusPromptTemplateField(isLayoutTarget);
                if (drawerForm) {
                    const templateField = drawerForm.querySelector('#edit-content-template');
                    if (!isLayoutTarget && templateField) {
                        templateField.value = templateText;
                    }
                    const layoutNameField = drawerForm.querySelector('#edit-work-layout');
                    if (layoutNameField && !layoutNameField.value.trim()) {
                        layoutNameField.value = 'poff-layout';
                    }
                    if (nextWork && typeof nextWork.type === 'string') {
                        const workTypeField = drawerForm.querySelector('#edit-work-type');
                        if (workTypeField) {
                            workTypeField.value = nextWork.type;
                        }
                    }
                }
                const { layoutPayload } = buildPromptLayoutPayload({
                    selection,
                    currentConfig,
                    drawerForm,
                    templateText,
                    responseSectionTemplate,
                    responseWorkTemplate,
                    responseWorksTemplate,
                    nextCss,
                    nextJs,
                    nextLayoutValue,
                    responseModel: response.model || '',
                    layoutPreset: getLayoutPreset(),
                });
                const savePayload = {
                    path: activePath,
                    layout: layoutPayload,
                };
                if (nextTitle !== null) {
                    savePayload.title = nextTitle;
                }
                if (nextDescription !== null) {
                    savePayload.description = nextDescription;
                }
                if (persistedWork && Object.keys(persistedWork).length) {
                    savePayload.work = persistedWork;
                }
                await saveConfig(savePayload, statusEl);
                renderContext();
                const providerLabel = response.provider || payload.provider;
                const modelLabel = response.model || payload.model || '';
                setPromptStatus(`${isLayoutTarget ? 'Layout' : 'Template'} updated via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}`, true);
                const extra = [];
                if (nextTitle !== null) extra.push('title');
                if (nextDescription !== null) extra.push('description');
                if (persistedWork && Object.keys(persistedWork).length) extra.push(`work: ${Object.keys(persistedWork).join(', ')}`);
                if (nextLayoutValue) extra.push('layout');
                if (nextCss !== null) extra.push('css');
                if (nextJs !== null) extra.push('js');
                const summaryText = `Saved ${templateText.length} ${isLayoutTarget ? 'layout ' : ''}HBS chars via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}${extra.length ? ` · updated ${extra.join('; ')}` : ''}`;
                renderSummary(summaryText);
                clearAttachment();
                reloadViewer();
            } catch (err) {
                settled = true;
                stopStreaming(stream);
                setGeneratingState(false);
                debugPromptLog('error', summarizePromptError(err, requestSummary || (activePath ? { path: activePath } : null)));
                setPromptStatus('Prompt failed.');
                const errMsg = 'Prompt failed.';
                setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
                writeHistoryForSelection(promptHistory, selection);
                renderHistory({ forceScroll: true });
                renderSummary(errMsg);
            } finally {
                window.clearTimeout(fallbackTimer);
                setGeneratingState(false);
                isSending = false;
                promptInputEl.focus();
            }
        },
        onAttachImage: attachImageFile,
        onRemoveImage: () => {
            clearAttachment();
            setPromptStatus('Image removed.');
        },
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

    const reloadViewer = () => {
        const frame = document.getElementById('contentFrame');
        const selection = currentSelection({ path: '', isFile: false });
        const selectionPath = selection && Object.prototype.hasOwnProperty.call(selection, 'previewPath')
            ? selection.previewPath
            : undefined;
        const activeViewerPath = selectionPath ?? activePath;
        if (frame && activeViewerPath !== null && activeViewerPath !== undefined) {
            window.dispatchEvent(new CustomEvent('poff:content-updated', {
                detail: {
                    path: activeViewerPath,
                    target: selection?.previewIsFile ? 'file' : 'folder',
                },
            }));
        }
    };

    const syncHistoryForPath = () => {
        const selection = currentSelection({ path: '' });
        const nextPath = selection?.path || '';
        const nextPromptMode = selection?.isLayout ? 'layout' : (selection?.previewIsFile ? 'file' : 'folder');
        if (nextPath !== activePath || nextPromptMode !== activePromptMode) {
            activePath = nextPath;
            activePromptMode = nextPromptMode;
            setHistory(readHistoryForSelection(selection));
            syncModeAwareSystemPrompt();
            renderHistory();
            renderContext();
            renderSummary('Waiting for response...');
            updateAttachmentUi();
        }
    };
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
}
