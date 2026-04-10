const PROMPT_REQUEST_TIMEOUT_MS = 90000;

export function buildCmsUrl(action, path) {
    const url = new URL(window.location.pathname, window.location.origin);
    url.searchParams.set('edit', action);
    if (path) {
        url.searchParams.set('path', path);
    }
    return url.toString();
}

export async function requestEditConfig(action, payload) {
    const url = buildCmsUrl(action, payload.path || '');
    try {
        const res = await fetch(url, {
            method: action === 'config' ? 'GET' : 'POST',
            headers: {
                'Accept': 'application/json',
                ...(action === 'config' ? {} : { 'Content-Type': 'application/json' }),
            },
            body: action === 'config' ? undefined : JSON.stringify(payload),
        });
        if (!res.ok) {
            return { allowed: false, error: 'Edit endpoint unavailable.' };
        }
        return await res.json();
    } catch (err) {
        return { allowed: false, error: 'Edit endpoint unavailable.' };
    }
}

export async function requestEditUpload(payload) {
    const url = buildCmsUrl('upload', payload.path || '');
    const formData = new FormData();
    formData.set('source', payload.source || 'upload');
    for (const file of payload.files || []) {
        formData.append('files[]', file);
    }
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
            },
            body: formData,
        });
        if (!res.ok) {
            const data = await res.json().catch(() => null);
            return data || { allowed: false, error: 'Upload endpoint unavailable.' };
        }
        return await res.json();
    } catch (err) {
        return { allowed: false, error: 'Upload endpoint unavailable.' };
    }
}

export async function requestPromptTemplate(payload) {
    const url = buildCmsUrl('prompt', payload.path || '');
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timeout = setTimeout(() => {
        if (controller) {
            controller.abort();
        }
    }, PROMPT_REQUEST_TIMEOUT_MS);
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
            signal: controller ? controller.signal : undefined,
        });
        clearTimeout(timeout);
        if (!res.ok) {
            const data = await res.json().catch(() => null);
            return data || { error: `Prompt endpoint failed (HTTP ${res.status}).` };
        }
        return await res.json();
    } catch (err) {
        clearTimeout(timeout);
        if (err?.name === 'AbortError') {
            return { error: 'Prompt request timed out after 90 seconds.' };
        }
        return { error: 'Prompt endpoint unavailable.' };
    }
}
