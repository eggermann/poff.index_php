<?php
/**
 * JavaScript functionality for the file browser
 */
?>
<script>
if (window.location.hash === '#mcp') {
    const basePath = window.location.pathname.split('#')[0];
    window.location.href = `${basePath}?mcp=1`;
}

const navList       = document.getElementById('navList');
const contentFrame  = document.getElementById('contentFrame');
const folderMetaEl  = document.getElementById('folderMeta');
const editPanel     = document.getElementById('editPanel');
const editDrawer    = document.getElementById('editDrawer');
const editToggle    = document.getElementById('editToggle');
const iframeLoading = document.getElementById('iframeLoading');
let activeLink      = null;
const currentPoffConfig = <?php echo json_encode($folderPoffConfig); ?>;
let editableConfig  = currentPoffConfig;
const editRequested = new URLSearchParams(window.location.search).get('edit') === 'true';
const editQuery     = editRequested ? '&edit=true' : '';
let drawerOpen      = false;
let promptHistory   = [];
const promptSettingsKey = 'poffEditPromptSettings';

function renderFolderMeta() {
    if (editableConfig && (editableConfig.title || editableConfig.description)) {
        let html = '';
        // Build linked title if link/url present
        if (editableConfig.title) {
            if (editableConfig.link || editableConfig.url) {
                const lnk = editableConfig.link || editableConfig.url;
                html += `<h3><a href="${lnk}" target="contentFrame">${editableConfig.title}</a></h3>`;
            } else {
                html += `<h3>${editableConfig.title}</h3>`;
            }
        }
        if (editableConfig.description) {
            html += `<p>${editableConfig.description}</p>`;
        }
        folderMetaEl.innerHTML = html;
        folderMetaEl.style.display = 'block';
    } else {
        folderMetaEl.innerHTML = '';
        folderMetaEl.style.display = 'none';
    }
}

function syncEditToggle() {
    if (!editToggle) {
        return;
    }
    editToggle.textContent = editRequested ? 'Exit edit mode' : 'Enable edit mode';
    editToggle.classList.toggle('on', editRequested);
    editToggle.setAttribute('aria-pressed', editRequested ? 'true' : 'false');
}

function loadCurrentFolderInIframe() {
    const currentPathForIframe = <?php echo !empty($currentRelativePath) ? json_encode(rtrim($currentRelativePath, "\\/") . '/') : 'null'; ?>;
    if (currentPathForIframe) {
        contentFrame.src = currentPathForIframe;
        if (activeLink) {
            activeLink.classList.remove('active');
            activeLink = null;
        }
    }
    renderFolderMeta();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function getActivePath() {
    const rawHash = window.location.hash.replace(/^#\/?/, '');
    let hashPath = rawHash;
    if (rawHash) {
        try {
            hashPath = decodeURIComponent(rawHash);
        } catch (err) {
            hashPath = rawHash;
        }
    }
    if (hashPath) {
        const isFile = /\.[^\\/]+$/.test(hashPath);
        if (isFile) {
            return hashPath.split('/').slice(0, -1).join('/');
        }
        return hashPath;
    }
    const params = new URLSearchParams(window.location.search);
    return params.get('path') || '';
}

function buildCmsUrl(action, path) {
    const url = new URL(window.location.pathname, window.location.origin);
    url.searchParams.set('edit', action);
    if (path) {
        url.searchParams.set('path', path);
    }
    return url.toString();
}

function extractNavHtml(html) {
    if (!html) {
        return html;
    }
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const nav = doc.getElementById('navList');
        return nav ? nav.innerHTML : html;
    } catch (err) {
        return html;
    }
}

async function requestEditConfig(action, payload) {
    const url = buildCmsUrl(action, payload.path || '');
    try {
        const res = await fetch(url, {
            method: action === 'config' ? 'GET' : 'POST',
            headers: {
                'Accept': 'application/json',
                ...(action === 'config' ? {} : { 'Content-Type': 'application/json' }),
            },
            body: action === 'config' ? undefined : JSON.stringify(payload),
        });
        if (!res.ok) {
            return { allowed: false, error: 'Edit endpoint unavailable.' };
        }
        return await res.json();
    } catch (err) {
        return { allowed: false, error: 'Edit endpoint unavailable.' };
    }
}

function getLayoutState(config) {
    const layoutValue = config?.work?.layout;
    if (layoutValue && typeof layoutValue === 'object' && !Array.isArray(layoutValue)) {
        return {
            mode: layoutValue.mode || layoutValue.value || layoutValue.name || '',
            template: layoutValue.template || '',
            model: layoutValue.model || '',
        };
    }
    if (typeof layoutValue === 'string') {
        return { mode: layoutValue, template: '', model: '' };
    }
    return { mode: '', template: '', model: '' };
}

function loadPromptSettings() {
    const defaults = {
        provider: 'local',
        model: '',
        endpoint: '',
        apiKey: '',
    };
    try {
        const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || '{}');
        return { ...defaults, ...stored };
    } catch (err) {
        return defaults;
    }
}

