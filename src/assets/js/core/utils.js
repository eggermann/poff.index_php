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
    const inferredSection = layoutValue && typeof layoutValue === 'object' && !Array.isArray(layoutValue) && layoutValue.section
        ? String(layoutValue.section)
        : ((config?.type === 'folder' || (config?.work?.type === 'folder' && !config?.name)) ? 'works' : 'work');
    const normalize = (state) => {
        const mode = state.mode || 'default-layout';
        const storage = state.storage || '';
        const directory = state.directory || '';
        let preset = 'actual';
        if (mode === 'none') {
            preset = 'none';
        } else if (storage === 'filesystem' && (directory === '.layout' || directory.startsWith('.works/'))) {
            preset = 'custom';
        }
        const sourceLabel = mode === 'none'
            ? 'No outer layout'
            : storage === 'filesystem'
                ? `Filesystem: ${directory || '.layout'}`
                : storage === 'default'
                    ? 'Built-in default layout'
                    : 'Current resolved layout';

        return {
            ...state,
            mode,
            storage,
            directory,
            section: state.section || inferredSection,
            sectionTemplate: state.sectionTemplate || '',
            sectionDirectory: state.sectionDirectory || '',
            preset,
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
            section: layoutValue.section || inferredSection,
            sectionTemplate: layoutValue.sectionTemplate || '',
            sectionDirectory: layoutValue.sectionDirectory || '',
            assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : [],
        });
    }
    if (typeof layoutValue === 'string') {
        return normalize({ mode: layoutValue, template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', storage: '', section: inferredSection, sectionTemplate: '', sectionDirectory: '', assets: [] });
    }
    return normalize({ mode: 'default-layout', template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', storage: '', section: inferredSection, sectionTemplate: '', sectionDirectory: '', assets: [] });
}
