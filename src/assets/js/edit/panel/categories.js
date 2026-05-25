import { escapeHtml } from '../../core/utils.js';
import { bindStoredDetailsState, renderPersistentDetailsSection } from './shared.js';

const DEFAULT_CATEGORY_OPTIONS = ['image', 'media', 'visual', 'video', 'motion', 'audio', 'sound', 'pdf', 'document', 'text', 'link', 'reference', 'folder', 'collection', 'other'];
const CATEGORY_FIELD_ID = 'edit-work-categories';
const CATEGORY_SELECT_ID = 'edit-work-category-select';
const CATEGORY_CUSTOM_ID = 'edit-work-category-custom';
const CATEGORY_PILLS_ID = 'editWorkCategoryPills';
const CATEGORY_ADD_ID = 'editWorkCategoryAdd';
const CATEGORY_CUSTOM_ADD_ID = 'editWorkCategoryCustomAdd';
const CATEGORY_DETAILS_STORAGE_KEY = 'category-details';
const CATEGORY_MAX_LENGTH = 24;
const CATEGORY_MAX_COUNT = 12;

function normalizeCategoryValue(value) {
    return String(value ?? '').trim().toLowerCase().slice(0, CATEGORY_MAX_LENGTH);
}

export function normalizeWorkCategories(value) {
    let rawValues = [];
    if (Array.isArray(value)) {
        rawValues = value;
    } else if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed.startsWith('[')) {
            try {
                const parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    rawValues = parsed;
                } else {
                    rawValues = trimmed.split(/\r?\n|,/);
                }
            } catch {
                rawValues = trimmed.split(/\r?\n|,/);
            }
        } else {
            rawValues = trimmed.split(/\r?\n|,/);
        }
    }
    const categories = [];

    rawValues.forEach((candidate) => {
        const normalized = normalizeCategoryValue(candidate);
        if (!normalized || categories.includes(normalized) || categories.length >= CATEGORY_MAX_COUNT) {
            return;
        }
        categories.push(normalized);
    });

    return categories;
}

export function getWorkCategoryOptions(catalog = null) {
    const options = Array.isArray(catalog?.categories) && catalog.categories.length
        ? catalog.categories
        : DEFAULT_CATEGORY_OPTIONS;
    return Array.from(new Set(options.map((category) => normalizeCategoryValue(category)).filter(Boolean)));
}

function renderCategoryPill(category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
        return '';
    }

    return `
        <button type="button" class="edit-work-category-pill" data-work-category-remove="${escapeHtml(normalized)}" aria-label="Remove category ${escapeHtml(normalized)}">
            <span>${escapeHtml(normalized)}</span>
            <span aria-hidden="true">−</span>
        </button>
    `;
}

function renderCategoryOptions(options, selectedValue = '') {
    const normalizedSelected = normalizeCategoryValue(selectedValue);
    return options.map((option) => {
        const normalized = normalizeCategoryValue(option);
        return `<option value="${escapeHtml(normalized)}"${normalized === normalizedSelected ? ' selected' : ''}>${escapeHtml(normalized)}</option>`;
    }).join('');
}

export function renderWorkCategorySection(config = {}) {
    const work = (config?.work && typeof config.work === 'object') ? config.work : {};
    const catalog = config?.workTemplateCatalog && typeof config.workTemplateCatalog === 'object'
        ? config.workTemplateCatalog
        : null;
    const categories = normalizeWorkCategories(work.categories ?? work.category ?? []);
    const options = Array.from(new Set([
        ...getWorkCategoryOptions(catalog),
        ...categories,
    ]));
    const selectedCategory = normalizeCategoryValue(options.find((option) => !categories.includes(option)) || options[0] || '');

    return renderPersistentDetailsSection({
        storageKey: CATEGORY_DETAILS_STORAGE_KEY,
        defaultOpen: false,
        id: 'editWorkCategorySection',
        className: 'edit-work-fields edit-work-category-section',
        summaryClassName: 'edit-work-category-summary',
        bodyClassName: 'edit-work-category-controls',
        titleHtml: 'Categories',
        noteHtml: 'Pick shared work categories for filtering and prompt context.',
        bodyHtml: `
            <div class="edit-work-category-picker">
                <label class="edit-label" for="${CATEGORY_SELECT_ID}">Add from works</label>
                <div class="edit-work-category-picker-row">
                    <select class="form-select" id="${CATEGORY_SELECT_ID}" name="work_category_select">
                        ${renderCategoryOptions(options, selectedCategory)}
                    </select>
                    <button class="btn btn-secondary" type="button" id="${CATEGORY_ADD_ID}" aria-label="Add selected category">+</button>
                </div>
                <div class="small-note">The list comes from the shared worktype categories.</div>
            </div>
            <div class="edit-work-category-picker">
                <label class="edit-label" for="${CATEGORY_CUSTOM_ID}">Custom category</label>
                <div class="edit-work-category-picker-row">
                    <input class="form-input" id="${CATEGORY_CUSTOM_ID}" type="text" maxlength="${CATEGORY_MAX_LENGTH}" placeholder="type a custom category">
                    <button class="btn btn-secondary" type="button" id="${CATEGORY_CUSTOM_ADD_ID}" aria-label="Add custom category">+</button>
                </div>
                <div class="small-note">Limit ${CATEGORY_MAX_LENGTH} chars, max ${CATEGORY_MAX_COUNT} categories.</div>
            </div>
            <div class="edit-work-category-current">
                <div class="edit-label">Current categories</div>
                <div class="edit-work-category-pills" id="${CATEGORY_PILLS_ID}">
                    ${categories.length ? categories.map(renderCategoryPill).join('') : '<div class="small-note">No categories selected yet.</div>'}
                </div>
            </div>
            <input type="hidden" id="${CATEGORY_FIELD_ID}" data-work-config-field data-work-config-key="categories" data-work-config-kind="json" value="${escapeHtml(JSON.stringify(categories))}">
        `,
    });
}