function savePromptSettings(settings) {
    try {
        localStorage.setItem(promptSettingsKey, JSON.stringify(settings));
    } catch (err) {
        // Ignore storage failures.
    }
}

function renderPromptHistory(container) {
    if (!container) {
        return;
    }
    if (!promptHistory.length) {
        container.innerHTML = '<div class="small-note">No messages yet.</div>';
        return;
    }
    container.innerHTML = promptHistory.map((msg) => `
        <div class="prompt-message">
            <span class="role">${escapeHtml(msg.role)}:</span>
            <span>${escapeHtml(msg.content)}</span>
        </div>
    `).join('');
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
        editableConfig = data.config || editableConfig;
        renderFolderMeta();
        renderEditUI(editableConfig, {
            allowed: data.allowed !== false,
            error: data.error,
        });
        if (statusEl) {
            statusEl.textContent = 'Config saved.';
            statusEl.className = 'edit-status success';
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

async function requestPromptTemplate(payload) {
    const url = buildCmsUrl('prompt', payload.path || '');
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            return { error: 'Prompt endpoint unavailable.' };
        }
        return await res.json();
    } catch (err) {
        return { error: 'Prompt endpoint unavailable.' };
    }
}

function syncDrawerVisibility() {
    if (!editDrawer) {
        return;
    }
    if (!editRequested || !drawerOpen) {
        editDrawer.classList.remove('open');
        editDrawer.hidden = true;
        return;
    }
    editDrawer.hidden = false;
    editDrawer.classList.add('open');
}

