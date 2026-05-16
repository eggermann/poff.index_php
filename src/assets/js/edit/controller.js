import { requestEditAuth, requestEditConfig, requestEditDelete, requestEditReset, requestEditUpload, requestPromptTemplate } from '../api/edit.js';
import { buildVirtualLayoutPath, getActiveSelection } from '../core/selection.js';
import { bindPromptWindow } from './prompt.js';
import { renderEditDrawer } from './drawer.js';
import { renderEditPanel } from './panel.js';
import { materializeWorkFields } from './work-fields.js';
import { buildLayoutPayload, createLayoutNameForPreset } from './controller/layout.js';
import { getContentTargetPath, getEditTargetPath } from './controller/paths.js';
import { setStatusMessage } from './status.js';

function readMediaConfigFromElements(elements, form, currentWork = {}) {
    const nextWork = { ...(currentWork && typeof currentWork === 'object' ? currentWork : {}) };
    const typeField = elements.work_type;
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
    return nextWork;
}

export function createEditController({ elements, context, editRequested }) {
    const {
        editPanel,
        editDrawer,
        editAuthDetails,
        editToggle,
        editAddWork,
        editAuthForm,
        editAuthPassword,
        editAuthSubmit,
        editAuthStatus,
    } = elements;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, 'currentPoffConfig')
        ? context.currentPoffConfig
        : null;
    const initialAuthState = context?.cmsAuth && typeof context.cmsAuth === 'object'
        ? context.cmsAuth
        : {};

    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = 'folder';
    let drawerOpen = false;
    let authFormVisible = false;
    let authState = {
        configured: !!initialAuthState.configured,
        authenticated: !!initialAuthState.authenticated,
        editModeAllowed: initialAuthState.editModeAllowed !== false,
        canEdit: !!initialAuthState.canEdit,
    };

    function annotateConfigPath(config, selection = getActiveSelection(), status = {}) {
        if (!config || typeof config !== 'object') {
            return config;
        }
        const relativePath = selection?.previewPath || selection?.path || context?.currentPathForIframe || '';
        const isFile = status?.subjectTarget === 'file'
            || (status?.target === 'file')
            || selection?.previewIsFile === true;
        Object.defineProperties(config, {
            __poffRelativePath: {
                value: relativePath,
                configurable: true,
            },
            __poffIsFile: {
                value: isFile,
                configurable: true,
            },
        });
        return config;
    }

    function updateAuthState(nextAuth) {
        if (!nextAuth || typeof nextAuth !== 'object') {
            return;
        }
        authState = {
            ...authState,
            ...nextAuth,
            configured: !!nextAuth.configured,
            authenticated: !!nextAuth.authenticated,
            editModeAllowed: nextAuth.editModeAllowed !== false,
            canEdit: !!nextAuth.canEdit,
        };
        if (window.POFF_CONTEXT && typeof window.POFF_CONTEXT === 'object') {
            window.POFF_CONTEXT.cmsAuth = authState;
        }
    }

    function authStatusMessage(status) {
        if (status?.error) {
            return status.error;
        }
        if (!authState.editModeAllowed) {
            return 'Editing is disabled in this folder.';
        }
        if (!authState.configured) {
            return 'Create .poff-auth.php with a password hash to enable editing.';
        }
        if (!authState.authenticated) {
            return 'Enter the editor password to unlock editing.';
        }
        return '';
    }

    function setAuthStatus(message, success = false) {
        if (!editAuthStatus) {
            return;
        }
        editAuthStatus.textContent = message || '';
        editAuthStatus.classList.toggle('edit-status-success', !!success);
    }

    function syncAuthDisclosure(forceVisible = false, status = null) {
        if (!editAuthDetails) {
            return;
        }
        const shouldShow = forceVisible || authFormVisible;
        editAuthDetails.open = shouldShow;
        if (editAuthForm) {
            editAuthForm.hidden = !shouldShow;
        }
        if (editAuthSubmit) {
            editAuthSubmit.hidden = !!authState.authenticated;
            editAuthSubmit.textContent = 'Unlock';
        }
        if (editAuthPassword) {
            editAuthPassword.hidden = !!authState.authenticated;
        }
        if (!shouldShow && !authState.authenticated) {
            setAuthStatus('');
            return;
        }
        const message = authStatusMessage(status);
        setAuthStatus(message, false);
    }

    annotateConfigPath(folderConfig, getActiveSelection(), { target: 'folder' });
    annotateConfigPath(editConfig, getActiveSelection(), { target: editTarget });

    function renderFolderMeta() {
        return folderConfig;
    }

    function syncEditToggle() {
        if (!editToggle) {
            return;
        }
        const editActive = editRequested && authState.canEdit;
        editAuthDetails?.classList.toggle('edit-auth-details-authenticated', !!authState.authenticated);
        editToggle.textContent = authState.authenticated ? 'Disable edit mode' : 'Enable edit mode';
        editToggle.classList.toggle('edit-toggle-on', editActive);
        editToggle.setAttribute('aria-expanded', editAuthDetails?.open ? 'true' : 'false');
    }

    function bindEditToggle() {
        if (!editAuthDetails) {
            return;
        }
        editAuthDetails.addEventListener('toggle', () => {
            authFormVisible = !!editAuthDetails.open;
            syncEditToggle();
            syncAuthDisclosure(editAuthDetails.open);
            if (editAuthDetails.open && !authState.authenticated && editAuthPassword) {
                editAuthPassword.focus();
            }
        });
        editToggle?.addEventListener('click', async (event) => {
            if (!authState.authenticated) {
                return;
            }
            event.preventDefault();
            const selection = getActiveSelection();
            const data = await requestEditAuth({
                path: getEditTargetPath(selection),
                intent: 'logout',
            });
            if (data?.auth) {
                updateAuthState(data.auth);
            }
            authFormVisible = false;
            syncAuthDisclosure(false, data);
            const url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.location.href = url.toString();
        });
    }

    function bindAddWorkButton() {
        if (!editAddWork) {
            return;
        }
        editAddWork.addEventListener('click', () => {
            const openUploadDialogButton = document.getElementById('editOpenUploadDialog');
            if (openUploadDialogButton instanceof HTMLButtonElement) {
                openUploadDialogButton.click();
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('edit', 'true');
            window.location.href = url.toString();
        });
    }

    function bindAuthForm() {
        if (editAuthForm) {
            editAuthForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const selection = getActiveSelection();
                const data = await requestEditAuth({
                    path: getEditTargetPath(selection),
                    intent: 'login',
                    password: editAuthPassword?.value || '',
                });
                if (data?.auth) {
                    updateAuthState(data.auth);
                }
                if (!data || data.allowed === false || !data.auth?.authenticated) {
                    syncEditToggle();
                    syncAuthDisclosure(true, data);
                    return;
                }
                authFormVisible = false;
                setAuthStatus('Edit mode unlocked.', true);
                syncAuthDisclosure(false, data);
                const url = new URL(window.location.href);
                url.searchParams.set('edit', 'true');
                window.location.href = url.toString();
            });
        }

    }

    async function saveConfig(payload, statusEl) {
        try {
            setStatusMessage(statusEl, 'Saving...');
            const data = await requestEditConfig('save', payload);
            if (!data || data.error) {
                throw new Error(data?.error || 'Save failed.');
            }
            editConfig = annotateConfigPath(data.config || editConfig, getActiveSelection(), data);
            editTarget = data.target || editTarget;
            if (editTarget === 'folder' || (editTarget === 'layout' && data.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
            setStatusMessage(statusEl, 'Config saved.', true);
            window.dispatchEvent(new CustomEvent('poff:content-updated', {
                detail: {
                    path: data.routePath || payload?.path || '',
                    slug: data.routeSlug || data.config?.slug || '',
                    routePath: data.routePath || payload?.path || '',
                    routeSlug: data.routeSlug || data.config?.slug || '',
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
        if (!editRequested || !authState.canEdit || !drawerOpen) {
            editDrawer.classList.remove('edit-drawer-open');
            editDrawer.hidden = true;
            return;
        }
        editDrawer.hidden = false;
        editDrawer.classList.add('edit-drawer-open');
    }

    async function refreshCurrentEditState(selection = getActiveSelection()) {
        const refreshed = await requestEditConfig('config', { path: getEditTargetPath(selection) });
        if (refreshed?.auth) {
            updateAuthState(refreshed.auth);
        }
        if (refreshed?.config) {
            editConfig = annotateConfigPath(refreshed.config, selection, refreshed);
            editTarget = refreshed.target || (selection.isLayout ? 'layout' : (selection.previewIsFile ? 'file' : 'folder'));
            if (editTarget === 'folder' || (editTarget === 'layout' && refreshed.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
        }
        renderEditUI(editConfig, {
            allowed: refreshed?.allowed !== false,
            error: refreshed?.error,
            target: refreshed?.target || editTarget,
            subjectTarget: refreshed?.subjectTarget,
            uploadLimits: refreshed?.uploadLimits,
            auth: refreshed?.auth || authState,
        });
    }

    function renderEditUI(config, status) {
        syncEditToggle();
        syncAuthDisclosure(false, status);
        const layoutNameForPreset = createLayoutNameForPreset(editConfig);
        const panelState = renderEditPanel({
            editPanel,
            editRequested: editRequested && authState.canEdit,
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
            onMediaInput: (mediaState) => {
                if (!editConfig || !mediaState || typeof mediaState !== 'object') {
                    return;
                }
                const currentWork = (editConfig.work && typeof editConfig.work === 'object') ? editConfig.work : {};
                editConfig.work = materializeWorkFields({ ...currentWork, ...mediaState });
            },
            onSubmit: async ({ elements, statusEl }) => {
                const selection = getActiveSelection();
                const currentWork = (editConfig?.work && typeof editConfig.work === 'object') ? editConfig.work : {};
                const form = elements?.form || null;
                const mediaWork = readMediaConfigFromElements(elements, form, currentWork);
                const payload = {
                    path: getEditTargetPath(selection),
                    title: (elements.title?.value || '').trim(),
                    description: (elements.description?.value || '').trim(),
                };
                if (editConfig?.work && typeof editConfig.work === 'object') {
                    payload.work = materializeWorkFields(mediaWork);
                }
                await saveConfig(payload, statusEl);
            },
            onToggleDrawer: () => {
                drawerOpen = !drawerOpen;
                syncDrawerVisibility();
            },
            onOpenLayoutPage: () => {
                const selection = getActiveSelection();
                const previewPath = selection.previewPath ?? selection.path ?? '';
                const layoutRootPath = selection.previewIsFile
                    ? previewPath.split('/').slice(0, -1).join('/')
                    : previewPath;
                const nextPath = buildVirtualLayoutPath(layoutRootPath);
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
            onCreateLink: async ({ source, linkName, linkUrl }) => {
                const selection = getActiveSelection();
                const data = await requestEditUpload({
                    path: getContentTargetPath(selection),
                    source,
                    fileName: linkName,
                    linkUrl,
                    files: [],
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Create link failed.');
                }
                await refreshCurrentEditState(selection);
                const inlineStatus = document.getElementById('editInlineStatus');
                if (inlineStatus) {
                    const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                        ? data.uploaded[0].name
                        : (linkName || linkUrl);
                    setStatusMessage(inlineStatus, `Created link ${createdName}.`, true);
                }
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            },
            onChangePassword: async ({ elements, form, statusEl }) => {
                try {
                    setStatusMessage(statusEl, 'Changing password...');
                    const selection = getActiveSelection();
                    const data = await requestEditAuth({
                        path: getEditTargetPath(selection),
                        intent: 'change-password',
                        currentPassword: (elements.currentPassword?.value || '').trim(),
                        newPassword: (elements.newPassword?.value || '').trim(),
                        confirmPassword: (elements.confirmPassword?.value || '').trim(),
                    });
                    if (data?.auth) {
                        updateAuthState(data.auth);
                    }
                    if (!data || data.allowed === false || data.changed !== true) {
                        throw new Error(data?.error || 'Password change failed.');
                    }
                    if (form && typeof form.reset === 'function') {
                        form.reset();
                    }
                    setStatusMessage(statusEl, 'Password changed.', true);
                } catch (err) {
                    setStatusMessage(statusEl, err.message || 'Password change failed.');
                }
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
            onSubmit: async ({ elements, drawerForm, statusEl, treeVisible }) => {
                const selection = getActiveSelection();
                const templateField = elements.work_template || elements.work_type;
                const selectedTemplateOption = templateField?.selectedOptions && templateField.selectedOptions[0]
                    ? templateField.selectedOptions[0]
                    : null;
                const selectedTemplate = (templateField?.value || '').trim();
                const selectedKind = (selectedTemplateOption?.dataset?.kind || selectedTemplate || '').trim();
                const templateMap = {};
                if (drawerForm) {
                    drawerForm.querySelectorAll('select[data-template-map-mime]').forEach((select) => {
                        const mime = String(select.dataset.templateMapMime || '').trim();
                        if (!mime) {
                            return;
                        }
                        const selectedValue = String(select.value || '').trim();
                        const baselineValue = String(select.dataset.templateMapSelected || '').trim();
                        if (selectedValue === baselineValue) {
                            return;
                        }
                        templateMap[mime] = selectedValue;
                    });
                }
                const payload = {
                    path: getEditTargetPath(selection),
                    link: (elements.link?.value || '').trim(),
                    url: (elements.url?.value || '').trim(),
                    work: {
                        type: selectedKind,
                        template: selectedTemplate,
                    },
                };
                if (Object.keys(templateMap).length > 0) {
                    payload.work.templateMap = templateMap;
                }
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
        const authResponse = await requestEditAuth({ method: 'GET', path: getEditTargetPath(selection) });
        if (authResponse?.auth) {
            updateAuthState(authResponse.auth);
        }
        if (!authResponse?.auth?.canEdit) {
            renderEditUI(editConfig, {
                allowed: false,
                error: authResponse?.error,
                target: editTarget,
                auth: authResponse?.auth || authState,
            });
            authFormVisible = true;
            syncAuthDisclosure(true, authResponse);
            return;
        }
        const data = await requestEditConfig('config', { path: getEditTargetPath(selection) });
        if (data?.auth) {
            updateAuthState(data.auth);
        }
        if (data.config) {
            editConfig = annotateConfigPath(data.config, selection, data);
            editTarget = data.target || (selection.isFile ? 'file' : 'folder');
            if (editTarget === 'folder' || (editTarget === 'layout' && data.subjectTarget === 'folder')) {
                folderConfig = editConfig;
                renderFolderMeta();
            }
        }
        renderEditUI(editConfig, {
            allowed: data.allowed !== false,
            error: data.error,
            target: editTarget,
            subjectTarget: data.subjectTarget,
            uploadLimits: data.uploadLimits,
            auth: data.auth || authState,
        });
    }

    return {
        renderFolderMeta,
        syncEditToggle,
        bindEditToggle,
        bindAddWorkButton,
        bindAuthForm,
        initEditMode,
    };
}
