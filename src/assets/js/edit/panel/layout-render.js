import { escapeHtml } from '../../core/utils.js';
import { renderPromptWindow } from '../prompt-window.js';
import { renderUploadSectionHtml } from './upload.js';

const LAYOUT_PRESET_OPTIONS = [
    { value: 'actual', label: 'Inherit' },
    { value: 'none', label: 'None' },
    { value: 'custom', label: 'Custom' },
    { value: 'shared', label: 'Collection' },
];

export function renderLayoutModeSummary({ displayMode, wrapperSourceLabel, sectionSourceLabel }) {
    return `
        <div class="edit-layout-launch edit-layout-summary">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="edit-layout-summary-line">Source: <code id="edit-layout-source-preview">${escapeHtml(wrapperSourceLabel)}</code></div>
                <div class="edit-layout-summary-line">Mode: <code id="edit-layout-mode-preview">${escapeHtml(displayMode)}</code></div>
                <div class="edit-layout-summary-line">Content template: <code id="edit-layout-section-source-preview">${escapeHtml(sectionSourceLabel)}</code></div>
            </div>
            <div class="edit-inline-actions edit-layout-header-actions">
                <button class="btn btn-secondary" type="button" id="editLayoutBack">Back to work</button>
                <button class="btn btn-secondary" type="button" id="editLayoutMore">Advanced</button>
            </div>
        </div>
    `;
}

export function renderSharedLayoutOptions(sharedLayouts = [], selectedName = '', currentLayoutDirectory = '') {
    if (!Array.isArray(sharedLayouts) || sharedLayouts.length === 0) {
        return '<div class="small-note">No collection layouts available for this worktype.</div>';
    }

    const groupedLayouts = sharedLayouts.reduce((groups, option) => {
        const groupKey = String(option?.source || 'collection').trim() === 'bundled' ? 'built-in' : 'collection';
        if (!groups[groupKey]) {
            groups[groupKey] = [];
        }
        groups[groupKey].push(option);
        return groups;
    }, {});
    const isCurrentOption = (option) => String(option?.directory || option?.name || '') === String(currentLayoutDirectory || '');
    const optionLabel = (option, fallback) => {
        const label = option?.label || option?.name || fallback;
        return `${label}${isCurrentOption(option) ? ' (current)' : ''}`;
    };

    return `
        <label class="edit-label" for="edit-layout-shared">Collection layout</label>
        <select class="form-select" id="edit-layout-shared" name="layout_shared">
            ${groupedLayouts['built-in']?.length ? `
                <optgroup label="Built-in">
                    ${groupedLayouts['built-in'].map((option) => `
                        <option value="${escapeHtml(option.name || '')}" ${String(selectedName || '') === String(option.name || '') ? 'selected' : ''}>
                            ${escapeHtml(optionLabel(option, 'built-in'))}
                        </option>
                    `).join('')}
                </optgroup>
            ` : ''}
            ${groupedLayouts.collection?.length ? `
                <optgroup label="Collection">
                    ${groupedLayouts.collection.map((option) => `
                        <option value="${escapeHtml(option.name || '')}" ${String(selectedName || '') === String(option.name || '') ? 'selected' : ''}>
                            ${escapeHtml(optionLabel(option, 'collection'))}
                        </option>
                    `).join('')}
                </optgroup>
            ` : ''}
        </select>
        <div class="small-note">Choose a layout from the same worktype. The visible names come from the folder names.</div>
    `;
}

function renderLayoutPresetSelect(layoutState, sharedLayouts, sharedLayoutName) {
    return `
        <label class="edit-label" for="edit-layout-preset">Layout source</label>
        <select class="form-select" id="edit-layout-preset" name="layout_preset">
            ${LAYOUT_PRESET_OPTIONS.map((option) => `
                <option value="${option.value}" ${layoutState.preset === option.value ? 'selected' : ''}>${option.label}</option>
            `).join('')}
        </select>
        <div class="mt-3${layoutState.preset === 'shared' ? '' : ' hidden'}" id="edit-layout-shared-wrap">
            ${renderSharedLayoutOptions(sharedLayouts, sharedLayoutName, layoutState.directory || '')}
        </div>
    `;
}

function renderLayoutEditorFields({ sectionWasLocal, sectionName, sectionTemplate = '' }) {
    return `
        <div class="edit-layout-editor">
            <div class="edit-layout-editor-head">
                <div>
                    <div class="edit-layout-meta-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                </div>
            </div>
            <textarea class="form-textarea" id="edit-layout-primary-template" name="layout_primary_template"></textarea>
            <div class="edit-layout-overlay-grid">
                <div>
                    <label class="edit-label" for="edit-layout-primary-css">Layout CSS</label>
                    <textarea class="form-textarea" id="edit-layout-primary-css" name="layout_primary_css"></textarea>
                </div>
                <div>
                    <label class="edit-label" for="edit-layout-primary-js">Layout JS</label>
                    <textarea class="form-textarea" id="edit-layout-primary-js" name="layout_primary_js"></textarea>
                </div>
            </div>
        </div>

        <details class="edit-layout-advanced" ${sectionWasLocal ? 'open' : ''}>
            <summary class="edit-layout-advanced-summary">Inner work section (advanced)</summary>
            <div class="edit-layout-editor">
                <div class="edit-layout-editor-head">
                    <div>
                        <div class="edit-layout-meta-title">Inner section partial</div>
                        <div class="small-note">Edit the wrapped <code>{{> ${escapeHtml(sectionName)}}</code> partial only when you need item-specific content inside the current layout.</div>
                    </div>
                </div>
                <textarea class="form-textarea" id="edit-content-template" name="content_template">${escapeHtml(sectionTemplate)}</textarea>
            </div>
        </details>
    `;
}

