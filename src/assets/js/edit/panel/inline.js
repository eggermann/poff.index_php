import { escapeHtml } from '../../core/utils.js';
import { loadPromptSettings } from '../prompt/storage.js';
import { renderPromptWindow } from '../prompt-window.js';
import {
    applyWorkFieldTypeDefaults,
    createDefaultWorkField,
    extractWorkFields,
    getWorkFieldSchemaProfile,
    materializeWorkFields,
    normalizeWorkField,
    schemaFieldTypeOptions,
} from '../work-fields.js';
import {
    bindWorkCategoryControls,
    getWorkCategoryOptions,
    normalizeWorkCategories,
    renderWorkCategorySection,
} from './categories.js';
import { layoutOverlayState, syncPromptDock } from './shared.js';
import { bindUploadDialog, renderUploadSectionHtml } from './upload.js';
import { renderEditLayoutPanel } from './layout.js';

const RESERVED_WORK_CONFIG_KEYS = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind']);

function readRowText(row, selector) {
    const field = row.querySelector(selector);
    return field && typeof field.value === 'string' ? field.value : '';
}

function readRowNumber(row, selector) {
    const value = readRowText(row, selector).trim();
    return value === '' ? '' : Number(value);
}

function readRowList(row, selector) {
    const value = readRowText(row, selector);
    return value
        .split(/\r?\n|,/) 
        .map((item) => item.trim())
        .filter(Boolean);
}

function readRowBool(row, selector) {
    const field = row.querySelector(selector);
    return !!field?.checked;
}

function readRowValue(row, selector) {
    const field = row.querySelector(selector);
    if (!field) {
        return '';
    }
    if (field instanceof HTMLInputElement && field.type === 'checkbox') {
        return field.checked;
    }
    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
        return field.value;
    }
    return '';
}

function renderValueControl(field, index) {
    const value = field.value;
    if (field.type === 'checkbox') {
        return `
            <label class="edit-work-field-value-toggle">
                <input class="edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="checkbox"${value ? ' checked' : ''}>
            </label>
        `;
    }
    if (field.type === 'number') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="number" step="any" value="${escapeHtml(value ?? '')}" placeholder="Value">`;
    }
    if (field.type === 'select') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="text" value="${escapeHtml(typeof value === 'string' ? value : String(value ?? ''))}" placeholder="Selected value">`;
    }
    if (field.type === 'color') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="color" value="${escapeHtml(value || '#000000')}">`;
    }
    if (field.type === 'date') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="date" value="${escapeHtml(typeof value === 'string' ? value : String(value ?? ''))}">`;
    }
    if (field.type === 'url') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="url" value="${escapeHtml(typeof value === 'string' ? value : String(value ?? ''))}" placeholder="https://example.com">`;
    }
    if (field.type === 'email') {
        return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="email" value="${escapeHtml(typeof value === 'string' ? value : String(value ?? ''))}" placeholder="name@example.com">`;
    }
    return `<textarea class="form-textarea edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value rows="${field.type === 'textarea' ? '5' : '2'}" placeholder="Value">${escapeHtml(typeof value === 'string' ? value : String(value ?? ''))}</textarea>`;
}

function renderSchemaControl(field, key, html) {
    return getWorkFieldSchemaProfile(field.type).visibleControls.has(key) ? html : '';
}

function renderSchemaGroup(field, keys, html) {
    const visible = keys.some((key) => getWorkFieldSchemaProfile(field.type).visibleControls.has(key));
    return visible ? html : '';
}

function renderMediaTypeOptions(selectedValue = '', catalog = null) {
    const normalizedSelected = String(selectedValue || '').trim();
    const choices = Array.isArray(catalog?.choices) ? catalog.choices : [];
    const options = choices.length
        ? choices.map((choice) => String(choice?.value || '').trim()).filter(Boolean)
        : ['video', 'image', 'audio', 'pdf', 'text', 'link', 'folder', 'other'];

    if (normalizedSelected && !options.includes(normalizedSelected)) {
        options.unshift(normalizedSelected);
    }

    return options
        .filter((option, index) => option && options.indexOf(option) === index)
        .map((option) => `<option value="${escapeHtml(option)}"${option === normalizedSelected ? ' selected' : ''}>${escapeHtml(option)}</option>`)
        .join('');
}

