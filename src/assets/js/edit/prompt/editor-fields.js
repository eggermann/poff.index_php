export function updatePromptEditorFields({ templateText, nextTitle, nextDescription, nextWork, isLayoutTarget, nextCss = null, nextJs = null }) {
    const templateSelectors = isLayoutTarget
        ? ['#edit-layout-primary-template']
        : ['#edit-content-template'];

    templateSelectors.forEach((selector) => {
        document.querySelectorAll(selector).forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = templateText;
            }
        });
    });

    if (nextTitle !== null) {
        document.querySelectorAll('#edit-title').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextTitle;
            }
        });
    }

    if (nextDescription !== null) {
        document.querySelectorAll('#edit-description').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextDescription;
            }
        });
    }

    if (nextWork && typeof nextWork.type === 'string') {
        document.querySelectorAll('#edit-work-type').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextWork.type;
            }
        });
    }

    if (isLayoutTarget && nextCss !== null) {
        document.querySelectorAll('#edit-layout-primary-css').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextCss;
            }
        });
    }

    if (isLayoutTarget && nextJs !== null) {
        document.querySelectorAll('#edit-layout-primary-js').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = nextJs;
            }
        });
    }
}
