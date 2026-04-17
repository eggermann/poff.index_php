import { getLayoutState } from '../core/utils.js';
import { defaultPromptSettings, getDefaultModelForProvider, getDefaultSystemPrompt } from './prompt/constants.js';
import { loadPromptSettings, savePromptSettings, readStoredHistory, writeStoredHistory } from './prompt/storage.js';
import { tagHistory, filterAllowedWork, inferWorkChangesFromPrompt } from './prompt/history.js';
import { buildPromptContext, renderPromptContext, renderPromptHistory, renderPromptSummary } from './prompt/render.js';
import { createStreamState, startStreaming, stopStreaming } from './prompt/stream.js';

const PROMPT_FALLBACK_TIMEOUT_MS = 95000;
const PROMPT_LAYER_STATE_KEY = 'poffEditPromptLayerState';

let promptHistory = [];
const stream = createStreamState();

const summarizePromptRequest = (payload) => ({
    path: typeof payload?.path === 'string' ? payload.path : '',
    provider: typeof payload?.provider === 'string' ? payload.provider : 'local',
    model: typeof payload?.model === 'string' ? payload.model : '',
    endpoint: typeof payload?.endpoint === 'string' ? payload.endpoint : '',
    promptLength: typeof payload?.prompt === 'string' ? payload.prompt.length : 0,
    historyCount: Array.isArray(payload?.history) ? payload.history.length : 0,
    hasApiKey: typeof payload?.apiKey === 'string' ? payload.apiKey.trim() !== '' : false,
    hasImage: !!payload?.image,
    systemPromptLength: typeof payload?.systemPrompt === 'string' ? payload.systemPrompt.length : 0,
});

const summarizePromptResponse = (response, requestSummary) => ({
    path: requestSummary?.path || '',
    provider: response?.provider || requestSummary?.provider || 'local',
    model: response?.model || requestSummary?.model || '',
    allowed: response?.allowed === true,
    hasTemplate: typeof response?.template === 'string' && response.template.trim() !== '',
    templateLength: typeof response?.template === 'string' ? response.template.trim().length : 0,
    error: typeof response?.error === 'string' ? response.error : '',
});

const summarizePromptError = (err, requestSummary) => ({
    path: requestSummary?.path || '',
    provider: requestSummary?.provider || 'local',
    model: requestSummary?.model || '',
    name: typeof err?.name === 'string' ? err.name : 'Error',
    message: typeof err?.message === 'string' ? err.message : String(err || 'Prompt failed.'),
});

const updatePromptEditorFields = ({ templateText, nextTitle, nextDescription, nextWork, isLayoutTarget, nextCss = null, nextJs = null }) => {
    const templateSelectors = isLayoutTarget
        ? ['#edit-layout-primary-template']
        : ['#edit-content-template'];

    templateSelectors.forEach((selector) => {
        document.querySelectorAll(selector).forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = templateText;
            }
        });
    });

    if (nextTitle !== null) {
        document.querySelectorAll('#edit-title').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextTitle;
            }
        });
    }

    if (nextDescription !== null) {
        document.querySelectorAll('#edit-description').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextDescription;
            }
        });
    }

    if (nextWork && typeof nextWork.type === 'string') {
        document.querySelectorAll('#edit-work-type').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextWork.type;
            }
        });
    }

    if (isLayoutTarget && nextCss !== null) {
        document.querySelectorAll('#edit-layout-primary-css').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextCss;
            }
        });
    }

    if (isLayoutTarget && nextJs !== null) {
        document.querySelectorAll('#edit-layout-primary-js').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextJs;
            }
        });
    }
};

const debugPromptLog = (label, payload) => {
    try {
        // Log quietly without breaking if console is missing
        /* eslint-disable no-console */
        console.info(`[prompt] ${label}`, payload);
        /* eslint-enable no-console */
    } catch (err) {
        // ignore
    }
};