function renderCategoryPills(editPanel, categories) {
    const pillsEl = editPanel.querySelector(`#${CATEGORY_PILLS_ID}`);
    if (!pillsEl) {
        return;
    }

    const normalizedCategories = normalizeWorkCategories(categories);
    pillsEl.innerHTML = normalizedCategories.length
        ? normalizedCategories.map(renderCategoryPill).join('')
        : '<div class="small-note">No categories selected yet.</div>';
}

function writeCategoriesField(editPanel, categories) {
    const field = editPanel.querySelector(`#${CATEGORY_FIELD_ID}`);
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return;
    }

    const normalizedCategories = normalizeWorkCategories(categories);
    field.value = JSON.stringify(normalizedCategories);
    field.dispatchEvent(new Event('input', { bubbles: true }));
    field.dispatchEvent(new Event('change', { bubbles: true }));
}

function readCategoriesField(editPanel) {
    const field = editPanel.querySelector(`#${CATEGORY_FIELD_ID}`);
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return [];
    }

    return normalizeWorkCategories(field.value);
}

function addCategory(editPanel, category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
        return;
    }

    const categories = readCategoriesField(editPanel);
    if (!categories.includes(normalized)) {
        categories.push(normalized);
    }
    renderCategoryPills(editPanel, categories);
    writeCategoriesField(editPanel, categories);
}

function removeCategory(editPanel, category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
        return;
    }

    const categories = readCategoriesField(editPanel).filter((item) => item !== normalized);
    renderCategoryPills(editPanel, categories);
    writeCategoriesField(editPanel, categories);
}

export function bindWorkCategoryControls({ editPanel, onMediaInput }) {
    if (!editPanel) {
        return () => {};
    }

    const selectEl = editPanel.querySelector(`#${CATEGORY_SELECT_ID}`);
    const customEl = editPanel.querySelector(`#${CATEGORY_CUSTOM_ID}`);
    const addEl = editPanel.querySelector(`#${CATEGORY_ADD_ID}`);
    const customAddEl = editPanel.querySelector(`#${CATEGORY_CUSTOM_ADD_ID}`);
    const sectionEl = editPanel.querySelector('[data-work-category-section]');
    if (!sectionEl) {
        return () => {};
    }

    const cleanupDetailsState = bindStoredDetailsState(sectionEl, CATEGORY_DETAILS_STORAGE_KEY);

    const onAddClick = () => {
        if (!(selectEl instanceof HTMLSelectElement)) {
            return;
        }
        addCategory(editPanel, selectEl.value);
    };

    const onCustomAddClick = () => {
        if (!(customEl instanceof HTMLInputElement)) {
            return;
        }
        addCategory(editPanel, customEl.value);
        customEl.value = '';
    };

    const onSectionClick = (event) => {
        const target = event.target;
        const button = target && typeof target.closest === 'function'
            ? target.closest('[data-work-category-remove]')
            : null;
        if (!button) {
            return;
        }
        removeCategory(editPanel, button.dataset.workCategoryRemove || button.getAttribute('data-work-category-remove') || '');
    };

    if (addEl) {
        addEl.addEventListener('click', onAddClick);
    }
    if (customAddEl) {
        customAddEl.addEventListener('click', onCustomAddClick);
    }
    sectionEl.addEventListener('click', onSectionClick);
    if (customEl) {
        customEl.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                onCustomAddClick();
            }
        });
    }

    renderCategoryPills(editPanel, readCategoriesField(editPanel));

    return () => {
        if (addEl) {
            addEl.removeEventListener('click', onAddClick);
        }
        if (customAddEl) {
            customAddEl.removeEventListener('click', onCustomAddClick);
        }
        sectionEl.removeEventListener('click', onSectionClick);
        cleanupDetailsState();
    };
}
