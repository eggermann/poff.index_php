export function summarizePromptRequest(payload) {
    return {
        path: typeof payload?.path === 'string' ? payload.path : '',
        provider: typeof payload?.provider === 'string' ? payload.provider : 'local',
        model: typeof payload?.model === 'string' ? payload.model : '',
        endpoint: typeof payload?.endpoint === 'string' ? payload.endpoint : '',
        promptLength: typeof payload?.prompt === 'string' ? payload.prompt.length : 0,
        historyCount: Array.isArray(payload?.history) ? payload.history.length : 0,
        hasApiKey: typeof payload?.apiKey === 'string' ? payload.apiKey.trim() !== '' : false,
        hasImage: !!payload?.image,
        systemPromptLength: typeof payload?.systemPrompt === 'string' ? payload.systemPrompt.length : 0,
    };
}

export function summarizePromptResponse(response, requestSummary) {
    return {
        path: requestSummary?.path || '',
        provider: response?.provider || requestSummary?.provider || 'local',
        model: response?.model || requestSummary?.model || '',
        allowed: response?.allowed === true,
        hasTemplate: typeof response?.template === 'string' && response.template.trim() !== '',
        templateLength: typeof response?.template === 'string' ? response.template.trim().length : 0,
        error: typeof response?.error === 'string' ? response.error : '',
    };
}

export function summarizePromptError(err, requestSummary) {
    return {
        path: requestSummary?.path || '',
        provider: requestSummary?.provider || 'local',
        model: requestSummary?.model || '',
        name: typeof err?.name === 'string' ? err.name : 'Error',
        message: typeof err?.message === 'string' ? err.message : String(err || 'Prompt failed.'),
    };
}
