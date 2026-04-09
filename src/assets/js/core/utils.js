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
            model: layoutValue.model || '',
            engine: layoutValue.engine || 'lightncandy',
        };
    }
    if (typeof layoutValue === 'string') {
        return { mode: layoutValue, template: '', model: '', engine: 'lightncandy' };
    }
    return { mode: 'default-layout', template: '', model: '', engine: 'lightncandy' };
}
