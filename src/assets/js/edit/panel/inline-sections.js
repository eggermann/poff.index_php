import { escapeHtml } from '../../core/utils.js';
import {
    extractWorkFields,
    normalizeWorkField,
} from '../work-fields.js';
import {
    getWorkCategoryOptions,
    normalizeWorkCategories,
    renderWorkCategorySection,
} from './categories.js';
import { renderPersistentDetailsSection } from './shared.js';

export const RESERVED_WORK_CONFIG_KEYS = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind', 'converter']);
export const PASSWORD_DETAILS_STORAGE_KEY = 'password-details';

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

export function readMediaConfigFromForm(form, currentWork = {}) {
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
    const converterSelect = form?.elements?.converter_id;
    const selectedOption = converterSelect?.selectedOptions && converterSelect.selectedOptions[0]
        ? converterSelect.selectedOptions[0]
        : null;
    const converterId = String(converterSelect?.value || '').trim();
    const converterName = String(selectedOption?.dataset?.converterName || '').trim();
    const converterType = String(selectedOption?.dataset?.converterType || 'converter').trim() || 'converter';
    const converterEngine = String(selectedOption?.dataset?.converterEngine || '').trim();
    const converterPath = String(selectedOption?.dataset?.converterPath || '').trim();
    const converterViewerHref = String(selectedOption?.dataset?.converterViewerHref || '').trim();
    const converterUrl = String(selectedOption?.dataset?.converterUrl || '').trim();
    const existingConverter = nextWork.converter && typeof nextWork.converter === 'object' ? nextWork.converter : {};
    if (converterId) {
        nextWork.converter = {
            ...existingConverter,
            type: converterType,
            name: converterName || String(existingConverter.name || converterId.split('/').pop() || 'converter'),
            enabled: true,
            id: converterId,
            path: converterPath,
            viewerHref: converterViewerHref,
            url: converterUrl,
            node: converterEngine === 'remote-node'
                ? (existingConverter.node && typeof existingConverter.node === 'object' ? existingConverter.node : { id: '', baseUrl: '', mcpUrl: '', endpoint: '' })
                : 'local',
            quality: String(form?.elements?.converter_quality?.value || 'default').trim() || 'default',
            format: String(form?.elements?.converter_format?.value || '').trim() || 'source',
            saveMode: String(form?.elements?.converter_save_mode?.value || 'new-hidden-work').trim() || 'new-hidden-work',
            hiddenByDefault: true,
        };
    } else if (Object.prototype.hasOwnProperty.call(nextWork, 'converter')) {
        delete nextWork.converter;
    }
    return nextWork;
}

