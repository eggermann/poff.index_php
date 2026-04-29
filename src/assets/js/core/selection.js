export function inferFilePath(path = '') {
    return /\.[^\\/]+$/.test(path);
}

function normalizeSelectionPath(path = '') {
    return String(path || '')
        .replace(/\\/g, '/')
        .replace(/^\/+|\/+$/g, '');
}

export function isVirtualLayoutPath(path = '') {
    const normalized = normalizeSelectionPath(path);
    return normalized === '.layout' || normalized.endsWith('/.layout');
}

export function subjectPathFromVirtualLayout(path = '') {
    const normalized = normalizeSelectionPath(path);
    if (!isVirtualLayoutPath(normalized)) {
        return normalized;
    }
    if (normalized === '.layout') {
        return '';
    }
    return normalized.slice(0, -'/.layout'.length);
}

export function buildVirtualLayoutPath(path = '') {
    const normalized = normalizeSelectionPath(path);
    return normalized ? `${normalized}/.layout` : '.layout';
}

export function getSelectionFromPath(path = '', options = {}) {
    const normalized = normalizeSelectionPath(path);
    const isLayout = isVirtualLayoutPath(normalized);
    const previewPath = isLayout ? subjectPathFromVirtualLayout(normalized) : normalized;
    const hasFileHint = typeof options?.isFile === 'boolean';
    const previewIsFile = hasFileHint ? !!options.isFile : inferFilePath(previewPath);

    return {
        path: normalized,
        isFile: !isLayout && previewIsFile,
        isLayout,
        layoutPath: isLayout ? previewPath : '',
        layoutIsFile: isLayout ? previewIsFile : false,
        previewPath,
        previewIsFile,
    };
}

export function getActiveSelection() {
    const rawHash = window.location.hash.replace(/^#\/?/, '');
    let hashPath = rawHash;
    const params = new URLSearchParams(window.location.search);
    const filePath = params.get('file') || '';
    const folderPath = params.get('path') || '';
    if (rawHash) {
        try {
            hashPath = decodeURIComponent(rawHash);
        } catch (err) {
            hashPath = rawHash;
        }
    }
    if (hashPath) {
        const hashMatchesFileParam = filePath !== '' && hashPath === filePath;
        const isFileHint = hashMatchesFileParam ? true : undefined;
        return getSelectionFromPath(hashPath, { isFile: isFileHint });
    }
    if (filePath) {
        return getSelectionFromPath(filePath, { isFile: true });
    }
    return getSelectionFromPath(folderPath);
}

export function getActivePath() {
    return getActiveSelection().path;
}