function readMediaConfigFromForm(form, currentWork = {}) {
    const nextWork = { ...(currentWork && typeof currentWork === 'object' ? currentWork : {}) };
    const typeField = form?.elements?.work_type;
    if (typeField && typeof typeField.value === 'string') {
        const type = typeField.value.trim();
        if (type) {
            nextWork.type = type;
        }
    }
    const configFields = form?.querySelectorAll('[data-work-config-field]') || [];
    configFields.forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }
        const key = String(field.dataset.workConfigKey || '').trim();
        if (!key) {
            return;
        }
        const kind = String(field.dataset.workConfigKind || 'text').trim();
        const isNullable = field.dataset.workConfigNullable === 'true';
        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
            nextWork[key] = !!field.checked;
            return;
        }
        const rawValue = field.value;
        if (kind === 'number') {
            nextWork[key] = rawValue === '' ? null : Number(rawValue);
            return;
        }
        if (kind === 'json') {
            const trimmed = String(rawValue || '').trim();
            if (trimmed === '') {
                nextWork[key] = null;
                return;
            }
            try {
                nextWork[key] = JSON.parse(trimmed);
            } catch {
                nextWork[key] = trimmed;
            }
            return;
        }
        const trimmed = String(rawValue || '').trim();
        if (isNullable && trimmed === '') {
            nextWork[key] = null;
            return;
        }
        nextWork[key] = rawValue;
    });
    const categories = normalizeWorkCategories(nextWork.categories ?? nextWork.category ?? []);
    nextWork.categories = categories;
    nextWork.category = categories;
    return nextWork;
}

function renderWorkValueControl(key, value) {
    const normalizedKey = String(key || '').trim();
    const inputId = `edit-work-config-${normalizedKey}`;
    if (typeof value === 'boolean') {
        return `
            <label class="edit-work-field-value-toggle">
                <input class="edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="checkbox" type="checkbox"${value ? ' checked' : ''}>
            </label>
        `;
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
        return `<input class="form-input edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="number" type="number" step="any" value="${escapeHtml(String(value))}" placeholder="Value">`;
    }
    if (Array.isArray(value) || (value && typeof value === 'object')) {
        const serialized = JSON.stringify(value, null, 2);
        return `<textarea class="form-textarea edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="json" rows="3" placeholder="JSON value">${escapeHtml(serialized)}</textarea>`;
    }
    const isNullable = normalizedKey === 'poster';
    return `<input class="form-input edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="text"${isNullable ? ' data-work-config-nullable="true"' : ''} type="text" value="${escapeHtml(value === null || value === undefined ? '' : String(value))}" placeholder="Value">`;
}

function renderWorkConfigFieldsSection(config = {}) {
    const work = (config?.work && typeof config.work === 'object') ? config.work : {};
    const workType = String(work.type || '').trim();
    const catalog = config?.workTemplateCatalog && typeof config.workTemplateCatalog === 'object'
        ? config.workTemplateCatalog
        : null;
    const categoryOptions = getWorkCategoryOptions(catalog);
    const fieldNames = new Set(extractWorkFields(work).map((field) => field.name));
    const dynamicKeys = Object.keys(work).filter((key) => !RESERVED_WORK_CONFIG_KEYS.has(key) && key !== 'type' && !fieldNames.has(key));
    const workTypeSummary = dynamicKeys.length ? dynamicKeys.join(', ') : 'No additional work fields yet.';
    const selectedValue = String(work.template || catalog?.selected || workType || '').trim();
    return `
        <div class="edit-work-fields edit-work-media">
            <div class="edit-work-fields-header">
                <div>
                    <div class="edit-work-fields-title">Work settings</div>
                    <div class="small-note">Derived from the current work config. Each worktype exposes its own fields here.</div>
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work type</label>
                    <select class="form-select" id="edit-work-type" name="work_type">
                        ${renderMediaTypeOptions(selectedValue || 'video', catalog)}
                    </select>
                    <div class="small-note">${catalog?.detectedMime
            ? `Detected ${escapeHtml(catalog.detectedMime)}${catalog.detectedExtension ? ` · .${escapeHtml(catalog.detectedExtension)}` : ''} · showing ${escapeHtml(catalog.detectedKind || 'current')} templates`
            : 'Base family for the current item.'}</div>
                </div>
                <div>
                    <div class="edit-label">Current work fields</div>
                    <div class="small-note">${escapeHtml(workTypeSummary)}</div>
                </div>
            </div>
            ${categoryOptions.length || normalizeWorkCategories(work.categories ?? work.category ?? []).length
                ? renderWorkCategorySection(config)
                : ''}
            ${dynamicKeys.length ? `
            <div class="edit-work-config-grid">
                ${dynamicKeys.map((key) => `
                    <div class="edit-work-config-field">
                        <label class="edit-label" for="edit-work-config-${escapeHtml(key)}">work.${escapeHtml(key)}</label>
                        ${renderWorkValueControl(key, work[key])}
                    </div>
                `).join('')}
            </div>
            ` : ''}
        </div>
    `;
}