export function renderWorkConfigFieldsSection(config = {}) {
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

export function renderConverterSection(config = {}) {
    const catalog = (config?.converterCatalog && typeof config.converterCatalog === 'object') ? config.converterCatalog : null;
    const available = Array.isArray(catalog?.available) ? catalog.available : [];
    const work = (config?.work && typeof config.work === 'object') ? config.work : {};
    const converter = (work.converter && typeof work.converter === 'object') ? work.converter : {};
    const rawSourceUrl = String(converter.sourceUrl || config?.srcUrl || '').trim();
    const sourceUrlValue = rawSourceUrl
        ? (() => {
            try {
                const baseHref = typeof window?.location?.href === 'string' && window.location.href
                    ? window.location.href
                    : 'http://localhost/';
                return new URL(rawSourceUrl, baseHref).toString();
            } catch {
                return rawSourceUrl;
            }
        })()
        : '';
    const selectedId = String(converter.id || '').trim();
    const selectedDefinition = selectedId ? (available.find((item) => item?.id === selectedId) || null) : null;
    const defaults = selectedDefinition?.defaults || {};
    const ui = selectedDefinition?.ui && typeof selectedDefinition.ui === 'object' ? selectedDefinition.ui : {};
    const selectedQuality = String(converter.quality || defaults.quality || 'default');
    const selectedFormat = String(converter.format || defaults.format || '');
    const selectedSaveMode = String(converter.saveMode || defaults.saveMode || 'new-hidden-work');
    const convertDisabled = selectedId === '';
    const unsupportedMessage = catalog && catalog.webReadable === false
        ? '<div class="edit-status">This file is not directly web-readable. Choose a converter to create a poff-standard work.</div>'
        : '';
    const options = [
        '<option value="">none</option>',
        ...available.map((item) => {
            const disabled = item.enabled === false;
            const label = `${item.label || item.id}${disabled && item.disabledReason ? ` (${item.disabledReason})` : ''}`;
            return `<option value="${escapeHtml(item.id || '')}" data-converter-type="${escapeHtml(item.type || 'converter')}" data-converter-name="${escapeHtml(item.name || '')}" data-converter-engine="${escapeHtml(item.engine || '')}" data-converter-path="${escapeHtml(item.path || '')}" data-converter-viewer-href="${escapeHtml(item.viewerHref || '')}" data-converter-url="${escapeHtml(item.url || '')}" data-converter-formats="${escapeHtml(Array.isArray(item.formats) ? item.formats.join(',') : '')}" data-converter-outputs="${escapeHtml(Array.isArray(item.outputs) ? item.outputs.join(',') : '')}" data-converter-ui="${escapeHtml(JSON.stringify(item.ui || {}))}" data-converter-defaults="${escapeHtml(JSON.stringify(item.defaults || {}))}"${item.id === selectedId ? ' selected' : ''}${disabled ? ' disabled' : ''}>${escapeHtml(label)}</option>`;
        }),
    ].join('');
    const generated = Array.isArray(config.generatedWorks) ? config.generatedWorks : [];
    const formatOptionLabel = (value) => {
        const normalized = String(value || '');
        return normalized === 'source' ? 'same as source' : normalized;
    };
    const renderSelectOptions = (values, selected, labeler = null) => Array.isArray(values)
        ? values.map((value) => {
            const normalized = String(value || '');
            const label = typeof labeler === 'function' ? labeler(normalized) : normalized;
            return `<option value="${escapeHtml(normalized)}"${normalized === String(selected || '') ? ' selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('')
        : '';
    const formatOptions = Array.isArray(ui?.format?.options) && ui.format.options.length
        ? ui.format.options
        : (Array.isArray(selectedDefinition?.formats) && selectedDefinition.formats.length ? selectedDefinition.formats : ['source']);
    const qualityOptions = Array.isArray(ui?.quality?.options) && ui.quality.options.length ? ui.quality.options : ['preview', 'default', 'archival', 'small-web'];
    const saveModeOptions = Array.isArray(ui?.saveMode?.options) && ui.saveMode.options.length ? ui.saveMode.options : ['new-hidden-work', 'replace-generated-work', 'temporary-preview-only'];
    const formatLabel = String(ui?.format?.label || 'Output format');
    const qualityLabel = String(ui?.quality?.label || 'Quality');
    const saveModeLabel = String(ui?.saveMode?.label || 'Save mode');
    return `
        <div class="edit-work-fields edit-work-converter">
            <div class="edit-work-fields-header">
                <div>
                    <div class="edit-work-fields-title">Converter</div>
                    <div class="small-note">Creates hidden generated works beside unsupported source files.</div>
                </div>
                <button class="btn btn-secondary" type="button" id="editCreateConverter">New converter</button>
            </div>
            ${unsupportedMessage}
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-converter-id">Converter</label>
                    <select class="form-select" id="edit-converter-id" name="converter_id" form="inlineEditForm">
                        ${options}
                    </select>
                </div>
                <div>
                    <label class="edit-label" for="edit-converter-source-url">Source URL</label>
                    <input class="form-input" id="edit-converter-source-url" name="converter_source_url" form="inlineEditForm" type="url" value="${escapeHtml(sourceUrlValue)}" placeholder="https://example.com/source.tiff">
                    <div class="small-note">Optional override used by remote converters when they need a public source URL.</div>
                </div>
                <div>
                    <label class="edit-label" for="edit-converter-quality">${escapeHtml(qualityLabel)}</label>
                    <select class="form-select" id="edit-converter-quality" name="converter_quality" form="inlineEditForm">
                        ${renderSelectOptions(qualityOptions, selectedQuality)}
                    </select>
                </div>
                <div>
                    <label class="edit-label" for="edit-converter-format">${escapeHtml(formatLabel)}</label>
                    <select class="form-select" id="edit-converter-format" name="converter_format" form="inlineEditForm">
                        ${renderSelectOptions(formatOptions, selectedFormat, formatOptionLabel)}
                    </select>
                </div>
                <div>
                    <label class="edit-label" for="edit-converter-save-mode">${escapeHtml(saveModeLabel)}</label>
                    <select class="form-select" id="edit-converter-save-mode" name="converter_save_mode" form="inlineEditForm">
                        ${renderSelectOptions(saveModeOptions, selectedSaveMode)}
                    </select>
                </div>
            </div>
            <div class="edit-inline-actions">
                <button class="btn btn-secondary" type="button" id="editConvertWork"${convertDisabled ? ' disabled' : ''}>Convert</button>
            </div>
            <div class="small-note">
                Generated works:
                ${generated.length ? generated.map((item) => `[${escapeHtml(item.name || '')}] [show] [make visible] [delete] [regenerate]`).join(' ') : 'none'}
            </div>
        </div>
    `;
}

export function renderDirectFileLink(config = {}, status = {}) {
    if (status?.target !== 'file') {
        return '';
    }
    const directUrl = String(config?.srcUrl || '').trim();
    if (!directUrl) {
        return '';
    }
    return `
        <div class="small-note">
            <a href="${escapeHtml(directUrl)}" target="_blank" rel="noopener noreferrer">Open original file</a>
        </div>
    `;
}

export function renderPasswordChangeSection(status = {}) {
    if (!status?.auth?.canEdit) {
        return '';
    }
    if (status?.target !== 'folder') {
        return '';
    }
    const relativePath = String(status?.relativePath || '').trim();
    if (relativePath !== '') {
        return '';
    }
    const pathname = typeof window?.location?.pathname === 'string'
        ? window.location.pathname.trim()
        : '';
    const normalizedPathname = pathname.replace(/\/+$/, '') || '/';
    if (normalizedPathname === '/') {
        return '';
    }

    const configPath = String(status?.auth?.configPath || '').trim();
    return renderPersistentDetailsSection({
        storageKey: PASSWORD_DETAILS_STORAGE_KEY,
        defaultOpen: true,
        id: 'editPasswordDetails',
        className: 'edit-work-fields edit-password-details',
        summaryClassName: 'edit-password-details-summary',
        bodyClassName: 'edit-inline edit-password-details-body',
        titleHtml: 'Change password',
        noteHtml: `Updates <code>.poff-auth.php</code>${configPath ? ` at <code>${escapeHtml(configPath)}</code>` : ''}.`,
        bodyHtml: `
            <form id="editPasswordForm" class="edit-inline">
                <div>
                    <label class="edit-label" for="edit-current-password">Current password</label>
                    <input class="form-input" id="edit-current-password" type="password" name="currentPassword" autocomplete="current-password">
                </div>
                <div>
                    <label class="edit-label" for="edit-new-password">New password</label>
                    <input class="form-input" id="edit-new-password" type="password" name="newPassword" autocomplete="new-password">
                </div>
                <div>
                    <label class="edit-label" for="edit-confirm-password">Confirm new password</label>
                    <input class="form-input" id="edit-confirm-password" type="password" name="confirmPassword" autocomplete="new-password">
                </div>
                <div class="edit-inline-actions">
                    <button class="btn btn-secondary" type="submit">Change password</button>
                </div>
                <div class="edit-status" id="editPasswordStatus"></div>
            </form>
        `,
    });
}