function renderEditPanel(config, status) {
    if (!editPanel) {
        return;
    }
    if (!editRequested) {
        editPanel.hidden = true;
        return;
    }
    editPanel.hidden = false;
    if (!config || status?.error) {
        const message = status?.error || 'Edit mode is unavailable.';
        editPanel.innerHTML = `
            <h3>Edit mode</h3>
            <div class="edit-status">${escapeHtml(message)}</div>
        `;
        return;
    }
    if (!status?.allowed) {
        editPanel.innerHTML = `
            <h3>Edit mode</h3>
            <div class="edit-status">Create a file named <code>.edit.allow</code> in the site root to enable edit mode.</div>
        `;
        return;
    }

    editPanel.innerHTML = `
        <h3>Edit mode</h3>
        <div class="edit-status" id="editInlineStatus"></div>
        <form id="inlineEditForm" class="edit-inline">
            <div>
                <label for="edit-title">Title</label>
                <input id="edit-title" type="text" name="title" value="${escapeHtml(config.title || '')}">
            </div>
            <div>
                <label for="edit-description">Description</label>
                <textarea id="edit-description" name="description">${escapeHtml(config.description || '')}</textarea>
            </div>
            <div class="edit-inline-actions">
                <button type="submit">Save</button>
                <button type="button" id="editMoreToggle" class="edit-secondary">More...</button>
            </div>
        </form>
    `;

    const form = document.getElementById('inlineEditForm');
    const statusEl = document.getElementById('editInlineStatus');
    const moreToggle = document.getElementById('editMoreToggle');
    const titleInput = document.getElementById('edit-title');
    const descInput = document.getElementById('edit-description');
    if (!form || !statusEl) {
        return;
    }
    if (titleInput) {
        titleInput.addEventListener('input', () => {
            if (!editableConfig) {
                return;
            }
            editableConfig.title = titleInput.value;
            renderFolderMeta();
        });
    }
    if (descInput) {
        descInput.addEventListener('input', () => {
            if (!editableConfig) {
                return;
            }
            editableConfig.description = descInput.value;
            renderFolderMeta();
        });
    }
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const elements = form.elements;
        const payload = {
            path: getActivePath(),
            title: (elements.title?.value || '').trim(),
            description: (elements.description?.value || '').trim(),
        };
        await saveConfig(payload, statusEl);
    });
    if (moreToggle) {
        moreToggle.addEventListener('click', () => {
            drawerOpen = !drawerOpen;
            syncDrawerVisibility();
        });
    }
}

