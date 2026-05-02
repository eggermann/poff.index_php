import { escapeHtml } from '../../core/utils.js';

export function createLayoutDraftState({
    originalTemplate = '',
    originalCss = '',
    originalJs = '',
    localWrapperTemplate = '',
    localWrapperCss = '',
    localWrapperJs = '',
}) {
    return {
        virtualTemplate: originalTemplate || '',
        virtualCss: originalCss || '',
        virtualJs: originalJs || '',
        localTemplate: localWrapperTemplate || '',
        localCss: localWrapperCss || '',
        localJs: localWrapperJs || '',
    };
}

export function createLayoutModeController({
    presetEl,
    getSharedLayoutName = () => '',
    getSharedLayoutPackage = () => null,
    wrapperTarget,
    originalTarget,
    originalEditable,
    hasVirtualSource,
    drafts,
}) {
    const getSharedLayoutLabel = () => {
        const sharedPackage = getSharedLayoutPackage?.();
        const sharedName = String(getSharedLayoutName() || '').trim();
        return String(sharedPackage?.label || sharedPackage?.name || sharedName || 'shared').trim();
    };

    const currentPrimaryMode = () => {
        const preset = (presetEl?.value || 'actual').trim();
        if (preset === 'custom') {
            return 'local';
        }
        if (preset === 'actual' || preset === 'shared') {
            return 'virtual';
        }
        return hasVirtualSource ? 'virtual' : 'local';
    };

    const syncLayoutMode = ({ modePreviewEl, sourcePreviewEl, primaryTitleEl, primaryHintEl, primaryTemplateEl, primaryCssEl, primaryJsEl }) => {
        const preset = (presetEl?.value || 'actual').trim();
        const sharedPackage = preset === 'shared' ? getSharedLayoutPackage() : null;
        if (preset === 'shared' && sharedPackage) {
            drafts.virtualTemplate = sharedPackage.template || drafts.virtualTemplate;
            drafts.virtualCss = sharedPackage.css || drafts.virtualCss;
            drafts.virtualJs = sharedPackage.js || drafts.virtualJs;
        }
        const nextMode = preset === 'none'
            ? 'none'
            : preset === 'custom'
                ? 'custom-layout'
                : preset === 'shared'
                    ? 'collection-layout'
                : (originalEditable ? 'custom-layout' : 'poff-layout');
        const primaryMode = currentPrimaryMode();
        const isVirtual = primaryMode === 'virtual';
        const localWrapperDirectory = wrapperTarget.replace(/\/template\.hbs$/, '');
        const sharedLayoutName = getSharedLayoutLabel();
        const sourcePreview = isVirtual
            ? (preset === 'shared'
                ? `Collection: ${sharedLayoutName || 'shared'}`
                : (originalEditable ? `Filesystem: ${originalTarget}` : 'PHP built-in poff-layout'))
            : `Filesystem: ${localWrapperDirectory}`;

        if (modePreviewEl) {
            modePreviewEl.textContent = nextMode;
        }
        if (sourcePreviewEl) {
            sourcePreviewEl.textContent = sourcePreview;
        }
        if (primaryTitleEl) {
            primaryTitleEl.textContent = preset === 'shared'
                ? 'Collection layout'
                : (isVirtual ? 'Virtual layout' : 'Custom layout');
        }
        if (primaryHintEl) {
            if (preset === 'shared') {
                primaryHintEl.innerHTML = `Editing collection layout <code>${escapeHtml(sharedLayoutName || 'shared')}</code>. Changes save inline unless you switch to <code>Custom</code>.`;
            } else if (isVirtual) {
                primaryHintEl.innerHTML = originalEditable
                    ? (originalTarget === localWrapperDirectory
                        ? `Editing the resolved layout source <code>${escapeHtml(originalTarget)}</code>.`
                        : `Editing the inherited parent layout source <code>${escapeHtml(originalTarget)}</code>. Switch to <code>Custom</code> when you want to create a local <code>${escapeHtml(wrapperTarget)}</code>.`)
                    : 'Showing the bundled poff-layout. It stays read-only until a parent .layout exists.';
            } else {
                primaryHintEl.innerHTML = `Editing the local wrapper override <code>${escapeHtml(wrapperTarget)}</code>.`;
            }
        }
        if (primaryTemplateEl) {
            primaryTemplateEl.value = isVirtual ? drafts.virtualTemplate : drafts.localTemplate;
            primaryTemplateEl.disabled = isVirtual && !originalEditable && preset !== 'shared';
        }
        if (primaryCssEl) {
            primaryCssEl.value = isVirtual ? drafts.virtualCss : drafts.localCss;
            primaryCssEl.disabled = isVirtual && !originalEditable && preset !== 'shared';
        }
        if (primaryJsEl) {
            primaryJsEl.value = isVirtual ? drafts.virtualJs : drafts.localJs;
            primaryJsEl.disabled = isVirtual && !originalEditable && preset !== 'shared';
        }
    };

    const storePrimaryDraft = ({ primaryTemplateEl, primaryCssEl, primaryJsEl }) => {
        const primaryMode = currentPrimaryMode();
        if (primaryMode === 'virtual') {
            drafts.virtualTemplate = primaryTemplateEl?.value ?? '';
            drafts.virtualCss = primaryCssEl?.value ?? '';
            drafts.virtualJs = primaryJsEl?.value ?? '';
            return;
        }
        drafts.localTemplate = primaryTemplateEl?.value ?? '';
        drafts.localCss = primaryCssEl?.value ?? '';
        drafts.localJs = primaryJsEl?.value ?? '';
    };

    return {
        currentPrimaryMode,
        syncLayoutMode,
        storePrimaryDraft,
    };
}

export function buildLayoutSubmitPayload({
    preset,
    sharedLayoutName = '',
    currentSectionTemplate,
    sectionWasLocal,
    contentTemplateEl,
    currentPrimaryMode,
    drafts,
    originalEditable,
    originalTarget,
    wrapperWasLocal,
}) {
    const payload = {
        layoutPreset: preset,
        layoutSharedName: sharedLayoutName,
    };

    const contentTemplateValue = contentTemplateEl?.value ?? '';
    if (sectionWasLocal || contentTemplateValue !== currentSectionTemplate) {
        payload.contentTemplate = contentTemplateValue;
    }

    if (preset === 'shared') {
        payload.layoutTemplate = drafts.virtualTemplate;
        payload.layoutCss = drafts.virtualCss;
        payload.layoutJs = drafts.virtualJs;
        return payload;
    }

    if (currentPrimaryMode() === 'virtual') {
        if (originalEditable) {
            payload.originalLayoutTarget = originalTarget;
            payload.originalLayoutTemplate = drafts.virtualTemplate;
            payload.originalLayoutCss = drafts.virtualCss;
            payload.originalLayoutJs = drafts.virtualJs;
        }
    } else {
        const hasLocalDraft = wrapperWasLocal
            || drafts.localTemplate.trim() !== ''
            || drafts.localCss.trim() !== ''
            || drafts.localJs.trim() !== '';
        if (hasLocalDraft) {
            payload.layoutTemplate = drafts.localTemplate;
            payload.layoutCss = drafts.localCss;
            payload.layoutJs = drafts.localJs;
        }
    }

    return payload;
}
