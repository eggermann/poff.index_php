import { defaultPromptSettings, defaultSystemPrompt } from './prompt/constants.js';
import { loadPromptSettings, savePromptSettings, readStoredHistory, writeStoredHistory } from './prompt/storage.js';
import { tagHistory, filterAllowedWork } from './prompt/history.js';
import { buildPromptContext, renderPromptContext, renderPromptHistory, renderPromptSummary } from './prompt/render.js';
import { createStreamState, startStreaming, stopStreaming } from './prompt/stream.js';

const PROMPT_FALLBACK_TIMEOUT_MS = 95000;

let promptHistory = [];
const stream = createStreamState();
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
    const promptInputEl = root.querySelector('#prompt-input');
    const promptSendEl = root.querySelector('#prompt-send');
    const promptAttachEl = root.querySelector('#prompt-attach');
    const promptClearEl = root.querySelector('#prompt-clear');
    const promptImageInputEl = root.querySelector('#prompt-image-input');
    const promptAttachmentEl = root.querySelector('#promptAttachment');
    const promptAttachmentPreviewEl = root.querySelector('#promptAttachmentPreview');
    const promptAttachmentNameEl = root.querySelector('#promptAttachmentName');
    const promptAttachmentRemoveEl = root.querySelector('#prompt-attachment-remove');
    const settings = loadPromptSettings();
    let isSending = false;
    let activePath = getActiveSelection ? getActiveSelection().path : '';
    let imageAttachment = null;
    const defaultPromptPlaceholder = promptInputEl?.getAttribute('placeholder') || 'Describe the component you want...';

    const setHistory = (nextHistory) => {
        const list = Array.isArray(nextHistory) ? nextHistory : [];
        promptHistory = tagHistory(list);
    };

    const renderHistory = (options = {}) => {
        renderPromptHistory(promptMessagesEl, promptHistory, stream.state, options);
    };

    const renderContext = () => {
        const context = buildPromptContext({ getActiveSelection, getConfig });
        activePath = context.path;
        renderPromptContext(promptContextEl, context);
    };

    const renderSummary = (content) => {
        renderPromptSummary(promptSummaryEl, content);
    };

    const updateAttachmentUi = () => {
        if (!promptAttachmentEl || !promptAttachmentPreviewEl || !promptAttachmentNameEl || !promptInputEl) {
            return;
        }
        const hasAttachment = !!imageAttachment;
        promptAttachmentEl.hidden = !hasAttachment;
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

    if (providerEl) {
        providerEl.value = settings.provider || 'local';
    }
    if (systemPromptEl) {
        systemPromptEl.value = settings.systemPrompt || defaultSystemPrompt;
    }
    if (streamToggleEl) {
        streamToggleEl.checked = settings.streamPreview !== false;
    }

    const readSettings = () => ({
        provider: providerEl ? providerEl.value : 'local',
        model: modelEl ? modelEl.value : '',
        endpoint: endpointEl ? endpointEl.value : '',
        apiKey: apiKeyEl ? apiKeyEl.value : '',
        systemPrompt: (systemPromptEl?.value || '').trim() || defaultSystemPrompt,
        streamPreview: streamToggleEl ? !!streamToggleEl.checked : true,
    });
    let suppressSave = false;

    const applySettingsToUi = (s) => {
        suppressSave = true;
        if (providerEl) providerEl.value = s.provider || defaultPromptSettings.provider;
        if (modelEl) modelEl.value = s.model || '';
        if (endpointEl) endpointEl.value = s.endpoint || '';
        if (apiKeyEl) apiKeyEl.value = s.apiKey || '';
        if (systemPromptEl) systemPromptEl.value = s.systemPrompt || defaultSystemPrompt;
        if (streamToggleEl) streamToggleEl.checked = s.streamPreview !== false;
        suppressSave = false;
        updateProviderUi();
    };

    const updateProviderUi = () => {
        const provider = providerEl ? providerEl.value : 'local';
        if (endpointRow) {
            endpointRow.style.display = provider === 'local' ? 'block' : 'none';
        }
        if (provider === 'openai' && modelEl && !modelEl.value.trim()) {
            modelEl.value = 'gpt-4o-mini';
        }
        if (provider === 'gemini' && modelEl && !modelEl.value.trim()) {
            modelEl.value = 'gemini-1.5-flash';
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
            systemPromptEl.value = defaultSystemPrompt;
            savePromptSettings(readSettings());
        });
    }
    if (settingsResetEl) {
        settingsResetEl.addEventListener('click', () => {
            applySettingsToUi(defaultPromptSettings);
            savePromptSettings(defaultPromptSettings);
            renderContext();
        });
    }

    updateProviderUi();
    setHistory(readStoredHistory(activePath));
    renderHistory();
    renderContext();
    renderSummary('Waiting for response...');
    updateAttachmentUi();

    const reloadViewer = () => {
        const frame = document.getElementById('contentFrame');
        const selection = getActiveSelection ? getActiveSelection() : { path: '', isFile: false };
        const activeViewerPath = selection?.path || activePath;
        if (frame && activeViewerPath) {
            const isFile = selection?.isFile ?? /\.[^\\/]+$/.test(activeViewerPath);
            frame.src = isFile
                ? `?view=1&file=${encodeURIComponent(activeViewerPath)}`
                : `?view=1&path=${encodeURIComponent(activeViewerPath)}`;
            return;
        }
        if (frame && frame.contentWindow) {
            try {
                frame.contentWindow.location.reload();
                return;
            } catch (err) {
                // ignore and fall back
            }
        }
        if (frame && frame.src) {
            frame.src = frame.src;
        }
    };

    const syncHistoryForPath = () => {
        const selection = getActiveSelection ? getActiveSelection() : { path: '' };
        const nextPath = selection?.path || '';
        if (nextPath !== activePath) {
            activePath = nextPath;
            setHistory(readStoredHistory(activePath));
            renderHistory();
            renderContext();
            renderSummary('Waiting for response...');
        }
    };
    window.addEventListener('hashchange', syncHistoryForPath);

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
                if (imageAttachment) {
                    payload.image = { ...imageAttachment };
                }
                debugPromptLog('request', payload);
                const response = await requestPromptTemplate(payload);
                settled = true;
                debugPromptLog('response', response);
                const templateText = (response && typeof response.template === 'string') ? response.template.trim() : '';
                const nextTitle = typeof response.title === 'string' ? response.title.trim() : null;
                const nextDescription = typeof response.description === 'string' ? response.description.trim() : null;
                const currentConfig = getConfig ? getConfig() : null;
                const nextWork = filterAllowedWork(response.work, currentConfig);
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
                if (drawerForm) {
                    const templateField = drawerForm.querySelector('#edit-work-template');
                    if (templateField) {
                        templateField.value = templateText;
                    }
                    const layoutNameField = drawerForm.querySelector('#edit-work-layout');
                    if (layoutNameField && !layoutNameField.value.trim()) {
                        layoutNameField.value = 'default-layout';
                    }
                    if (nextWork && typeof nextWork.type === 'string') {
                        const workTypeField = drawerForm.querySelector('#edit-work-type');
                        if (workTypeField) {
                            workTypeField.value = nextWork.type;
                        }
                    }
                }
                const titleField = document.getElementById('edit-title');
                if (titleField && nextTitle !== null) {
                    titleField.value = nextTitle;
                }
                const descriptionField = document.getElementById('edit-description');
                if (descriptionField && nextDescription !== null) {
                    descriptionField.value = nextDescription;
                }
                const elements = drawerForm ? drawerForm.elements : null;
                const layoutPayload = {
                    name: (elements?.work_layout?.value || 'default-layout').trim(),
                    engine: 'lightncandy',
                    template: templateText,
                };
                if (response.model) {
                    layoutPayload.model = response.model;
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
                if (nextWork) {
                    savePayload.work = nextWork;
                }
                await saveConfig(savePayload, statusEl);
                if (statusEl) {
                    const providerLabel = response.provider || payload.provider;
                    const modelLabel = response.model || payload.model;
                    statusEl.textContent = `Template updated via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}`;
                    statusEl.className = 'edit-status edit-status-success';
                }
                const providerLabel = response.provider || payload.provider;
                const modelLabel = response.model || payload.model || '';
                const extra = [];
                if (nextTitle !== null) extra.push('title');
                if (nextDescription !== null) extra.push('description');
                if (nextWork && Object.keys(nextWork).length) extra.push(`work: ${Object.keys(nextWork).join(', ')}`);
                const summaryText = `Saved ${templateText.length} HBS chars via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}${extra.length ? ` · updated ${extra.join('; ')}` : ''}`;
                renderSummary(summaryText);
                clearAttachment();
                reloadViewer();
            } catch (err) {
                settled = true;
                stopStreaming(stream);
                setGeneratingState(false);
                debugPromptLog('error', err);
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