function renderEditDrawer(config, status) {
    if (!editDrawer) {
        return;
    }
    if (!editRequested) {
        editDrawer.hidden = true;
        editDrawer.classList.remove('open');
        return;
    }
    if (!config || status?.error) {
        editDrawer.innerHTML = '';
        syncDrawerVisibility();
        return;
    }
    if (!status?.allowed) {
        editDrawer.innerHTML = '';
        syncDrawerVisibility();
        return;
    }

    const layoutState = getLayoutState(config);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const treeHtml = treeItems.length
        ? treeItems.map((item) => {
            const label = escapeHtml(item.name || '');
            const key = escapeHtml(item.path || item.name || '');
            const visible = item.visible !== false ? 'checked' : '';
            const type = escapeHtml(item.type || 'item');
            return `
                <label class="edit-tree-item">
                    <input type="checkbox" name="tree_visible" value="${key}" ${visible}>
                    <span>${label} <span style="opacity:0.6">(${type})</span></span>
                </label>
            `;
        }).join('')
        : '<div class="edit-tree-item">No items found.</div>';

    const settings = loadPromptSettings();
    editDrawer.innerHTML = `
        <div class="drawer-header">
            <h4>More settings</h4>
            <button type="button" class="drawer-close" id="editDrawerClose">&times;</button>
        </div>
        <div class="edit-status" id="editDrawerStatus"></div>
        <form id="editDrawerForm">
            <div class="edit-grid">
                <div>
                    <label for="edit-link">Link</label>
                    <input id="edit-link" type="text" name="link" value="${escapeHtml(config.link || '')}">
                </div>
                <div>
                    <label for="edit-url">URL</label>
                    <input id="edit-url" type="text" name="url" value="${escapeHtml(config.url || '')}">
                </div>
            </div>
            <div class="edit-grid">
                <div>
                    <label for="edit-work-type">Work Type</label>
                    <input id="edit-work-type" type="text" name="work_type" value="${escapeHtml((config.work || {}).type || '')}">
                </div>
                <div>
                    <label for="edit-work-layout">Work Layout</label>
                    <input id="edit-work-layout" type="text" name="work_layout" value="${escapeHtml(layoutState.mode)}">
                </div>
            </div>
            <div>
                <label for="edit-work-template">Work Layout Template</label>
                <textarea id="edit-work-template" name="work_template">${escapeHtml(layoutState.template)}</textarea>
            </div>
            <div>
                <label>Visible items</label>
                <div class="edit-tree">${treeHtml}</div>
            </div>
            <div class="edit-actions">
                <button type="submit">Save advanced</button>
            </div>
        </form>
        <div class="prompt-window">
            <h4>Prompt edit window</h4>
            <div class="edit-grid">
                <div>
                    <label for="prompt-provider">Provider</label>
                    <select id="prompt-provider">
                        <option value="local">Local URL</option>
                        <option value="openai">OpenAI</option>
                        <option value="gemini">Gemini</option>
                    </select>
                </div>
                <div>
                    <label for="prompt-model">Model</label>
                    <input id="prompt-model" type="text" value="${escapeHtml(settings.model || '')}" placeholder="optional">
                </div>
            </div>
            <div id="prompt-endpoint-row">
                <label for="prompt-endpoint">Local endpoint URL</label>
                <input id="prompt-endpoint" type="text" value="${escapeHtml(settings.endpoint || '')}" placeholder="http://localhost:1234/generate">
            </div>
            <div>
                <label for="prompt-api-key">API key (stored in localStorage)</label>
                <input id="prompt-api-key" type="password" value="${escapeHtml(settings.apiKey || '')}">
            </div>
            <div class="prompt-messages" id="promptMessages"></div>
            <textarea id="prompt-input" placeholder="Describe the template you want..."></textarea>
            <div class="prompt-actions">
                <button type="button" id="prompt-send">Send</button>
                <button type="button" id="prompt-clear" class="edit-secondary">Clear</button>
            </div>
            <div class="small-note">Template responses are saved to <code>work.layout.template</code>.</div>
        </div>
    `;

    const drawerClose = document.getElementById('editDrawerClose');
    if (drawerClose) {
        drawerClose.addEventListener('click', () => {
            drawerOpen = false;
            syncDrawerVisibility();
        });
    }

    const drawerStatus = document.getElementById('editDrawerStatus');
    const drawerForm = document.getElementById('editDrawerForm');
    if (drawerForm && drawerStatus) {
        drawerForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const elements = drawerForm.elements;
            const visibleInputs = editDrawer.querySelectorAll('input[name="tree_visible"]:checked');
            const treeVisible = Array.from(visibleInputs).map((input) => input.value);
            const layoutPayload = {
                mode: (elements.work_layout?.value || '').trim(),
                template: elements.work_template?.value ?? '',
            };
            const payload = {
                path: getActivePath(),
                link: (elements.link?.value || '').trim(),
                url: (elements.url?.value || '').trim(),
                work: {
                    type: (elements.work_type?.value || '').trim(),
                },
                layout: layoutPayload,
                treeVisible,
            };
            await saveConfig(payload, drawerStatus);
        });
    }

    const providerEl = document.getElementById('prompt-provider');
    const modelEl = document.getElementById('prompt-model');
    const endpointRow = document.getElementById('prompt-endpoint-row');
    const endpointEl = document.getElementById('prompt-endpoint');
    const apiKeyEl = document.getElementById('prompt-api-key');
    const promptMessagesEl = document.getElementById('promptMessages');
    const promptInputEl = document.getElementById('prompt-input');
    const promptSendEl = document.getElementById('prompt-send');
    const promptClearEl = document.getElementById('prompt-clear');

    if (providerEl) {
        providerEl.value = settings.provider || 'local';
    }

    const updateProviderUi = () => {
        const provider = providerEl ? providerEl.value : 'local';
        if (endpointRow) {
            endpointRow.style.display = provider === 'local' ? 'block' : 'none';
        }
        const nextSettings = {
            provider,
            model: modelEl ? modelEl.value : '',
            endpoint: endpointEl ? endpointEl.value : '',
            apiKey: apiKeyEl ? apiKeyEl.value : '',
        };
        savePromptSettings(nextSettings);
    };

    if (providerEl) {
        providerEl.addEventListener('change', updateProviderUi);
    }
    if (modelEl) {
        modelEl.addEventListener('input', updateProviderUi);
    }
    if (endpointEl) {
        endpointEl.addEventListener('input', updateProviderUi);
    }
    if (apiKeyEl) {
        apiKeyEl.addEventListener('input', updateProviderUi);
    }

    updateProviderUi();
    renderPromptHistory(promptMessagesEl);

    if (promptClearEl) {
        promptClearEl.addEventListener('click', () => {
            promptHistory = [];
            renderPromptHistory(promptMessagesEl);
        });
    }

    if (promptSendEl && promptInputEl) {
        promptSendEl.addEventListener('click', async () => {
            if (!promptInputEl.value.trim()) {
                return;
            }
            const userPrompt = promptInputEl.value.trim();
            promptHistory = [...promptHistory, { role: 'user', content: userPrompt }].slice(-8);
            renderPromptHistory(promptMessagesEl);
            promptInputEl.value = '';
            if (drawerStatus) {
                drawerStatus.textContent = 'Generating template...';
                drawerStatus.className = 'edit-status';
            }
            const historyForRequest = promptHistory.slice(0, -1);
            const payload = {
                path: getActivePath(),
                provider: providerEl ? providerEl.value : 'local',
                model: modelEl ? modelEl.value.trim() : '',
                endpoint: endpointEl ? endpointEl.value.trim() : '',
                apiKey: apiKeyEl ? apiKeyEl.value.trim() : '',
                prompt: userPrompt,
                history: historyForRequest,
            };
            const response = await requestPromptTemplate(payload);
            if (response.error || !response.template) {
                if (drawerStatus) {
                    drawerStatus.textContent = response.error || 'Prompt failed.';
                    drawerStatus.className = 'edit-status';
                }
                return;
            }
            promptHistory = [...promptHistory, { role: 'assistant', content: response.template }].slice(-8);
            renderPromptHistory(promptMessagesEl);
            if (drawerForm) {
                const templateField = drawerForm.querySelector('#edit-work-template');
                if (templateField) {
                    templateField.value = response.template;
                }
            }
            const elements = drawerForm ? drawerForm.elements : null;
            const layoutPayload = {
                mode: (elements?.work_layout?.value || '').trim(),
                template: response.template,
            };
            if (response.model) {
                layoutPayload.model = response.model;
            }
            await saveConfig({
                path: getActivePath(),
                layout: layoutPayload,
            }, drawerStatus);
        });
    }
}

