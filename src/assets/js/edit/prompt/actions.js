export function bindPromptActions({
    promptClearEl,
    promptTemplateResetEl,
    promptSendEl,
    promptInputEl,
    promptAttachEl,
    promptInsertNameEl,
    promptImageInputEl,
    promptAttachmentRemoveEl,
    layoutPresetEl,
    onClearChat,
    onResetTemplate,
    onSendPrompt,
    onAttachImage,
    onInsertName,
    onRemoveImage,
    onTemplateInput,
    onLayoutPresetChange,
}) {
    if (layoutPresetEl && typeof onLayoutPresetChange === 'function') {
        layoutPresetEl.addEventListener('change', onLayoutPresetChange);
    }

    if (promptClearEl && typeof onClearChat === 'function') {
        promptClearEl.addEventListener('click', onClearChat);
    }

    if (promptTemplateResetEl && typeof onResetTemplate === 'function') {
        promptTemplateResetEl.addEventListener('click', onResetTemplate);
    }

    if (promptSendEl && promptInputEl && typeof onSendPrompt === 'function') {
        promptSendEl.addEventListener('click', () => {
            void onSendPrompt();
        });

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
                void onSendPrompt();
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
            if (typeof onAttachImage === 'function') {
                void onAttachImage(file);
            }
        });
    }

    if (promptInsertNameEl && typeof onInsertName === 'function') {
        promptInsertNameEl.addEventListener('click', () => {
            void onInsertName();
        });
    }

    if (promptAttachEl && promptImageInputEl) {
        promptAttachEl.addEventListener('click', () => {
            promptImageInputEl.click();
        });
        promptImageInputEl.addEventListener('change', async () => {
            const file = promptImageInputEl.files && promptImageInputEl.files[0] ? promptImageInputEl.files[0] : null;
            if (!file || typeof onAttachImage !== 'function') {
                return;
            }
            await onAttachImage(file);
        });
    }

    if (promptAttachmentRemoveEl && typeof onRemoveImage === 'function') {
        promptAttachmentRemoveEl.addEventListener('click', onRemoveImage);
    }
}
