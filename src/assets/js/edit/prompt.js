import { defaultPromptSettings, defaultSystemPrompt } from './prompt/constants.js';
import { loadPromptSettings, savePromptSettings, readStoredHistory, writeStoredHistory } from './prompt/storage.js';
import { tagHistory, filterAllowedWork } from './prompt/history.js';
import { buildPromptContext, renderPromptContext, renderPromptHistory, renderPromptSummary } from './prompt/render.js';
import { createStreamState, startStreaming, stopStreaming } from './prompt/stream.js';

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
    const promptInputEl = root.querySelector('#prompt-input');
    const promptSendEl = root.querySelector('#prompt-send');
    const promptClearEl = root.querySelector('#prompt-clear');
    const settings = loadPromptSettings();
    let isSending = false;
    let activePath = getActiveSelection ? getActiveSelection().path : '';

    const setHistory = (nextHistory) => {
        const list = Array.isArray(nextHistory) ? nextHistory : [];
        promptHistory = tagHistory(list);
    };

    const renderHistory = () => {
        renderPromptHistory(promptMessagesEl, promptHistory, stream.state);
    };

    const renderContext = () => {
        const context = buildPromptContext({ getActiveSelection, getConfig });
        activePath = context.path;
        renderPromptContext(promptContextEl, context);
    };

    const renderSummary = (content) => {
        renderPromptSummary(promptSummaryEl, content);
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

    const reloadViewer = () => {
        const frame = document.getElementById('contentFrame');
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
            if (statusEl) {
                statusEl.textContent = 'Chat cleared.';
                statusEl.className = 'edit-status';
            }
        });
    }

    if (promptSendEl && promptInputEl) {
        promptSendEl.addEventListener('click', async () => {
            if (isSending || !promptInputEl.value.trim()) {
                return;
            }
            isSending = true;
            stopStreaming(stream);
            let pendingAssistantIndex = null;
            let settled = false;
            const fallbackTimer = window.setTimeout(() => {
                if (settled) {
                    return;
                }
                stopStreaming(stream);
                const errMsg = 'Prompt timed out.';
                if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                    promptHistory[pendingAssistantIndex].content = errMsg;
                    setHistory(promptHistory);
                } else {
                    setHistory([...promptHistory, { role: 'assistant', content: errMsg }].slice(-12));
                }
                renderHistory();
                if (statusEl) {
                    statusEl.textContent = errMsg;
                    statusEl.className = 'edit-status';
                }
                isSending = false;
            }, 22000);
            try {
                const userPrompt = promptInputEl.value.trim();
                const providerValue = providerEl ? providerEl.value : 'local';
                const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : '';
                if ((providerValue === 'openai' || providerValue === 'gemini') && apiKeyValue === '') {
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
                setHistory([...promptHistory, { role: 'assistant', content: '...' }].slice(-12));
                pendingAssistantIndex = promptHistory.length - 1;
                writeStoredHistory(activePath, promptHistory);
                renderHistory();
                renderContext();
                promptInputEl.value = '';
                if (statusEl) {
                    statusEl.textContent = 'Generating template...';
                    statusEl.className = 'edit-status';
                }
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
                debugPromptLog('request', payload);
                const response = await requestPromptTemplate(payload);
                settled = true;
                debugPromptLog('response', response);
                const templateText = (response && typeof response.template === 'string') ? response.template.trim() : '';
                const nextTitle = typeof response.title === 'string' ? response.title.trim() : null;
                const nextDescription = typeof response.description === 'string' ? response.description.trim() : null;
                const nextWork = filterAllowedWork(response.work);
                if (response.error || !templateText) {
                    stopStreaming(stream);
                    const errMsg = response.error || 'Prompt returned no content.';
                    if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
                        promptHistory[pendingAssistantIndex].content = errMsg;
                        setHistory(promptHistory);
                    } else {
                        setHistory([...promptHistory, { role: 'assistant', content: errMsg }].slice(-12));
                        pendingAssistantIndex = promptHistory.length - 1;
                    }
                    writeStoredHistory(activePath, promptHistory);
                    renderHistory();
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
                renderHistory();
                if (streamToggleEl && streamToggleEl.checked && templateText) {
                    startStreaming({
                        stream,
                        targetIndex: pendingAssistantIndex ?? (promptHistory.length - 1),
                        fullText: templateText,
                        history: promptHistory,
                        renderHistory,
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
                }
                const elements = drawerForm ? drawerForm.elements : null;
                const layoutPayload = {
                    mode: (elements?.work_layout?.value || '').trim(),
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
                const summaryText = `Saved ${templateText.length} chars via ${providerLabel}${modelLabel ? ` · ${modelLabel}` : ''}${extra.length ? ` · updated ${extra.join('; ')}` : ''}`;
                renderSummary(summaryText);
                reloadViewer();
            } catch (err) {
                settled = true;
                stopStreaming(stream);
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
                renderHistory();
                renderSummary(errMsg);
            } finally {
                window.clearTimeout(fallbackTimer);
                isSending = false;
                promptInputEl.focus();
            }
        });
    }
}
