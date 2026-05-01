export function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
}

function trimHistoryText(value, maxLength = 600) {
    const normalized = String(value ?? '').replace(/\s+/g, ' ').trim();
    if (!normalized) {
        return '';
    }
    if (normalized.length <= maxLength) {
        return normalized;
    }
    return `${normalized.slice(0, Math.max(0, maxLength - 3))}...`;
}

export function buildTemplateHistorySnapshot({
    templateText = '',
    nextCss = null,
    nextJs = null,
    nextTitle = null,
    nextDescription = null,
    nextWork = null,
    isLayoutTarget = false,
} = {}) {
    const template = trimHistoryText(templateText, isLayoutTarget ? 1000 : 800);
    if (!template) {
        return null;
    }

    const snapshot = {
        targetType: isLayoutTarget ? 'layout' : 'partial',
        template,
        templateLength: String(templateText || '').trim().length,
    };

    if (typeof nextCss === 'string' && nextCss.trim() !== '') {
        snapshot.css = trimHistoryText(nextCss, 400);
        snapshot.cssLength = nextCss.length;
    }
    if (typeof nextJs === 'string' && nextJs.trim() !== '') {
        snapshot.js = trimHistoryText(nextJs, 320);
        snapshot.jsLength = nextJs.length;
    }
    if (typeof nextTitle === 'string' && nextTitle.trim() !== '') {
        snapshot.title = trimHistoryText(nextTitle, 160);
    }
    if (typeof nextDescription === 'string' && nextDescription.trim() !== '') {
        snapshot.description = trimHistoryText(nextDescription, 220);
    }
    if (nextWork && typeof nextWork === 'object') {
        const keys = Object.keys(nextWork).filter((key) => key !== 'layout').slice(0, 6);
        if (keys.length) {
            snapshot.workKeys = keys;
        }
        if (Array.isArray(nextWork.fields) && nextWork.fields.length) {
            snapshot.workFieldNames = nextWork.fields
                .map((field) => (field && typeof field.name === 'string') ? field.name.trim() : '')
                .filter(Boolean)
                .slice(0, 8);
        }
        if (nextWork.layout && typeof nextWork.layout === 'object') {
            const layoutCandidate = nextWork.layout.name || nextWork.layout.mode || nextWork.layout.value || '';
            if (typeof layoutCandidate === 'string' && layoutCandidate.trim() !== '') {
                snapshot.layoutName = layoutCandidate.trim();
            }
        } else if (typeof nextWork.layout === 'string' && nextWork.layout.trim() !== '') {
            snapshot.layoutName = nextWork.layout.trim();
        }
    }

    return snapshot;
}

export function serializeHistoryForRequest(history) {
    const list = Array.isArray(history) ? history : [];
    return list.map((item) => {
        const role = item?.role || 'user';
        let content = String(item?.content || '');
        const snapshot = item?.templateSnapshot;
        if (snapshot && typeof snapshot === 'object') {
            const lines = [];
            if (typeof snapshot.targetType === 'string' && snapshot.targetType) {
                lines.push(`Template snapshot target: ${snapshot.targetType}`);
            }
            if (typeof snapshot.template === 'string' && snapshot.template) {
                lines.push(`Template snapshot:\n${snapshot.template}`);
            }
            if (typeof snapshot.css === 'string' && snapshot.css) {
                lines.push(`CSS snapshot:\n${snapshot.css}`);
            }
            if (typeof snapshot.js === 'string' && snapshot.js) {
                lines.push(`JS snapshot:\n${snapshot.js}`);
            }
            if (typeof snapshot.title === 'string' && snapshot.title) {
                lines.push(`Title snapshot: ${snapshot.title}`);
            }
            if (typeof snapshot.description === 'string' && snapshot.description) {
                lines.push(`Description snapshot: ${snapshot.description}`);
            }
            if (Array.isArray(snapshot.workKeys) && snapshot.workKeys.length) {
                lines.push(`Work keys updated: ${snapshot.workKeys.join(', ')}`);
            }
            if (Array.isArray(snapshot.workFieldNames) && snapshot.workFieldNames.length) {
                lines.push(`Work fields snapshot: ${snapshot.workFieldNames.join(', ')}`);
            }
            if (typeof snapshot.layoutName === 'string' && snapshot.layoutName) {
                lines.push(`Layout name snapshot: ${snapshot.layoutName}`);
            }
            if (lines.length) {
                content = content
                    ? `${content}\n\n${lines.join('\n\n')}`
                    : lines.join('\n\n');
            }
        }
        return { role, content };
    });
}

export function summarizeSerializedHistory(history) {
    const serialized = serializeHistoryForRequest(history);
    return serialized.reduce((summary, item) => ({
        count: summary.count + 1,
        chars: summary.chars + String(item?.content || '').length,
    }), { count: 0, chars: 0 });
}

function compactValue(value) {
    return String(value ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '');
}

function parseBooleanToken(token) {
    if (['true', 'on', 'yes', '1'].includes(token)) {
        return true;
    }
    if (['false', 'off', 'no', '0'].includes(token)) {
        return false;
    }
    return null;
}

export function inferWorkChangesFromPrompt(prompt, config) {
    const work = (config && typeof config === 'object' && config.work && typeof config.work === 'object')
        ? config.work
        : {};
    const compactPrompt = compactValue(prompt);
    if (!compactPrompt) {
        return null;
    }

    const nextWork = {};
    Object.entries(work).forEach(([key, value]) => {
        if (typeof value !== 'boolean') {
            return;
        }
        const compactKey = compactValue(key);
        if (!compactKey) {
            return;
        }

        const tokenPatterns = [
            new RegExp(`set${compactKey}(?:to|=)?(true|false|on|off|yes|no|1|0)`),
            new RegExp(`(?:make|set)?${compactKey}(true|false|on|off|yes|no|1|0)`),
            new RegExp(`turn${compactKey}(on|off)`),
        ];
        for (const pattern of tokenPatterns) {
            const match = compactPrompt.match(pattern);
            if (match) {
                const parsed = parseBooleanToken(match[1]);
                if (parsed !== null) {
                    nextWork[key] = parsed;
                    return;
                }
            }
        }

        if (compactPrompt.includes(`enable${compactKey}`)) {
            nextWork[key] = true;
            return;
        }
        if (compactPrompt.includes(`disable${compactKey}`)) {
            nextWork[key] = false;
        }
    });

    return Object.keys(nextWork).length ? nextWork : null;
}

export function filterAllowedWork(work, config) {
    if (!work || typeof work !== 'object') {
        return null;
    }
    const baseWork = (config && typeof config === 'object' && config.work && typeof config.work === 'object')
        ? config.work
        : {};
    const allowedKeys = new Set([
        ...Object.keys(baseWork),
        'type',
        'layout',
        'model',
    ]);
    const filtered = {};
    Object.entries(work).forEach(([key, value]) => {
        if (allowedKeys.has(key)) {
            filtered[key] = value;
        }
    });
    return filtered;
}
