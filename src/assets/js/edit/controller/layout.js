export function createLayoutNameForPreset(editConfig) {
    return (layoutPreset = 'actual') => {
        const preset = String(layoutPreset || 'actual').trim() === 'inherit'
            ? 'actual'
            : String(layoutPreset || 'actual').trim();
        if (preset === 'none') {
            return 'none';
        }
        if (preset === 'custom') {
            return 'custom-layout';
        }
        const currentLayout = editConfig?.work?.layout;
        const hasFilesystemSource = !!(
            currentLayout
            && typeof currentLayout === 'object'
            && (
                currentLayout.storage === 'filesystem'
                || (typeof currentLayout.directory === 'string' && currentLayout.directory.trim() !== '')
                || (typeof currentLayout.inheritedDirectory === 'string' && currentLayout.inheritedDirectory.trim() !== '')
            )
        );
        return hasFilesystemSource ? 'filesystem-layout' : 'poff-layout';
    };
}

export function buildLayoutPayload(payload, layoutNameForPreset) {
    const rawLayoutPreset = (payload.layoutPreset || 'actual').trim();
    const layoutPreset = rawLayoutPreset === 'inherit' ? 'actual' : rawLayoutPreset;
    const layoutPayload = {
        name: layoutNameForPreset(layoutPreset),
        engine: 'lightncandy',
        preset: layoutPreset,
    };
    if (Object.prototype.hasOwnProperty.call(payload, 'contentTemplate')) {
        layoutPayload.sectionTemplate = payload.contentTemplate ?? '';
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'layoutTemplate')) {
        layoutPayload.template = payload.layoutTemplate ?? '';
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'layoutCss')) {
        layoutPayload.css = payload.layoutCss ?? '';
    }
    if (Object.prototype.hasOwnProperty.call(payload, 'layoutJs')) {
        layoutPayload.js = payload.layoutJs ?? '';
    }
    const hasOriginalDraftWrite = (
        Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTemplate')
        || Object.prototype.hasOwnProperty.call(payload, 'originalLayoutCss')
        || Object.prototype.hasOwnProperty.call(payload, 'originalLayoutJs')
    );
    if (Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTarget') && hasOriginalDraftWrite) {
        layoutPayload.originalTarget = payload.originalLayoutTarget ?? '';
        layoutPayload.originalTemplate = payload.originalLayoutTemplate ?? '';
        layoutPayload.originalCss = payload.originalLayoutCss ?? '';
        layoutPayload.originalJs = payload.originalLayoutJs ?? '';
    }
    return { layoutPreset, layoutPayload };
}
