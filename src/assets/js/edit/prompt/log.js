export function debugPromptLog(label, payload) {
    try {
        // Log quietly without breaking if console is missing.
        /* eslint-disable no-console */
        console.info(`[prompt] ${label}`, payload);
        /* eslint-enable no-console */
    } catch (err) {
        // ignore
    }
}
