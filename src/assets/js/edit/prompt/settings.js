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
    requestPromptModels,
    loadPromptSettings,
    savePromptSettings,
    onRenderContext,
}) {
    const settings = loadPromptSettings();
    let suppressSave = false;
    let promptModelsRequestId = 0;
    const openAiFallbackModels = [
        'gpt-4o-mini',
        'gpt-4o',
        'gpt-4.1-mini',
        'gpt-4.1',
        'o4-mini',
        'o3-mini',
    ];
    const geminiFallbackModels = [
        'gemini-2.5-flash',
        'gemini-2.5-pro',
        'gemini-2.0-flash',
        'gemini-1.5-flash',
        'gemini-1.5-pro',
    ];

    const resolvePreferredModel = (provider, models, currentValue) => {
        const list = Array.isArray(models) ? models : [];
        const value = String(currentValue || '').trim();
        if (value && list.includes(value)) {
            return value;
        }
        if (provider !== 'local') {
            return list[0] || value || '';
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

    const setPromptModelOptions = (provider, models, selectedValue, placeholder = 'No models found') => {
        if (!modelSelectEl) {
            return;
        }
        const list = Array.isArray(models) ? models.filter((value) => typeof value === 'string' && value.trim() !== '') : [];
        const resolvedValue = resolvePreferredModel(provider, list, selectedValue);
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

    const providerUsesRemoteModelList = () => new Set(['local', 'openai', 'gemini']).has(providerEl?.value || 'local');

    const refreshPromptModelOptions = async () => {
        if (!modelSelectEl || !requestPromptModels || !providerUsesRemoteModelList()) {
            return;
        }
        const requestId = ++promptModelsRequestId;
        const currentValue = modelEl ? modelEl.value.trim() : '';
        const provider = providerEl?.value || 'local';
        const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : '';
        if (provider === 'openai' && apiKeyValue === '') {
            setPromptModelOptions(provider, openAiFallbackModels, currentValue, 'OpenAI models');
            persistSettings();
            return;
        }
        if (provider === 'gemini' && apiKeyValue === '') {
            setPromptModelOptions(provider, geminiFallbackModels, currentValue, 'Gemini models');
            persistSettings();
            return;
        }
        const waitingLabel = provider === 'openai'
            ? 'Loading OpenAI models...'
            : provider === 'gemini'
                ? 'Loading Gemini models...'
                : 'Loading local models...';
        modelSelectEl.innerHTML = `<option value="${currentValue || ''}">${waitingLabel}</option>`;
        modelSelectEl.value = currentValue || '';
        const result = await requestPromptModels({
            provider,
            endpoint: endpointEl ? endpointEl.value.trim() : '',
            apiKey: apiKeyValue,
        });
        if (requestId !== promptModelsRequestId) {
            return;
        }
        if (provider === 'openai' && (!result.models || result.models.length === 0)) {
            setPromptModelOptions(provider, openAiFallbackModels, currentValue, result.error || 'OpenAI models');
            if (!result.error) {
                persistSettings();
            }
            return;
        }
        if (provider === 'gemini' && (!result.models || result.models.length === 0)) {
            setPromptModelOptions(provider, geminiFallbackModels, currentValue, result.error || 'Gemini models');
            if (!result.error) {
                persistSettings();
            }
            return;
        }
        const emptyLabel = provider === 'openai'
            ? (result.error || 'No OpenAI models found')
            : provider === 'gemini'
                ? (result.error || 'No Gemini models found')
                : (result.error || 'No local models found');
        setPromptModelOptions(provider, result.models || [], currentValue, emptyLabel);
        if (!result.error) {
            persistSettings();
        }
    };

    const readModelValue = () => {
        if (providerUsesRemoteModelList() && modelSelectEl) {
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
            modelSelectEl.hidden = !providerUsesRemoteModelList();
        }
        if (modelEl) {
            modelEl.hidden = providerUsesRemoteModelList();
        }
        if (modelEl && resetModel && !modelEl.value.trim()) {
            modelEl.value = getDefaultModelForProvider(provider);
        }
        if (providerUsesRemoteModelList()) {
            void refreshPromptModelOptions();
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
                void refreshPromptModelOptions();
            }
        });
    }
    if (apiKeyEl) {
        apiKeyEl.addEventListener('input', () => {
            persistSettings();
            if (providerEl?.value === 'openai' || providerEl?.value === 'gemini') {
                void refreshPromptModelOptions();
            }
        });
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
