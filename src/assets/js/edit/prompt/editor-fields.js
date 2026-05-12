export function updatePromptEditorFields({ templateText, nextTitle, nextDescription, nextWork, isLayoutTarget, nextCss = null, nextJs = null }) {
    const workUpdates = nextWork && typeof nextWork === 'object' ? nextWork : null;
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

    if (workUpdates && typeof workUpdates.type === 'string') {
        document.querySelectorAll('#edit-work-type').forEach((field) => {
            if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
                field.value = workUpdates.type;
            }
        });
    }
    document.querySelectorAll('[data-work-config-field]').forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }
        const key = String(field.dataset.workConfigKey || '').trim();
        if (!key || !workUpdates || !Object.prototype.hasOwnProperty.call(workUpdates, key)) {
            return;
        }
        const value = workUpdates[key];
        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
            field.checked = !!value;
            return;
        }
        if (field.dataset.workConfigKind === 'json') {
            field.value = value === null || value === undefined
                ? ''
                : (typeof value === 'string' ? value : JSON.stringify(value, null, 2));
            return;
        }
        field.value = value === null || value === undefined ? '' : String(value);
    });

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

export function focusPromptTemplateField(isLayoutTarget) {
    const selector = isLayoutTarget ? '#edit-layout-primary-template' : '#edit-content-template';
    const field = document.querySelector(selector);
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement)) {
        return;
    }
    field.focus({ preventScroll: true });
    if (typeof field.select === 'function') {
        field.select();
    } else if (typeof field.setSelectionRange === 'function') {
        const value = field.value || '';
        field.setSelectionRange(value.length, value.length);
    }
    field.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

export function syncWorkFieldEditors(nextWork = null) {
    if (!nextWork || typeof nextWork !== 'object') {
        return;
    }

    const fields = Array.isArray(nextWork.fields) ? nextWork.fields : [];
    const fieldsByName = new Map();
    fields.forEach((field) => {
        if (!field || typeof field !== 'object' || typeof field.name !== 'string' || !field.name.trim()) {
            return;
        }
        fieldsByName.set(field.name.trim(), field);
    });

    document.querySelectorAll('[data-work-field-row]').forEach((row) => {
        const nameInput = row.querySelector('[data-work-field-name]');
        const typeInput = row.querySelector('[data-work-field-type]');
        const valueInput = row.querySelector('[data-work-field-value]');
        const currentName = nameInput && typeof nameInput.value === 'string' ? nameInput.value.trim() : '';
        if (!currentName) {
            return;
        }

        const nextField = fieldsByName.get(currentName) || (
            Object.prototype.hasOwnProperty.call(nextWork, currentName)
                ? { name: currentName, type: 'text', value: nextWork[currentName] }
                : null
        );
        if (!nextField) {
            return;
        }

        const setText = (selector, value) => {
            const input = row.querySelector(selector);
            if (input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
                input.value = Array.isArray(value) ? value.join('\n') : String(value ?? '');
            }
        };
        const setChecked = (selector, value) => {
            const input = row.querySelector(selector);
            if (input instanceof HTMLInputElement && input.type === 'checkbox') {
                input.checked = !!value;
            }
        };

        setText('[data-work-field-type]', nextField.type);
        setText('[data-work-field-name]', nextField.name);
        setText('[data-work-field-value]', nextField.value);
        setText('[data-work-field-title]', nextField.title);
        setText('[data-work-field-description]', nextField.description);
        setText('[data-work-field-placeholder]', nextField.placeholder);
        setText('[data-work-field-const]', nextField.const);
        setText('[data-work-field-default]', nextField.default);
        setText('[data-work-field-format]', nextField.format);
        setText('[data-work-field-contentMediaType]', nextField.contentMediaType);
        setText('[data-work-field-contentEncoding]', nextField.contentEncoding);
        setText('[data-work-field-pattern]', nextField.pattern);
        setText('[data-work-field-minLength]', nextField.minLength);
        setText('[data-work-field-maxLength]', nextField.maxLength);
        setText('[data-work-field-minimum]', nextField.minimum);
        setText('[data-work-field-maximum]', nextField.maximum);
        setText('[data-work-field-step]', nextField.step);
        setText('[data-work-field-minProperties]', nextField.minProperties);
        setText('[data-work-field-maxProperties]', nextField.maxProperties);
        setText('[data-work-field-enum]', Array.isArray(nextField.enum) ? nextField.enum : []);
        setText('[data-work-field-examples]', Array.isArray(nextField.examples) ? nextField.examples : []);
        setChecked('[data-work-field-required]', nextField.required);
        setChecked('[data-work-field-readOnly]', nextField.readOnly);
        setChecked('[data-work-field-writeOnly]', nextField.writeOnly);
        setChecked('[data-work-field-deprecated]', nextField.deprecated);
        setChecked('[data-work-field-nullable]', nextField.nullable);
        setChecked('[data-work-field-uniqueItems]', nextField.uniqueItems);
    });
}
