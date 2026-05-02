import { requestEditConfig, requestEditDelete, requestEditReset, requestEditUpload, requestPromptTemplate } from '../api/edit.js';
import { buildVirtualLayoutPath, getActiveSelection } from '../core/selection.js';
import { bindPromptWindow } from './prompt.js';
import { renderEditDrawer } from './drawer.js';
import { renderEditPanel } from './panel.js';
import { materializeWorkFields } from './work-fields.js';
import { buildLayoutPayload, createLayoutNameForPreset } from './controller/layout.js';
import { getContentTargetPath, getEditTargetPath } from './controller/paths.js';
import { setStatusMessage } from './status.js';

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
            setStatusMessage(statusEl, 'Saving...');
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
            setStatusMessage(statusEl, 'Config saved.', true);
            window.dispatchEvent(new CustomEvent('poff:content-updated', {
                detail: {
                    path: payload?.path || '',
                    target: editTarget,
                    subjectTarget: data.subjectTarget || editTarget,
                },
            }));
            return data.config;
        } catch (err) {
            setStatusMessage(statusEl, err.message || 'Save failed.');
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

    async function refreshCurrentEditState(selection = getActiveSelection()) {
        const refreshed = await requestEditConfig('config', { path: getEditTargetPath(selection) });
        if (refreshed?.config) {
            editConfig = refreshed.config;
            editTarget = refreshed.target || (selection.isLayout ? 'layout' : (selection.previewIsFile ? 'file' : 'folder'));
            if (editTarget === 'folder' || (editTarget === 'layout' && refreshed.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
        }
        renderEditUI(refreshed?.config || editConfig, {
            allowed: refreshed?.allowed !== false,
            error: refreshed?.error,
            target: refreshed?.target || editTarget,
            subjectTarget: refreshed?.subjectTarget,
            uploadLimits: refreshed?.uploadLimits,
        });
    }

    function renderEditUI(config, status) {
        const layoutNameForPreset = createLayoutNameForPreset(editConfig);
        const panelState = renderEditPanel({
            editPanel,
            editRequested,
            config,
            status,
            contentTargetLabel: getContentTargetPath(),
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
            onWorkFieldsInput: (fields) => {
                if (!editConfig) {
                    return;
                }
                const currentWork = (editConfig.work && typeof editConfig.work === 'object') ? editConfig.work : {};
                editConfig.work = materializeWorkFields(currentWork, fields);
                if (status?.target !== 'file') {
                    folderConfig = editConfig;
                    renderFolderMeta();
                }
            },
            onSubmit: async ({ elements, statusEl }) => {
                const selection = getActiveSelection();
                const payload = {
                    path: getEditTargetPath(selection),
                    title: (elements.title?.value || '').trim(),
                    description: (elements.description?.value || '').trim(),
                };
                if (editConfig?.work && typeof editConfig.work === 'object') {
                    payload.work = materializeWorkFields(editConfig.work);
                }
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
            onDeleteTarget: async ({ statusEl }) => {
                const selection = getActiveSelection();
                const targetPath = getEditTargetPath(selection);
                if (!targetPath) {
                    throw new Error('Delete target unavailable.');
                }
                const data = await requestEditDelete({
                    path: targetPath,
                    return: selection.previewPath || selection.path || '',
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Delete failed.');
                }
                drawerOpen = false;
                syncDrawerVisibility();
                setStatusMessage(statusEl, 'Deleted.', true);
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
                const nextPath = selection.previewPath ? selection.previewPath.split('/').slice(0, -1).join('/') : '';
                window.location.hash = nextPath ? `#/${nextPath}` : '';
                await refreshCurrentEditState(getActiveSelection());
            },
            onResetFolderWork: async ({ statusEl }) => {
                const selection = getActiveSelection();
                const targetPath = getEditTargetPath(selection);
                if (!targetPath) {
                    throw new Error('Reset target unavailable.');
                }
                const data = await requestEditReset({
                    path: targetPath,
                    return: selection.previewPath || selection.path || '',
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Reset failed.');
                }
                drawerOpen = false;
                syncDrawerVisibility();
                setStatusMessage(statusEl, 'Folder work reset to default.', true);
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
                await refreshCurrentEditState(getActiveSelection());
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
                const { layoutPayload } = buildLayoutPayload(payload, layoutNameForPreset);
                await saveConfig({
                    path: getEditTargetPath(selection),
                    layout: layoutPayload,
                }, statusEl);
            },
            onLayoutPresetChange: async ({ payload, statusEl }) => {
                const layoutPreset = (payload?.layoutPreset || 'actual').trim() === 'inherit'
                    ? 'actual'
                    : (payload?.layoutPreset || 'actual').trim();
                await saveConfig({
                    path: getEditTargetPath(getActiveSelection()),
                    layout: {
                        name: layoutNameForPreset(layoutPreset, payload?.layoutSharedName || ''),
                        engine: 'lightncandy',
                        preset: layoutPreset,
                        ...(layoutPreset === 'shared'
                            ? {
                                source: 'shared',
                                sharedName: payload?.layoutSharedName || layoutNameForPreset(layoutPreset, payload?.layoutSharedName || ''),
                            }
                            : {}),
                    },
                }, statusEl);
            },
            onUploadFiles: async ({ source, files }) => {
                const selection = getActiveSelection();
                const data = await requestEditUpload({
                    path: getContentTargetPath(selection),
                    source,
                    files,
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Upload failed.');
                }
                await refreshCurrentEditState(selection);
                const inlineStatus = document.getElementById('editInlineStatus');
                if (inlineStatus) {
                    const count = Array.isArray(data.uploaded) ? data.uploaded.length : 0;
                    setStatusMessage(inlineStatus, count === 1 ? 'Uploaded 1 file.' : `Uploaded ${count} files.`, true);
                }
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            },
            onCreateBlankFile: async ({ source, fileName }) => {
                const selection = getActiveSelection();
                const data = await requestEditUpload({
                    path: getContentTargetPath(selection),
                    source,
                    fileName,
                    files: [],
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Create blank file failed.');
                }
                await refreshCurrentEditState(selection);
                const inlineStatus = document.getElementById('editInlineStatus');
                if (inlineStatus) {
                    const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                        ? data.uploaded[0].name
                        : fileName;
                    setStatusMessage(inlineStatus, `Created ${createdName}.`, true);
                }
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            },
            onCreateFolder: async ({ source, folderName }) => {
                const selection = getActiveSelection();
                const data = await requestEditUpload({
                    path: getContentTargetPath(selection),
                    source,
                    fileName: folderName,
                    files: [],
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Create folder failed.');
                }
                await refreshCurrentEditState(selection);
                const inlineStatus = document.getElementById('editInlineStatus');
                if (inlineStatus) {
                    const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                        ? data.uploaded[0].name
                        : folderName;
                    setStatusMessage(inlineStatus, `Created folder ${createdName}.`, true);
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
                    path: getEditTargetPath(selection),
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
        const data = await requestEditConfig('config', { path: getEditTargetPath(selection) });
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
            subjectTarget: data.subjectTarget,
            uploadLimits: data.uploadLimits,
        });
    }

    return {
        renderFolderMeta,
        syncEditToggle,
        bindEditToggle,
        initEditMode,
    };
}