const builtInSystemPrompts = new Set([
    getDefaultSystemPrompt('work'),
    getDefaultSystemPrompt('layout'),
]);

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
    const settings = loadPromptSettings();
    let isSending = false;
    let activePath = getActiveSelection ? getActiveSelection().path : '';
    let activePromptMode = getActiveSelection?.().isLayout ? 'layout' : 'work';
    let imageAttachment = null;
    let promptLayerCollapsed = false;
    const imageContextPattern = /\.(avif|bmp|gif|heic|heif|jpe?g|png|svg|webp)$/i;
    const defaultPromptPlaceholder = promptInputEl?.getAttribute('placeholder') || 'Describe the component you want...';
    const currentPromptPlaceholder = () => (currentPromptMode() === 'layout'
        ? 'Describe the layout you want...'
        : defaultPromptPlaceholder);
    const currentPromptMode = () => (getActiveSelection?.().isLayout ? 'layout' : 'work');
    const currentDefaultSystemPrompt = () => getDefaultSystemPrompt(currentPromptMode());
    const currentSystemPromptSettingKey = () => (currentPromptMode() === 'layout' ? 'systemPromptLayout' : 'systemPromptWork');
    const currentHasImageContext = () => {
        const selection = getActiveSelection ? getActiveSelection() : null;
        const selectionPath = typeof selection?.previewPath === 'string' && selection.previewPath.trim() !== ''
            ? selection.previewPath
            : (typeof selection?.path === 'string' ? selection.path : '');

        return imageContextPattern.test(selectionPath);
    };

    const readPromptLayerState = () => {
        try {
            const stored = JSON.parse(localStorage.getItem(PROMPT_LAYER_STATE_KEY) || '{}');
            return !!stored.collapsed;
        } catch (err) {
            return false;
        }
    };

    const writePromptLayerState = (collapsed) => {
        try {
            localStorage.setItem(PROMPT_LAYER_STATE_KEY, JSON.stringify({ collapsed: !!collapsed }));
        } catch (err) {
            // Ignore storage failures.
        }
    };

    const applyPromptLayerState = (collapsed, options = {}) => {
        promptLayerCollapsed = !!collapsed;
        root.classList.toggle('prompt-layer-collapsed', promptLayerCollapsed);
        if (promptWindowEl) {
            promptWindowEl.hidden = promptLayerCollapsed;
        }
        if (promptLayerCloseEl) {
            promptLayerCloseEl.hidden = promptLayerCollapsed;
        }
        if (promptLayerOpenEl) {
            promptLayerOpenEl.hidden = !promptLayerCollapsed;
        }
        if (!options.skipPersist) {
            writePromptLayerState(promptLayerCollapsed);
        }
    };

    const syncModeAwareSystemPrompt = () => {
        if (!systemPromptEl) {
            return;
        }
        const currentValue = (systemPromptEl.value || '').trim();
        if (currentValue !== '' && !builtInSystemPrompts.has(currentValue)) {
            return;
        }
        const nextValue = currentDefaultSystemPrompt();
        if (systemPromptEl.value !== nextValue) {
            systemPromptEl.value = nextValue;
            settings[currentSystemPromptSettingKey()] = nextValue;
            savePromptSettings(readSettings());
        }
        if (promptInputEl && !isSending) {
            promptInputEl.placeholder = currentPromptPlaceholder();
        }
    };

    const setHistory = (nextHistory) => {
        const list = Array.isArray(nextHistory) ? nextHistory : [];
        promptHistory = tagHistory(list);
    };

    const renderHistory = (options = {}) => {
        renderPromptHistory(promptMessagesEl, promptHistory, stream.state, options);
    };

    const getCurrentTemplateField = () => {
        const selection = getActiveSelection ? getActiveSelection() : null;
        const selector = selection?.isLayout ? '#edit-layout-primary-template' : '#edit-content-template';
        return document.querySelector(selector);
    };

    const renderTemplatePreview = () => {
        if (!promptTemplateCodeEl) {
            return;
        }
        const selection = getActiveSelection ? getActiveSelection() : null;
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
        activePath = context.path;
        activePromptMode = currentPromptMode();
        syncModeAwareSystemPrompt();
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

    const isSupportedImageFile = (file) => !!file && typeof file.type === 'string' && file.type.startsWith('image/');

    const readImageFile = (file) => new Promise((resolve, reject) => {
        if (!isSupportedImageFile(file)) {
            reject(new Error('Only image files are supported.'));
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = typeof reader.result === 'string' ? reader.result : '';
            if (!dataUrl.startsWith('data:image/')) {
                reject(new Error('Invalid image data.'));
                return;
            }
            resolve({
                name: file.name || 'clipboard-image.png',
                mimeType: file.type || 'image/png',
                dataUrl,
            });
        };
        reader.onerror = () => reject(new Error('Failed to read image.'));
        reader.readAsDataURL(file);
    });

    const attachImageFile = async (file) => {
        try {
            imageAttachment = await readImageFile(file);
            updateAttachmentUi();
            if (statusEl) {
                statusEl.textContent = `Attached image: ${imageAttachment.name}`;
                statusEl.className = 'edit-status edit-status-success';
            }
        } catch (err) {
            if (statusEl) {
                statusEl.textContent = err.message || 'Failed to attach image.';
                statusEl.className = 'edit-status';
            }
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

    promptLayerCollapsed = readPromptLayerState();
    applyPromptLayerState(promptLayerCollapsed, { skipPersist: true });

    if (promptLayerCloseEl) {
        promptLayerCloseEl.addEventListener('click', () => {
            applyPromptLayerState(true);
        });
    }

    if (promptLayerOpenEl) {
        promptLayerOpenEl.addEventListener('click', () => {
            applyPromptLayerState(false);
        });
    }

    if (providerEl) {
        providerEl.value = settings.provider || 'local';
    }
    if (systemPromptEl) {
        systemPromptEl.value = currentPromptMode() === 'layout'
            ? (settings.systemPromptLayout || settings.systemPrompt || currentDefaultSystemPrompt())
            : (settings.systemPromptWork || settings.systemPrompt || currentDefaultSystemPrompt());
    }
    if (streamToggleEl) {
        streamToggleEl.checked = settings.streamPreview !== false;
    }

    const readSettings = () => {
        const systemPrompt = (systemPromptEl?.value || '').trim() || currentDefaultSystemPrompt();
        const nextSettings = {
            provider: providerEl ? providerEl.value : 'local',
            model: modelEl ? modelEl.value : '',
            endpoint: endpointEl ? endpointEl.value : '',
            apiKey: apiKeyEl ? apiKeyEl.value : '',
            systemPrompt,
            systemPromptWork: settings.systemPromptWork || getDefaultSystemPrompt('work'),
            systemPromptLayout: settings.systemPromptLayout || getDefaultSystemPrompt('layout'),
            streamPreview: streamToggleEl ? !!streamToggleEl.checked : true,
        };
        nextSettings[currentSystemPromptSettingKey()] = systemPrompt;
        return nextSettings;
    };
    let suppressSave = false;

    const applySettingsToUi = (s) => {
        suppressSave = true;
        if (providerEl) providerEl.value = s.provider || defaultPromptSettings.provider;
        if (modelEl) modelEl.value = s.model || '';
        if (endpointEl) endpointEl.value = s.endpoint || '';
        if (apiKeyEl) apiKeyEl.value = s.apiKey || '';
        if (systemPromptEl) {
            systemPromptEl.value = currentPromptMode() === 'layout'
                ? (s.systemPromptLayout || s.systemPrompt || currentDefaultSystemPrompt())
                : (s.systemPromptWork || s.systemPrompt || currentDefaultSystemPrompt());
        }
        if (streamToggleEl) streamToggleEl.checked = s.streamPreview !== false;
        suppressSave = false;
        updateProviderUi();
    };

    const updateProviderUi = () => {
        const provider = providerEl ? providerEl.value : 'local';
        if (endpointRow) {
            endpointRow.style.display = provider === 'local' ? 'block' : 'none';
        }
        if (modelEl) {
            modelEl.value = getDefaultModelForProvider(provider);
        }
        if (!suppressSave) {
            savePromptSettings(readSettings());
        }
    };

    if (providerEl) {
        providerEl.addEventListener('change', updateProviderUi);
    }
    if (modelEl) {
        modelEl.addEventListener('input', updateProviderUi);
    }
    if (endpointEl) {
        endpointEl.addEventListener('input', updateProviderUi);
    }
    if (apiKeyEl) {
        apiKeyEl.addEventListener('input', updateProviderUi);
    }
    if (systemPromptEl) {
        systemPromptEl.addEventListener('input', () => {
            settings[currentSystemPromptSettingKey()] = (systemPromptEl.value || '').trim() || currentDefaultSystemPrompt();
            savePromptSettings(readSettings());
        });
    }
    if (streamToggleEl) {
        streamToggleEl.addEventListener('change', () => {
            savePromptSettings(readSettings());
        });
    }
    if (systemResetEl && systemPromptEl) {
        systemResetEl.addEventListener('click', () => {
            systemPromptEl.value = currentDefaultSystemPrompt();
            settings[currentSystemPromptSettingKey()] = systemPromptEl.value;
            savePromptSettings(readSettings());
        });
    }
    if (settingsResetEl) {
        settingsResetEl.addEventListener('click', () => {
            const nextSettings = {
                ...defaultPromptSettings,
                systemPrompt: currentDefaultSystemPrompt(),
            };
            applySettingsToUi(nextSettings);
            savePromptSettings(nextSettings);
            renderContext();
        });
    }

    updateProviderUi();
    syncModeAwareSystemPrompt();
    setHistory(readStoredHistory(activePath));
    renderHistory();
    renderContext();
    renderSummary('Waiting for response...');
    updateAttachmentUi();

    const reloadViewer = () => {
        const frame = document.getElementById('contentFrame');
        const selection = getActiveSelection ? getActiveSelection() : { path: '', isFile: false };
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
        const selection = getActiveSelection ? getActiveSelection() : { path: '' };
        const nextPath = selection?.path || '';
        const nextPromptMode = selection?.isLayout ? 'layout' : 'work';
        if (nextPath !== activePath || nextPromptMode !== activePromptMode) {
            activePath = nextPath;
            activePromptMode = nextPromptMode;
            setHistory(readStoredHistory(activePath));
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

    const layoutPresetEl = document.getElementById('edit-layout-preset');
    if (layoutPresetEl) {
        layoutPresetEl.addEventListener('change', () => {
            renderContext();
        });
    }

    if (promptClearEl) {
        promptClearEl.addEventListener('click', () => {
            syncHistoryForPath();
            stopStreaming(stream);
            setHistory([]);
            writeStoredHistory(activePath, promptHistory);
            renderHistory();
            clearAttachment();
            if (statusEl) {
                statusEl.textContent = 'Chat cleared.';
                statusEl.className = 'edit-status';
            }
        });
    }

    if (promptTemplateResetEl) {
        promptTemplateResetEl.addEventListener('click', async () => {
            if (isSending) {
                return;
            }

            const selection = getActiveSelection ? getActiveSelection() : { path: activePath, isLayout: false, previewPath: activePath, layoutIsFile: false };
            const isLayoutTarget = !!selection?.isLayout;
            const resetLabel = isLayoutTarget ? 'current layout wrapper template' : 'current wrapped partial template';
            if (!window.confirm(`Reset the ${resetLabel} to the inherited/default version?`)) {
                return;
            }

            try {
                isSending = true;
                setGeneratingState(true, 'Resetting template...');
                if (statusEl) {
                    statusEl.textContent = 'Resetting template...';
                    statusEl.className = 'edit-status';
                }

                const currentConfig = getConfig ? (getConfig() || {}) : {};
                const layoutState = getLayoutState(currentConfig || {});
                const layoutPayload = {
                    name: currentConfig?.work?.layout?.name || 'poff-layout',
                    engine: currentConfig?.work?.layout?.engine || 'lightncandy',
                };

                if (isLayoutTarget) {
                    const layoutPresetEl = document.getElementById('edit-layout-preset');
                    const preset = (layoutPresetEl?.value || layoutState.preset || 'actual').trim();
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

                renderContext();
                renderSummary(`Reset ${isLayoutTarget ? 'layout wrapper' : 'wrapped partial'} to inherited/default template.`);
                reloadViewer();
                if (statusEl) {
                    statusEl.textContent = `${isLayoutTarget ? 'Layout wrapper' : 'Wrapped partial'} reset to inherited/default template.`;
                    statusEl.className = 'edit-status edit-status-success';
                }
            } catch (err) {
                if (statusEl) {
                    statusEl.textContent = err?.message || 'Template reset failed.';
                    statusEl.className = 'edit-status';
                }
                renderSummary('Template reset failed.');
            } finally {
                setGeneratingState(false);
                isSending = false;
            }
        });
    }

    if (promptSendEl && promptInputEl) {
        const sendPrompt = async () => {
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
                const errMsg = 'Prompt timed out after 95 seconds.';
                if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                    promptHistory[pendingAssistantIndex].content = errMsg;
                    setHistory(promptHistory);
                } else {
                    setHistory([...promptHistory, { role: 'assistant', content: errMsg }].slice(-12));
                }
                renderHistory({ forceScroll: true });
                if (statusEl) {
                    statusEl.textContent = errMsg;
                    statusEl.className = 'edit-status';
                }
                isSending = false;
            }, PROMPT_FALLBACK_TIMEOUT_MS);
            try {
                const userPrompt = promptInputEl.value.trim();
                const providerValue = providerEl ? providerEl.value : 'local';
                const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : '';
                if ((providerValue === 'openai' || providerValue === 'gemini') && apiKeyValue === '') {
                    setGeneratingState(false);
                    if (statusEl) {
                        statusEl.textContent = providerValue === 'openai'
                            ? 'Add an OpenAI API key to send prompts.'
                            : 'Add a Gemini API key to send prompts.';
                        statusEl.className = 'edit-status';
                    }
                    isSending = false;
                    return;
                }
                setHistory([...promptHistory, { role: 'user', content: userPrompt }].slice(-12));
                // Add a temporary assistant placeholder
                setHistory([...promptHistory, { role: 'assistant', content: 'Generating answer...' }].slice(-12));
                pendingAssistantIndex = promptHistory.length - 1;
                writeStoredHistory(activePath, promptHistory);
                renderHistory({ forceScroll: true });
                renderContext();
                promptInputEl.value = '';
                if (statusEl) {
                    statusEl.textContent = 'Generating answer...';
                    statusEl.className = 'edit-status';
                }
                renderSummary('Generating answer...');
                const historyForRequest = promptHistory.slice(0, -1).map((item) => ({
                    role: item.role,
                    content: item.content,
                }));
                const systemPromptValue = (systemPromptEl?.value || '').trim();
                const selection = getActiveSelection ? getActiveSelection() : { path: activePath, previewPath: activePath, previewIsFile: false, isLayout: false };
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
                if (selection?.isLayout) {
                    const layoutPresetEl = document.getElementById('edit-layout-preset');
                    if (layoutPresetEl && typeof layoutPresetEl.value === 'string' && layoutPresetEl.value.trim() !== '') {
                        payload.layoutPreset = layoutPresetEl.value.trim();
                    }
                }
                if (imageAttachment) {
                    payload.image = { ...imageAttachment };
                }
                requestSummary = summarizePromptRequest(payload);
                debugPromptLog('request', requestSummary);
                const response = await requestPromptTemplate(payload);
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
                    ? { ...nextWork }
                    : null;
                if (persistedWork && Object.prototype.hasOwnProperty.call(persistedWork, 'layout')) {
                    delete persistedWork.layout;
                }
                if (response.error || !templateText) {
                    stopStreaming(stream);
                    setGeneratingState(false);
                    const errMsg = response.error || 'Prompt returned no content.';
                    if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                        promptHistory[pendingAssistantIndex].content = errMsg;
                        setHistory(promptHistory);
                    } else {
                        setHistory([...promptHistory, { role: 'assistant', content: errMsg }].slice(-12));
                        pendingAssistantIndex = promptHistory.length - 1;
                    }
                    writeStoredHistory(activePath, promptHistory);
                    renderHistory({ forceScroll: true });
                    if (statusEl) {
                        statusEl.textContent = errMsg;
                        statusEl.className = 'edit-status';
                    }
                    renderSummary(errMsg);
                    return;
                }
                stopStreaming(stream);
                if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                    promptHistory[pendingAssistantIndex].content = templateText;
                    setHistory(promptHistory);
                } else {
                    setHistory([...promptHistory, { role: 'assistant', content: templateText }].slice(-12));
                    pendingAssistantIndex = promptHistory.length - 1;
                }
                writeStoredHistory(activePath, promptHistory);
                renderHistory({ forceScroll: true });
                if (streamToggleEl && streamToggleEl.checked && templateText) {
                    startStreaming({
                        stream,
                        targetIndex: pendingAssistantIndex ?? (promptHistory.length - 1),
                        fullText: templateText,
                        history: promptHistory,
                        renderHistory: () => renderHistory({ forceScroll: true }),
                    });
                }
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
                const elements = drawerForm ? drawerForm.elements : null;
                const resolvedLayoutName = (() => {
                    if (typeof nextLayoutValue === 'string' && nextLayoutValue.trim()) {
                        return nextLayoutValue.trim();
                    }
                    if (nextLayoutValue && typeof nextLayoutValue === 'object') {
                        const candidate = nextLayoutValue.name || nextLayoutValue.mode || nextLayoutValue.value || '';
                        if (typeof candidate === 'string' && candidate.trim()) {
                            return candidate.trim();
                        }
                    }
                    return (elements?.work_layout?.value || currentConfig?.work?.layout?.name || 'poff-layout').trim();
                })();
                const layoutPayload = {
                    name: resolvedLayoutName,
                    engine: 'lightncandy',
                };
                if (nextLayoutValue && typeof nextLayoutValue === 'object') {
                    if (typeof nextLayoutValue.engine === 'string' && nextLayoutValue.engine.trim()) {
                        layoutPayload.engine = nextLayoutValue.engine.trim();
                    }
                    if (typeof nextLayoutValue.model === 'string' && nextLayoutValue.model.trim()) {
                        layoutPayload.model = nextLayoutValue.model.trim();
                    }
                }
                if (response.model) {
                    layoutPayload.model = response.model;
                }
                if (isLayoutTarget) {
                    const layoutState = getLayoutState(currentConfig || {});
                    const layoutPresetEl = document.getElementById('edit-layout-preset');
                    const preset = (layoutPresetEl?.value || layoutState.preset || 'actual').trim();
                    layoutPayload.preset = preset;
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
                    layoutPayload.name = preset === 'none'
                        ? 'none'
                        : preset === 'custom'
                            ? 'custom-layout'
                            : (canEditResolvedFilesystemTarget ? 'filesystem-layout' : 'poff-layout');
                    if (shouldPersistToLocalWrapper) {
                        layoutPayload.template = templateText;
                        if (responseSectionTemplate !== null) {
                            layoutPayload.sectionTemplate = responseSectionTemplate;
                        }
                        if (nextCss !== null) {
                            layoutPayload.css = nextCss;
                        }
                        if (nextJs !== null) {
                            layoutPayload.js = nextJs;
                        }
                    } else if (canEditResolvedFilesystemTarget) {
                        layoutPayload.originalTarget = resolvedLayoutDirectory;
                        layoutPayload.originalTemplate = templateText;
                        if (responseSectionTemplate !== null) {
                            layoutPayload.sectionTemplate = responseSectionTemplate;
                        }
                        if (nextCss !== null) {
                            layoutPayload.originalCss = nextCss;
                        }
                        if (nextJs !== null) {
                            layoutPayload.originalJs = nextJs;
                        }
                    } else {
                        layoutPayload.name = 'custom-layout';
                        layoutPayload.template = templateText;
                        if (responseSectionTemplate !== null) {
                            layoutPayload.sectionTemplate = responseSectionTemplate;
                        }
                        if (nextCss !== null) {
                            layoutPayload.css = nextCss;
                        }
                        if (nextJs !== null) {
                            layoutPayload.js = nextJs;
                        }
                    }
                } else {
                    layoutPayload.sectionTemplate = templateText;
                }
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
                if (statusEl) {
                    const providerLabel = response.provider || payload.provider;
                    const modelLabel = response.model || payload.model;
                    statusEl.textContent = `${isLayoutTarget ? 'Layout' : 'Template'} updated via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}`;
                    statusEl.className = 'edit-status edit-status-success';
                }
                const providerLabel = response.provider || payload.provider;
                const modelLabel = response.model || payload.model || '';
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
                if (statusEl) {
                    statusEl.textContent = 'Prompt failed.';
                    statusEl.className = 'edit-status';
                }
                const errMsg = 'Prompt failed.';
                if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                    promptHistory[pendingAssistantIndex].content = errMsg;
                    setHistory(promptHistory);
                } else {
                    setHistory([...promptHistory, { role: 'assistant', content: errMsg }].slice(-12));
                }
                writeStoredHistory(activePath, promptHistory);
                renderHistory({ forceScroll: true });
                renderSummary(errMsg);
            } finally {
                window.clearTimeout(fallbackTimer);
                setGeneratingState(false);
                isSending = false;
                promptInputEl.focus();
            }
        };

        promptSendEl.addEventListener('click', () => {
            void sendPrompt();
        });

        if (promptAttachEl && promptImageInputEl) {
            promptAttachEl.addEventListener('click', () => {
                promptImageInputEl.click();
            });
            promptImageInputEl.addEventListener('change', async () => {
                const file = promptImageInputEl.files && promptImageInputEl.files[0] ? promptImageInputEl.files[0] : null;
                if (!file) {
                    return;
                }
                await attachImageFile(file);
            });
        }

        if (promptAttachmentRemoveEl) {
            promptAttachmentRemoveEl.addEventListener('click', () => {
                clearAttachment();
                if (statusEl) {
                    statusEl.textContent = 'Image removed.';
                    statusEl.className = 'edit-status';
                }
            });
        }

        promptInputEl.addEventListener('keydown', (event) => {
            if (
                event.key === 'Enter' &&
                !event.shiftKey &&
                !event.altKey &&
                !event.ctrlKey &&
                !event.metaKey &&
                !event.isComposing
            ) {
                event.preventDefault();
                void sendPrompt();
            }
        });

        promptInputEl.addEventListener('paste', (event) => {
            const items = event.clipboardData?.items ? Array.from(event.clipboardData.items) : [];
            const imageItem = items.find((item) => typeof item.type === 'string' && item.type.startsWith('image/'));
            if (!imageItem) {
                return;
            }
            const file = imageItem.getAsFile();
            if (!file) {
                return;
            }
            event.preventDefault();
            void attachImageFile(file);
        });
    }
}
