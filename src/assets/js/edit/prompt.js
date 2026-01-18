import { escapeHtml } from '../core/utils.js';

const promptSettingsKey = 'poffEditPromptSettings';
let promptHistory = [];

export function loadPromptSettings() {
    const defaults = {
        provider: 'local',
        model: '',
        endpoint: '',
        apiKey: '',
    };
    try {
        const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || '{}');
        return { ...defaults, ...stored };
    } catch (err) {
        return defaults;
    }
}

export function savePromptSettings(settings) {
    try {
        localStorage.setItem(promptSettingsKey, JSON.stringify(settings));
    } catch (err) {
        // Ignore storage failures.
    }
}

function renderPromptHistory(container) {
    if (!container) {
        return;
    }
    if (!promptHistory.length) {
        container.innerHTML = '<div class="small-note">No messages yet.</div>';
        return;
    }
    container.innerHTML = promptHistory.map((msg) => `
        <div class="prompt-message">
            <span class="role">${escapeHtml(msg.role)}:</span>
            <span>${escapeHtml(msg.content)}</span>
        </div>
    `).join('');
}

export function bindPromptWindow({
    root,
    statusEl,
    drawerForm,
    getActiveSelection,
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
    const promptMessagesEl = root.querySelector('#promptMessages');
    const promptInputEl = root.querySelector('#prompt-input');
    const promptSendEl = root.querySelector('#prompt-send');
    const promptClearEl = root.querySelector('#prompt-clear');
    const settings = loadPromptSettings();

    if (providerEl) {
        providerEl.value = settings.provider || 'local';
    }

    const updateProviderUi = () => {
        const provider = providerEl ? providerEl.value : 'local';
        if (endpointRow) {
            endpointRow.style.display = provider === 'local' ? 'block' : 'none';
        }
        const nextSettings = {
            provider,
            model: modelEl ? modelEl.value : '',
            endpoint: endpointEl ? endpointEl.value : '',
            apiKey: apiKeyEl ? apiKeyEl.value : '',
        };
        savePromptSettings(nextSettings);
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

    updateProviderUi();
    renderPromptHistory(promptMessagesEl);

    if (promptClearEl) {
        promptClearEl.addEventListener('click', () => {
            promptHistory = [];
            renderPromptHistory(promptMessagesEl);
        });
    }

    if (promptSendEl && promptInputEl) {
        promptSendEl.addEventListener('click', async () => {
            if (!promptInputEl.value.trim()) {
                return;
            }
            const userPrompt = promptInputEl.value.trim();
            promptHistory = [...promptHistory, { role: 'user', content: userPrompt }].slice(-8);
            renderPromptHistory(promptMessagesEl);
            promptInputEl.value = '';
            if (statusEl) {
                statusEl.textContent = 'Generating template...';
                statusEl.className = 'edit-status';
            }
            const historyForRequest = promptHistory.slice(0, -1);
            const payload = {
                path: getActiveSelection().path,
                provider: providerEl ? providerEl.value : 'local',
                model: modelEl ? modelEl.value.trim() : '',
                endpoint: endpointEl ? endpointEl.value.trim() : '',
                apiKey: apiKeyEl ? apiKeyEl.value.trim() : '',
                prompt: userPrompt,
                history: historyForRequest,
            };
            const response = await requestPromptTemplate(payload);
            if (response.error || !response.template) {
                if (statusEl) {
                    statusEl.textContent = response.error || 'Prompt failed.';
                    statusEl.className = 'edit-status';
                }
                return;
            }
            promptHistory = [...promptHistory, { role: 'assistant', content: response.template }].slice(-8);
            renderPromptHistory(promptMessagesEl);
            if (drawerForm) {
                const templateField = drawerForm.querySelector('#edit-work-template');
                if (templateField) {
                    templateField.value = response.template;
                }
            }
            const elements = drawerForm ? drawerForm.elements : null;
            const layoutPayload = {
                mode: (elements?.work_layout?.value || '').trim(),
                template: response.template,
            };
            if (response.model) {
                layoutPayload.model = response.model;
            }
            await saveConfig({
                path: getActiveSelection().path,
                layout: layoutPayload,
            }, statusEl);
        });
    }
}
