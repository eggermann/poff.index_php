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
    if (layoutValue && typeof layoutValue === 'object' && !Array.isArray(layoutValue)) {
        return {
            mode: layoutValue.name || layoutValue.mode || layoutValue.value || '',
            template: layoutValue.template || '',
            css: layoutValue.css || '',
            js: layoutValue.js || '',
            model: layoutValue.model || '',
            engine: layoutValue.engine || 'lightncandy',
            directory: layoutValue.directory || '',
            assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : [],
        };
    }
    if (typeof layoutValue === 'string') {
        return { mode: layoutValue, template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', assets: [] };
    }
    return { mode: 'default-layout', template: '', css: '', js: '', model: '', engine: 'lightncandy', directory: '', assets: [] };
}
