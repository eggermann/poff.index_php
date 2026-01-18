export function getActiveSelection() {
    const rawHash = window.location.hash.replace(/^#\/?/, '');
    let hashPath = rawHash;
    if (rawHash) {
        try {
            hashPath = decodeURIComponent(rawHash);
        } catch (err) {
            hashPath = rawHash;
        }
    }
    if (hashPath) {
        const isFile = /\.[^\\/]+$/.test(hashPath);
        return {
            path: hashPath,
            isFile,
        };
    }
    const params = new URLSearchParams(window.location.search);
    return {
        path: params.get('path') || '',
        isFile: false,
    };
}

export function getActivePath() {
    return getActiveSelection().path;
}
