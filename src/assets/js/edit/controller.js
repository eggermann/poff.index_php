import { requestEditConfig, requestEditUpload, requestPromptTemplate } from '../api/edit.js';
import { buildVirtualLayoutPath, getActiveSelection } from '../core/selection.js';
import { bindPromptWindow } from './prompt.js';
import { renderEditDrawer } from './drawer.js';
import { renderEditPanel } from './panel.js';

export function createEditController({ elements, context, editRequested }) {
    const { editPanel, editDrawer, editToggle } = elements;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, 'currentPoffConfig')
        ? context.currentPoffConfig
        : null;

    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = 'folder';
    let drawerOpen = false;

    function renderFolderMeta() {
        return folderConfig;
    }

    function syncEditToggle() {
        if (!editToggle) {
            return;
        }
        editToggle.textContent = editRequested ? 'Exit edit mode' : 'Enable edit mode';
        editToggle.classList.toggle('edit-toggle-on', editRequested);
        editToggle.setAttribute('aria-pressed', editRequested ? 'true' : 'false');
    }

    function bindEditToggle() {
        if (!editToggle) {
            return;
        }
        editToggle.addEventListener('click', () => {
            const url = new URL(window.location.href);
            if (editRequested) {
                url.searchParams.delete('edit');
            } else {
                url.searchParams.set('edit', 'true');
            }
            window.location.href = url.toString();
        });
    }

    async function saveConfig(payload, statusEl) {
        try {
            if (statusEl) {
                statusEl.textContent = 'Saving...';
                statusEl.className = 'edit-status';
            }
            const data = await requestEditConfig('save', payload);
            if (!data || data.error) {
                throw new Error(data?.error || 'Save failed.');
            }
            editConfig = data.config || editConfig;
            editTarget = data.target || editTarget;
            if (editTarget === 'folder' || (editTarget === 'layout' && data.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
            if (statusEl) {
                statusEl.textContent = 'Config saved.';
                statusEl.className = 'edit-status edit-status-success';
            }
            window.dispatchEvent(new CustomEvent('poff:content-updated', {
                detail: {
                    path: payload?.path || '',
                    target: editTarget,
                    subjectTarget: data.subjectTarget || editTarget,
                },
            }));
            return data.config;
        } catch (err) {
            if (statusEl) {
                statusEl.textContent = err.message || 'Save failed.';
                statusEl.className = 'edit-status';
            }
            throw err;
        }
    }

    function syncDrawerVisibility() {
        if (!editDrawer) {
            return;
        }
        if (!editRequested || !drawerOpen) {
            editDrawer.classList.remove('edit-drawer-open');
            editDrawer.hidden = true;
            return;
        }
        editDrawer.hidden = false;
        editDrawer.classList.add('edit-drawer-open');
    }

    function renderEditUI(config, status) {
        const panelState = renderEditPanel({
            editPanel,
            editRequested,
            config,
            status,
            onTitleInput: (value) => {
                if (!editConfig) {
                    return;
                }
                editConfig.title = value;
                if (status?.target !== 'file') {
                    folderConfig = editConfig;
                    renderFolderMeta();
                }
            },
            onDescriptionInput: (value) => {
                if (!editConfig) {
                    return;
                }
                editConfig.description = value;
                if (status?.target !== 'file') {
                    folderConfig = editConfig;
                    renderFolderMeta();
                }
            },
            onSubmit: async ({ elements, statusEl }) => {
                const selection = getActiveSelection();
                const payload = {
                    path: selection.path,
                    title: (elements.title?.value || '').trim(),
                    description: (elements.description?.value || '').trim(),
                };
                await saveConfig(payload, statusEl);
            },
            onToggleDrawer: () => {
                drawerOpen = !drawerOpen;
                syncDrawerVisibility();
            },
            onOpenLayoutPage: () => {
                const selection = getActiveSelection();
                const nextPath = buildVirtualLayoutPath(selection.previewPath ?? selection.path);
                drawerOpen = false;
                syncDrawerVisibility();
                window.location.hash = `#/${nextPath}`;
            },
            onReturnToWork: () => {
                const selection = getActiveSelection();
                const nextPath = selection.previewPath || '';
                drawerOpen = false;
                syncDrawerVisibility();
                if (nextPath) {
                    window.location.hash = `#/${nextPath}`;
                    return;
                }
                window.history.replaceState(null, '', window.location.pathname + window.location.search);
                window.dispatchEvent(new Event('hashchange'));
            },
            onSubmitLayout: async ({ payload, statusEl }) => {
                const selection = getActiveSelection();
                const layoutPreset = (payload.layoutPreset || 'actual').trim();
                const layoutName = layoutPreset === 'none'
                    ? 'none'
                    : layoutPreset === 'custom'
                        ? 'custom-layout'
                        : (Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTarget') ? 'filesystem-layout' : 'poff-layout');
                const layoutPayload = {
                    name: layoutName,
                    engine: 'lightncandy',
                    preset: layoutPreset,
                };
                if (Object.prototype.hasOwnProperty.call(payload, 'contentTemplate')) {
                    layoutPayload.sectionTemplate = payload.contentTemplate ?? '';
                }
                if (Object.prototype.hasOwnProperty.call(payload, 'layoutTemplate')) {
                    layoutPayload.template = payload.layoutTemplate ?? '';
                }
                if (Object.prototype.hasOwnProperty.call(payload, 'layoutCss')) {
                    layoutPayload.css = payload.layoutCss ?? '';
                }
                if (Object.prototype.hasOwnProperty.call(payload, 'layoutJs')) {
                    layoutPayload.js = payload.layoutJs ?? '';
                }
                if (Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTarget')) {
                    layoutPayload.originalTarget = payload.originalLayoutTarget ?? '';
                    layoutPayload.originalTemplate = payload.originalLayoutTemplate ?? '';
                    layoutPayload.originalCss = payload.originalLayoutCss ?? '';
                    layoutPayload.originalJs = payload.originalLayoutJs ?? '';
                }
                await saveConfig({
                    path: selection.path,
                    layout: layoutPayload,
                }, statusEl);
            },
            onUploadFiles: async ({ source, files, statusEl }) => {
                const selection = getActiveSelection();
                const data = await requestEditUpload({
                    path: selection.previewPath || selection.path,
                    source,
                    files,
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Upload failed.');
                }

                editConfig = data.config || editConfig;
                editTarget = data.target || editTarget;
                if (editTarget === 'folder') {
                    folderConfig = editConfig;
                }
                renderEditUI(editConfig, {
                    allowed: data.allowed !== false,
                    target: editTarget,
                });
                const inlineStatus = document.getElementById('editInlineStatus');
                if (inlineStatus) {
                    const count = Array.isArray(data.uploaded) ? data.uploaded.length : 0;
                    inlineStatus.textContent = count === 1 ? 'Uploaded 1 file.' : `Uploaded ${count} files.`;
                    inlineStatus.className = 'edit-status edit-status-success';
                }
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            },
        });

        const drawerState = renderEditDrawer({
            editDrawer,
            editRequested,
            config,
            status,
            onClose: () => {
                drawerOpen = false;
                syncDrawerVisibility();
            },
            onSubmit: async ({ elements, statusEl, treeVisible }) => {
                const selection = getActiveSelection();
                const payload = {
                    path: selection.path,
                    link: (elements.link?.value || '').trim(),
                    url: (elements.url?.value || '').trim(),
                    work: {
                        type: (elements.work_type?.value || '').trim(),
                    },
                };
                if (status?.target !== 'file') {
                    payload.treeVisible = treeVisible;
                }
                await saveConfig(payload, statusEl);
            },
        });

        if (panelState.promptRoot) {
            bindPromptWindow({
                root: panelState.promptRoot,
                statusEl: panelState.statusEl,
                drawerForm: drawerState.drawerForm,
                getActiveSelection,
                getConfig: () => editConfig,
                requestPromptTemplate,
                saveConfig,
            });
        }
        syncDrawerVisibility();
    }

    async function initEditMode() {
        if (!editRequested || !editPanel) {
            return;
        }
        const selection = getActiveSelection();
        const data = await requestEditConfig('config', { path: selection.path });
        if (data.config) {
            editConfig = data.config;
            editTarget = data.target || (selection.isFile ? 'file' : 'folder');
            if (editTarget === 'folder' || (editTarget === 'layout' && data.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
        }
        renderEditUI(data.config || editConfig, {
            allowed: data.allowed !== false,
            error: data.error,
            target: editTarget,
        });
    }

    return {
        renderFolderMeta,
        syncEditToggle,
        bindEditToggle,
        initEditMode,
    };
}
