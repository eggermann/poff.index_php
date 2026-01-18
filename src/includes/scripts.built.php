<?php
/**
 * JavaScript functionality for the file browser
 */
?>
<script>
const currentPoffConfig = /* POFF_CONTEXT */ null;
const currentPathForIframe = /* POFF_IFRAME_PATH */ null;
window.POFF_CONTEXT = { currentPoffConfig, currentPathForIframe };
</script>
<script>
/* POFF_SCRIPT_START */
(() => {
  // src/assets/js/api/edit.js
  function buildCmsUrl(action, path) {
    const url = new URL(window.location.pathname, window.location.origin);
    url.searchParams.set("edit", action);
    if (path) {
      url.searchParams.set("path", path);
    }
    return url.toString();
  }
  async function requestEditConfig(action, payload) {
    const url = buildCmsUrl(action, payload.path || "");
    try {
      const res = await fetch(url, {
        method: action === "config" ? "GET" : "POST",
        headers: {
          "Accept": "application/json",
          ...action === "config" ? {} : { "Content-Type": "application/json" }
        },
        body: action === "config" ? void 0 : JSON.stringify(payload)
      });
      if (!res.ok) {
        return { allowed: false, error: "Edit endpoint unavailable." };
      }
      return await res.json();
    } catch (err) {
      return { allowed: false, error: "Edit endpoint unavailable." };
    }
  }
  async function requestPromptTemplate(payload) {
    const url = buildCmsUrl("prompt", payload.path || "");
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
      });
      if (!res.ok) {
        return { error: "Prompt endpoint unavailable." };
      }
      return await res.json();
    } catch (err) {
      return { error: "Prompt endpoint unavailable." };
    }
  }

  // src/assets/js/core/selection.js
  function getActiveSelection() {
    const rawHash = window.location.hash.replace(/^#\/?/, "");
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
      return {
        path: hashPath,
        isFile
      };
    }
    const params = new URLSearchParams(window.location.search);
    return {
      path: params.get("path") || "",
      isFile: false
    };
  }

  // src/assets/js/core/utils.js
  function escapeHtml(value) {
    return String(value != null ? value : "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }
  function extractNavHtml(html) {
    if (!html) {
      return html;
    }
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, "text/html");
      const nav = doc.getElementById("navList");
      return nav ? nav.innerHTML : html;
    } catch (err) {
      return html;
    }
  }
  function getLayoutState(config) {
    var _a;
    const layoutValue = (_a = config == null ? void 0 : config.work) == null ? void 0 : _a.layout;
    if (layoutValue && typeof layoutValue === "object" && !Array.isArray(layoutValue)) {
      return {
        mode: layoutValue.mode || layoutValue.value || layoutValue.name || "",
        template: layoutValue.template || "",
        model: layoutValue.model || ""
      };
    }
    if (typeof layoutValue === "string") {
      return { mode: layoutValue, template: "", model: "" };
    }
    return { mode: "", template: "", model: "" };
  }

  // src/assets/js/edit/prompt.js
  var promptSettingsKey = "poffEditPromptSettings";
  var promptHistory = [];
  function loadPromptSettings() {
    const defaults = {
      provider: "local",
      model: "",
      endpoint: "",
      apiKey: ""
    };
    try {
      const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || "{}");
      return { ...defaults, ...stored };
    } catch (err) {
      return defaults;
    }
  }
  function savePromptSettings(settings) {
    try {
      localStorage.setItem(promptSettingsKey, JSON.stringify(settings));
    } catch (err) {
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
    `).join("");
  }
  function bindPromptWindow({
    root,
    statusEl,
    drawerForm,
    getActiveSelection: getActiveSelection2,
    requestPromptTemplate: requestPromptTemplate2,
    saveConfig
  }) {
    if (!root) {
      return;
    }
    const providerEl = root.querySelector("#prompt-provider");
    const modelEl = root.querySelector("#prompt-model");
    const endpointRow = root.querySelector("#prompt-endpoint-row");
    const endpointEl = root.querySelector("#prompt-endpoint");
    const apiKeyEl = root.querySelector("#prompt-api-key");
    const promptMessagesEl = root.querySelector("#promptMessages");
    const promptInputEl = root.querySelector("#prompt-input");
    const promptSendEl = root.querySelector("#prompt-send");
    const promptClearEl = root.querySelector("#prompt-clear");
    const settings = loadPromptSettings();
    if (providerEl) {
      providerEl.value = settings.provider || "local";
    }
    const updateProviderUi = () => {
      const provider = providerEl ? providerEl.value : "local";
      if (endpointRow) {
        endpointRow.style.display = provider === "local" ? "block" : "none";
      }
      const nextSettings = {
        provider,
        model: modelEl ? modelEl.value : "",
        endpoint: endpointEl ? endpointEl.value : "",
        apiKey: apiKeyEl ? apiKeyEl.value : ""
      };
      savePromptSettings(nextSettings);
    };
    if (providerEl) {
      providerEl.addEventListener("change", updateProviderUi);
    }
    if (modelEl) {
      modelEl.addEventListener("input", updateProviderUi);
    }
    if (endpointEl) {
      endpointEl.addEventListener("input", updateProviderUi);
    }
    if (apiKeyEl) {
      apiKeyEl.addEventListener("input", updateProviderUi);
    }
    updateProviderUi();
    renderPromptHistory(promptMessagesEl);
    if (promptClearEl) {
      promptClearEl.addEventListener("click", () => {
        promptHistory = [];
        renderPromptHistory(promptMessagesEl);
      });
    }
    if (promptSendEl && promptInputEl) {
      promptSendEl.addEventListener("click", async () => {
        var _a;
        if (!promptInputEl.value.trim()) {
          return;
        }
        const userPrompt = promptInputEl.value.trim();
        promptHistory = [...promptHistory, { role: "user", content: userPrompt }].slice(-8);
        renderPromptHistory(promptMessagesEl);
        promptInputEl.value = "";
        if (statusEl) {
          statusEl.textContent = "Generating template...";
          statusEl.className = "edit-status";
        }
        const historyForRequest = promptHistory.slice(0, -1);
        const payload = {
          path: getActiveSelection2().path,
          provider: providerEl ? providerEl.value : "local",
          model: modelEl ? modelEl.value.trim() : "",
          endpoint: endpointEl ? endpointEl.value.trim() : "",
          apiKey: apiKeyEl ? apiKeyEl.value.trim() : "",
          prompt: userPrompt,
          history: historyForRequest
        };
        const response = await requestPromptTemplate2(payload);
        if (response.error || !response.template) {
          if (statusEl) {
            statusEl.textContent = response.error || "Prompt failed.";
            statusEl.className = "edit-status";
          }
          return;
        }
        promptHistory = [...promptHistory, { role: "assistant", content: response.template }].slice(-8);
        renderPromptHistory(promptMessagesEl);
        if (drawerForm) {
          const templateField = drawerForm.querySelector("#edit-work-template");
          if (templateField) {
            templateField.value = response.template;
          }
        }
        const elements2 = drawerForm ? drawerForm.elements : null;
        const layoutPayload = {
          mode: (((_a = elements2 == null ? void 0 : elements2.work_layout) == null ? void 0 : _a.value) || "").trim(),
          template: response.template
        };
        if (response.model) {
          layoutPayload.model = response.model;
        }
        await saveConfig({
          path: getActiveSelection2().path,
          layout: layoutPayload
        }, statusEl);
      });
    }
  }

  // src/assets/js/edit/controller.js
  function createEditController({ elements: elements2, context, editRequested: editRequested2 }) {
    const { editPanel, editDrawer, editToggle, folderMetaEl } = elements2;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, "currentPoffConfig") ? context.currentPoffConfig : null;
    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = "folder";
    let drawerOpen = false;
    function renderFolderMeta() {
      if (!folderMetaEl) {
        return;
      }
      if (folderConfig && (folderConfig.title || folderConfig.description)) {
        let html = "";
        if (folderConfig.title) {
          if (folderConfig.link || folderConfig.url) {
            const lnk = folderConfig.link || folderConfig.url;
            html += `<h3><a href="${lnk}" target="contentFrame">${folderConfig.title}</a></h3>`;
          } else {
            html += `<h3>${folderConfig.title}</h3>`;
          }
        }
        if (folderConfig.description) {
          html += `<p>${folderConfig.description}</p>`;
        }
        folderMetaEl.innerHTML = html;
        folderMetaEl.style.display = "block";
      } else if (folderMetaEl) {
        folderMetaEl.innerHTML = "";
        folderMetaEl.style.display = "none";
      }
    }
    function syncEditToggle() {
      if (!editToggle) {
        return;
      }
      editToggle.textContent = editRequested2 ? "Exit edit mode" : "Enable edit mode";
      editToggle.classList.toggle("on", editRequested2);
      editToggle.setAttribute("aria-pressed", editRequested2 ? "true" : "false");
    }
    function bindEditToggle() {
      if (!editToggle) {
        return;
      }
      editToggle.addEventListener("click", () => {
        const url = new URL(window.location.href);
        if (editRequested2) {
          url.searchParams.delete("edit");
        } else {
          url.searchParams.set("edit", "true");
        }
        window.location.href = url.toString();
      });
    }
    async function saveConfig(payload, statusEl) {
      try {
        if (statusEl) {
          statusEl.textContent = "Saving...";
          statusEl.className = "edit-status";
        }
        const data = await requestEditConfig("save", payload);
        if (!data || data.error) {
          throw new Error((data == null ? void 0 : data.error) || "Save failed.");
        }
        editConfig = data.config || editConfig;
        editTarget = data.target || editTarget;
        if (editTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
        renderEditUI(editConfig, {
          allowed: data.allowed !== false,
          error: data.error,
          target: editTarget
        });
        if (statusEl) {
          statusEl.textContent = "Config saved.";
          statusEl.className = "edit-status success";
        }
        return data.config;
      } catch (err) {
        if (statusEl) {
          statusEl.textContent = err.message || "Save failed.";
          statusEl.className = "edit-status";
        }
        throw err;
      }
    }
    function syncDrawerVisibility() {
      if (!editDrawer) {
        return;
      }
      if (!editRequested2 || !drawerOpen) {
        editDrawer.classList.remove("open");
        editDrawer.hidden = true;
        return;
      }
      editDrawer.hidden = false;
      editDrawer.classList.add("open");
    }
    function renderEditPanel(config, status) {
      if (!editPanel) {
        return;
      }
      if (!editRequested2) {
        editPanel.hidden = true;
        return;
      }
      editPanel.hidden = false;
      if (!config || (status == null ? void 0 : status.error)) {
        const message = (status == null ? void 0 : status.error) || "Edit mode is unavailable.";
        editPanel.innerHTML = `
                <h3>Edit mode</h3>
                <div class="edit-status">${escapeHtml(message)}</div>
            `;
        return;
      }
      if (!(status == null ? void 0 : status.allowed)) {
        editPanel.innerHTML = `
                <h3>Edit mode</h3>
                <div class="edit-status">Create a file named <code>.edit.allow</code> in the site root to enable edit mode.</div>
            `;
        return;
      }
      const label = (status == null ? void 0 : status.target) === "file" ? "Edit mode (file)" : "Edit mode (folder)";
      editPanel.innerHTML = `
            <h3>${label}</h3>
            <div class="edit-status" id="editInlineStatus"></div>
            <form id="inlineEditForm" class="edit-inline">
                <div>
                    <label for="edit-title">Title</label>
                    <input id="edit-title" type="text" name="title" value="${escapeHtml(config.title || "")}">
                </div>
                <div>
                    <label for="edit-description">Description</label>
                    <textarea id="edit-description" name="description">${escapeHtml(config.description || "")}</textarea>
                </div>
                <div class="edit-inline-actions">
                    <button type="submit">Save</button>
                    <button type="button" id="editMoreToggle" class="edit-secondary">More...</button>
                </div>
            </form>
        `;
      const form = document.getElementById("inlineEditForm");
      const statusEl = document.getElementById("editInlineStatus");
      const moreToggle = document.getElementById("editMoreToggle");
      const titleInput = document.getElementById("edit-title");
      const descInput = document.getElementById("edit-description");
      if (!form || !statusEl) {
        return;
      }
      if (titleInput) {
        titleInput.addEventListener("input", () => {
          if (!editConfig) {
            return;
          }
          editConfig.title = titleInput.value;
          if ((status == null ? void 0 : status.target) !== "file") {
            folderConfig = editConfig;
            renderFolderMeta();
          }
        });
      }
      if (descInput) {
        descInput.addEventListener("input", () => {
          if (!editConfig) {
            return;
          }
          editConfig.description = descInput.value;
          if ((status == null ? void 0 : status.target) !== "file") {
            folderConfig = editConfig;
            renderFolderMeta();
          }
        });
      }
      form.addEventListener("submit", async (event) => {
        var _a, _b;
        event.preventDefault();
        const elements3 = form.elements;
        const selection = getActiveSelection();
        const payload = {
          path: selection.path,
          title: (((_a = elements3.title) == null ? void 0 : _a.value) || "").trim(),
          description: (((_b = elements3.description) == null ? void 0 : _b.value) || "").trim()
        };
        await saveConfig(payload, statusEl);
      });
      if (moreToggle) {
        moreToggle.addEventListener("click", () => {
          drawerOpen = !drawerOpen;
          syncDrawerVisibility();
        });
      }
    }
    function renderEditDrawer(config, status) {
      if (!editDrawer) {
        return;
      }
      if (!editRequested2) {
        editDrawer.hidden = true;
        editDrawer.classList.remove("open");
        return;
      }
      if (!config || (status == null ? void 0 : status.error)) {
        editDrawer.innerHTML = "";
        syncDrawerVisibility();
        return;
      }
      if (!(status == null ? void 0 : status.allowed)) {
        editDrawer.innerHTML = "";
        syncDrawerVisibility();
        return;
      }
      const layoutState = getLayoutState(config);
      let treeHtml = "";
      if ((status == null ? void 0 : status.target) !== "file") {
        const treeItems = Array.isArray(config.tree) ? config.tree : [];
        treeHtml = treeItems.length ? treeItems.map((item) => {
          const label = escapeHtml(item.name || "");
          const key = escapeHtml(item.path || item.name || "");
          const visible = item.visible !== false ? "checked" : "";
          const type = escapeHtml(item.type || "item");
          return `
                        <label class="edit-tree-item">
                            <input type="checkbox" name="tree_visible" value="${key}" ${visible}>
                            <span>${label} <span style="opacity:0.6">(${type})</span></span>
                        </label>
                    `;
        }).join("") : '<div class="edit-tree-item">No items found.</div>';
      }
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
                        <input id="edit-link" type="text" name="link" value="${escapeHtml(config.link || "")}">
                    </div>
                    <div>
                        <label for="edit-url">URL</label>
                        <input id="edit-url" type="text" name="url" value="${escapeHtml(config.url || "")}">
                    </div>
                </div>
                <div class="edit-grid">
                    <div>
                        <label for="edit-work-type">Work Type</label>
                        <input id="edit-work-type" type="text" name="work_type" value="${escapeHtml((config.work || {}).type || "")}">
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
                ${(status == null ? void 0 : status.target) !== "file" ? `
                <div>
                    <label>Visible items</label>
                    <div class="edit-tree">${treeHtml}</div>
                </div>
                ` : ""}
                <div class="edit-actions">
                    <button type="submit">Save advanced</button>
                </div>
            </form>
            <div class="prompt-window" id="promptWindow">
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
                        <input id="prompt-model" type="text" value="${escapeHtml(settings.model || "")}" placeholder="optional">
                    </div>
                </div>
                <div id="prompt-endpoint-row">
                    <label for="prompt-endpoint">Local endpoint URL</label>
                    <input id="prompt-endpoint" type="text" value="${escapeHtml(settings.endpoint || "")}" placeholder="http://localhost:1234/generate">
                </div>
                <div>
                    <label for="prompt-api-key">API key (stored in localStorage)</label>
                    <input id="prompt-api-key" type="password" value="${escapeHtml(settings.apiKey || "")}">
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
      const drawerClose = document.getElementById("editDrawerClose");
      if (drawerClose) {
        drawerClose.addEventListener("click", () => {
          drawerOpen = false;
          syncDrawerVisibility();
        });
      }
      const drawerStatus = document.getElementById("editDrawerStatus");
      const drawerForm = document.getElementById("editDrawerForm");
      if (drawerForm && drawerStatus) {
        drawerForm.addEventListener("submit", async (event) => {
          var _a, _b, _c, _d, _e, _f;
          event.preventDefault();
          const elements3 = drawerForm.elements;
          let treeVisible = [];
          if ((status == null ? void 0 : status.target) !== "file") {
            const visibleInputs = editDrawer.querySelectorAll('input[name="tree_visible"]:checked');
            treeVisible = Array.from(visibleInputs).map((input) => input.value);
          }
          const layoutPayload = {
            mode: (((_a = elements3.work_layout) == null ? void 0 : _a.value) || "").trim(),
            template: (_c = (_b = elements3.work_template) == null ? void 0 : _b.value) != null ? _c : ""
          };
          const selection = getActiveSelection();
          const payload = {
            path: selection.path,
            link: (((_d = elements3.link) == null ? void 0 : _d.value) || "").trim(),
            url: (((_e = elements3.url) == null ? void 0 : _e.value) || "").trim(),
            work: {
              type: (((_f = elements3.work_type) == null ? void 0 : _f.value) || "").trim()
            },
            layout: layoutPayload
          };
          if ((status == null ? void 0 : status.target) !== "file") {
            payload.treeVisible = treeVisible;
          }
          await saveConfig(payload, drawerStatus);
        });
      }
      bindPromptWindow({
        root: editDrawer,
        statusEl: drawerStatus,
        drawerForm,
        getActiveSelection,
        requestPromptTemplate,
        saveConfig
      });
    }
    function renderEditUI(config, status) {
      renderEditPanel(config, status);
      renderEditDrawer(config, status);
      syncDrawerVisibility();
    }
    async function initEditMode() {
      if (!editRequested2 || !editPanel) {
        return;
      }
      const selection = getActiveSelection();
      const data = await requestEditConfig("config", { path: selection.path });
      if (data.config) {
        editConfig = data.config;
        editTarget = data.target || (selection.isFile ? "file" : "folder");
        if (editTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
      }
      renderEditUI(data.config || editConfig, {
        allowed: data.allowed !== false,
        error: data.error,
        target: editTarget
      });
    }
    return {
      renderFolderMeta,
      syncEditToggle,
      bindEditToggle,
      initEditMode
    };
  }

  // src/assets/js/nav/navigation.js
  function initNavigation({
    elements: elements2,
    editQuery: editQuery2,
    currentPathForIframe: currentPathForIframe2,
    renderFolderMeta,
    initEditMode
  }) {
    const { navList, contentFrame, iframeLoading, sidebarLoading } = elements2;
    let activeLink = null;
    function loadCurrentFolderInIframe() {
      if (currentPathForIframe2 && contentFrame) {
        contentFrame.src = currentPathForIframe2;
        if (activeLink) {
          activeLink.classList.remove("active");
          activeLink = null;
        }
      }
      if (renderFolderMeta) {
        renderFolderMeta();
      }
    }
    function handleInitialHash() {
      if (!contentFrame) {
        return;
      }
      const rawHashPath = window.location.hash.replace(/^#\/?/, "");
      let hashPath = rawHashPath;
      if (rawHashPath) {
        try {
          hashPath = decodeURIComponent(rawHashPath);
        } catch (err) {
          hashPath = rawHashPath;
        }
      }
      const isFile = /\.[^\\/]+$/.test(hashPath);
      contentFrame.src = isFile ? `?view=1&file=${encodeURIComponent(hashPath)}` : hashPath;
      const parts = hashPath.split("/");
      if (parts.length < 2 || !navList) {
        return;
      }
      const folderPath = parts.slice(0, parts.length - 1).join("/");
      const fileName = parts[parts.length - 1];
      if (sidebarLoading) {
        sidebarLoading.style.display = "block";
      }
      fetch(`?ajax=1&path=${encodeURIComponent(folderPath)}${editQuery2}`).then((response) => response.text()).then((html) => {
        navList.innerHTML = extractNavHtml(html);
        const fileEls = navList.querySelectorAll("li[data-file]");
        fileEls.forEach((el) => {
          el.classList.remove("active");
          if (el.getAttribute("data-file") === fileName) {
            el.classList.add("active");
          }
        });
        if (sidebarLoading) {
          sidebarLoading.style.display = "none";
        }
      }).catch(() => {
        navList.innerHTML = "<li>Error loading folder contents</li>";
        if (sidebarLoading) {
          sidebarLoading.style.display = "none";
        }
      });
    }
    function handleNavClick(event) {
      if (!navList || !contentFrame) {
        return;
      }
      let target = event.target;
      while (target && target.tagName !== "A") {
        target = target.parentElement;
      }
      if (!target || target.tagName !== "A") {
        return;
      }
      let relPath = "";
      if (target.hasAttribute("href") && target.getAttribute("href").startsWith("?path=")) {
        const href = target.getAttribute("href") || "";
        const params = new URLSearchParams(href.replace(/^\?/, ""));
        relPath = params.get("path") || "";
      } else if (target.dataset.src) {
        relPath = target.dataset.src;
      }
      if (!relPath) {
        return;
      }
      event.preventDefault();
      if (relPath.match(/^https?:\/\//)) {
        window.open(relPath, "_blank");
        return;
      }
      if (target.hasAttribute("href") && target.getAttribute("href").startsWith("?path=")) {
        if (iframeLoading) {
          iframeLoading.style.display = "block";
        }
        fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery2}`).then((response) => response.text()).then((html) => {
          navList.innerHTML = extractNavHtml(html);
          const indexFiles = ["index.html", "index.htm"];
          let foundIndex = null;
          indexFiles.forEach((idx) => {
            const indexEl = navList.querySelector(`li[data-file="${idx}"]`);
            if (indexEl && !foundIndex) {
              foundIndex = idx;
            }
          });
          if (foundIndex) {
            const indexPath = relPath.replace(/\/$/, "") + "/" + foundIndex;
            contentFrame.src = `?view=1&file=${encodeURIComponent(indexPath)}`;
            window.location.hash = "/" + relPath.replace(/^\/+/, "") + "/" + foundIndex;
          } else {
            contentFrame.src = relPath.replace(/\/$/, "") + "/";
            window.location.hash = "/" + relPath.replace(/^\/+/, "");
          }
          if (initEditMode) {
            initEditMode();
          }
        }).catch(() => {
          navList.innerHTML = "<li>Error loading folder contents</li>";
          contentFrame.src = relPath.replace(/\/$/, "") + "/";
          window.location.hash = "/" + relPath.replace(/^\/+/, "");
          if (initEditMode) {
            initEditMode();
          }
        });
      } else {
        if (iframeLoading) {
          iframeLoading.style.display = "block";
        }
        contentFrame.src = `?view=1&file=${encodeURIComponent(relPath)}`;
        window.location.hash = "/" + relPath.replace(/^\/+/, "");
        if (initEditMode) {
          initEditMode();
        }
      }
      if (activeLink) {
        activeLink.classList.remove("active");
      }
      target.classList.add("active");
      activeLink = target;
    }
    if (navList) {
      navList.addEventListener("click", handleNavClick);
    }
    if (contentFrame) {
      contentFrame.addEventListener("load", () => {
        if (iframeLoading) {
          iframeLoading.style.display = "none";
        }
      });
    }
    return {
      loadCurrentFolderInIframe,
      handleInitialHash
    };
  }

  // src/assets/js/app.js
  if (window.location.hash === "#mcp") {
    const basePath = window.location.pathname.split("#")[0];
    window.location.href = `${basePath}?mcp=1`;
  }
  var elements = {
    navList: document.getElementById("navList"),
    contentFrame: document.getElementById("contentFrame"),
    folderMetaEl: document.getElementById("folderMeta"),
    editPanel: document.getElementById("editPanel"),
    editDrawer: document.getElementById("editDrawer"),
    editToggle: document.getElementById("editToggle"),
    iframeLoading: document.getElementById("iframeLoading"),
    sidebarLoading: document.getElementById("sidebarLoading")
  };
  var poffContext = window.POFF_CONTEXT || {};
  var currentPathForIframe = Object.prototype.hasOwnProperty.call(poffContext, "currentPathForIframe") ? poffContext.currentPathForIframe : null;
  var editRequested = new URLSearchParams(window.location.search).get("edit") === "true";
  var editQuery = editRequested ? "&edit=true" : "";
  var editController = createEditController({
    elements,
    context: poffContext,
    editRequested
  });
  var navigation = initNavigation({
    elements,
    editQuery,
    currentPathForIframe,
    renderFolderMeta: editController.renderFolderMeta,
    initEditMode: editController.initEditMode
  });
  document.addEventListener("DOMContentLoaded", () => {
    editController.syncEditToggle();
    editController.bindEditToggle();
    if (window.location.hash && window.location.hash.length > 1) {
      navigation.handleInitialHash();
    } else {
      navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
  });
  window.addEventListener("hashchange", () => {
    if (editRequested) {
      editController.initEditMode();
    }
  });
})();
/* POFF_SCRIPT_END */
</script>
