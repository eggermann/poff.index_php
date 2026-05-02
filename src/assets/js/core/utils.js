export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function extractNavHtml(html) {
    if (!html) {
        return html;
    }
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nav = doc.getElementById('navList');
        return nav ? nav.innerHTML : html;
    } catch (err) {
        return html;
    }
}

export function getLayoutState(config) {
    const layoutValue = config?.work?.layout;
    const normalizePreset = (value) => {
        const preset = String(value || '').trim();
        if (preset === 'inherit') {
            return 'actual';
        }
        return ['actual', 'none', 'custom', 'shared'].includes(preset) ? preset : '';
    };
    const inferredSection = layoutValue && typeof layoutValue === 'object' && !Array.isArray(layoutValue) && layoutValue.section
        ? String(layoutValue.section)
        : ((config?.type === 'folder' || (config?.work?.type === 'folder' && !config?.name)) ? 'works' : 'work');
    const normalize = (state) => {
        const rawMode = state.mode || state.name || 'poff-layout';
        const mode = rawMode === 'poff'
            ? 'poff-layout'
            : rawMode === 'filesystem'
                ? 'filesystem-layout'
                : rawMode;
        const storage = state.storage || '';
        const directory = state.directory || '';
        let preset = normalizePreset(state.preset) || 'actual';
        if (!normalizePreset(state.preset)) {
            if (mode === 'none') {
                preset = 'none';
            } else if (storage === 'shared' || state.source === 'shared') {
                preset = 'shared';
            } else if (storage === 'filesystem' && (directory === '.layout' || directory.startsWith('.works/'))) {
                preset = 'custom';
            }
        }
        const sourceLabel = mode === 'none'
            ? 'No outer layout'
            : preset === 'shared' || storage === 'shared' || state.source === 'shared'
                ? `Marketplace: ${state.sharedName || state.name || 'shared'}`
            : storage === 'filesystem'
                ? `Filesystem: ${directory || '.layout'}`
            : storage === 'default'
                    ? 'Built-in poff-layout'
                    : 'Current resolved layout';
        const displayMode = preset === 'shared' || storage === 'shared' || state.source === 'shared'
            ? 'marketplace-layout'
            : mode;

        return {
            ...state,
            mode: displayMode,
            resolvedMode: mode,
            storage,
            directory,
            inheritedDirectory: state.inheritedDirectory || '',
            section: state.section || inferredSection,
            sectionTemplate: state.sectionTemplate || '',
            sectionDirectory: state.sectionDirectory || '',
            phpTemplate: state.phpTemplate || '',
            preset,
            source: state.source || '',
            sharedName: state.sharedName || '',
            sharedLayouts: Array.isArray(state.sharedLayouts) ? state.sharedLayouts : [],
            sourceLabel,
        };
    };
    if (layoutValue && typeof layoutValue === 'object' && !Array.isArray(layoutValue)) {
        return normalize({
            mode: layoutValue.name || layoutValue.mode || layoutValue.value || '',
            template: layoutValue.template || '',
            css: layoutValue.css || '',
            js: layoutValue.js || '',
            model: layoutValue.model || '',
            engine: layoutValue.engine || 'lightncandy',
            directory: layoutValue.directory || '',
            storage: layoutValue.storage || '',
            inheritedDirectory: layoutValue.inheritedDirectory || '',
            section: layoutValue.section || inferredSection,
            sectionTemplate: layoutValue.sectionTemplate || '',
            sectionDirectory: layoutValue.sectionDirectory || '',
            phpTemplate: layoutValue.phpTemplate || '',
            preset: layoutValue.preset || '',
            source: layoutValue.source || '',
            sharedName: layoutValue.sharedName || '',
            sharedLayouts: Array.isArray(layoutValue.sharedLayouts) ? layoutValue.sharedLayouts : [],
            assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : [],
        });
    }
    if (typeof layoutValue === 'string') {
        return normalize({ mode: layoutValue, template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', storage: '', section: inferredSection, sectionTemplate: '', sectionDirectory: '', assets: [] });
    }
    return normalize({ mode: 'poff-layout', template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', storage: '', section: inferredSection, sectionTemplate: '', sectionDirectory: '', assets: [] });
}
