import { getLayoutState } from '../../core/utils.js';
import { requestPromptTemplate, requestPromptTemplateStream } from '../../api/edit.js';
import { filterAllowedWork, inferWorkChangesFromPrompt, buildTemplateHistorySnapshot, serializeHistoryForRequest } from './history.js';
import { readPromptEditorDraft } from './draft.js';
import { summarizePromptError, summarizePromptRequest, summarizePromptResponse } from './summary.js';
import { debugPromptLog } from './log.js';
import { materializeWorkFields } from '../work-fields.js';
import { buildPromptLayoutPayload } from './layout-payload.js';
import { appendStreamingChunk, beginStreaming, finishStreaming, stopStreaming } from './stream.js';

export function createPromptWindowWorkflows({
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
    currentSelectionName,
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
    getCurrentTemplateText,
    getLayoutPreset,
    forceLayoutPromptToCustom,
    updatePromptEditorFields,
    syncWorkFieldEditors,
    focusPromptTemplateField,
    reloadViewer,
    state,
    stream,
}) {
    const dropPendingAssistantHistory = (pendingAssistantIndex) => {
        if (pendingAssistantIndex === null || pendingAssistantIndex < 0 || pendingAssistantIndex >= state.promptHistory.length) {
            return state.promptHistory;
        }
        const nextHistory = state.promptHistory.slice();
        nextHistory.splice(pendingAssistantIndex, 1);
        return nextHistory;
    };

    const onClearChat = () => {
        stopStreaming(stream);
        setHistory([]);
        writeHistoryForSelection(state.promptHistory);
        renderHistory();
    };

    const onResetTemplate = async () => {
        if (state.isSending) {
            return;
        }

        const selection = currentSelection({ path: state.activePath, isLayout: false, previewPath: state.activePath, layoutIsFile: false });
        const isLayoutTarget = !!selection?.isLayout;
        const resetLabel = isLayoutTarget ? 'current layout wrapper template' : 'current wrapped partial template';
        if (!window.confirm(`Reset the ${resetLabel} to the inherited/default version?`)) {
            return;
        }

        try {
            state.isSending = true;
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
                path: state.activePath,
                layout: layoutPayload,
            }, statusEl);

            state.promptHistory = [];
            renderHistory();
            renderContext();
            renderSummary(`Reset ${isLayoutTarget ? 'layout wrapper' : 'wrapped partial'} to inherited/default template.`);
            reloadViewer();
            setPromptStatus(`${isLayoutTarget ? 'Layout wrapper' : 'Wrapped partial'} reset to inherited/default template.`, true);
        } catch (err) {
            setPromptStatus(err?.message || 'Template reset failed.');
            renderSummary('Template reset failed.');
        } finally {
            setGeneratingState(false);
            state.isSending = false;
        }
    };

    const onSendPrompt = async () => {
        if (state.isSending || (!promptInputEl.value.trim() && !state.imageAttachment)) {
            return;
        }
        state.isSending = true;
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
            state.isSending = false;
        }, 305000);
        try {
            const userPrompt = promptInputEl.value.trim();
            const providerValue = providerEl ? providerEl.value : 'local';
            const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : '';
            const selection = currentSelection({ path: state.activePath, previewPath: state.activePath, previewIsFile: false, isLayout: false });
            if ((providerValue === 'openai' || providerValue === 'gemini') && apiKeyValue === '') {
                setGeneratingState(false);
                setPromptStatus(providerValue === 'openai'
                    ? 'Add an OpenAI API key to send prompts.'
                    : 'Add a Gemini API key to send prompts.');
                state.isSending = false;
                return;
            }
            setHistory([...state.promptHistory, { role: 'user', content: userPrompt }].slice(-12));
            setHistory([...state.promptHistory, { role: 'assistant', content: 'Generating answer...' }].slice(-12));
            pendingAssistantIndex = state.promptHistory.length - 1;
            writeHistoryForSelection(state.promptHistory, selection);
            renderHistory({ forceScroll: true });
            renderContext();
            promptInputEl.value = '';
            setPromptStatus('Generating answer...');
            renderSummary('Generating answer...');
            const historyForRequest = serializeHistoryForRequest(state.promptHistory.slice(0, -1), {
                initialTemplateText: getCurrentTemplateText(),
            });
            const systemPromptValue = (systemPromptEl?.value || '').trim();
            const payload = {
                path: state.activePath,
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
            if (state.imageAttachment) {
                payload.image = { ...state.imageAttachment };
            }
            requestSummary = summarizePromptRequest(payload);
            debugPromptLog('request', requestSummary);
            const useStreaming = !!(streamToggleEl && streamToggleEl.checked);
            if (useStreaming) {
                beginStreaming({
                    stream,
                    targetIndex: pendingAssistantIndex,
                    history: state.promptHistory,
                    renderHistory: () => renderHistory({ forceScroll: true }),
                });
            }
            const response = useStreaming
                ? await requestPromptTemplateStream(payload, {
                    onDelta: (chunk) => appendStreamingChunk({
                        stream,
                        chunk,
                        history: state.promptHistory,
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
                writeHistoryForSelection(state.promptHistory, selection);
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
            if (pendingAssistantIndex !== null && state.promptHistory[pendingAssistantIndex]) {
                state.promptHistory[pendingAssistantIndex].content = templateText;
                state.promptHistory[pendingAssistantIndex].templateSnapshot = templateSnapshot;
                setHistory(state.promptHistory);
            } else {
                setHistory([...state.promptHistory, {
                    role: 'assistant',
                    content: templateText,
                    templateSnapshot,
                }].slice(-12));
                pendingAssistantIndex = state.promptHistory.length - 1;
            }
            if (useStreaming) {
                finishStreaming({
                    stream,
                    history: state.promptHistory,
                    fullText: templateText,
                    renderHistory: () => renderHistory({ forceScroll: true }),
                });
            }
            writeHistoryForSelection(state.promptHistory, selection);
            renderHistory({ forceScroll: true });
            renderContext();
            if (response.systemPrompt && systemPromptEl && !systemPromptEl.value.trim()) {
                systemPromptEl.value = response.systemPrompt;
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
                if (nextWork && (typeof nextWork.template === 'string' || typeof nextWork.type === 'string')) {
                    const workTypeField = drawerForm.querySelector('#edit-work-type');
                    if (workTypeField) {
                        workTypeField.value = nextWork.template || nextWork.type;
                    }
                }
            }
            const effectiveLayoutPreset = isLayoutTarget
                ? forceLayoutPromptToCustom()
                : getLayoutPreset();
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
                layoutPreset: effectiveLayoutPreset,
            });
            const savePayload = {
                path: state.activePath,
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
            if (Array.isArray(response?.treeVisible)) {
                savePayload.treeVisible = response.treeVisible;
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
            if (isLayoutTarget && nextCss !== null) extra.push('css');
            if (isLayoutTarget && nextJs !== null) extra.push('js');
            const summaryText = `Saved ${templateText.length} ${isLayoutTarget ? 'layout ' : ''}HBS chars via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}${extra.length ? ` · updated ${extra.join('; ')}` : ''}`;
            renderSummary(summaryText);
            clearAttachment();
            reloadViewer();
        } catch (err) {
            settled = true;
            stopStreaming(stream);
            setGeneratingState(false);
            debugPromptLog('error', summarizePromptError(err, requestSummary || (state.activePath ? { path: state.activePath } : null)));
            setPromptStatus('Prompt failed.');
            const errMsg = 'Prompt failed.';
            setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
            writeHistoryForSelection(state.promptHistory, selection);
            renderHistory({ forceScroll: true });
            renderSummary(errMsg);
        } finally {
            window.clearTimeout(fallbackTimer);
            setGeneratingState(false);
            state.isSending = false;
            promptInputEl.focus();
        }
    };

    return {
        onClearChat,
        onResetTemplate,
        onSendPrompt,
        onAttachImage: attachImageFile,
        onInsertName: async () => {
            const name = currentSelectionName();
            if (!name) {
                setPromptStatus('No file name selected.');
                return;
            }
            await insertPromptText(name);
        },
        onRemoveImage: () => {
            clearAttachment();
            setPromptStatus('Image removed.');
        },
        onTemplateInput: () => {
            if (typeof renderContext === 'function') {
                renderContext();
            }
        },
        onLayoutPresetChange: renderContext,
        restoreHistorySnapshot: async (snapshot) => {
            if (!snapshot || typeof snapshot !== 'object') {
                return false;
            }
            const isLayoutTarget = snapshot.targetType === 'layout';
            const templateText = typeof snapshot.template === 'string' ? snapshot.template : '';
            if (!templateText) {
                return false;
            }
            const restoredWork = snapshot.workSnapshot && typeof snapshot.workSnapshot === 'object'
                ? snapshot.workSnapshot
                : {};

            updatePromptEditorFields({
                templateText,
                nextTitle: typeof snapshot.title === 'string' ? snapshot.title : null,
                nextDescription: typeof snapshot.description === 'string' ? snapshot.description : null,
                nextWork: restoredWork,
                isLayoutTarget,
                nextCss: typeof snapshot.css === 'string' ? snapshot.css : null,
                nextJs: typeof snapshot.js === 'string' ? snapshot.js : null,
            });
            renderContext();
            const selection = currentSelection({ path: state.activePath, previewPath: state.activePath, previewIsFile: false, isLayout: isLayoutTarget });
            const currentConfig = getConfig ? (getConfig() || {}) : {};
            const { layoutPayload } = buildPromptLayoutPayload({
                selection,
                currentConfig,
                drawerForm,
                templateText,
                nextCss: typeof snapshot.css === 'string' ? snapshot.css : null,
                nextJs: typeof snapshot.js === 'string' ? snapshot.js : null,
                layoutPreset: isLayoutTarget ? forceLayoutPromptToCustom() : getLayoutPreset(),
            });
            const savePayload = {
                path: state.activePath,
                layout: layoutPayload,
            };
            if (typeof snapshot.title === 'string' && snapshot.title.trim() !== '') {
                savePayload.title = snapshot.title.trim();
            }
            if (typeof snapshot.description === 'string' && snapshot.description.trim() !== '') {
                savePayload.description = snapshot.description.trim();
            }
            await saveConfig(savePayload, statusEl);
            renderSummary(`Restored ${isLayoutTarget ? 'layout' : 'template'} from assistant stage.`);
            reloadViewer();
            focusPromptTemplateField(isLayoutTarget);
            setPromptStatus('Restored template from assistant stage.', true);
            return true;
        },
    };
}
