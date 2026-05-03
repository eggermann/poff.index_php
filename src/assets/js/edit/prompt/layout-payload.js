import { getLayoutState } from '../../core/utils.js';
import { getLayoutPresetValue } from '../selection.js';

function resolveLayoutName(nextLayoutValue, drawerForm, currentConfig) {
    if (typeof nextLayoutValue === 'string' && nextLayoutValue.trim()) {
        return nextLayoutValue.trim();
    }
    if (nextLayoutValue && typeof nextLayoutValue === 'object') {
        const candidate = nextLayoutValue.name || nextLayoutValue.mode || nextLayoutValue.value || '';
        if (typeof candidate === 'string' && candidate.trim()) {
            return candidate.trim();
        }
    }
    const elements = drawerForm ? drawerForm.elements : null;
    return (elements?.work_layout?.value || currentConfig?.work?.layout?.name || 'poff-layout').trim();
}

export function buildPromptLayoutPayload({
    selection,
    currentConfig,
    drawerForm,
    templateText,
    responseSectionTemplate = null,
    responseWorkTemplate = null,
    responseWorksTemplate = null,
    nextCss = null,
    nextJs = null,
    nextLayoutValue = null,
    responseModel = '',
    layoutPreset = getLayoutPresetValue(),
}) {
    const layoutState = getLayoutState(currentConfig || {});
    if (!selection?.isLayout) {
        return {
            layoutPayload: {
                sectionTemplate: templateText,
            },
            layoutState,
            resolvedLayoutName: resolveLayoutName(nextLayoutValue, drawerForm, currentConfig),
        };
    }

    const resolvedLayoutName = resolveLayoutName(nextLayoutValue, drawerForm, currentConfig);
    const layoutPayload = {
        name: resolvedLayoutName,
        engine: 'lightncandy',
    };
    const resolvedSharedName = typeof nextLayoutValue === 'object' && nextLayoutValue && typeof nextLayoutValue.sharedName === 'string'
        ? nextLayoutValue.sharedName.trim()
        : String(currentConfig?.work?.layout?.sharedName || '').trim();

    if (nextLayoutValue && typeof nextLayoutValue === 'object') {
        if (typeof nextLayoutValue.engine === 'string' && nextLayoutValue.engine.trim()) {
            layoutPayload.engine = nextLayoutValue.engine.trim();
        }
        if (typeof nextLayoutValue.model === 'string' && nextLayoutValue.model.trim()) {
            layoutPayload.model = nextLayoutValue.model.trim();
        }
    }
    if (responseModel) {
        layoutPayload.model = responseModel;
    }

    const attachSiblingSectionTemplates = () => {
        if (responseWorkTemplate !== null) {
            layoutPayload.workTemplate = responseWorkTemplate;
        }
        if (responseWorksTemplate !== null) {
            layoutPayload.worksTemplate = responseWorksTemplate;
        }
    };

    const preset = (layoutPreset || layoutState.preset || 'actual').trim();
    layoutPayload.preset = preset;
    if (preset === 'shared') {
        layoutPayload.source = 'shared';
        layoutPayload.sharedName = resolvedSharedName || resolvedLayoutName;
    }
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
            : preset === 'shared'
                ? (resolvedSharedName || resolvedLayoutName || 'poff-layout')
            : (canEditResolvedFilesystemTarget ? 'filesystem-layout' : 'poff-layout');

    if (shouldPersistToLocalWrapper) {
        layoutPayload.template = templateText;
        if (responseSectionTemplate !== null) {
            layoutPayload.sectionTemplate = responseSectionTemplate;
        }
        attachSiblingSectionTemplates();
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
        attachSiblingSectionTemplates();
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
        attachSiblingSectionTemplates();
        if (nextCss !== null) {
            layoutPayload.css = nextCss;
        }
        if (nextJs !== null) {
            layoutPayload.js = nextJs;
        }
    }

    return { layoutPayload, layoutState, resolvedLayoutName };
}
