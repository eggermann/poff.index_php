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

    function getContentTargetPath(selection = getActiveSelection()) {
        if (selection?.isLayout) {
            return selection.path || '';
        }
        const previewPath = selection?.previewPath || selection?.path || '';
        if (selection?.previewIsFile) {
            return previewPath.split('/').slice(0, -1).join('/');
        }
        return previewPath;
    }

    function getEditTargetPath(selection = getActiveSelection()) {
        if (selection?.isLayout) {
            return selection.path || '';
        }
        if (selection?.previewIsFile) {
            const activeFileLink = document.querySelector('#navList a.nav-link-active[data-path]');
            const navPath = (activeFileLink?.getAttribute('data-path') || '').trim();
            if (navPath) {
                return navPath;
            }
        }
        return selection?.previewPath || selection?.path || '';
    }

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
        const layoutNameForPreset = (layoutPreset = 'actual') => {
            const preset = String(layoutPreset || 'actual').trim() === 'inherit'
                ? 'actual'
                : String(layoutPreset || 'actual').trim();
            if (preset === 'none') {
                return 'none';
            }
            if (preset === 'custom') {
                return 'custom-layout';
            }
            const currentLayout = editConfig?.work?.layout;
            const hasFilesystemSource = !!(
                currentLayout
                && typeof currentLayout === 'object'
                && (
                    currentLayout.storage === 'filesystem'
                    || (typeof currentLayout.directory === 'string' && currentLayout.directory.trim() !== '')
                    || (typeof currentLayout.inheritedDirectory === 'string' && currentLayout.inheritedDirectory.trim() !== '')
                )
            );
            return hasFilesystemSource ? 'filesystem-layout' : 'poff-layout';
        };
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
            onSubmit: async ({ elements, statusEl }) => {
                const selection = getActiveSelection();
                const payload = {
                    path: getEditTargetPath(selection),
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
                const rawLayoutPreset = (payload.layoutPreset || 'actual').trim();
                const layoutPreset = rawLayoutPreset === 'inherit' ? 'actual' : rawLayoutPreset;
                const layoutName = layoutNameForPreset(layoutPreset);
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
                const hasOriginalDraftWrite = (
                    Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTemplate')
                    || Object.prototype.hasOwnProperty.call(payload, 'originalLayoutCss')
                    || Object.prototype.hasOwnProperty.call(payload, 'originalLayoutJs')
                );
                if (Object.prototype.hasOwnProperty.call(payload, 'originalLayoutTarget') && hasOriginalDraftWrite) {
                    layoutPayload.originalTarget = payload.originalLayoutTarget ?? '';
                    layoutPayload.originalTemplate = payload.originalLayoutTemplate ?? '';
                    layoutPayload.originalCss = payload.originalLayoutCss ?? '';
                    layoutPayload.originalJs = payload.originalLayoutJs ?? '';
                }
                await saveConfig({
                    path: getEditTargetPath(selection),
                    layout: layoutPayload,
                }, statusEl);
            },
            onLayoutPresetChange: async ({ payload, statusEl }) => {
                const rawLayoutPreset = (payload?.layoutPreset || 'actual').trim();
                const layoutPreset = rawLayoutPreset === 'inherit' ? 'actual' : rawLayoutPreset;
                await saveConfig({
                    path: getEditTargetPath(getActiveSelection()),
                    layout: {
                        name: layoutNameForPreset(layoutPreset),
                        engine: 'lightncandy',
                        preset: layoutPreset,
                    },
                }, statusEl);
            },
            onUploadFiles: async ({ source, files, statusEl }) => {
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
                    inlineStatus.textContent = count === 1 ? 'Uploaded 1 file.' : `Uploaded ${count} files.`;
                    inlineStatus.className = 'edit-status edit-status-success';
                }
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            },
            onCreateBlankFile: async ({ source, fileName, statusEl }) => {
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
                    inlineStatus.textContent = `Created ${createdName}.`;
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
