import { requestEditConfig, requestPromptTemplate } from '../api/edit.js';
import { getActiveSelection } from '../core/selection.js';
import { bindPromptWindow } from './prompt.js';
import { renderEditDrawer } from './drawer.js';
import { renderEditPanel } from './panel.js';

export function createEditController({ elements, context, editRequested }) {
    const { editPanel, editDrawer, editToggle, folderMetaEl } = elements;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, 'currentPoffConfig')
        ? context.currentPoffConfig
        : null;

    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = 'folder';
    let drawerOpen = false;

    function renderFolderMeta() {
        if (!folderMetaEl) {
            return;
        }
        if (folderConfig && (folderConfig.title || folderConfig.description)) {
            let html = '';
            if (folderConfig.title) {
                if (folderConfig.link || folderConfig.url) {
                    const lnk = folderConfig.link || folderConfig.url;
                    html += `<h3 class="folder-meta-title"><a class="folder-meta-link" href="${lnk}" target="contentFrame">${folderConfig.title}</a></h3>`;
                } else {
                    html += `<h3 class="folder-meta-title">${folderConfig.title}</h3>`;
                }
            }
            if (folderConfig.description) {
                html += `<p class="folder-meta-desc">${folderConfig.description}</p>`;
            }
            folderMetaEl.innerHTML = html;
            folderMetaEl.style.display = 'block';
        } else if (folderMetaEl) {
            folderMetaEl.innerHTML = '';
            folderMetaEl.style.display = 'none';
        }
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
            if (editTarget === 'folder') {
                folderConfig = editConfig;
                renderFolderMeta();
            }
            renderEditUI(editConfig, {
                allowed: data.allowed !== false,
                error: data.error,
                target: editTarget,
            });
            if (statusEl) {
                statusEl.textContent = 'Config saved.';
                statusEl.className = 'edit-status edit-status-success';
            }
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
                const layoutPayload = {
                    name: (elements.work_layout?.value || '').trim(),
                    engine: 'lightncandy',
                    template: elements.work_template?.value ?? '',
                };
                const selection = getActiveSelection();
                const payload = {
                    path: selection.path,
                    link: (elements.link?.value || '').trim(),
                    url: (elements.url?.value || '').trim(),
                    work: {
                        type: (elements.work_type?.value || '').trim(),
                    },
                    layout: layoutPayload,
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
            if (editTarget === 'folder') {
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
