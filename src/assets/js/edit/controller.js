import { requestEditAuth, requestEditConfig, requestEditDelete, requestEditUpload, requestPromptTemplate } from '../api/edit.js';
import { buildVirtualLayoutPath, getActiveSelection } from '../core/selection.js';
import { registerEscapeClose, registerEscapeCloseDetails } from '../core/escape-stack.js';
import { bindPromptWindow } from './prompt.js';
import { renderEditDrawer } from './drawer.js';
import { renderEditPanel } from './panel.js';
import { uploadValidationError } from './panel/shared.js';
import { materializeWorkFields } from './work-fields.js';
import { buildLayoutPayload, createLayoutNameForPreset } from './controller/layout.js';
import { getAddWorkTargetPath, getContentTargetPath, getEditTargetPath } from './controller/paths.js';
import { createPanelActions } from './controller/panel-actions.js';
import { setStatusMessage } from './status.js';

export function createEditController({ elements, context, editRequested }) {
    const {
        editPanel,
        editDrawer,
        editActionsMenu,
        editAuthDetails,
        editToggle,
        editAddWork,
        editJumpToSection,
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
    let uploadLimits = null;
    let drawerOpen = false;
    let authFormVisible = false;
    let unregisterDrawerEscapeClose = null;
    let authState = {
        configured: !!initialAuthState.configured,
        authenticated: !!initialAuthState.authenticated,
        editModeAllowed: initialAuthState.editModeAllowed !== false,
        canEdit: !!initialAuthState.canEdit,
    };
    let previewRefreshHandler = null;
    let activeConverterPreview = null;

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

    function previewStateKey(previewState) {
        if (!previewState || typeof previewState !== 'object') {
            return '';
        }
        return JSON.stringify({
            path: previewState.path || '',
            isFile: !!previewState.isFile,
            params: previewState.params || {},
        });
    }

    function buildConverterPreviewState(workState, selection = getActiveSelection()) {
        if (!selection?.previewIsFile) {
            return null;
        }
        const sourcePath = getEditTargetPath(selection);
        const converter = workState?.converter && typeof workState.converter === 'object'
            ? workState.converter
            : null;
        const converterId = String(converter?.id || '').trim();
        if (!sourcePath || !converterId) {
            return null;
        }
        return {
            path: selection.previewPath || sourcePath,
            isFile: true,
            params: {
                converter_preview: '1',
                converter_id: converterId,
                converter_path: String(converter.path || '').trim(),
                converter_url: String(converter.url || '').trim(),
                converter_format: String(converter.format || '').trim() || 'source',
                converter_quality: String(converter.quality || 'default').trim() || 'default',
                converter_save_mode: String(converter.saveMode || 'new-hidden-work').trim() || 'new-hidden-work',
            },
        };
    }

    function updateConverterPreviewState(nextState, { refresh = true, force = false } = {}) {
        const previousKey = previewStateKey(activeConverterPreview);
        const nextKey = previewStateKey(nextState);
        activeConverterPreview = nextState;
        if (refresh && (force || previousKey !== nextKey) && typeof previewRefreshHandler === 'function') {
            previewRefreshHandler();
        }
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

    function activeWorkTitle(config, fallbackPath = '') {
        const title = String(config?.title || config?.name || config?.folderName || '').trim();
        if (title) {
            return title;
        }
        const lastPathPart = String(fallbackPath || '').split('/').filter(Boolean).pop() || '';
        return lastPathPart || 'work';
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
        registerEscapeCloseDetails(editAuthDetails, {
            label: 'edit-auth-details',
        });
        if (editActionsMenu) {
            registerEscapeCloseDetails(editActionsMenu, {
                label: 'edit-actions-menu',
            });
        }
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

            if (editRequested) {
                let attempts = 0;
                const maxAttempts = 24;
                const openWhenReady = () => {
                    const delayedButton = document.getElementById('editOpenUploadDialog');
                    if (delayedButton instanceof HTMLButtonElement) {
                        delayedButton.click();
                        return;
                    }
                    attempts += 1;
                    if (attempts >= maxAttempts) {
                        return;
                    }
                    window.requestAnimationFrame(openWhenReady);
                };
                window.requestAnimationFrame(openWhenReady);
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('edit', 'true');
            url.searchParams.set('add', authState.canEdit ? 'work' : 'url');
            window.location.href = url.toString();
        });
    }

    function bindJumpToSectionButton() {
        if (!editJumpToSection) {
            return;
        }

        const resolveScrollTarget = () => ((editDrawer && !editDrawer.hidden)
            ? editDrawer
            : ((editPanel && !editPanel.hidden) ? editPanel : null));
        const resolveToolsTarget = () => editActionsMenu || editJumpToSection;
        let lastJumpTarget = 'tools';

        const syncJumpLabel = () => {
            const title = lastJumpTarget === 'edit'
                ? 'Jump to editor tools'
                : 'Jump to edit section';
            editJumpToSection.setAttribute('aria-label', title);
            editJumpToSection.setAttribute('title', title);
        };

        editJumpToSection.addEventListener('click', () => {
            const editTarget = resolveScrollTarget();
            const toolsTarget = resolveToolsTarget();

            if (lastJumpTarget === 'edit' && toolsTarget && typeof toolsTarget.scrollIntoView === 'function') {
                toolsTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                lastJumpTarget = 'tools';
                syncJumpLabel();
                return;
            }

            if (editTarget && typeof editTarget.scrollIntoView === 'function') {
                editTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
                const focusTarget = editTarget.querySelector?.('input, textarea, select, button');
                if (focusTarget && typeof focusTarget.focus === 'function') {
                    focusTarget.focus({ preventScroll: true });
                }
                lastJumpTarget = 'edit';
                syncJumpLabel();
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('edit', 'true');
            window.location.href = url.toString() + '#editPanel';
        });

        syncJumpLabel();
    }

    function bindSidebarFileDrop(sidebarEl) {
        if (!sidebarEl || !editRequested) {
            return null;
        }

        const dropZone = document.createElement('div');
        dropZone.className = 'sidebar-file-drop-zone';
        dropZone.setAttribute('aria-hidden', 'true');
        dropZone.innerHTML = '<div class="sidebar-file-drop-zone__inner"><strong>Drop files</strong><span>Add to current folder</span></div>';
        sidebarEl.appendChild(dropZone);

        let dragDepth = 0;
        let uploading = false;

        const syncDropZoneBounds = () => {
            const rect = sidebarEl.getBoundingClientRect();
            dropZone.style.left = `${Math.round(rect.left)}px`;
            dropZone.style.top = `${Math.round(rect.top)}px`;
            dropZone.style.width = `${Math.round(rect.width)}px`;
            dropZone.style.height = `${Math.round(rect.height)}px`;
        };

        const clearDropZoneBounds = () => {
            dropZone.style.left = '';
            dropZone.style.top = '';
            dropZone.style.width = '';
            dropZone.style.height = '';
        };

        const hasDraggedFiles = (event) => {
            const types = Array.from(event.dataTransfer?.types || []);
            return types.includes('Files');
        };

        const setDropActive = (active) => {
            if (active) {
                syncDropZoneBounds();
            }
            sidebarEl.classList.toggle('sidebar-file-drop-active', !!active);
        };

        const setDropBusy = (busy) => {
            uploading = !!busy;
            sidebarEl.classList.toggle('sidebar-file-drop-busy', uploading);
        };

        const setDropStatus = (message, success = false) => {
            const inlineStatus = document.getElementById('editInlineStatus') || editAuthStatus;
            if (inlineStatus) {
                setStatusMessage(inlineStatus, message, success);
            }
        };

        const onDragEnter = (event) => {
            if (uploading || !hasDraggedFiles(event)) {
                return;
            }
            event.preventDefault();
            dragDepth += 1;
            setDropActive(true);
        };

        const onDragOver = (event) => {
            if (uploading || !hasDraggedFiles(event)) {
                return;
            }
            event.preventDefault();
            syncDropZoneBounds();
            event.dataTransfer.dropEffect = authState.canEdit ? 'copy' : 'none';
            setDropActive(true);
        };

        const onDragLeave = () => {
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) {
                setDropActive(false);
            }
        };

        const onDrop = async (event) => {
            if (!hasDraggedFiles(event)) {
                return;
            }
            event.preventDefault();
            dragDepth = 0;
            setDropActive(false);
            clearDropZoneBounds();

            const files = Array.from(event.dataTransfer?.files || []);
            if (!authState.canEdit) {
                setDropStatus('Unlock edit mode before uploading files.');
                return;
            }
            if (files.length === 0) {
                setDropStatus('Drop at least one file.');
                return;
            }

            const validationError = uploadValidationError(files, uploadLimits);
            if (validationError) {
                setDropStatus(validationError);
                return;
            }

            const selection = getActiveSelection();
            try {
                setDropBusy(true);
                setDropStatus('Uploading...');
                const data = await requestEditUpload({
                    path: getContentTargetPath(selection),
                    source: 'upload',
                    files,
                });
                if (!data || data.error) {
                    throw new Error(data?.error || 'Upload failed.');
                }
                await refreshCurrentEditState(selection);
                const count = Array.isArray(data.uploaded) ? data.uploaded.length : files.length;
                setDropStatus(count === 1 ? 'Uploaded 1 file.' : `Uploaded ${count} files.`, true);
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            } catch (err) {
                setDropStatus(err.message || 'Upload failed.');
            } finally {
                setDropBusy(false);
            }
        };

        sidebarEl.addEventListener('dragenter', onDragEnter);
        sidebarEl.addEventListener('dragover', onDragOver);
        sidebarEl.addEventListener('dragleave', onDragLeave);
        sidebarEl.addEventListener('drop', onDrop);
        window.addEventListener('resize', syncDropZoneBounds);

        return {
            destroy() {
                sidebarEl.removeEventListener('dragenter', onDragEnter);
                sidebarEl.removeEventListener('dragover', onDragOver);
                sidebarEl.removeEventListener('dragleave', onDragLeave);
                sidebarEl.removeEventListener('drop', onDrop);
                window.removeEventListener('resize', syncDropZoneBounds);
                clearDropZoneBounds();
                dropZone.remove();
            },
        };
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

    async function persistSelectedConverter(selection, converter, statusEl) {
        if (!selection?.previewIsFile || !converter?.id || !editConfig || typeof editConfig !== 'object') {
            return;
        }
        const currentWork = (editConfig.work && typeof editConfig.work === 'object') ? editConfig.work : {};
        const nextWork = materializeWorkFields({
            ...currentWork,
            converter: {
                ...(currentWork.converter && typeof currentWork.converter === 'object' ? currentWork.converter : {}),
                ...converter,
            },
        });
        await saveConfig({
            path: getEditTargetPath(selection),
            title: String(editConfig.title || '').trim(),
            description: String(editConfig.description || '').trim(),
            work: nextWork,
        }, statusEl);
    }

    function syncDrawerVisibility() {
        if (!editDrawer) {
            return;
        }
        if (!editRequested || !authState.canEdit || !drawerOpen) {
            if (typeof unregisterDrawerEscapeClose === 'function') {
                unregisterDrawerEscapeClose();
                unregisterDrawerEscapeClose = null;
            }
            editDrawer.classList.remove('edit-drawer-open');
            editDrawer.hidden = true;
            return;
        }
        editDrawer.hidden = false;
        editDrawer.classList.add('edit-drawer-open');
        if (typeof unregisterDrawerEscapeClose !== 'function') {
            unregisterDrawerEscapeClose = registerEscapeClose(() => {
                drawerOpen = false;
                syncDrawerVisibility();
                return true;
            }, { label: 'edit-drawer' });
        }
    }

    async function refreshCurrentEditState(selection = getActiveSelection(), { forcePreviewRefresh = false } = {}) {
        const refreshed = await requestEditConfig('config', { path: getEditTargetPath(selection) });
        if (refreshed?.auth) {
            updateAuthState(refreshed.auth);
        }
        if (refreshed?.config) {
            editConfig = annotateConfigPath(refreshed.config, selection, refreshed);
            editTarget = refreshed.target || (selection.isLayout ? 'layout' : (selection.previewIsFile ? 'file' : 'folder'));
            updateConverterPreviewState(buildConverterPreviewState(editConfig?.work || {}, selection), { force: forcePreviewRefresh });
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
        uploadLimits = status?.uploadLimits || uploadLimits;
        const layoutNameForPreset = createLayoutNameForPreset(editConfig);
        const panelActions = createPanelActions({
            getSelection: getActiveSelection,
            getEditTargetPath,
            getContentTargetPath,
            getAddWorkTargetPath,
            getEditConfig: () => editConfig,
            setEditConfig: (nextConfig) => {
                editConfig = nextConfig;
                if (status?.target !== 'file') {
                    folderConfig = nextConfig;
                    renderFolderMeta();
                }
            },
            getEditTarget: () => editTarget,
            setEditTarget: (nextTarget) => {
                editTarget = nextTarget;
            },
            getAuthState: () => authState,
            updateAuthState,
            syncDrawerVisibility,
            setDrawerOpen: (nextValue) => {
                drawerOpen = typeof nextValue === 'function' ? nextValue(drawerOpen) : nextValue;
            },
            saveConfig,
            persistSelectedConverter,
            refreshCurrentEditState,
            buildLayoutPayload,
            layoutNameForPreset,
        });
        const panelState = renderEditPanel({
            editPanel,
            editRequested,
            config,
            status,
            contentTargetLabel: getContentTargetPath(),
            activeWorkName: activeWorkTitle(config, getEditTargetPath(getActiveSelection())),
            onTitleInput: (value) => panelActions.onTitleInput(value, status),
            onDescriptionInput: (value) => panelActions.onDescriptionInput(value, status),
            onWorkFieldsInput: (fields) => panelActions.onWorkFieldsInput(fields, status),
            onMediaInput: (mediaState) => {
                panelActions.onMediaInput(mediaState);
                updateConverterPreviewState(buildConverterPreviewState(editConfig?.work || {}));
            },
            onSubmit: panelActions.onSubmit,
            onToggleDrawer: panelActions.onToggleDrawer,
            onOpenLayoutPage: () => panelActions.onOpenLayoutPage(buildVirtualLayoutPath),
            onDeleteTarget: panelActions.onDeleteTarget,
            onConvertWork: panelActions.onConvertWork,
            onCreateConverter: panelActions.onCreateConverter,
            onResetFolderWork: panelActions.onResetFolderWork,
            onReturnToWork: panelActions.onReturnToWork,
            onSubmitLayout: panelActions.onSubmitLayout,
            onLayoutPresetChange: panelActions.onLayoutPresetChange,
            onUploadFiles: panelActions.onUploadFiles,
            onCreateBlankFile: panelActions.onCreateBlankFile,
            onCreateFolder: panelActions.onCreateFolder,
            onCreateLink: panelActions.onCreateLink,
            onChangePassword: panelActions.onChangePassword,
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
        updateConverterPreviewState(buildConverterPreviewState(config?.work || {}));

        window.POFF_REVIEW_PENDING_LINK = ({ path = '' } = {}) => {
            drawerOpen = true;
            syncDrawerVisibility();
            if (!path || typeof drawerState.focusTreeItem !== 'function') {
                return;
            }
            window.setTimeout(() => {
                drawerState.focusTreeItem(path);
            }, 30);
        };
        const reviewPath = new URL(window.location.href).searchParams.get('review') || '';
        if (reviewPath) {
            window.POFF_REVIEW_PENDING_LINK({ path: reviewPath });
            const url = new URL(window.location.href);
            url.searchParams.delete('review');
            window.history.replaceState(null, '', url.toString());
        }
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
            updateConverterPreviewState(buildConverterPreviewState(editConfig?.work || {}, selection));
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

    window.addEventListener('poff:review-external-link', (event) => {
        const reviewPath = event?.detail?.path || '';
        if (!editRequested || !authState.canEdit) {
            const url = new URL(window.location.href);
            url.searchParams.set('edit', 'true');
            url.searchParams.set('review', reviewPath);
            window.location.href = url.toString();
            return;
        }
        if (typeof window.POFF_REVIEW_PENDING_LINK === 'function') {
            window.POFF_REVIEW_PENDING_LINK({ path: reviewPath });
        }
    });

    return {
        renderFolderMeta,
        syncEditToggle,
        bindEditToggle,
        bindAddWorkButton,
        bindJumpToSectionButton,
        bindSidebarFileDrop,
        bindAuthForm,
        initEditMode,
        getPreviewParams({ path = '', isFile = false } = {}) {
            if (!activeConverterPreview || !isFile) {
                return null;
            }
            const targetPath = String(path || '').trim();
            if (targetPath === '' || targetPath !== String(activeConverterPreview.path || '').trim()) {
                return null;
            }
            return activeConverterPreview.params || null;
        },
        setPreviewRefreshHandler(handler) {
            previewRefreshHandler = typeof handler === 'function' ? handler : null;
        },
    };
}
