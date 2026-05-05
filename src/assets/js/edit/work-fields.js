const RESERVED_WORK_FIELD_NAMES = new Set(['fields', 'layout', 'type', 'model', 'engine', 'syntax', 'mimeType', 'categories', 'category']);
const SUPPORTED_WORK_FIELD_TYPES = new Set(['text', 'textarea', 'number', 'checkbox', 'select', 'color', 'date', 'url', 'email']);
const SCHEMA_TEXT_KEYS = ['title', 'description', 'placeholder', 'format', 'pattern', 'contentMediaType', 'contentEncoding', 'const'];
const SCHEMA_BOOLEAN_KEYS = ['required', 'readOnly', 'writeOnly', 'deprecated', 'nullable', 'uniqueItems'];
const SCHEMA_NUMBER_KEYS = ['minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'minLength', 'maxLength', 'minItems', 'maxItems', 'minProperties', 'maxProperties', 'step'];
const SCHEMA_ARRAY_KEYS = ['enum', 'examples'];
const WORK_FIELD_SCHEMA_PROFILES = {
    text: {
        defaults: {},
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'format', 'pattern', 'minLength', 'maxLength', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    textarea: {
        defaults: {},
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'format', 'pattern', 'minLength', 'maxLength', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    number: {
        defaults: { step: 1 },
        visibleControls: new Set(['title', 'description', 'const', 'default', 'minimum', 'maximum', 'exclusiveMinimum', 'exclusiveMaximum', 'multipleOf', 'step', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    checkbox: {
        defaults: { default: false },
        visibleControls: new Set(['title', 'description', 'const', 'default', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    select: {
        defaults: {},
        visibleControls: new Set(['title', 'description', 'const', 'default', 'enum', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    color: {
        defaults: { format: 'color' },
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    date: {
        defaults: { format: 'date' },
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    url: {
        defaults: { format: 'uri' },
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
    email: {
        defaults: { format: 'email' },
        visibleControls: new Set(['title', 'description', 'placeholder', 'const', 'default', 'examples', 'required', 'readOnly', 'writeOnly', 'deprecated', 'nullable']),
    },
};

export function getWorkFieldSchemaProfile(type = 'text') {
    const normalizedType = normalizeFieldType(type);
    return WORK_FIELD_SCHEMA_PROFILES[normalizedType] || WORK_FIELD_SCHEMA_PROFILES.text;
}

function normalizeFieldName(value, fallbackIndex = 1) {
    const trimmed = String(value ?? '').trim().toLowerCase();
    const compact = trimmed.replace(/[^a-z0-9]+/g, '');
    return compact || `text${fallbackIndex}`;
}

function normalizeFieldLabel(value, fallbackIndex = 1) {
    const trimmed = String(value ?? '').trim();
    if (!trimmed) {
        return `Text ${fallbackIndex}`;
    }
    return trimmed
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/([a-zA-Z])([0-9])/g, '$1 $2')
        .replace(/([0-9])([a-zA-Z])/g, '$1 $2')
        .replace(/[_-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/(^|\s)\w/g, (match) => match.toUpperCase());
}

function normalizeFieldType(value) {
    const type = String(value ?? '').trim().toLowerCase();
    return SUPPORTED_WORK_FIELD_TYPES.has(type) ? type : 'text';
}

function normalizeBoolean(value, fallback = false) {
    if (value === null || value === undefined || value === '') {
        return !!fallback;
    }
    if (typeof value === 'boolean') {
        return value;
    }
    const token = String(value).trim().toLowerCase();
    if (['true', '1', 'yes', 'on'].includes(token)) {
        return true;
    }
    if (['false', '0', 'no', 'off'].includes(token)) {
        return false;
    }
    return !!fallback;
}

function normalizeNullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
}

function normalizeText(value) {
    return String(value ?? '');
}

function normalizeList(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item ?? '').trim()).filter(Boolean);
    }
    const normalized = String(value ?? '').trim();
    if (!normalized) {
        return [];
    }
    return normalized
        .split(/\r?\n|,/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function listToText(value) {
    const list = normalizeList(value);
    return list.join('\n');
}

function normalizeFieldValue(type, value) {
    if (type === 'checkbox') {
        return normalizeBoolean(value);
    }
    if (type === 'number') {
        const number = normalizeNullableNumber(value);
        return number === null ? '' : number;
    }
    return normalizeText(value);
}

function normalizeFieldExtras(candidate = {}, fallbackIndex = 1) {
    const extras = {
        title: normalizeFieldLabel(candidate.title ?? candidate.label ?? candidate.name, fallbackIndex),
        description: normalizeText(candidate.description ?? ''),
        placeholder: normalizeText(candidate.placeholder ?? ''),
        const: normalizeText(candidate.const ?? ''),
        required: normalizeBoolean(candidate.required, false),
        readOnly: normalizeBoolean(candidate.readOnly, false),
        writeOnly: normalizeBoolean(candidate.writeOnly, false),
        deprecated: normalizeBoolean(candidate.deprecated, false),
        nullable: normalizeBoolean(candidate.nullable, false),
        uniqueItems: normalizeBoolean(candidate.uniqueItems, false),
        format: normalizeText(candidate.format ?? ''),
        pattern: normalizeText(candidate.pattern ?? ''),
        contentMediaType: normalizeText(candidate.contentMediaType ?? ''),
        contentEncoding: normalizeText(candidate.contentEncoding ?? ''),
    };

    SCHEMA_NUMBER_KEYS.forEach((key) => {
        if (key === 'step') {
            const stepValue = normalizeNullableNumber(candidate[key]);
            if (stepValue !== null) {
                extras[key] = stepValue;
            }
            return;
        }
        const numberValue = normalizeNullableNumber(candidate[key]);
        if (numberValue !== null) {
            extras[key] = numberValue;
        }
    });

    SCHEMA_ARRAY_KEYS.forEach((key) => {
        const list = normalizeList(candidate[key]);
        if (list.length) {
            extras[key] = list;
        }
    });

    const defaultValue = Object.prototype.hasOwnProperty.call(candidate, 'default')
        ? candidate.default
        : candidate.value;
    if (defaultValue !== undefined) {
        extras.default = defaultValue;
    }

    if (Object.prototype.hasOwnProperty.call(candidate, 'examples')) {
        extras.examples = normalizeList(candidate.examples);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'enum')) {
        extras.enum = normalizeList(candidate.enum);
    }
    if (Object.prototype.hasOwnProperty.call(candidate, 'options')) {
        extras.options = normalizeList(candidate.options);
        if (!extras.enum || !extras.enum.length) {
            extras.enum = extras.options.slice();
        }
    }

    return extras;
}

function normalizePreservedExtras(candidate = {}) {
    const extras = {};
    Object.keys(candidate || {}).forEach((key) => {
        if (
            key === 'type'
            || key === 'name'
            || key === 'key'
            || key === 'label'
            || key === 'title'
            || key === 'description'
            || key === 'placeholder'
            || key === 'value'
            || key === 'default'
            || key === 'const'
            || key === 'text'
            || key === 'fields'
            || key === 'options'
            || key === 'enum'
            || key === 'examples'
            || key === 'required'
            || key === 'readOnly'
            || key === 'writeOnly'
            || key === 'deprecated'
            || key === 'nullable'
            || key === 'uniqueItems'
            || key === 'format'
            || key === 'pattern'
            || key === 'contentMediaType'
            || key === 'contentEncoding'
            || SCHEMA_NUMBER_KEYS.includes(key)
            || RESERVED_WORK_FIELD_NAMES.has(String(key).trim().toLowerCase())
        ) {
            return;
        }
        extras[key] = candidate[key];
    });
    return extras;
}

export function isReservedWorkFieldName(name = '') {
    return RESERVED_WORK_FIELD_NAMES.has(String(name || '').trim().toLowerCase());
}

export function createDefaultWorkField(fields = []) {
    const index = Array.isArray(fields) ? fields.length + 1 : 1;
    const base = {
        type: 'text',
        name: `text${index}`,
        title: `Text ${index}`,
        label: `Text ${index}`,
        description: '',
        placeholder: '',
        const: '',
        value: '',
        default: '',
        required: false,
        readOnly: false,
        writeOnly: false,
        nullable: false,
        deprecated: false,
        uniqueItems: false,
        format: '',
        pattern: '',
        enum: [],
        examples: [],
    };
    return applyWorkFieldTypeDefaults(base, base.type);
}

export function applyWorkFieldTypeDefaults(field = {}, type = field?.type || 'text') {
    const profile = getWorkFieldSchemaProfile(type);
    const nextField = { ...(field || {}), type: normalizeFieldType(type) };
    Object.entries(profile.defaults || {}).forEach(([key, value]) => {
        if (nextField[key] === undefined || nextField[key] === null || nextField[key] === '') {
            nextField[key] = value;
        }
    });
    return nextField;
}

export function normalizeWorkField(entry = {}, index = 0) {
    const candidate = entry && typeof entry === 'object' ? entry : {};
    const type = normalizeFieldType(candidate.type);
    const name = normalizeFieldName(candidate.name ?? candidate.key ?? candidate.label ?? candidate.title, index + 1);
    const title = normalizeFieldLabel(candidate.title ?? candidate.label ?? candidate.name ?? name, index + 1);
    const description = normalizeText(candidate.description ?? '');
    const placeholder = normalizeText(candidate.placeholder ?? '');
    const valueSource = Object.prototype.hasOwnProperty.call(candidate, 'value')
        ? candidate.value
        : (Object.prototype.hasOwnProperty.call(candidate, 'text')
            ? candidate.text
            : (Object.prototype.hasOwnProperty.call(candidate, 'const') ? candidate.const : candidate.default));
    const normalized = {
        type,
        name,
        title,
        label: title,
        description,
        placeholder,
        const: normalizeText(candidate.const ?? ''),
        value: normalizeFieldValue(type, valueSource),
        default: Object.prototype.hasOwnProperty.call(candidate, 'default')
            ? normalizeFieldValue(type, candidate.default)
            : normalizeFieldValue(type, valueSource),
        required: normalizeBoolean(candidate.required, false),
        readOnly: normalizeBoolean(candidate.readOnly, false),
        writeOnly: normalizeBoolean(candidate.writeOnly, false),
        nullable: normalizeBoolean(candidate.nullable, false),
        deprecated: normalizeBoolean(candidate.deprecated, false),
        uniqueItems: normalizeBoolean(candidate.uniqueItems, false),
        format: normalizeText(candidate.format ?? ''),
        pattern: normalizeText(candidate.pattern ?? ''),
        contentMediaType: normalizeText(candidate.contentMediaType ?? ''),
        contentEncoding: normalizeText(candidate.contentEncoding ?? ''),
    };

    SCHEMA_NUMBER_KEYS.forEach((key) => {
        const numberValue = normalizeNullableNumber(candidate[key]);
        if (numberValue !== null) {
            normalized[key] = numberValue;
        }
    });

    const enumValues = normalizeList(candidate.enum ?? candidate.options ?? []);
    if (enumValues.length) {
        normalized.enum = enumValues;
        normalized.options = enumValues.slice();
    } else {
        normalized.enum = [];
        normalized.options = [];
    }
    const examples = normalizeList(candidate.examples ?? []);
    normalized.examples = examples;

    const extras = normalizePreservedExtras(candidate);
    Object.assign(normalized, extras);

    return normalized;
}

export function extractWorkFields(work = {}) {
    if (!work || typeof work !== 'object' || !Array.isArray(work.fields)) {
        return [];
    }

    return work.fields
        .map((field, index) => normalizeWorkField(field, index))
        .filter((field) => field.name && !isReservedWorkFieldName(field.name));
}

export function materializeWorkFields(work = {}, fields = null) {
    const nextWork = { ...(work || {}) };
    const sourceFields = Array.isArray(fields) ? fields : extractWorkFields(work);
    const hasFieldsInput = Array.isArray(fields) || Array.isArray(work?.fields);
    const allowTopLevelOverrides = fields === null;
    const previousFields = extractWorkFields(work);
    const previousNames = new Set(previousFields.map((field) => field.name));
    const normalized = sourceFields
        .map((field, index) => {
            const normalizedField = normalizeWorkField(field, index);
            if (
                allowTopLevelOverrides
                && Object.prototype.hasOwnProperty.call(nextWork, normalizedField.name)
            ) {
                normalizedField.value = normalizeFieldValue(normalizedField.type, nextWork[normalizedField.name]);
            }
            return normalizedField;
        })
        .filter((field) => field.name && !isReservedWorkFieldName(field.name));
    const normalizedNames = new Set(normalized.map((field) => field.name));

    previousNames.forEach((name) => {
        if (!normalizedNames.has(name)) {
            delete nextWork[name];
        }
    });

    if (normalized.length || hasFieldsInput) {
        nextWork.fields = normalized.map((field) => ({ ...field }));
    } else {
        delete nextWork.fields;
    }
    normalized.forEach((field) => {
        nextWork[field.name] = field.value;
    });

    return nextWork;
}

export function mergeWorkFields(work = {}, fields = []) {
    return materializeWorkFields(work, fields);
}

export function summarizeWorkFieldValue(field = {}) {
    const normalized = normalizeWorkField(field);
    if (normalized.type === 'checkbox') {
        return normalized.value ? 'true' : 'false';
    }
    if (normalized.type === 'select' && Array.isArray(normalized.enum) && normalized.enum.length) {
        return String(normalized.value ?? normalized.default ?? normalized.enum[0] ?? '').trim() || '-';
    }
    const text = String(normalized.value ?? '').trim();
    return text || '-';
}

export function summarizeWorkFields(fields = []) {
    const normalized = (Array.isArray(fields) ? fields : [])
        .map((field, index) => normalizeWorkField(field, index))
        .filter((field) => field.name && !isReservedWorkFieldName(field.name));
    if (!normalized.length) {
        return '';
    }

    return normalized.slice(0, 6).map((field) => {
        const nameLabel = field.title || field.label || field.name;
        return `${nameLabel}: ${summarizeWorkFieldValue(field)}`;
    }).join(' | ');
}

export function schemaFieldTypeOptions() {
    return ['text', 'textarea', 'number', 'checkbox', 'select', 'color', 'date', 'url', 'email'];
}
