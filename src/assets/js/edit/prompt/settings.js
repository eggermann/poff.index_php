export function bindPromptSettings({
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
    onRenderContext,
}) {
    const settings = loadPromptSettings();
    let suppressSave = false;

    const readSettings = () => {
        const systemPrompt = (systemPromptEl?.value || '').trim() || currentDefaultSystemPrompt();
        const nextSettings = {
            provider: providerEl ? providerEl.value : 'local',
            model: modelEl ? modelEl.value : '',
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
        if (modelEl && resetModel && !modelEl.value.trim()) {
            modelEl.value = getDefaultModelForProvider(provider);
        }
        persistSettings();
    };

    const applySettingsToUi = (nextSettings) => {
        suppressSave = true;
        if (providerEl) providerEl.value = nextSettings.provider || defaultPromptSettings.provider;
        if (modelEl) modelEl.value = nextSettings.model || '';
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
    if (endpointEl) {
        endpointEl.addEventListener('input', persistSettings);
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