function renderEditUI(config, status) {
    renderEditPanel(config, status);
    renderEditDrawer(config, status);
    syncDrawerVisibility();
}

async function initEditMode() {
    if (!editRequested || !editPanel) {
        return;
    }
    const path = getActivePath();
    const data = await requestEditConfig('config', { path });
    if (data.config) {
        editableConfig = data.config;
        renderFolderMeta();
    }
    renderEditUI(data.config || editableConfig, {
        allowed: data.allowed !== false,
        error: data.error,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const sidebarLoading = document.getElementById('sidebarLoading');
    syncEditToggle();
    if (editToggle) {
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
    // If hash is present, load iframe first, then update sidebar only if hash is for subfolder
    if (window.location.hash && window.location.hash.length > 1) {
        const rawHashPath = window.location.hash.replace(/^#\/?/, '');
        let hashPath = rawHashPath;
        if (rawHashPath) {
            try {
                hashPath = decodeURIComponent(rawHashPath);
            } catch (err) {
                hashPath = rawHashPath;
            }
        }
        const isFile = /\.[^\\/]+$/.test(hashPath);
        contentFrame.src = isFile
            ? `?view=1&file=${encodeURIComponent(hashPath)}`
            : hashPath;
        // Only update sidebar if hash is for subfolder (not root)
        const parts = hashPath.split('/');
        let folderPath = '';
        let fileName = '';
        if (parts.length >= 2) {
            folderPath = parts.slice(0, parts.length - 1).join('/');
            fileName = parts[parts.length - 1];
            if (sidebarLoading) sidebarLoading.style.display = 'block';
            fetch(`?ajax=1&path=${encodeURIComponent(folderPath)}${editQuery}`)
                .then(response => response.text())
                .then(html => {
                    navList.innerHTML = extractNavHtml(html);
                    // Highlight the file if present
                    const fileEls = navList.querySelectorAll('li[data-file]');
                    fileEls.forEach(el => {
                        el.classList.remove('active');
                        if (el.getAttribute('data-file') === fileName) {
                            el.classList.add('active');
                        }
                    });
                    if (sidebarLoading) sidebarLoading.style.display = 'none';
                })
                .catch(() => {
                    navList.innerHTML = '<li>Error loading folder contents</li>';
                    if (sidebarLoading) sidebarLoading.style.display = 'none';
                });
        }
    } else {
        loadCurrentFolderInIframe();
    }
    initEditMode();
});

navList.addEventListener('click', (e) => {
    let target = e.target;
    while (target && target.tagName !== 'A') {
        target = target.parentElement;
    }
    if (!target || target.tagName !== 'A') {
        return;
    }
    // Get the href or data-src for directories/files
    let relPath = '';
    if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
        // Directory link
        const href = target.getAttribute('href') || '';
        const params = new URLSearchParams(href.replace(/^\?/, ''));
        relPath = params.get('path') || '';
    } else if (target.dataset.src) {
        // File link
        relPath = target.dataset.src;
    }
    if (relPath) {
        e.preventDefault();
        // Check if the URL is external (starts with http:// or https://)
        if (relPath.match(/^https?:\/\//)) {
            window.open(relPath, '_blank');
        } else if (target.hasAttribute('href') && target.getAttribute('href').startsWith('?path=')) {
            // Directory link: fetch contents via AJAX and update sidebar
            if (iframeLoading) iframeLoading.style.display = 'block';
            fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery}`)
                .then(response => response.text())
                .then(html => {
                    navList.innerHTML = extractNavHtml(html);
                    // Auto-load index.html or index.htm if present (never index.php)
                    const indexFiles = ['index.html', 'index.htm'];
                    let foundIndex = null;
                    indexFiles.forEach(idx => {
                        const indexEl = navList.querySelector(`li[data-file="${idx}"]`);
                        if (indexEl && !foundIndex) {
                            foundIndex = idx;
                        }
                    });
                    if (foundIndex) {
                        // Load index file in iframe (relative to folder)
                        const indexPath = relPath.replace(/\/$/, '') + '/' + foundIndex;
                        contentFrame.src = `?view=1&file=${encodeURIComponent(indexPath)}`;
                        window.location.hash = '/' + relPath.replace(/^\/+/, '') + '/' + foundIndex;
                    } else {
                        // If no index file, load folder itself in iframe (for folder view)
                        contentFrame.src = relPath.replace(/\/$/, '') + '/';
                        window.location.hash = '/' + relPath.replace(/^\/+/, '');
                    }
                    initEditMode();
                    // Optionally, re-attach event listeners if needed
                })
                .catch(err => {
                    navList.innerHTML = '<li>Error loading folder contents</li>';
                    contentFrame.src = relPath.replace(/\/$/, '') + '/';
                    window.location.hash = '/' + relPath.replace(/^\/+/, '');
                    initEditMode();
                });
        } else {
            // File link: load in iframe
            if (iframeLoading) iframeLoading.style.display = 'block';
            contentFrame.src = `?view=1&file=${encodeURIComponent(relPath)}`;
            window.location.hash = '/' + relPath.replace(/^\/+/, '');
            initEditMode();
        }
        if (activeLink) {
            activeLink.classList.remove('active');
        }
        target.classList.add('active');
        activeLink = target;
    }
});
contentFrame.addEventListener('load', () => {
    if (iframeLoading) iframeLoading.style.display = 'none';
});

window.addEventListener('hashchange', () => {
    if (editRequested) {
        initEditMode();
    }
});
</script>
</body>
</html>
