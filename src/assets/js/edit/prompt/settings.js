export function bindPromptSettings({
    providerEl,
    modelEl,
    modelSelectEl,
    endpointRow,
    endpointEl,
    apiKeyRow,
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
    requestLocalPromptModels,
    loadPromptSettings,
    savePromptSettings,
    onRenderContext,
}) {
    const settings = loadPromptSettings();
    let suppressSave = false;
    let localModelsRequestId = 0;

    const resolvePreferredLocalModel = (models, currentValue) => {
        const list = Array.isArray(models) ? models : [];
        const value = String(currentValue || '').trim();
        if (value && list.includes(value)) {
            return value;
        }
        const aliases = {
            gemma4: ['google/gemma-4-e4b', 'google/gemma-4-31b', 'google/gemma-4-e2b'],
            qwen3_vl: ['qwen/qwen3-vl-4b', 'qwen3-vl-32b-instruct-mlx'],
            mistral3: ['mistralai/ministral-3-3b', 'mistralai/ministral-3-14b-reasoning'],
        };
        const aliasMatches = aliases[value] || [];
        for (const candidate of aliasMatches) {
            if (list.includes(candidate)) {
                return candidate;
            }
        }
        return list[0] || value || '';
    };

    const syncModelField = (value) => {
        if (modelEl) {
            modelEl.value = value || '';
        }
        if (modelSelectEl) {
            modelSelectEl.value = value || '';
        }
    };

    const setLocalModelOptions = (models, selectedValue, placeholder = 'No local models found') => {
        if (!modelSelectEl) {
            return;
        }
        const list = Array.isArray(models) ? models.filter((value) => typeof value === 'string' && value.trim() !== '') : [];
        const resolvedValue = resolvePreferredLocalModel(list, selectedValue);
        const currentValue = String(selectedValue || '').trim();
        const options = [];
        if (list.length === 0) {
            options.push({ value: currentValue, label: currentValue || placeholder });
        } else {
            for (const value of list) {
                options.push({ value, label: value });
            }
        }
        modelSelectEl.innerHTML = options
            .map(({ value, label }) => `<option value="${value}">${label}</option>`)
            .join('');
        syncModelField(resolvedValue || currentValue);
    };

    const refreshLocalModelOptions = async () => {
        if (!modelSelectEl || !requestLocalPromptModels) {
            return;
        }
        const requestId = ++localModelsRequestId;
        const currentValue = modelEl ? modelEl.value.trim() : '';
        modelSelectEl.innerHTML = `<option value="${currentValue || ''}">Loading local models...</option>`;
        modelSelectEl.value = currentValue || '';
        const result = await requestLocalPromptModels(endpointEl ? endpointEl.value.trim() : '');
        if (requestId !== localModelsRequestId) {
            return;
        }
        setLocalModelOptions(result.models || [], currentValue, result.error || 'No local models found');
        if (!result.error) {
            persistSettings();
        }
    };

    const readModelValue = () => {
        if (providerEl?.value === 'local' && modelSelectEl) {
            return modelSelectEl.value || modelEl?.value || '';
        }
        return modelEl ? modelEl.value : '';
    };

    const readSettings = () => {
        const systemPrompt = (systemPromptEl?.value || '').trim() || currentDefaultSystemPrompt();
        const nextSettings = {
            provider: providerEl ? providerEl.value : 'local',
            model: readModelValue(),
            endpoint: endpointEl ? endpointEl.value : '',
            apiKey: apiKeyEl ? apiKeyEl.value : '',
            systemPrompt,
            systemPromptFile: settings.systemPromptFile || defaultPromptSettings.systemPromptFile,
            systemPromptFolder: settings.systemPromptFolder || defaultPromptSettings.systemPromptFolder,
            systemPromptLayout: settings.systemPromptLayout || defaultPromptSettings.systemPromptLayout,
            streamPreview: streamToggleEl ? !!streamToggleEl.checked : true,
        };
        nextSettings[currentSystemPromptSettingKey()] = systemPrompt;
        return nextSettings;
    };

    const persistSettings = () => {
        if (!suppressSave) {
            savePromptSettings(readSettings());
        }
    };

    const updateProviderUi = ({ resetModel = false } = {}) => {
        const provider = providerEl ? providerEl.value : 'local';
        if (endpointRow) {
            endpointRow.hidden = provider !== 'local';
        }
        if (apiKeyRow) {
            apiKeyRow.hidden = provider === 'local';
        }
        if (modelSelectEl) {
            modelSelectEl.hidden = provider !== 'local';
        }
        if (modelEl) {
            modelEl.hidden = provider === 'local';
        }
        if (modelEl && resetModel && !modelEl.value.trim()) {
            modelEl.value = getDefaultModelForProvider(provider);
        }
        if (provider === 'local') {
            void refreshLocalModelOptions();
        }
        persistSettings();
    };

    const applySettingsToUi = (nextSettings) => {
        suppressSave = true;
        if (providerEl) providerEl.value = nextSettings.provider || defaultPromptSettings.provider;
        if (modelEl) modelEl.value = nextSettings.model || '';
        if (modelSelectEl) modelSelectEl.value = nextSettings.model || '';
        if (endpointEl) endpointEl.value = nextSettings.endpoint || '';
        if (apiKeyEl) apiKeyEl.value = nextSettings.apiKey || '';
        if (systemPromptEl) {
            const mode = currentPromptMode();
            systemPromptEl.value = mode === 'layout'
                ? (nextSettings.systemPromptLayout || nextSettings.systemPrompt || currentDefaultSystemPrompt())
                : mode === 'folder'
                    ? (nextSettings.systemPromptFolder || nextSettings.systemPrompt || currentDefaultSystemPrompt())
                    : (nextSettings.systemPromptFile || nextSettings.systemPrompt || currentDefaultSystemPrompt());
        }
        if (streamToggleEl) streamToggleEl.checked = nextSettings.streamPreview !== false;
        suppressSave = false;
        updateProviderUi();
    };

    const syncModeAwareSystemPrompt = () => {
        if (!systemPromptEl) {
            return;
        }
        const currentValue = (systemPromptEl.value || '').trim();
        if (currentValue !== '' && !new Set([settings.systemPromptFile, settings.systemPromptFolder, settings.systemPromptLayout]).has(currentValue)) {
            return;
        }
        const nextValue = currentDefaultSystemPrompt();
        if (systemPromptEl.value !== nextValue) {
            systemPromptEl.value = nextValue;
            settings[currentSystemPromptSettingKey()] = nextValue;
            savePromptSettings(readSettings());
        }
    };

    if (providerEl) {
        providerEl.addEventListener('change', () => updateProviderUi({ resetModel: false }));
    }
    if (modelEl) {
        modelEl.addEventListener('input', persistSettings);
    }
    if (modelSelectEl) {
        modelSelectEl.addEventListener('change', () => {
            syncModelField(modelSelectEl.value || '');
            persistSettings();
        });
    }
    if (endpointEl) {
        endpointEl.addEventListener('input', () => {
            persistSettings();
            if (providerEl?.value === 'local') {
                void refreshLocalModelOptions();
            }
        });
    }
    if (apiKeyEl) {
        apiKeyEl.addEventListener('input', persistSettings);
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
            if (typeof onRenderContext === 'function') {
                onRenderContext();
            }
        });
    }

    return {
        settings,
        readSettings,
        applySettingsToUi,
        updateProviderUi,
        syncModeAwareSystemPrompt,
    };
}
