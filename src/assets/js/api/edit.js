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

export async function requestEditDelete(payload) {
    const url = buildCmsUrl('delete', payload.path || '');
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
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
                error: responseText.trim() || `Delete endpoint failed (HTTP ${res.status}).`,
            };
        }
        return data || {
            allowed: false,
            error: responseText.trim() || 'Delete endpoint returned invalid JSON.',
        };
    } catch (err) {
        return {
            allowed: false,
            error: err?.message || 'Delete endpoint unavailable.',
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

function parsePromptStreamEventBlock(block) {
    const lines = String(block || '').replace(/\r\n/g, '\n').split('\n');
    let eventName = 'message';
    const dataLines = [];
    for (const line of lines) {
        if (line.startsWith('event:')) {
            eventName = line.slice(6).trim() || 'message';
            continue;
        }
        if (line.startsWith('data:')) {
            dataLines.push(line.slice(5).replace(/^\s/, ''));
        }
    }

    return {
        event: eventName,
        data: dataLines.join('\n'),
    };
}

function emitPromptStreamDelta(data, onDelta) {
    if (typeof onDelta !== 'function' || !data || data === '[DONE]') {
        return;
    }
    try {
        const decoded = JSON.parse(data);
        const delta = decoded?.choices?.[0]?.delta?.content;
        if (typeof delta === 'string' && delta !== '') {
            onDelta(delta);
            return;
        }
        const content = decoded?.choices?.[0]?.message?.content;
        if (typeof content === 'string' && content !== '') {
            onDelta(content);
        }
    } catch (err) {
        if (data !== '') {
            onDelta(data);
        }
    }
}

export async function requestPromptTemplateStream(payload, { onDelta } = {}) {
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
                'Accept': 'text/event-stream, application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ ...payload, stream: true }),
            signal: controller ? controller.signal : undefined,
        });
        clearTimeout(timeout);
        const contentType = (res.headers.get('content-type') || '').toLowerCase();
        const isJsonResponse = contentType.includes('application/json') || contentType.includes('application/problem+json');
        if (!res.ok) {
            const responseText = await res.text();
            let data = null;
            try {
                data = responseText ? JSON.parse(responseText) : null;
            } catch (err) {
                data = null;
            }
            return data || {
                error: responseText.trim() || `Prompt endpoint failed (HTTP ${res.status}).`,
            };
        }
        if (isJsonResponse || !res.body || typeof res.body.getReader !== 'function') {
            const responseText = await res.text();
            let data = null;
            try {
                data = responseText ? JSON.parse(responseText) : null;
            } catch (err) {
                data = null;
            }
            return data || {
                error: responseText.trim() || 'Prompt endpoint returned invalid JSON.',
            };
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let finalPayload = null;

        while (true) {
            const { done, value } = await reader.read();
            if (done) {
                break;
            }
            buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, '\n');
            let splitIndex = buffer.indexOf('\n\n');
            while (splitIndex !== -1) {
                const eventBlock = buffer.slice(0, splitIndex).trim();
                buffer = buffer.slice(splitIndex + 2);
                if (eventBlock !== '') {
                    const event = parsePromptStreamEventBlock(eventBlock);
                    if (event.event === 'final') {
                        try {
                            finalPayload = JSON.parse(event.data);
                        } catch (err) {
                            finalPayload = {
                                error: event.data || 'Prompt stream returned invalid final payload.',
                            };
                        }
                    } else {
                        emitPromptStreamDelta(event.data, onDelta);
                    }
                }
                splitIndex = buffer.indexOf('\n\n');
            }
        }

        buffer += decoder.decode().replace(/\r\n/g, '\n');
        const trailing = buffer.trim();
        if (trailing !== '') {
            const event = parsePromptStreamEventBlock(trailing);
            if (event.event === 'final') {
                try {
                    finalPayload = JSON.parse(event.data);
                } catch (err) {
                    finalPayload = {
                        error: event.data || 'Prompt stream returned invalid final payload.',
                    };
                }
            } else {
                emitPromptStreamDelta(event.data, onDelta);
            }
        }

        return finalPayload || {
            error: 'Prompt stream ended without a final response.',
        };
    } catch (err) {
        clearTimeout(timeout);
        if (err?.name === 'AbortError') {
            return { error: 'Prompt request timed out after 5 minutes.' };
        }
        return { error: err?.message || 'Prompt endpoint unavailable.' };
    }
}