export function renderEditLayoutPanelHtml({
    settings,
    subjectLabel,
    activeWorkName,
    displayMode,
    layoutState,
    sectionName,
    wrapperTarget,
    sectionTarget,
    sectionSourceLabel,
    sectionWasLocal,
    wrapperSourceLabel,
    inheritedLayoutLabel,
    originalLabel,
    sharedLayouts,
    sharedLayoutName,
}) {
    const uploadSectionHtml = renderUploadSectionHtml({
        isFileTarget: subjectLabel === 'file',
        isEmptyFolder: false,
        activeWorkName,
    });

    return `
        <h3 class="edit-panel-title">Edit layout (${subjectLabel})</h3>
        <div class="small-note">Choose where this ${escapeHtml(subjectLabel)} gets its visual wrapper. Preview stays on the current work.</div>
        <div class="edit-status" id="editLayoutStatus"></div>
        <form id="editLayoutPanelForm" class="edit-inline edit-layout-panel">
            ${renderLayoutModeSummary({ displayMode, wrapperSourceLabel, sectionSourceLabel })}
            <div class="edit-grid edit-grid-cols">
                <div>
                    ${renderLayoutPresetSelect(layoutState, sharedLayouts, sharedLayoutName)}
                </div>
                <div class="edit-layout-copy edit-layout-section-note">
                    <div class="edit-layout-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                    <div class="edit-inline-actions edit-layout-select-actions">
                        <button class="btn" type="submit">Save changes</button>
                    </div>
                </div>
            </div>
        </form>
        ${uploadSectionHtml}
        ${renderPromptWindow(settings, {
            mode: 'layout',
            subjectType: subjectLabel,
            templateTarget: wrapperTarget,
            sectionTarget,
        })}
        <details class="edit-layout-advanced edit-layout-manual" id="editLayoutManual" ${sectionWasLocal ? 'open' : ''}>
            <summary class="edit-layout-advanced-summary">Template files (advanced)</summary>
            <div class="edit-layout-overlay-grid">
                <div class="edit-layout-meta-card">
                    <div class="edit-layout-meta-title">Sources</div>
                    <div class="small-note">Inherited parent layout: <code>${escapeHtml(inheritedLayoutLabel)}</code></div>
                    <div class="small-note">Built-in default: <code>poff-layout.hbs</code></div>
                    <div class="small-note">Layout target: <code>${escapeHtml(originalLabel)}</code></div>
                    <div class="small-note">Custom wrapper target: <code>${escapeHtml(wrapperTarget)}</code></div>
                    <div class="small-note">Inner content source: <code>${escapeHtml(sectionSourceLabel)}</code></div>
                    <div class="small-note">Content override: <code>${escapeHtml(sectionTarget)}</code></div>
                </div>
            </div>
            ${renderLayoutEditorFields({
                sectionWasLocal,
                sectionName,
                sectionTemplate: layoutState.sectionTemplate || '',
            })}
        </details>
    `;
}

export function renderEditLayoutOverlayHtml({
    layoutState,
    displayMode,
    sectionName,
    wrapperTarget,
    sectionTarget,
    sectionSourceLabel,
    sectionWasLocal,
    wrapperSourceLabel,
    inheritedLayoutLabel,
    originalLabel,
    sharedLayouts,
    sharedLayoutName,
}) {
    return `
        <div class="edit-layout-overlay-shell">
            <div class="drawer-header">
                <div>
                    <h4 class="drawer-title">Layout overlay</h4>
                    <div class="small-note">Inherited layouts stay read-only until a local <code>.layout</code> exists.</div>
                </div>
                <button type="button" class="drawer-close" id="editLayoutOverlayClose">&times;</button>
            </div>
            <div class="edit-status" id="editLayoutOverlayStatus"></div>
            <form id="editLayoutOverlayForm" class="edit-layout-overlay-form">
                <div class="edit-layout-overlay-grid">
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Mode</div>
                        ${renderLayoutPresetSelect(layoutState, sharedLayouts, sharedLayoutName)}
                        <div class="small-note">Resolved mode: <code id="edit-layout-mode-preview">${escapeHtml(displayMode)}</code></div>
                        <div class="small-note">Resolved wrapper: <code>${escapeHtml(wrapperSourceLabel)}</code></div>
                    </div>
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Inheritance</div>
                        <div class="small-note">Inherited parent layout: <code>${escapeHtml(inheritedLayoutLabel)}</code></div>
                        <div class="small-note">Built-in default: <code>poff-layout.hbs</code></div>
                        <div class="small-note">Wrapped inner content source: <code>${escapeHtml(sectionSourceLabel)}</code></div>
                    </div>
                    <div class="edit-layout-meta-card">
                        <div class="edit-layout-meta-title">Targets</div>
                        <div class="small-note">Current source: <code>${escapeHtml(originalLabel)}</code></div>
                        <div class="small-note">Local override: <code>${escapeHtml(wrapperTarget)}</code></div>
                        <div class="small-note">Content override: <code>${escapeHtml(sectionTarget)}</code></div>
                    </div>
                </div>

                ${renderLayoutEditorFields({
                    sectionWasLocal,
                    sectionName,
                    sectionTemplate: layoutState.sectionTemplate || '',
                })}

                <div class="edit-inline-actions">
                    <button class="btn" type="submit">Save changes</button>
                    <button class="btn btn-secondary" type="button" id="editLayoutOverlayCancel">Close</button>
                </div>
            </form>
        </div>
    `;
}
