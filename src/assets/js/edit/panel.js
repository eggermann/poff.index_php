import { escapeHtml } from '../core/utils.js';
import { loadPromptSettings } from './prompt/storage.js';
import { renderPromptWindow } from './prompt-window.js';

export function renderEditPanel({
    editPanel,
    editRequested,
    config,
    status,
    onTitleInput,
    onDescriptionInput,
    onSubmit,
    onToggleDrawer,
}) {
    if (!editPanel) {
        return { statusEl: null, promptRoot: null };
    }
    if (!editRequested) {
        editPanel.hidden = true;
        return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!config || status?.error) {
        const message = status?.error || 'Edit mode is unavailable.';
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(message)}</div>
        `;
        return { statusEl: null, promptRoot: null };
    }
    if (!status?.allowed) {
        editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create a file named <code>.edit.allow</code> in the site root to enable edit mode.</div>
        `;
        return { statusEl: null, promptRoot: null };
    }

    const label = status?.target === 'file' ? 'Edit mode (file)' : 'Edit mode (folder)';
    const settings = loadPromptSettings();
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
            <div class="edit-inline-actions">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" id="editMoreToggle">More...</button>
            </div>
        </form>
        <div class="edit-layout-launch">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="small-note">Open the HBS layout editor for this item.</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
        </div>
        ${renderPromptWindow(settings)}
    `;

    const form = editPanel.querySelector('#inlineEditForm');
    const statusEl = editPanel.querySelector('#editInlineStatus');
    const moreToggle = editPanel.querySelector('#editMoreToggle');
    const changeLayoutButton = editPanel.querySelector('#editChangeLayout');
    const titleInput = editPanel.querySelector('#edit-title');
    const descInput = editPanel.querySelector('#edit-description');
    const promptRoot = editPanel.querySelector('#promptWindow');

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
    if (form && typeof onSubmit === 'function') {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            onSubmit({
                elements: form.elements,
                statusEl,
            });
        });
    }
    if (moreToggle && typeof onToggleDrawer === 'function') {
        moreToggle.addEventListener('click', () => onToggleDrawer());
    }
    if (changeLayoutButton && typeof onToggleDrawer === 'function') {
        changeLayoutButton.addEventListener('click', () => onToggleDrawer());
    }

    return { statusEl, promptRoot };
}