function renderWorkFieldRows(fields = [], typeOptions = schemaFieldTypeOptions()) {
    if (!fields.length) {
        return '<div class="small-note edit-work-fields-empty">No extra work fields yet.</div>';
    }

    return fields.map((field, index) => `
        <div class="edit-work-field-row" data-work-field-row="${index}">
            <div class="edit-work-field-main">
                <div class="edit-work-field-head">
                    <div>
                        <label class="edit-label" for="edit-work-field-type-${index}">Type</label>
                        <select class="form-select edit-work-field-type" id="edit-work-field-type-${index}" data-work-field-type>
                            ${typeOptions.map((option) => `<option value="${option}" ${field.type === option ? 'selected' : ''}>${option}</option>`).join('')}
                        </select>
                    </div>
                    <div class="edit-work-field-name-wrap">
                        <label class="edit-label" for="edit-work-field-name-${index}">Name</label>
                        <input class="form-input edit-work-field-name" id="edit-work-field-name-${index}" data-work-field-name type="text" value="${escapeHtml(field.name || '')}" placeholder="text1">
                        <div class="small-note">Use <code>work.${escapeHtml(field.name || 'text1')}</code> or <code>{{${escapeHtml(field.name || 'text1')}}}</code> in templates.</div>
                    </div>
                    <button class="btn btn-secondary edit-work-field-remove" type="button" data-work-field-remove aria-label="Remove work field">×</button>
                </div>
                <div class="edit-work-field-value-row">
                    <div class="edit-work-field-value-wrap">
                        <label class="edit-label" for="edit-work-field-value-${index}">Value</label>
                        ${renderValueControl(field, index)}
                    </div>
                </div>
                <details class="edit-work-field-advanced">
                    <summary>Schema options</summary>
                    <div class="edit-work-field-advanced-grid">
                        ${renderSchemaGroup(field, ['title', 'description', 'placeholder', 'const', 'default'], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Text</div>
                            <div class="edit-work-field-schema-group-grid">
                                ${renderSchemaControl(field, 'title', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-title-${index}">Title</label>
                                    <input class="form-input edit-work-field-title" id="edit-work-field-title-${index}" data-work-field-title type="text" value="${escapeHtml(field.title || '')}" placeholder="Label title">
                                </div>
                                `)}
                                ${renderSchemaControl(field, 'description', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-description-${index}">Description</label>
                                    <textarea class="form-textarea edit-work-field-description" id="edit-work-field-description-${index}" data-work-field-description rows="2" placeholder="Short help text">${escapeHtml(field.description || '')}</textarea>
                                </div>
                                `)}
                                ${renderSchemaControl(field, 'placeholder', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-placeholder-${index}">Placeholder</label>
                                    <input class="form-input edit-work-field-placeholder" id="edit-work-field-placeholder-${index}" data-work-field-placeholder type="text" value="${escapeHtml(field.placeholder || '')}" placeholder="Placeholder">
                                </div>
                                `)}
                                ${renderSchemaControl(field, 'const', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-const-${index}">Const</label>
                                    ${field.type === 'checkbox'
            ? `<input class="form-input edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const type="checkbox"${field.const ? ' checked' : ''}>`
            : field.type === 'number'
                ? `<input class="form-input edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const type="number" step="any" value="${escapeHtml(field.const ?? '')}" placeholder="Locked value">`
                : `<textarea class="form-textarea edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const rows="2" placeholder="Locked value">${escapeHtml(typeof field.const === 'string' ? field.const : String(field.const ?? ''))}</textarea>`}
                                </div>
                                `)}
                                ${renderSchemaControl(field, 'default', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-default-${index}">Default</label>
                                    ${field.type === 'checkbox'
            ? `<input class="form-input edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default type="checkbox"${field.default ? ' checked' : ''}>`
            : field.type === 'number'
                ? `<input class="form-input edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default type="number" step="any" value="${escapeHtml(field.default ?? '')}" placeholder="Default value">`
                : `<textarea class="form-textarea edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default rows="2" placeholder="Default value">${escapeHtml(typeof field.default === 'string' ? field.default : String(field.default ?? ''))}</textarea>`}
                                </div>
                                `)}
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ['format', 'pattern', 'minLength', 'maxLength', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'step'], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Constraints</div>
                            <div class="edit-work-field-schema-group-grid">
                                ${renderSchemaControl(field, 'format', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-format-${index}">Format</label>
                                    <input class="form-input edit-work-field-format" id="edit-work-field-format-${index}" data-work-field-format type="text" value="${escapeHtml(field.format || '')}" placeholder="date-time, uri, email">
                                </div>
                                `)}
                                ${renderSchemaControl(field, 'pattern', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-pattern-${index}">Pattern</label>
                                    <input class="form-input edit-work-field-pattern" id="edit-work-field-pattern-${index}" data-work-field-pattern type="text" value="${escapeHtml(field.pattern || '')}" placeholder="Regex">
                                </div>
                                `)}
                                <div class="edit-work-field-small-grid">
                                    ${renderSchemaControl(field, 'minLength', `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-minLength-${index}">Min length</label>
                                        <input class="form-input edit-work-field-minLength" id="edit-work-field-minLength-${index}" data-work-field-minLength type="number" step="1" value="${escapeHtml(field.minLength ?? '')}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, 'maxLength', `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-maxLength-${index}">Max length</label>
                                        <input class="form-input edit-work-field-maxLength" id="edit-work-field-maxLength-${index}" data-work-field-maxLength type="number" step="1" value="${escapeHtml(field.maxLength ?? '')}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, 'minimum', `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-minimum-${index}">Minimum</label>
                                        <input class="form-input edit-work-field-minimum" id="edit-work-field-minimum-${index}" data-work-field-minimum type="number" step="any" value="${escapeHtml(field.minimum ?? '')}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, 'maximum', `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-maximum-${index}">Maximum</label>
                                        <input class="form-input edit-work-field-maximum" id="edit-work-field-maximum-${index}" data-work-field-maximum type="number" step="any" value="${escapeHtml(field.maximum ?? '')}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, 'step', `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-step-${index}">Step</label>
                                        <input class="form-input edit-work-field-step" id="edit-work-field-step-${index}" data-work-field-step type="number" step="any" value="${escapeHtml(field.step ?? '')}" placeholder="1">
                                    </div>
                                    `)}
                                </div>
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ['enum', 'examples'], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Values</div>
                            <div class="edit-work-field-schema-group-grid">
                                <div>
                                    <label class="edit-label" for="edit-work-field-enum-${index}">Enum</label>
                                    <textarea class="form-textarea edit-work-field-enum" id="edit-work-field-enum-${index}" data-work-field-enum rows="2" placeholder="One option per line">${escapeHtml(Array.isArray(field.enum) ? field.enum.join('\n') : '')}</textarea>
                                </div>
                                ${renderSchemaControl(field, 'examples', `
                                <div>
                                    <label class="edit-label" for="edit-work-field-examples-${index}">Examples</label>
                                    <textarea class="form-textarea edit-work-field-examples" id="edit-work-field-examples-${index}" data-work-field-examples rows="2" placeholder="One example per line">${escapeHtml(Array.isArray(field.examples) ? field.examples.join('\n') : '')}</textarea>
                                </div>
                                `)}
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ['required', 'readOnly', 'writeOnly', 'deprecated', 'nullable'], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Flags</div>
                            <div class="edit-work-field-bools">
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-required${field.required ? ' checked' : ''}><span>required</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-readOnly${field.readOnly ? ' checked' : ''}><span>readOnly</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-writeOnly${field.writeOnly ? ' checked' : ''}><span>writeOnly</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-deprecated${field.deprecated ? ' checked' : ''}><span>deprecated</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-nullable${field.nullable ? ' checked' : ''}><span>nullable</span></label>
                            </div>
                        </section>
                        `)}
                    </div>
                </details>
            </div>
        </div>
    `).join('');
}

function createWorkFieldEditor({ editPanel, onWorkFieldsInput, initialState = [] }) {
    const typeOptions = schemaFieldTypeOptions();
    let workFieldState = initialState;

    const commitWorkFieldState = () => {
        if (typeof onWorkFieldsInput !== 'function') {
            return;
        }
        workFieldState = workFieldState.map((field, index) => normalizeWorkField(field, index));
        onWorkFieldsInput(workFieldState);
    };

    const syncWorkFieldsFromDom = () => {
        const rows = Array.from(editPanel.querySelectorAll('[data-work-field-row]'));
        workFieldState = rows.map((row, index) => {
            const previous = workFieldState[index] && typeof workFieldState[index] === 'object' ? workFieldState[index] : {};
            const candidate = {
                ...previous,
                type: readRowText(row, '[data-work-field-type]') || 'text',
                name: readRowText(row, '[data-work-field-name]') || '',
                title: readRowText(row, '[data-work-field-title]') || '',
                description: readRowText(row, '[data-work-field-description]') || '',
                placeholder: readRowText(row, '[data-work-field-placeholder]') || '',
                const: readRowText(row, '[data-work-field-const]') || '',
                value: readRowValue(row, '[data-work-field-value]'),
                default: readRowValue(row, '[data-work-field-default]'),
                format: readRowText(row, '[data-work-field-format]') || '',
                pattern: readRowText(row, '[data-work-field-pattern]') || '',
                required: readRowBool(row, '[data-work-field-required]'),
                readOnly: readRowBool(row, '[data-work-field-readOnly]'),
                writeOnly: readRowBool(row, '[data-work-field-writeOnly]'),
                deprecated: readRowBool(row, '[data-work-field-deprecated]'),
                nullable: readRowBool(row, '[data-work-field-nullable]'),
                minLength: readRowNumber(row, '[data-work-field-minLength]'),
                maxLength: readRowNumber(row, '[data-work-field-maxLength]'),
                minimum: readRowNumber(row, '[data-work-field-minimum]'),
                maximum: readRowNumber(row, '[data-work-field-maximum]'),
                step: readRowNumber(row, '[data-work-field-step]'),
                enum: readRowList(row, '[data-work-field-enum]'),
                examples: readRowList(row, '[data-work-field-examples]'),
            };
            return applyWorkFieldTypeDefaults(normalizeWorkField(candidate, index), candidate.type);
        });
        commitWorkFieldState();
    };

    const rerenderWorkFields = () => {
        const listEl = editPanel.querySelector('#editWorkFieldsList');
        if (!listEl) {
            return;
        }
        listEl.innerHTML = renderWorkFieldRows(workFieldState, typeOptions);
        listEl.querySelectorAll('[data-work-field-row]').forEach((row) => {
            const typeEl = row.querySelector('[data-work-field-type]');
            const nameEl = row.querySelector('[data-work-field-name]');
            const valueEl = row.querySelector('[data-work-field-value]');
            const removeEl = row.querySelector('[data-work-field-remove]');
            const updateFromRow = () => syncWorkFieldsFromDom();
            if (typeEl) {
                typeEl.addEventListener('change', () => {
                    syncWorkFieldsFromDom();
                    rerenderWorkFields();
                });
                typeEl.addEventListener('input', updateFromRow);
            }
            if (nameEl) {
                nameEl.addEventListener('input', updateFromRow);
            }
            if (valueEl) {
                valueEl.addEventListener('input', updateFromRow);
            }
            if (removeEl) {
                removeEl.addEventListener('click', () => {
                    const index = Number(row.getAttribute('data-work-field-row') || '0');
                    workFieldState.splice(index, 1);
                    rerenderWorkFields();
                    commitWorkFieldState();
                });
            }
        });
    };

    const addWorkField = () => {
        workFieldState = [...workFieldState, createDefaultWorkField(workFieldState)];
        rerenderWorkFields();
        commitWorkFieldState();
    };

    return {
        getFields: () => workFieldState,
        renderRows: () => renderWorkFieldRows(workFieldState, typeOptions),
        syncWorkFieldsFromDom,
        rerenderWorkFields,
        addWorkField,
    };
}

export function renderEditPanel({
    editPanel,
    editRequested,
    config,
    status,
    contentTargetLabel,
    onTitleInput,
    onDescriptionInput,
    onWorkFieldsInput,
    onMediaInput,
    onSubmit,
    onToggleDrawer,
    onOpenLayoutPage,
    onReturnToWork,
    onSubmitLayout,
    onLayoutPresetChange,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onResetFolderWork,
    onDeleteTarget,
}) {
    if (!editPanel) {
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    if (!editRequested) {
        editPanel.hidden = true;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!config || status?.error) {
        const authMessage = status?.auth && !status.auth.canEdit
            ? (status?.error || 'Enter the editor password to unlock editing.')
            : null;
        const message = status?.error || 'Edit mode is unavailable.';
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(authMessage || message)}</div>
        `;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }
    if (!status?.allowed) {
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create <code>.edit.allow</code> in this folder or an ancestor to enable edit mode. Add <code>edit.not-allow</code> to stop inheritance in a subtree.</div>
        `;
        syncPromptDock();
        return { statusEl: null, promptRoot: null };
    }

    if (status?.target === 'layout') {
        return renderEditLayoutPanel({
            editPanel,
            config,
            status,
            contentTargetLabel,
            onSubmitLayout,
            onLayoutPresetChange,
            onReturnToWork,
            onUploadFiles,
            onCreateBlankFile,
            onCreateFolder,
        });
    }

    const label = status?.target === 'file' ? 'Edit mode (file)' : 'Edit mode (folder)';
    const settings = loadPromptSettings();
    const overlayState = layoutOverlayState(config, status);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isFileTarget = status?.target === 'file';
    const isEmptyFolder = !isFileTarget && treeItems.length === 0;
    const initialWorkFields = extractWorkFields(config?.work || {}).map((field, index) => applyWorkFieldTypeDefaults(normalizeWorkField(field, index), field.type));
    const workFieldEditor = createWorkFieldEditor({
        editPanel,
        onWorkFieldsInput,
        initialState: initialWorkFields,
    });
    const uploadSectionHtml = renderUploadSectionHtml({
        isFileTarget,
        isEmptyFolder,
    });

    editPanel.innerHTML = `
        <h3 class="edit-panel-title">${label}</h3>
        <div class="edit-status" id="editInlineStatus"></div>
        <form id="inlineEditForm" class="edit-inline">
            <div>
                <label class="edit-label" for="edit-title">Title</label>
                <input class="form-input" id="edit-title" type="text" name="title" value="${escapeHtml(config.title || '')}">
            </div>
            <div>
                <label class="edit-label" for="edit-description">Description</label>
                <textarea class="form-textarea" id="edit-description" name="description">${escapeHtml(config.description || '')}</textarea>
            </div>
            ${status?.target === 'file' || status?.target === 'folder' || (config?.work && typeof config.work === 'object' && Object.keys(config.work).some((key) => !RESERVED_WORK_CONFIG_KEYS.has(key) || key === 'type'))
                ? renderWorkConfigFieldsSection(config)
                : ''}
            <div class="edit-work-fields">
                <div class="edit-work-fields-header">
                    <div>
                        <div class="edit-work-fields-title">Work fields</div>
                        <div class="small-note">Add extra values below Description. Saved as <code>work.&lt;name&gt;</code> and shown in prompt context.</div>
                    </div>
                    <button class="btn btn-secondary edit-work-fields-add" type="button" id="editWorkFieldAdd" aria-label="Add work field">+</button>
                </div>
                <div class="edit-work-fields-list" id="editWorkFieldsList">
                    ${workFieldEditor.renderRows()}
                </div>
            </div>
            <div class="edit-inline-actions">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" id="editMoreToggle">More...</button>
                ${typeof onDeleteTarget === 'function' ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editDeleteTarget">
                    <img class="block" src="https://cdn.jsdelivr.net/npm/heroicons@2.2.0/24/outline/trash.svg" alt="" width="16" height="16">
                    Delete
                </button>
                ` : ''}
            </div>
        </form>
        <div class="edit-layout-launch">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="small-note">${escapeHtml(overlayState.wrapperSourceLabel)}</div>
                <div class="small-note">Inherited parent layout: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code></div>
                <div class="small-note">Current mode: <code>${escapeHtml(overlayState.displayMode)}</code></div>
            </div>
            <div class="edit-inline-actions">
                ${!isFileTarget && typeof onResetFolderWork === 'function' ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editResetFolderWork">
                    Reset work
                </button>
                ` : ''}
                <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
            </div>
        </div>
        ${uploadSectionHtml}
        ${renderPromptWindow(settings, { mode: isFileTarget ? 'file' : 'folder' })}
    `;

    const form = editPanel.querySelector('#inlineEditForm');
    const statusEl = editPanel.querySelector('#editInlineStatus');
    const moreToggle = editPanel.querySelector('#editMoreToggle');
    const deleteTargetButton = editPanel.querySelector('#editDeleteTarget');
    const resetFolderWorkButton = editPanel.querySelector('#editResetFolderWork');
    const changeLayoutButton = editPanel.querySelector('#editChangeLayout');
    const titleInput = editPanel.querySelector('#edit-title');
    const descInput = editPanel.querySelector('#edit-description');
    const addWorkFieldButton = editPanel.querySelector('#editWorkFieldAdd');
    const promptRoot = editPanel.querySelector('#promptLayer');
    syncPromptDock(promptRoot);

    const syncMediaState = () => {
        if (typeof onMediaInput !== 'function' || !form) {
            return;
        }
        onMediaInput(readMediaConfigFromForm(form, config?.work || {}));
    };

    if (titleInput && typeof onTitleInput === 'function') {
        titleInput.addEventListener('input', () => {
            onTitleInput(titleInput.value);
        });
    }
    if (descInput && typeof onDescriptionInput === 'function') {
        descInput.addEventListener('input', () => {
            onDescriptionInput(descInput.value);
        });
    }
    if (addWorkFieldButton) {
        addWorkFieldButton.addEventListener('click', () => {
            workFieldEditor.addWorkField();
        });
    }
    editPanel.querySelectorAll('[data-work-config-field], #edit-work-type').forEach((input) => {
        if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
            return;
        }
        input.addEventListener('input', syncMediaState);
        input.addEventListener('change', syncMediaState);
    });
    bindWorkCategoryControls({
        editPanel,
        onMediaInput,
    });
    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            workFieldEditor.syncWorkFieldsFromDom();
            onSubmit({
                elements: form.elements,
                form,
                statusEl,
            });
        });
    }
    if (moreToggle && typeof onToggleDrawer === 'function') {
        moreToggle.addEventListener('click', () => onToggleDrawer());
    }
    if (changeLayoutButton && typeof onOpenLayoutPage === 'function') {
        changeLayoutButton.addEventListener('click', () => onOpenLayoutPage());
    }
    if (resetFolderWorkButton && typeof onResetFolderWork === 'function') {
        resetFolderWorkButton.addEventListener('click', async () => {
            const confirmed = window.prompt('Type rm -rf to reset this folder work to the default inherited layout. This removes the local .layout override.');
            if ((confirmed || '').trim() !== 'rm -rf') {
                return;
            }
            await onResetFolderWork({ statusEl });
        });
    }
    if (deleteTargetButton && typeof onDeleteTarget === 'function') {
        deleteTargetButton.addEventListener('click', async () => {
            const confirmed = window.confirm('Delete this item? This cannot be undone.');
            if (!confirmed) {
                return;
            }
            await onDeleteTarget({ statusEl });
        });
    }

    bindUploadDialog({
        editPanel,
        statusEl,
        uploadLimits: status?.uploadLimits || null,
        onUploadFiles,
        onCreateBlankFile,
        onCreateFolder,
    });

    return { statusEl, promptRoot };
}
