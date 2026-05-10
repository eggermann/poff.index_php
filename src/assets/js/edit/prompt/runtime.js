import { getLayoutState } from '../../core/utils.js';
import { getDefaultSystemPromptForMode, getPromptMode, getPromptPlaceholderForMode, getSystemPromptSettingKeyForMode } from './mode.js';
import { defaultFileSystemPrompt, defaultFolderSystemPrompt, defaultLayoutSystemPrompt } from './constants.js';
import { readStoredHistory, writeStoredHistory } from './storage.js';
import { tagHistory } from './history.js';
import { buildPromptContext } from './build/context.js';
import { renderPromptContext } from './render/context.js';
import { renderPromptHistory } from './render/history.js';
import { renderPromptSummary } from './render/summary.js';
import { createStreamState } from './stream.js';
import { getLayoutPresetValue, getSelectionOrFallback } from '../selection.js';
import { setStatusMessage } from '../status.js';
import { updatePromptEditorFields, focusPromptTemplateField, syncWorkFieldEditors } from './editor-fields.js';

export function createPromptRuntime({
    root,
    statusEl,
    drawerForm,
    getActiveSelection,
    getConfig,
    requestEditConfig,
    promptInputEl,
    promptMessagesEl,
    promptContextEl,
    promptSummaryEl,
    promptGenerationEl,
    promptGenerationLabelEl,
    promptTemplateLabelEl,
    promptTemplateCodeEl,
    promptAttachmentEl,
    promptAttachEl,
    promptAttachmentPreviewEl,
    promptAttachmentNameEl,
    promptImageInputEl,
    promptSendEl,
    promptClearEl,
    promptAttachmentRemoveEl,
}) {
    const stream = createStreamState();
    const state = {
        promptHistory: [],
        activePath: getSelectionOrFallback(getActiveSelection, { path: '' }).path,
        activePromptMode: getSelectionOrFallback(getActiveSelection, {}).isLayout ? 'layout' : (getSelectionOrFallback(getActiveSelection, {}).previewIsFile ? 'file' : 'folder'),
        imageAttachment: null,
        isSending: false,
    };

    const defaultPromptPlaceholder = promptInputEl?.getAttribute('placeholder') || 'Describe the component you want...';
    const imageContextPattern = /\.(avif|bmp|gif|heic|heif|jpe?g|png|svg|webp)$/i;

    const currentSelection = (fallback = {}) => getSelectionOrFallback(getActiveSelection, fallback);
    const currentPromptMode = () => getPromptMode(currentSelection());
    const currentPromptPlaceholder = () => getPromptPlaceholderForMode(currentPromptMode(), defaultPromptPlaceholder);
    const currentDefaultSystemPrompt = () => getDefaultSystemPromptForMode(currentPromptMode(), {
        file: defaultFileSystemPrompt,
        folder: defaultFolderSystemPrompt,
        layout: defaultLayoutSystemPrompt,
    });
    const currentSystemPromptSettingKey = () => getSystemPromptSettingKeyForMode(currentPromptMode());

    const setPromptStatus = (message, success = false) => setStatusMessage(statusEl, message, success);

    const getLayoutPreset = () => getLayoutPresetValue();
    const forceLayoutPromptToCustom = () => {
        const presetEl = document.getElementById('edit-layout-preset');
        if (presetEl && presetEl.value !== 'custom') {
            presetEl.value = 'custom';
        }
        return 'custom';
    };

    const currentHasImageContext = () => {
        const selection = currentSelection(null);
        const selectionPath = typeof selection?.previewPath === 'string' && selection.previewPath.trim() !== ''
            ? selection.previewPath
            : (typeof selection?.path === 'string' ? selection.path : '');
        return imageContextPattern.test(selectionPath);
    };

    const currentSelectionName = () => {
        const selection = currentSelection(null);
        const selectionPath = typeof selection?.previewPath === 'string' && selection.previewPath.trim() !== ''
            ? selection.previewPath
            : (typeof selection?.path === 'string' ? selection.path : '');
        const normalizedPath = String(selectionPath || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
        if (!normalizedPath) {
            return '';
        }
        const parts = normalizedPath.split('/').filter(Boolean);
        return parts.length ? parts[parts.length - 1] : '';
    };

    const insertPromptText = async (text) => {
        if (!promptInputEl || !text) {
            return false;
        }
        const start = typeof promptInputEl.selectionStart === 'number' ? promptInputEl.selectionStart : promptInputEl.value.length;
        const end = typeof promptInputEl.selectionEnd === 'number' ? promptInputEl.selectionEnd : promptInputEl.value.length;
        const before = promptInputEl.value.slice(0, start);
        const after = promptInputEl.value.slice(end);
        const separator = before && !/\s$/.test(before) ? ' ' : '';
        const nextValue = `${before}${separator}${text}${after}`;
        promptInputEl.value = nextValue;
        const caret = (before + separator + text).length;
        if (typeof promptInputEl.setSelectionRange === 'function') {
            promptInputEl.setSelectionRange(caret, caret);
        }
        promptInputEl.focus({ preventScroll: true });
        renderSummary(`Inserted ${text} into the prompt.`);
        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(text);
            }
            setPromptStatus(`Inserted and copied: ${text}`, true);
        } catch {
            setPromptStatus(`Inserted: ${text}`);
        }
        return true;
    };

    const setHistory = (nextHistory) => {
        const list = Array.isArray(nextHistory) ? nextHistory : [];
        state.promptHistory = tagHistory(list);
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
        const config = getConfig ? (getConfig() || {}) : {};
        if (config && Object.prototype.hasOwnProperty.call(config, 'promptHistory')) {
            return Array.isArray(config.promptHistory) ? config.promptHistory : [];
        }
        return readStoredHistory(scope.path, scope.mode);
    };

    const writeHistoryForSelection = (history, selection = null) => {
        const scope = getHistoryScope(selection);
        writeStoredHistory(scope.path, history, scope.mode);
        void requestEditConfig('save', {
            path: scope.path,
            promptHistory: Array.isArray(history) ? history.slice(-12) : [],
        });
    };

    const renderHistory = (options = {}) => {
        renderPromptHistory(promptMessagesEl, state.promptHistory, stream.state, options);
    };

    const getCurrentTemplateField = () => {
        const selection = currentSelection(null);
        const selector = selection?.isLayout ? '#edit-layout-primary-template' : '#edit-content-template';
        return document.querySelector(selector);
    };

    const getCurrentTemplateText = () => {
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
        return explicitTemplate || fallbackTemplate || '';
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
        state.activePath = context.isLayout ? (context.virtualPath || context.path) : context.path;
        state.activePromptMode = currentPromptMode();
        if (promptInputEl && !state.isSending) {
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
        const hasImageContext = currentHasImageContext() || !!state.imageAttachment;
        const hasAttachment = !!state.imageAttachment;
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
        promptAttachmentPreviewEl.src = state.imageAttachment.dataUrl;
        promptAttachmentNameEl.textContent = state.imageAttachment.name || 'clipboard-image.png';
    };

    const clearAttachment = () => {
        state.imageAttachment = null;
        if (promptImageInputEl) {
            promptImageInputEl.value = '';
        }
        updateAttachmentUi();
    };

    const clearPromptHistory = () => {
        state.promptHistory = [];
        renderHistory();
    };

    const attachImageFile = async (file) => {
        const { readImageFile } = await import('./image.js');
        try {
            state.imageAttachment = await readImageFile(file);
            updateAttachmentUi();
            setPromptStatus(`Attached image: ${state.imageAttachment.name}`, true);
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

    const reloadViewer = () => {
        const frame = document.getElementById('contentFrame');
        const selection = currentSelection({ path: '', isFile: false });
        const selectionPath = selection && Object.prototype.hasOwnProperty.call(selection, 'previewPath')
            ? selection.previewPath
            : undefined;
        const activeViewerPath = selectionPath ?? state.activePath;
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
        if (nextPath !== state.activePath || nextPromptMode !== state.activePromptMode) {
            state.activePath = nextPath;
            state.activePromptMode = nextPromptMode;
            setHistory(readHistoryForSelection(selection));
            renderHistory();
            renderContext();
            renderSummary('Waiting for response...');
            updateAttachmentUi();
        }
    };

    const restoreHistorySnapshot = async (snapshot, { saveConfig, drawerForm: nextDrawerForm, updatePromptEditorFields: updateFields = updatePromptEditorFields, buildPromptLayoutPayload, focusPromptTemplateField: focusField = focusPromptTemplateField } = {}) => {
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

        updateFields({
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
            drawerForm: nextDrawerForm,
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
        focusField(isLayoutTarget);
        setPromptStatus('Restored template from assistant stage.', true);
        return true;
    };

    return {
        stream,
        state,
        setPromptStatus,
        currentSelection,
        currentPromptMode,
        currentPromptPlaceholder,
        currentDefaultSystemPrompt,
        currentSystemPromptSettingKey,
        currentHasImageContext,
        currentSelectionName,
        insertPromptText,
        setHistory,
        getHistoryScope,
        readHistoryForSelection,
        writeHistoryForSelection,
        renderHistory,
        getCurrentTemplateField,
        getCurrentTemplateText,
        renderTemplatePreview,
        renderContext,
        renderSummary,
        updateAttachmentUi,
        clearAttachment,
        clearPromptHistory,
        attachImageFile,
        setGeneratingState,
        reloadViewer,
        syncHistoryForPath,
        restoreHistorySnapshot,
        getLayoutPreset,
        forceLayoutPromptToCustom,
        updatePromptEditorFields,
        focusPromptTemplateField,
        syncWorkFieldEditors,
        setActivePath: (path) => { state.activePath = path; },
    };
}
