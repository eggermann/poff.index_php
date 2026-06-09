import { requestEditAuth, requestEditDelete, requestEditReset, requestEditUpload, requestMcpRoute } from '../../api/edit.js';
import { buildConverterSaveAs, resolveConverterPayloadProvider, readMediaConfigFromElements } from './media-config.js';
import { materializeWorkFields } from '../work-fields.js';
import { setStatusMessage } from '../status.js';

function activeWorkTitle(config, fallbackPath = '') {
    const title = String(config?.title || config?.name || config?.folderName || '').trim();
    if (title) {
        return title;
    }
    const lastPathPart = String(fallbackPath || '').split('/').filter(Boolean).pop() || '';
    return lastPathPart || 'work';
}

function formatStoredNames(uploaded = []) {
    if (!Array.isArray(uploaded) || uploaded.length === 0) {
        return '';
    }
    const names = uploaded
        .map((entry) => String(entry?.name || '').trim())
        .filter(Boolean);
    if (names.length === 0) {
        return '';
    }
    if (names.length === 1) {
        return names[0];
    }
    if (names.length === 2) {
        return `${names[0]} and ${names[1]}`;
    }
    return `${names.slice(0, -1).join(', ')}, and ${names[names.length - 1]}`;
}

export function createPanelActions({
    getSelection,
    getEditTargetPath,
    getContentTargetPath,
    getAddWorkTargetPath,
    getEditConfig,
    setEditConfig,
    getEditTarget,
    setEditTarget,
    getAuthState,
    updateAuthState,
    syncDrawerVisibility,
    setDrawerOpen,
    saveConfig,
    persistSelectedConverter,
    refreshCurrentEditState,
    buildLayoutPayload,
    layoutNameForPreset,
}) {
    function buildAddWorkRequestPayload(selection, extra = {}) {
        const contentTargetPath = getContentTargetPath(selection);
        const activePath = getEditTargetPath(selection);
        return {
            path: getAddWorkTargetPath(selection) || contentTargetPath,
            activeWorkPath: activePath,
            activeWorkName: activeWorkTitle(getEditConfig(), activePath),
            activeWorkIsFile: selection?.previewIsFile === true,
            ...extra,
        };
    }

    function describeAddWorkTarget(selection) {
        const addWorkTargetPath = getAddWorkTargetPath(selection);
        return addWorkTargetPath ? ` in ${addWorkTargetPath}` : '';
    }

    function describeUploadDestination(data, selection) {
        const workFolderName = String(data?.workFolderName || '').trim();
        if (workFolderName) {
            const parentTarget = getAddWorkTargetPath(selection);
            return ` in ${parentTarget ? `${parentTarget}/` : ''}${workFolderName}`;
        }
        return describeAddWorkTarget(selection);
    }

    return {
        onTitleInput: (value, status) => {
            const editConfig = getEditConfig();
            if (!editConfig) {
                return;
            }
            editConfig.title = value;
            if (status?.target !== 'file') {
                setEditConfig(editConfig);
            }
        },
        onDescriptionInput: (value, status) => {
            const editConfig = getEditConfig();
            if (!editConfig) {
                return;
            }
            editConfig.description = value;
            if (status?.target !== 'file') {
                setEditConfig(editConfig);
            }
        },
        onWorkFieldsInput: (fields, status) => {
            const editConfig = getEditConfig();
            if (!editConfig) {
                return;
            }
            const currentWork = (editConfig.work && typeof editConfig.work === 'object') ? editConfig.work : {};
            editConfig.work = materializeWorkFields(currentWork, fields);
            if (status?.target !== 'file') {
                setEditConfig(editConfig);
            }
        },
        onMediaInput: (mediaState) => {
            const editConfig = getEditConfig();
            if (!editConfig || !mediaState || typeof mediaState !== 'object') {
                return;
            }
            const currentWork = (editConfig.work && typeof editConfig.work === 'object') ? editConfig.work : {};
            editConfig.work = materializeWorkFields({ ...currentWork, ...mediaState });
            setEditConfig(editConfig);
        },
        onSubmit: async ({ elements, statusEl }) => {
            const selection = getSelection();
            const editConfig = getEditConfig();
            const currentWork = (editConfig?.work && typeof editConfig.work === 'object') ? editConfig.work : {};
            const form = elements?.form || null;
            const mediaWork = readMediaConfigFromElements(elements, form, currentWork);
            const payload = {
                path: getEditTargetPath(selection),
                title: (elements.title?.value || '').trim(),
                description: (elements.description?.value || '').trim(),
            };
            if (mediaWork && typeof mediaWork === 'object' && Object.keys(mediaWork).length > 0) {
                payload.work = materializeWorkFields(mediaWork);
            }
            await saveConfig(payload, statusEl);
        },
        onToggleDrawer: () => {
            setDrawerOpen((open) => !open);
            syncDrawerVisibility();
        },
        onOpenLayoutPage: (buildVirtualLayoutPath) => {
            const selection = getSelection();
            const previewPath = selection.previewPath ?? selection.path ?? '';
            const layoutRootPath = selection.previewIsFile
                ? previewPath.split('/').slice(0, -1).join('/')
                : previewPath;
            const nextPath = buildVirtualLayoutPath(layoutRootPath);
            setDrawerOpen(false);
            syncDrawerVisibility();
            window.location.hash = `#/${nextPath}`;
        },
        onDeleteTarget: async ({ statusEl }) => {
            const selection = getSelection();
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
            setDrawerOpen(false);
            syncDrawerVisibility();
            setStatusMessage(statusEl, 'Deleted.', true);
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
            const nextPath = selection.previewPath ? selection.previewPath.split('/').slice(0, -1).join('/') : '';
            window.location.hash = nextPath ? `#/${nextPath}` : '';
            await refreshCurrentEditState(getSelection());
        },
        onConvertWork: async ({ statusEl, converter }) => {
            const selection = getSelection();
            const sourcePath = getEditTargetPath(selection);
            if (!sourcePath) {
                throw new Error('Convert target unavailable.');
            }
            if (!converter?.id) {
                setStatusMessage(statusEl, 'Choose a converter first.');
                return;
            }
            setStatusMessage(statusEl, 'Converting...');
            const currentConfig = getEditConfig() && typeof getEditConfig() === 'object' ? getEditConfig() : {};
            const sourceName = sourcePath.split('/').pop() || sourcePath;
            const payload = {
                source: {
                    name: sourceName,
                    path: sourcePath,
                    mimeType: currentConfig.mimeType || '',
                    extension: sourceName.includes('.') ? sourceName.split('.').pop() : '',
                    size: currentConfig.size || 0,
                    srcUrl: currentConfig.srcUrl || '',
                },
                converter,
                target: {
                    folder: sourcePath.split('/').slice(0, -1).join('/'),
                    saveAs: buildConverterSaveAs(sourceName, converter.format || 'source'),
                    mode: converter.saveMode || 'new-hidden-work',
                },
            };
            const payloadProvider = resolveConverterPayloadProvider();
            if (typeof payloadProvider === 'function') {
                const extraPayload = await Promise.resolve(payloadProvider({
                    sourcePath,
                    sourceName,
                    converter: { ...converter },
                    target: { ...payload.target },
                }));
                if (extraPayload && typeof extraPayload === 'object') {
                    if (extraPayload.source && typeof extraPayload.source === 'object') {
                        payload.source = { ...payload.source, ...extraPayload.source };
                    }
                    if (extraPayload.converter && typeof extraPayload.converter === 'object') {
                        payload.converter = { ...payload.converter, ...extraPayload.converter };
                    }
                    if (extraPayload.target && typeof extraPayload.target === 'object') {
                        payload.target = { ...payload.target, ...extraPayload.target };
                    }
                    if (extraPayload.editor && typeof extraPayload.editor === 'object') {
                        payload.editor = { ...extraPayload.editor };
                    }
                }
            }
            const converted = await requestMcpRoute('convert', payload);
            if (!converted || converted.error || converted.ok === false) {
                throw new Error(converted?.error || 'Conversion failed.');
            }
            const saveMode = converter.saveMode || 'new-hidden-work';
            if (saveMode !== 'temporary-preview-only') {
                const saved = await requestMcpRoute('save-converted-work', {
                    sourcePath,
                    conversion: {
                        ...converted,
                        target: { ...(converted?.target && typeof converted.target === 'object' ? converted.target : {}), ...payload.target },
                    },
                });
                if (!saved || saved.error || saved.ok === false) {
                    throw new Error(saved?.error || 'Saving converted work failed.');
                }
                await persistSelectedConverter(selection, converter, statusEl);
                if (saveMode === 'replace-source-file' || saved.type === 'saved-source-file') {
                    window.alert('OK — the edited preview content was saved back into the source file.');
                    await refreshCurrentEditState(selection, { forcePreviewRefresh: true });
                    return;
                }
            }
            window.alert('OK — this file was converted into a web-readable poff work and saved in the folder.');
            await refreshCurrentEditState(selection);
        },
        onCreateConverter: async ({ statusEl, form }) => {
            const selection = getSelection();
            const sourcePath = getEditTargetPath(selection);
            const editConfig = getEditConfig();
            const currentWork = (editConfig?.work && typeof editConfig.work === 'object') ? editConfig.work : {};
            const suggestedBase = String(currentWork.type || editConfig?.kind || 'image').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'converter';
            const suggestedName = suggestedBase.startsWith('convert-') ? suggestedBase : `convert-${suggestedBase}`;
            const requestedName = window.prompt('New converter folder name', suggestedName);
            if (requestedName === null) {
                return;
            }
            const name = requestedName.trim();
            if (!name) {
                setStatusMessage(statusEl, 'Converter creation cancelled.');
                return;
            }
            setStatusMessage(statusEl, 'Creating converter...');
            const created = await requestMcpRoute('create-converter', {
                path: sourcePath,
                name,
            });
            if (!created || created.ok === false || created.error) {
                throw new Error(created?.error || 'Creating converter failed.');
            }
            const definition = created.definition && typeof created.definition === 'object' ? created.definition : null;
            const nextPath = String(definition?.path || created.folder || '').trim().replace(/^\/+|\/+$/g, '');
            if (nextPath) {
                const nextHash = `#/${nextPath}`;
                if (window.location.hash === nextHash) {
                    window.dispatchEvent(new Event('hashchange'));
                } else {
                    window.location.hash = nextHash;
                }
                return;
            }
            await refreshCurrentEditState(selection);
            const refreshedForm = document.getElementById('inlineEditForm') || form;
            const refreshedSelect = refreshedForm?.elements?.converter_id instanceof HTMLSelectElement
                ? refreshedForm.elements.converter_id
                : null;
            if (definition && refreshedSelect) {
                refreshedSelect.value = String(definition.id || '');
                refreshedSelect.dispatchEvent(new Event('input', { bubbles: true }));
                refreshedSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            setStatusMessage(
                document.getElementById('editInlineStatus') || statusEl,
                `Created converter ${definition?.label || definition?.name || name} at ${created.folder || ''}. Select it to continue.`,
                true
            );
        },
        onResetFolderWork: async ({ statusEl }) => {
            const selection = getSelection();
            const targetPath = getContentTargetPath(selection);
            if (targetPath === null || typeof targetPath === 'undefined') {
                throw new Error('Reset target unavailable.');
            }
            const data = await requestEditReset({
                path: targetPath,
                return: selection.previewPath || selection.path || '',
            });
            if (!data || data.error) {
                throw new Error(data?.error || 'Reset failed.');
            }
            setDrawerOpen(false);
            syncDrawerVisibility();
            setStatusMessage(statusEl, 'Folder work reset to default.', true);
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
            await refreshCurrentEditState(getSelection());
        },
        onReturnToWork: () => {
            const selection = getSelection();
            const nextPath = selection.previewPath || '';
            setDrawerOpen(false);
            syncDrawerVisibility();
            if (nextPath) {
                window.location.hash = `#/${nextPath}`;
                return;
            }
            window.history.replaceState(null, '', window.location.pathname + window.location.search);
            window.dispatchEvent(new Event('hashchange'));
        },
        onSubmitLayout: async ({ payload, statusEl }) => {
            const selection = getSelection();
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
            const selection = getSelection();
            await saveConfig({
                path: getEditTargetPath(selection),
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
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
            await refreshCurrentEditState(selection, { forcePreviewRefresh: true });
        },
        onUploadFiles: async ({ source, files, saveTarget, closeDialog }) => {
            const selection = getSelection();
            const data = await requestEditUpload(buildAddWorkRequestPayload(selection, {
                source,
                files,
                saveTarget,
            }));
            if (!data || data.error) {
                throw new Error(data?.error || 'Upload failed.');
            }
            if (typeof closeDialog === 'function') {
                closeDialog();
            }
            await refreshCurrentEditState(selection);
            const inlineStatus = document.getElementById('editInlineStatus');
            if (inlineStatus) {
                const uploaded = Array.isArray(data.uploaded) ? data.uploaded : [];
                const names = formatStoredNames(uploaded);
                const targetLabel = describeUploadDestination(data, selection);
                const fallbackCount = uploaded.length || files.length;
                const message = names
                    ? `Uploaded ${names}${targetLabel}.`
                    : (fallbackCount === 1 ? `Uploaded 1 file${targetLabel}.` : `Uploaded ${fallbackCount} files${targetLabel}.`);
                setStatusMessage(inlineStatus, message, true);
            }
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
        },
        onCreateBlankFile: async ({ source, fileName, saveTarget, closeDialog }) => {
            const selection = getSelection();
            const data = await requestEditUpload(buildAddWorkRequestPayload(selection, {
                source,
                fileName,
                saveTarget,
                files: [],
            }));
            if (!data || data.error) {
                throw new Error(data?.error || 'Create blank file failed.');
            }
            if (typeof closeDialog === 'function') {
                closeDialog();
            }
            await refreshCurrentEditState(selection);
            const inlineStatus = document.getElementById('editInlineStatus');
            if (inlineStatus) {
                const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                    ? data.uploaded[0].name
                    : fileName;
                setStatusMessage(inlineStatus, `Created ${createdName}${describeUploadDestination(data, selection)}.`, true);
            }
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
        },
        onCreateFolder: async ({ source, folderName, saveTarget, closeDialog }) => {
            const selection = getSelection();
            const data = await requestEditUpload(buildAddWorkRequestPayload(selection, {
                source,
                fileName: folderName,
                saveTarget,
                files: [],
            }));
            if (!data || data.error) {
                throw new Error(data?.error || 'Create folder failed.');
            }
            if (typeof closeDialog === 'function') {
                closeDialog();
            }
            await refreshCurrentEditState(selection);
            const inlineStatus = document.getElementById('editInlineStatus');
            if (inlineStatus) {
                const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                    ? data.uploaded[0].name
                    : folderName;
                setStatusMessage(inlineStatus, `Created folder ${createdName}${describeUploadDestination(data, selection)}.`, true);
            }
            window.dispatchEvent(new CustomEvent('poff:content-updated'));
        },
        onCreateLink: async ({ source, linkName, linkUrl, saveTarget, closeDialog }) => {
            const selection = getSelection();
            const data = await requestEditUpload(buildAddWorkRequestPayload(selection, {
                source,
                linkName,
                fileName: linkName,
                linkUrl,
                saveTarget,
                files: [],
            }));
            if (data?.auth) {
                updateAuthState(data.auth);
            }
            if (!data || data.error) {
                throw new Error(data?.error || 'Create link failed.');
            }
            if (typeof closeDialog === 'function') {
                closeDialog();
            }
            const inlineStatus = document.getElementById('editInlineStatus');
            if (inlineStatus) {
                const createdName = Array.isArray(data.uploaded) && data.uploaded[0]?.name
                    ? data.uploaded[0].name
                    : (linkName || linkUrl);
                const message = data.pendingApproval
                    ? `Submitted ${createdName} for approval${describeAddWorkTarget(selection)}.`
                    : `Created link ${createdName}${describeUploadDestination(data, selection)}.`;
                setStatusMessage(inlineStatus, message, true);
            }
            if (!(data.pendingApproval && !getAuthState().canEdit)) {
                await refreshCurrentEditState(selection);
                window.dispatchEvent(new CustomEvent('poff:content-updated'));
            }
        },
        onChangePassword: async ({ elements, form, statusEl }) => {
            try {
                setStatusMessage(statusEl, 'Changing password...');
                const selection = getSelection();
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
    };
}
