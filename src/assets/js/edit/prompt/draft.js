function readFieldValue(root, selector) {
    if (!root || typeof root.querySelector !== 'function') {
        return null;
    }
    const field = root.querySelector(selector);
    if (!field || typeof field.value !== 'string') {
        return null;
    }

    return field.value;
}

export function readPromptEditorDraft(selection = {}, root = document) {
    const isLayout = !!selection?.isLayout;
    const template = readFieldValue(root, isLayout ? '#edit-layout-primary-template' : '#edit-content-template');
    if (template === null) {
        return null;
    }

    const draft = {
        template,
    };

    if (isLayout) {
        const sectionTemplate = readFieldValue(root, '#edit-content-template');
        const css = readFieldValue(root, '#edit-layout-primary-css');
        const js = readFieldValue(root, '#edit-layout-primary-js');

        if (sectionTemplate !== null) {
            draft.sectionTemplate = sectionTemplate;
        }
        if (css !== null) {
            draft.css = css;
        }
        if (js !== null) {
            draft.js = js;
        }
    }

    return draft;
}
