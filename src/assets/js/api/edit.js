const PROMPT_REQUEST_TIMEOUT_MS = 300000;

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
    if (typeof payload.fileName === 'string') {
        formData.set('fileName', payload.fileName);
    }
    if (typeof payload.contents === 'string') {
        formData.set('contents', payload.contents);
    }
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
        const responseText = await res.text();
        let data = null;
        try {
            data = responseText ? JSON.parse(responseText) : null;
        } catch (err) {
            data = null;
        }
        if (!res.ok) {
            return data || {
                allowed: false,
                error: responseText.trim() || `Upload endpoint failed (HTTP ${res.status}).`,
            };
        }
        return data || {
            allowed: false,
            error: responseText.trim() || 'Upload endpoint returned invalid JSON.',
        };
    } catch (err) {
        return {
            allowed: false,
            error: err?.message || 'Upload endpoint unavailable.',
        };
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
        const responseText = await res.text();
        let data = null;
        try {
            data = responseText ? JSON.parse(responseText) : null;
        } catch (err) {
            data = null;
        }
        if (!res.ok) {
            return data || {
                error: responseText.trim() || `Prompt endpoint failed (HTTP ${res.status}).`,
            };
        }
        return data || {
            error: responseText.trim() || 'Prompt endpoint returned invalid JSON.',
        };
    } catch (err) {
        clearTimeout(timeout);
        if (err?.name === 'AbortError') {
            return { error: 'Prompt request timed out after 5 minutes.' };
        }
        return { error: 'Prompt endpoint unavailable.' };
    }
}
