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
  var PROMPT_REQUEST_TIMEOUT_MS = 9e4;
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
    const controller = typeof AbortController !== "undefined" ? new AbortController() : null;
    const timeout = setTimeout(() => {
      if (controller) {
        controller.abort();
      }
    }, PROMPT_REQUEST_TIMEOUT_MS);
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload),
        signal: controller ? controller.signal : void 0
      });
      clearTimeout(timeout);
      if (!res.ok) {
        return { error: "Prompt endpoint unavailable." };
      }
      return await res.json();
    } catch (err) {
      clearTimeout(timeout);
      if ((err == null ? void 0 : err.name) === "AbortError") {
        return { error: "Prompt request timed out after 90 seconds." };
      }
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

  // src/assets/js/edit/prompt/constants.js
  var promptSettingsKey = "poffEditPromptSettings";
  var promptHistoryKey = "poffEditPromptHistory";
  var defaultSystemPrompt = [
    "You are a Handlebars (HBS) template generator for this single-page CMS.",
    "Transform the user description into one HBS template string that will be saved to .layout/template.hbs and rendered by LightnCandy.",
    "Return only the template (no Markdown, no fences).",
    "Use {{> default-layout}} as the default layout technique. Inside that layout, the section includes {{> works}} for folders and {{> work}} for files.",
    "Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.",
    "Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available.",
    "For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.",
    "Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.",
    "You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.",
    "If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled."
  ].join("\n");
  var defaultPromptSettings = {
    provider: "openai",
    model: "gpt-4o-mini",
    endpoint: "",
    apiKey: "",
    systemPrompt: defaultSystemPrompt,
    streamPreview: true
  };

  // src/assets/js/edit/prompt/storage.js
  function loadPromptSettings() {
    try {
      const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || "{}");
      return { ...defaultPromptSettings, ...stored };
    } catch (err) {
      return defaultPromptSettings;
    }
  }
  function savePromptSettings(settings) {
    try {
      localStorage.setItem(promptSettingsKey, JSON.stringify(settings));
    } catch (err) {
    }
  }
  function readStoredHistory(path) {
    if (!path) {
      return [];
    }
    try {
      const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || "{}");
      const list = stored[path] || [];
      return Array.isArray(list) ? list : [];
    } catch (err) {
      return [];
    }
  }
  function writeStoredHistory(path, history) {
    if (!path) {
      return;
    }
    try {
      const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || "{}");
      stored[path] = history;
      localStorage.setItem(promptHistoryKey, JSON.stringify(stored));
    } catch (err) {
    }
  }

  // src/assets/js/edit/prompt/history.js
  function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
  }
  function filterAllowedWork(work, config) {
    if (!work || typeof work !== "object") {
      return null;
    }
    const baseWork = config && typeof config === "object" && config.work && typeof config.work === "object" ? config.work : {};
    const allowedKeys = /* @__PURE__ */ new Set([
      ...Object.keys(baseWork),
      "type",
      "layout",
      "model"
    ]);
    const filtered = {};
    Object.entries(work).forEach(([key, value]) => {
      if (allowedKeys.has(key)) {
        filtered[key] = value;
      }
    });
    return filtered;
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
        mode: layoutValue.name || layoutValue.mode || layoutValue.value || "",
        template: layoutValue.template || "",
        css: layoutValue.css || "",
        js: layoutValue.js || "",
        model: layoutValue.model || "",
        engine: layoutValue.engine || "lightncandy",
        directory: layoutValue.directory || "",
        assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : []
      };
    }
    if (typeof layoutValue === "string") {
      return { mode: layoutValue, template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", assets: [] };
    }
    return { mode: "default-layout", template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", assets: [] };
  }

  // src/assets/js/edit/prompt/render.js
  function renderPromptHistory(container, history, streamState, options = {}) {
    if (!container) {
      return;
    }
    const { forceScroll = false } = options;
    const stickToBottom = container.scrollHeight - container.clientHeight - container.scrollTop < 24;
    if (!history || !history.length) {
      container.innerHTML = '<div class="small-note">No messages yet.</div>';
      return;
    }
    container.innerHTML = history.map((msg) => {
      const role = (msg.role || "user").toLowerCase();
      const isStreaming = streamState && streamState.index === msg._index;
      const content = isStreaming ? streamState.text : msg.content;
      const safeContent = content || "";
      return `
            <div class="prompt-message prompt-message-${role}">
                <span class="prompt-message-role">${escapeHtml(role)}:</span>
                <span class="prompt-message-content">${escapeHtml(safeContent)}${isStreaming ? '<span class="stream-cursor"></span>' : ""}</span>
            </div>
        `;
    }).join("");
    if (forceScroll || stickToBottom) {
      container.scrollTop = container.scrollHeight;
    }
  }
  function renderPromptSummary(summaryEl, content) {
    if (!summaryEl) {
      return;
    }
    const safeContent = content || "Waiting for response...";
    const body = summaryEl.querySelector(".prompt-summary-body");
    if (!body) {
      summaryEl.innerHTML = `<div class="prompt-summary-title">Template summary</div><div class="prompt-summary-body">${escapeHtml(safeContent)}</div>`;
      return;
    }
    body.innerHTML = escapeHtml(safeContent);
  }
  function buildPromptContext({ getActiveSelection: getActiveSelection2, getConfig }) {
    const selection = typeof getActiveSelection2 === "function" ? getActiveSelection2() : { path: "", isFile: false };
    const config = typeof getConfig === "function" ? getConfig() || {} : {};
    const path = (selection == null ? void 0 : selection.path) || "";
    const name = path ? path.split(/[\\/]/).pop() : "";
    const work = config && typeof config === "object" && config.work ? config.work : {};
    const ellipsis = "\u2026";
    const workPreview = Object.entries(work || {}).slice(0, 6).map(([key, value]) => {
      if (typeof value === "boolean") {
        return `${key}: ${value ? "true" : "false"}`;
      }
      if (value === null || value === void 0) {
        return `${key}: null`;
      }
      const str = String(value);
      return `${key}: ${str.length > 28 ? str.slice(0, 25) + ellipsis : str}`;
    }).join(", ");
    return { path, name, workPreview };
  }
  function renderPromptContext(contextEl, context) {
    if (!contextEl) {
      return;
    }
    const path = (context == null ? void 0 : context.path) || "";
    const name = (context == null ? void 0 : context.name) || "";
    const workPreview = (context == null ? void 0 : context.workPreview) || "";
    contextEl.innerHTML = `
        <div class="prompt-context-row"><strong>path</strong>: ${escapeHtml(path)}</div>
        <div class="prompt-context-row"><strong>name</strong>: ${escapeHtml(name)}</div>
        <div class="prompt-context-row"><strong>partials</strong>: ${escapeHtml("default-layout, works, work")}</div>
        ${workPreview ? `<div class="prompt-context-row"><strong>work.*</strong>: ${escapeHtml(workPreview)}</div>` : ""}
    `;
  }

  // src/assets/js/edit/prompt/stream.js
  function createStreamState() {
    return { timer: null, state: null };
  }
  function stopStreaming(stream2) {
    if (!stream2) {
      return;
    }
    if (stream2.timer) {
      clearInterval(stream2.timer);
      stream2.timer = null;
    }
    stream2.state = null;
  }
  function startStreaming({ stream: stream2, targetIndex, fullText, history, renderHistory }) {
    if (!stream2 || typeof renderHistory !== "function") {
      return;
    }
    stopStreaming(stream2);
    if (!fullText) {
      renderHistory();
      return;
    }
    if (history && history[targetIndex]) {
      history[targetIndex].content = "";
    }
    stream2.state = { index: targetIndex, text: "" };
    const total = fullText.length;
    const step = Math.max(1, Math.ceil(total / 80));
    stream2.timer = window.setInterval(() => {
      if (!stream2.state) {
        return;
      }
      stream2.state.text = fullText.slice(0, stream2.state.text.length + step);
      renderHistory();
      if (stream2.state.text.length >= total) {
        stopStreaming(stream2);
        if (history && history[targetIndex]) {
          history[targetIndex].content = fullText;
        }
        renderHistory();
      }
    }, 18);
  }

  // src/assets/js/edit/prompt.js
  var PROMPT_FALLBACK_TIMEOUT_MS = 95e3;
  var promptHistory = [];
  var stream = createStreamState();
  var debugPromptLog = (label, payload) => {
    try {
      console.info(`[prompt] ${label}`, payload);
    } catch (err) {
    }
  };
  function bindPromptWindow({
    root,
    statusEl,
    drawerForm,
    getActiveSelection: getActiveSelection2,
    getConfig,
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
    const systemPromptEl = root.querySelector("#prompt-system");
    const systemResetEl = root.querySelector("#prompt-system-reset");
    const settingsResetEl = root.querySelector("#prompt-settings-reset");
    const streamToggleEl = root.querySelector("#prompt-stream");
    const promptMessagesEl = root.querySelector("#promptMessages");
    const promptContextEl = root.querySelector("#promptContext");
    const promptSummaryEl = root.querySelector("#promptSummary");
    const promptGenerationEl = root.querySelector("#promptGeneration");
    const promptGenerationLabelEl = root.querySelector("#promptGenerationLabel");
    const promptInputEl = root.querySelector("#prompt-input");
    const promptSendEl = root.querySelector("#prompt-send");
    const promptAttachEl = root.querySelector("#prompt-attach");
    const promptClearEl = root.querySelector("#prompt-clear");
    const promptImageInputEl = root.querySelector("#prompt-image-input");
    const promptAttachmentEl = root.querySelector("#promptAttachment");
    const promptAttachmentPreviewEl = root.querySelector("#promptAttachmentPreview");
    const promptAttachmentNameEl = root.querySelector("#promptAttachmentName");
    const promptAttachmentRemoveEl = root.querySelector("#prompt-attachment-remove");
    const settings = loadPromptSettings();
    let isSending = false;
    let activePath = getActiveSelection2 ? getActiveSelection2().path : "";
    let imageAttachment = null;
    const defaultPromptPlaceholder = (promptInputEl == null ? void 0 : promptInputEl.getAttribute("placeholder")) || "Describe the component you want...";
    const setHistory = (nextHistory) => {
      const list = Array.isArray(nextHistory) ? nextHistory : [];
      promptHistory = tagHistory(list);
    };
    const renderHistory = (options = {}) => {
      renderPromptHistory(promptMessagesEl, promptHistory, stream.state, options);
    };
    const renderContext = () => {
      const context = buildPromptContext({ getActiveSelection: getActiveSelection2, getConfig });
      activePath = context.path;
      renderPromptContext(promptContextEl, context);
    };
    const renderSummary = (content) => {
      renderPromptSummary(promptSummaryEl, content);
    };
    const updateAttachmentUi = () => {
      if (!promptAttachmentEl || !promptAttachmentPreviewEl || !promptAttachmentNameEl || !promptInputEl) {
        return;
      }
      const hasAttachment = !!imageAttachment;
      promptAttachmentEl.hidden = !hasAttachment;
      promptInputEl.classList.toggle("prompt-input-has-attachment", hasAttachment);
      if (!hasAttachment) {
        promptAttachmentPreviewEl.removeAttribute("src");
        promptAttachmentNameEl.textContent = "Image attached";
        return;
      }
      promptAttachmentPreviewEl.src = imageAttachment.dataUrl;
      promptAttachmentNameEl.textContent = imageAttachment.name || "clipboard-image.png";
    };
    const clearAttachment = () => {
      imageAttachment = null;
      if (promptImageInputEl) {
        promptImageInputEl.value = "";
      }
      updateAttachmentUi();
    };
    const isSupportedImageFile = (file) => !!file && typeof file.type === "string" && file.type.startsWith("image/");
    const readImageFile = (file) => new Promise((resolve, reject) => {
      if (!isSupportedImageFile(file)) {
        reject(new Error("Only image files are supported."));
        return;
      }
      const reader = new FileReader();
      reader.onload = () => {
        const dataUrl = typeof reader.result === "string" ? reader.result : "";
        if (!dataUrl.startsWith("data:image/")) {
          reject(new Error("Invalid image data."));
          return;
        }
        resolve({
          name: file.name || "clipboard-image.png",
          mimeType: file.type || "image/png",
          dataUrl
        });
      };
      reader.onerror = () => reject(new Error("Failed to read image."));
      reader.readAsDataURL(file);
    });
    const attachImageFile = async (file) => {
      try {
        imageAttachment = await readImageFile(file);
        updateAttachmentUi();
        if (statusEl) {
          statusEl.textContent = `Attached image: ${imageAttachment.name}`;
          statusEl.className = "edit-status edit-status-success";
        }
      } catch (err) {
        if (statusEl) {
          statusEl.textContent = err.message || "Failed to attach image.";
          statusEl.className = "edit-status";
        }
      }
    };
    const setGeneratingState = (active, label = "Generating answer...") => {
      root.classList.toggle("prompt-window-generating", active);
      root.setAttribute("aria-busy", active ? "true" : "false");
      if (promptSummaryEl) {
        promptSummaryEl.classList.toggle("prompt-summary-generating", active);
      }
      if (promptGenerationEl) {
        promptGenerationEl.hidden = !active;
      }
      if (promptGenerationLabelEl) {
        promptGenerationLabelEl.textContent = label;
      }
      if (promptSendEl) {
        promptSendEl.disabled = active;
        promptSendEl.textContent = active ? "Generating..." : "Send";
      }
      if (promptAttachEl) {
        promptAttachEl.disabled = active;
      }
      if (promptClearEl) {
        promptClearEl.disabled = active;
      }
      if (promptAttachmentRemoveEl) {
        promptAttachmentRemoveEl.disabled = active;
      }
      if (promptInputEl) {
        promptInputEl.disabled = active;
        promptInputEl.placeholder = active ? "Generating answer..." : defaultPromptPlaceholder;
      }
    };
    if (providerEl) {
      providerEl.value = settings.provider || "local";
    }
    if (systemPromptEl) {
      systemPromptEl.value = settings.systemPrompt || defaultSystemPrompt;
    }
    if (streamToggleEl) {
      streamToggleEl.checked = settings.streamPreview !== false;
    }
    const readSettings = () => ({
      provider: providerEl ? providerEl.value : "local",
      model: modelEl ? modelEl.value : "",
      endpoint: endpointEl ? endpointEl.value : "",
      apiKey: apiKeyEl ? apiKeyEl.value : "",
      systemPrompt: ((systemPromptEl == null ? void 0 : systemPromptEl.value) || "").trim() || defaultSystemPrompt,
      streamPreview: streamToggleEl ? !!streamToggleEl.checked : true
    });
    let suppressSave = false;
    const applySettingsToUi = (s) => {
      suppressSave = true;
      if (providerEl) providerEl.value = s.provider || defaultPromptSettings.provider;
      if (modelEl) modelEl.value = s.model || "";
      if (endpointEl) endpointEl.value = s.endpoint || "";
      if (apiKeyEl) apiKeyEl.value = s.apiKey || "";
      if (systemPromptEl) systemPromptEl.value = s.systemPrompt || defaultSystemPrompt;
      if (streamToggleEl) streamToggleEl.checked = s.streamPreview !== false;
      suppressSave = false;
      updateProviderUi();
    };
    const updateProviderUi = () => {
      const provider = providerEl ? providerEl.value : "local";
      if (endpointRow) {
        endpointRow.style.display = provider === "local" ? "block" : "none";
      }
      if (provider === "openai" && modelEl && !modelEl.value.trim()) {
        modelEl.value = "gpt-4o-mini";
      }
      if (provider === "gemini" && modelEl && !modelEl.value.trim()) {
        modelEl.value = "gemini-1.5-flash";
      }
      if (!suppressSave) {
        savePromptSettings(readSettings());
      }
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
    if (systemPromptEl) {
      systemPromptEl.addEventListener("input", () => {
        savePromptSettings(readSettings());
      });
    }
    if (streamToggleEl) {
      streamToggleEl.addEventListener("change", () => {
        savePromptSettings(readSettings());
      });
    }
    if (systemResetEl && systemPromptEl) {
      systemResetEl.addEventListener("click", () => {
        systemPromptEl.value = defaultSystemPrompt;
        savePromptSettings(readSettings());
      });
    }
    if (settingsResetEl) {
      settingsResetEl.addEventListener("click", () => {
        applySettingsToUi(defaultPromptSettings);
        savePromptSettings(defaultPromptSettings);
        renderContext();
      });
    }
    updateProviderUi();
    setHistory(readStoredHistory(activePath));
    renderHistory();
    renderContext();
    renderSummary("Waiting for response...");
    updateAttachmentUi();
    const reloadViewer = () => {
      var _a;
      const frame = document.getElementById("contentFrame");
      const selection = getActiveSelection2 ? getActiveSelection2() : { path: "", isFile: false };
      const selectionPath = selection && Object.prototype.hasOwnProperty.call(selection, "path") ? selection.path : void 0;
      const activeViewerPath = selectionPath != null ? selectionPath : activePath;
      if (frame && activeViewerPath !== null && activeViewerPath !== void 0) {
        const isFile = (_a = selection == null ? void 0 : selection.isFile) != null ? _a : /\.[^\\/]+$/.test(activeViewerPath);
        const url = new URL(window.location.href);
        url.search = "";
        url.hash = "";
        url.searchParams.set("view", "1");
        url.searchParams.set(isFile ? "file" : "path", activeViewerPath);
        url.searchParams.set("_refresh", String(Date.now()));
        frame.src = url.pathname + url.search;
        return;
      }
      if (frame && frame.contentWindow) {
        try {
          frame.contentWindow.location.reload();
          return;
        } catch (err) {
        }
      }
      if (frame && frame.src) {
        frame.src = frame.src;
      }
    };
    const syncHistoryForPath = () => {
      const selection = getActiveSelection2 ? getActiveSelection2() : { path: "" };
      const nextPath = (selection == null ? void 0 : selection.path) || "";
      if (nextPath !== activePath) {
        activePath = nextPath;
        setHistory(readStoredHistory(activePath));
        renderHistory();
        renderContext();
        renderSummary("Waiting for response...");
      }
    };
    window.addEventListener("hashchange", syncHistoryForPath);
    if (promptClearEl) {
      promptClearEl.addEventListener("click", () => {
        syncHistoryForPath();
        stopStreaming(stream);
        setHistory([]);
        writeStoredHistory(activePath, promptHistory);
        renderHistory();
        clearAttachment();
        if (statusEl) {
          statusEl.textContent = "Chat cleared.";
          statusEl.className = "edit-status";
        }
      });
    }
    if (promptSendEl && promptInputEl) {
      const sendPrompt = async () => {
        var _a;
        if (isSending || !promptInputEl.value.trim() && !imageAttachment) {
          return;
        }
        isSending = true;
        setGeneratingState(true, "Generating answer...");
        stopStreaming(stream);
        let pendingAssistantIndex = null;
        let settled = false;
        const fallbackTimer = window.setTimeout(() => {
          if (settled) {
            return;
          }
          stopStreaming(stream);
          setGeneratingState(false);
          const errMsg = "Prompt timed out after 95 seconds.";
          if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
            promptHistory[pendingAssistantIndex].content = errMsg;
            setHistory(promptHistory);
          } else {
            setHistory([...promptHistory, { role: "assistant", content: errMsg }].slice(-12));
          }
          renderHistory({ forceScroll: true });
          if (statusEl) {
            statusEl.textContent = errMsg;
            statusEl.className = "edit-status";
          }
          isSending = false;
        }, PROMPT_FALLBACK_TIMEOUT_MS);
        try {
          const userPrompt = promptInputEl.value.trim();
          const providerValue = providerEl ? providerEl.value : "local";
          const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : "";
          if ((providerValue === "openai" || providerValue === "gemini") && apiKeyValue === "") {
            setGeneratingState(false);
            if (statusEl) {
              statusEl.textContent = providerValue === "openai" ? "Add an OpenAI API key to send prompts." : "Add a Gemini API key to send prompts.";
              statusEl.className = "edit-status";
            }
            isSending = false;
            return;
          }
          setHistory([...promptHistory, { role: "user", content: userPrompt }].slice(-12));
          setHistory([...promptHistory, { role: "assistant", content: "Generating answer..." }].slice(-12));
          pendingAssistantIndex = promptHistory.length - 1;
          writeStoredHistory(activePath, promptHistory);
          renderHistory({ forceScroll: true });
          renderContext();
          promptInputEl.value = "";
          if (statusEl) {
            statusEl.textContent = "Generating answer...";
            statusEl.className = "edit-status";
          }
          renderSummary("Generating answer...");
          const historyForRequest = promptHistory.slice(0, -1).map((item) => ({
            role: item.role,
            content: item.content
          }));
          const systemPromptValue = ((systemPromptEl == null ? void 0 : systemPromptEl.value) || "").trim();
          const payload = {
            path: activePath,
            provider: providerEl ? providerEl.value : "local",
            model: modelEl ? modelEl.value.trim() : "",
            endpoint: endpointEl ? endpointEl.value.trim() : "",
            apiKey: apiKeyEl ? apiKeyEl.value.trim() : "",
            prompt: userPrompt,
            history: historyForRequest,
            systemPrompt: systemPromptValue
          };
          if (imageAttachment) {
            payload.image = { ...imageAttachment };
          }
          debugPromptLog("request", payload);
          const response = await requestPromptTemplate2(payload);
          settled = true;
          debugPromptLog("response", response);
          const templateText = response && typeof response.template === "string" ? response.template.trim() : "";
          const nextTitle = typeof response.title === "string" ? response.title.trim() : null;
          const nextDescription = typeof response.description === "string" ? response.description.trim() : null;
          const currentConfig = getConfig ? getConfig() : null;
          const nextWork = filterAllowedWork(response.work, currentConfig);
          if (response.error || !templateText) {
            stopStreaming(stream);
            setGeneratingState(false);
            const errMsg = response.error || "Prompt returned no content.";
            if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
              promptHistory[pendingAssistantIndex].content = errMsg;
              setHistory(promptHistory);
            } else {
              setHistory([...promptHistory, { role: "assistant", content: errMsg }].slice(-12));
              pendingAssistantIndex = promptHistory.length - 1;
            }
            writeStoredHistory(activePath, promptHistory);
            renderHistory({ forceScroll: true });
            if (statusEl) {
              statusEl.textContent = errMsg;
              statusEl.className = "edit-status";
            }
            renderSummary(errMsg);
            return;
          }
          stopStreaming(stream);
          if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
            promptHistory[pendingAssistantIndex].content = templateText;
            setHistory(promptHistory);
          } else {
            setHistory([...promptHistory, { role: "assistant", content: templateText }].slice(-12));
            pendingAssistantIndex = promptHistory.length - 1;
          }
          writeStoredHistory(activePath, promptHistory);
          renderHistory({ forceScroll: true });
          if (streamToggleEl && streamToggleEl.checked && templateText) {
            startStreaming({
              stream,
              targetIndex: pendingAssistantIndex != null ? pendingAssistantIndex : promptHistory.length - 1,
              fullText: templateText,
              history: promptHistory,
              renderHistory: () => renderHistory({ forceScroll: true })
            });
          }
          renderContext();
          if (response.systemPrompt && systemPromptEl && !systemPromptEl.value.trim()) {
            systemPromptEl.value = response.systemPrompt;
            savePromptSettings(readSettings());
          }
          if (drawerForm) {
            const templateField = drawerForm.querySelector("#edit-work-template");
            if (templateField) {
              templateField.value = templateText;
            }
            const layoutNameField = drawerForm.querySelector("#edit-work-layout");
            if (layoutNameField && !layoutNameField.value.trim()) {
              layoutNameField.value = "default-layout";
            }
            if (nextWork && typeof nextWork.type === "string") {
              const workTypeField = drawerForm.querySelector("#edit-work-type");
              if (workTypeField) {
                workTypeField.value = nextWork.type;
              }
            }
          }
          const titleField = document.getElementById("edit-title");
          if (titleField && nextTitle !== null) {
            titleField.value = nextTitle;
          }
          const descriptionField = document.getElementById("edit-description");
          if (descriptionField && nextDescription !== null) {
            descriptionField.value = nextDescription;
          }
          const elements2 = drawerForm ? drawerForm.elements : null;
          const layoutPayload = {
            name: (((_a = elements2 == null ? void 0 : elements2.work_layout) == null ? void 0 : _a.value) || "default-layout").trim(),
            engine: "lightncandy",
            template: templateText
          };
          if (response.model) {
            layoutPayload.model = response.model;
          }
          const savePayload = {
            path: activePath,
            layout: layoutPayload
          };
          if (nextTitle !== null) {
            savePayload.title = nextTitle;
          }
          if (nextDescription !== null) {
            savePayload.description = nextDescription;
          }
          if (nextWork) {
            savePayload.work = nextWork;
          }
          await saveConfig(savePayload, statusEl);
          if (statusEl) {
            const providerLabel2 = response.provider || payload.provider;
            const modelLabel2 = response.model || payload.model;
            statusEl.textContent = `Template updated via ${providerLabel2}${modelLabel2 ? ` \xB7 ${modelLabel2}` : ""}`;
            statusEl.className = "edit-status edit-status-success";
          }
          const providerLabel = response.provider || payload.provider;
          const modelLabel = response.model || payload.model || "";
          const extra = [];
          if (nextTitle !== null) extra.push("title");
          if (nextDescription !== null) extra.push("description");
          if (nextWork && Object.keys(nextWork).length) extra.push(`work: ${Object.keys(nextWork).join(", ")}`);
          const summaryText = `Saved ${templateText.length} HBS chars via ${providerLabel}${modelLabel ? ` \xB7 ${modelLabel}` : ""}${extra.length ? ` \xB7 updated ${extra.join("; ")}` : ""}`;
          renderSummary(summaryText);
          clearAttachment();
          reloadViewer();
        } catch (err) {
          settled = true;
          stopStreaming(stream);
          setGeneratingState(false);
          debugPromptLog("error", err);
          if (statusEl) {
            statusEl.textContent = "Prompt failed.";
            statusEl.className = "edit-status";
          }
          const errMsg = "Prompt failed.";
          if (pendingAssistantIndex !== null && promptHistory[pendingAssistantIndex]) {
            promptHistory[pendingAssistantIndex].content = errMsg;
            setHistory(promptHistory);
          } else {
            setHistory([...promptHistory, { role: "assistant", content: errMsg }].slice(-12));
          }
          writeStoredHistory(activePath, promptHistory);
          renderHistory({ forceScroll: true });
          renderSummary(errMsg);
        } finally {
          window.clearTimeout(fallbackTimer);
          setGeneratingState(false);
          isSending = false;
          promptInputEl.focus();
        }
      };
      promptSendEl.addEventListener("click", () => {
        void sendPrompt();
      });
      if (promptAttachEl && promptImageInputEl) {
        promptAttachEl.addEventListener("click", () => {
          promptImageInputEl.click();
        });
        promptImageInputEl.addEventListener("change", async () => {
          const file = promptImageInputEl.files && promptImageInputEl.files[0] ? promptImageInputEl.files[0] : null;
          if (!file) {
            return;
          }
          await attachImageFile(file);
        });
      }
      if (promptAttachmentRemoveEl) {
        promptAttachmentRemoveEl.addEventListener("click", () => {
          clearAttachment();
          if (statusEl) {
            statusEl.textContent = "Image removed.";
            statusEl.className = "edit-status";
          }
        });
      }
      promptInputEl.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey && !event.isComposing) {
          event.preventDefault();
          void sendPrompt();
        }
      });
      promptInputEl.addEventListener("paste", (event) => {
        var _a;
        const items = ((_a = event.clipboardData) == null ? void 0 : _a.items) ? Array.from(event.clipboardData.items) : [];
        const imageItem = items.find((item) => typeof item.type === "string" && item.type.startsWith("image/"));
        if (!imageItem) {
          return;
        }
        const file = imageItem.getAsFile();
        if (!file) {
          return;
        }
        event.preventDefault();
        void attachImageFile(file);
      });
    }
  }

  // src/assets/js/edit/drawer.js
  function renderEditDrawer({
    editDrawer,
    editRequested: editRequested2,
    config,
    status,
    onClose,
    onSubmit
  }) {
    if (!editDrawer) {
      return { drawerForm: null, drawerStatus: null };
    }
    if (!editRequested2) {
      editDrawer.hidden = true;
      editDrawer.classList.remove("edit-drawer-open");
      return { drawerForm: null, drawerStatus: null };
    }
    if (!config || (status == null ? void 0 : status.error)) {
      editDrawer.innerHTML = "";
      return { drawerForm: null, drawerStatus: null };
    }
    if (!(status == null ? void 0 : status.allowed)) {
      editDrawer.innerHTML = "";
      return { drawerForm: null, drawerStatus: null };
    }
    const layoutState = getLayoutState(config);
    const layoutDirectory = layoutState.directory || ".layout";
    const layoutAssets = Array.isArray(layoutState.assets) ? layoutState.assets : [];
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
                        <span>${label} <span class="opacity-60">(${type})</span></span>
                    </label>
                `;
      }).join("") : '<div class="edit-tree-item">No items found.</div>';
    }
    const layoutAssetsHtml = layoutAssets.length ? `
            <div>
                <label class="edit-label">Layout assets</label>
                <div class="edit-tree">
                    ${layoutAssets.map((asset) => `
                        <div class="edit-tree-item">
                            <span>${escapeHtml(asset.path || asset.name || "")}</span>
                        </div>
                    `).join("")}
                </div>
            </div>
        ` : "";
    editDrawer.innerHTML = `
        <div class="drawer-header">
            <h4 class="drawer-title">More settings</h4>
            <button type="button" class="drawer-close" id="editDrawerClose">&times;</button>
        </div>
        <div class="edit-status" id="editDrawerStatus"></div>
        <form id="editDrawerForm" class="edit-form">
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-link">Link</label>
                    <input class="form-input" id="edit-link" type="text" name="link" value="${escapeHtml(config.link || "")}">
                </div>
                <div>
                    <label class="edit-label" for="edit-url">URL</label>
                    <input class="form-input" id="edit-url" type="text" name="url" value="${escapeHtml(config.url || "")}">
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work Type</label>
                    <input class="form-input" id="edit-work-type" type="text" name="work_type" value="${escapeHtml((config.work || {}).type || "")}">
                </div>
                <div>
                    <label class="edit-label" for="edit-work-layout">Work Layout Name</label>
                    <input class="form-input" id="edit-work-layout" type="text" name="work_layout" value="${escapeHtml(layoutState.mode)}">
                </div>
            </div>
            <div>
                <label class="edit-label" for="edit-work-template">Work Layout Template (HBS)</label>
                <textarea class="form-textarea" id="edit-work-template" name="work_template">${escapeHtml(layoutState.template)}</textarea>
            </div>
            <div class="small-note">Layout files live in <code>${escapeHtml(layoutDirectory)}</code>. Put thumbnails, background images, and other layout-specific files there.</div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-css">Layout CSS</label>
                    <textarea class="form-textarea" id="edit-layout-css" name="layout_css">${escapeHtml(layoutState.css)}</textarea>
                </div>
                <div>
                    <label class="edit-label" for="edit-layout-js">Layout JS</label>
                    <textarea class="form-textarea" id="edit-layout-js" name="layout_js">${escapeHtml(layoutState.js)}</textarea>
                </div>
            </div>
            ${layoutAssetsHtml}
            ${(status == null ? void 0 : status.target) !== "file" ? `
            <div>
                <label class="edit-label">Visible items</label>
                <div class="edit-tree">${treeHtml}</div>
            </div>
            ` : ""}
            <div class="edit-actions">
                <button class="btn" type="submit">Save advanced</button>
            </div>
        </form>
    `;
    const drawerClose = editDrawer.querySelector("#editDrawerClose");
    if (drawerClose && typeof onClose === "function") {
      drawerClose.addEventListener("click", () => onClose());
    }
    const drawerStatus = editDrawer.querySelector("#editDrawerStatus");
    const drawerForm = editDrawer.querySelector("#editDrawerForm");
    if (drawerForm && drawerStatus && typeof onSubmit === "function") {
      drawerForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const treeVisible = (status == null ? void 0 : status.target) !== "file" ? Array.from(editDrawer.querySelectorAll('input[name="tree_visible"]:checked')).map((input) => input.value) : [];
        onSubmit({
          elements: drawerForm.elements,
          statusEl: drawerStatus,
          treeVisible
        });
      });
    }
    return { drawerForm, drawerStatus };
  }

  // src/assets/js/edit/prompt-window.js
  function renderPromptWindow(settings = {}) {
    return `
        <div class="prompt-window prompt-inline" id="promptWindow">
            <div class="prompt-header">
                <div>
                    <h4 class="edit-panel-title">Prompt edit window</h4>
                    <div class="small-note">Chat + completion helper</div>
                </div>
            </div>
            <details class="prompt-system" open>
                <summary class="prompt-system-summary">Connection</summary>
                <div class="edit-grid prompt-grid">
                    <div>
                        <label class="edit-label" for="prompt-provider">Provider</label>
                        <select class="form-input" id="prompt-provider">
                            <option value="local">Local URL</option>
                            <option value="openai">OpenAI</option>
                            <option value="gemini">Gemini</option>
                        </select>
                    </div>
                    <div>
                        <label class="edit-label" for="prompt-model">Model</label>
                        <input class="form-input" id="prompt-model" type="text" value="${escapeHtml(settings.model || "")}" placeholder="optional">
                    </div>
                    <div>
                        <label class="edit-label" for="prompt-api-key">API key (stored in localStorage)</label>
                        <input class="form-input" id="prompt-api-key" type="password" value="${escapeHtml(settings.apiKey || "")}">
                    </div>
                </div>
                <div class="prompt-settings-actions">
                    <button class="btn btn-secondary" type="button" id="prompt-settings-reset">Reset settings</button>
                </div>
                <div id="prompt-endpoint-row">
                    <label class="edit-label" for="prompt-endpoint">Local endpoint URL</label>
                    <input class="form-input" id="prompt-endpoint" type="text" value="${escapeHtml(settings.endpoint || "")}" placeholder="http://localhost:1234/generate">
                </div>
            </details>
            <details class="prompt-system" open>
                <summary class="prompt-system-summary">System prompt (description &rarr; HBS component)</summary>
                <textarea class="form-textarea prompt-textarea" id="prompt-system" placeholder="Set the instruction your model should follow.">${escapeHtml(settings.systemPrompt || "")}</textarea>
                <div class="prompt-system-footer">
                    <span class="small-note">Used for chat + completions. Stored only in this browser.</span>
                    <button class="btn btn-secondary" type="button" id="prompt-system-reset">Reset default</button>
                </div>
            </details>
            <div class="prompt-summary" id="promptSummary">
                <div class="prompt-summary-title">Template summary</div>
                <div class="prompt-summary-body">Waiting for response...</div>
            </div>
            <div class="prompt-generation" id="promptGeneration" hidden>
                <span class="prompt-generation-pulse" aria-hidden="true"></span>
                <span class="prompt-generation-label" id="promptGenerationLabel">Generating answer...</span>
            </div>
            <div class="prompt-allowed">
                <span class="prompt-dot"></span> Editable via prompt: <strong>title</strong>, <strong>description</strong>, <strong>work.*</strong>
            </div>
            <div class="prompt-messages" id="promptMessages"></div>
            <div class="prompt-context" id="promptContext">
                <div class="prompt-context-title">Placeholders</div>
                <div class="prompt-context-body">
                    <div>{{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}</div>
                    <div>{{> default-layout}}, {{> works}}, {{> work}}, {{work.key}}</div>
                </div>
            </div>
            <input id="prompt-image-input" type="file" accept="image/*" hidden>
            <div class="prompt-attachment" id="promptAttachment" hidden>
                <div class="prompt-attachment-preview-wrap">
                    <img class="prompt-attachment-preview" id="promptAttachmentPreview" alt="Prompt attachment preview">
                </div>
                <div class="prompt-attachment-meta">
                    <div class="prompt-attachment-name" id="promptAttachmentName">Image attached</div>
                    <div class="small-note">Clipboard paste and image uploads are supported.</div>
                </div>
                <button class="btn btn-secondary" type="button" id="prompt-attachment-remove">Remove image</button>
            </div>
            <textarea class="prompt-input" id="prompt-input" placeholder="Describe the component you want..."></textarea>
            <div class="prompt-actions">
                <div class="prompt-actions-left">
                    <button class="btn" type="button" id="prompt-send">Send</button>
                    <button class="btn btn-secondary" type="button" id="prompt-attach">Attach image</button>
                    <button class="btn btn-secondary" type="button" id="prompt-clear">Clear</button>
                </div>
                <label class="prompt-inline-toggle">
                    <input class="prompt-inline-toggle-input" type="checkbox" id="prompt-stream" ${settings.streamPreview === false ? "" : "checked"}>
                    Stream response
                </label>
            </div>
            <div class="small-note">Press <code>Enter</code> to send. Use <code>Shift+Enter</code> for a new line.</div>
            <div class="small-note">Paste an image from the clipboard directly into the prompt input to attach it.</div>
            <div class="small-note">Template responses are saved to <code>.layout/template.hbs</code> for the LightnCandy renderer.</div>
        </div>
    `;
  }

  // src/assets/js/edit/panel.js
  function renderEditPanel({
    editPanel,
    editRequested: editRequested2,
    config,
    status,
    onTitleInput,
    onDescriptionInput,
    onSubmit,
    onToggleDrawer
  }) {
    if (!editPanel) {
      return { statusEl: null, promptRoot: null };
    }
    if (!editRequested2) {
      editPanel.hidden = true;
      return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!config || (status == null ? void 0 : status.error)) {
      const message = (status == null ? void 0 : status.error) || "Edit mode is unavailable.";
      editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(message)}</div>
        `;
      return { statusEl: null, promptRoot: null };
    }
    if (!(status == null ? void 0 : status.allowed)) {
      editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create a file named <code>.edit.allow</code> in the site root to enable edit mode.</div>
        `;
      return { statusEl: null, promptRoot: null };
    }
    const label = (status == null ? void 0 : status.target) === "file" ? "Edit mode (file)" : "Edit mode (folder)";
    const settings = loadPromptSettings();
    editPanel.innerHTML = `
        <h3 class="edit-panel-title">${label}</h3>
        <div class="edit-status" id="editInlineStatus"></div>
        <form id="inlineEditForm" class="edit-inline">
            <div>
                <label class="edit-label" for="edit-title">Title</label>
                <input class="form-input" id="edit-title" type="text" name="title" value="${escapeHtml(config.title || "")}">
            </div>
            <div>
                <label class="edit-label" for="edit-description">Description</label>
                <textarea class="form-textarea" id="edit-description" name="description">${escapeHtml(config.description || "")}</textarea>
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
    const form = editPanel.querySelector("#inlineEditForm");
    const statusEl = editPanel.querySelector("#editInlineStatus");
    const moreToggle = editPanel.querySelector("#editMoreToggle");
    const changeLayoutButton = editPanel.querySelector("#editChangeLayout");
    const titleInput = editPanel.querySelector("#edit-title");
    const descInput = editPanel.querySelector("#edit-description");
    const promptRoot = editPanel.querySelector("#promptWindow");
    if (titleInput && typeof onTitleInput === "function") {
      titleInput.addEventListener("input", () => {
        onTitleInput(titleInput.value);
      });
    }
    if (descInput && typeof onDescriptionInput === "function") {
      descInput.addEventListener("input", () => {
        onDescriptionInput(descInput.value);
      });
    }
    if (form && typeof onSubmit === "function") {
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        onSubmit({
          elements: form.elements,
          statusEl
        });
      });
    }
    if (moreToggle && typeof onToggleDrawer === "function") {
      moreToggle.addEventListener("click", () => onToggleDrawer());
    }
    if (changeLayoutButton && typeof onToggleDrawer === "function") {
      changeLayoutButton.addEventListener("click", () => onToggleDrawer());
    }
    return { statusEl, promptRoot };
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
            html += `<h3 class="folder-meta-title"><a class="folder-meta-link" href="${lnk}" target="contentFrame">${folderConfig.title}</a></h3>`;
          } else {
            html += `<h3 class="folder-meta-title">${folderConfig.title}</h3>`;
          }
        }
        if (folderConfig.description) {
          html += `<p class="folder-meta-desc">${folderConfig.description}</p>`;
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
      editToggle.classList.toggle("edit-toggle-on", editRequested2);
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
        if (statusEl) {
          statusEl.textContent = "Config saved.";
          statusEl.className = "edit-status edit-status-success";
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
        editDrawer.classList.remove("edit-drawer-open");
        editDrawer.hidden = true;
        return;
      }
      editDrawer.hidden = false;
      editDrawer.classList.add("edit-drawer-open");
    }
    function renderEditUI(config, status) {
      const panelState = renderEditPanel({
        editPanel,
        editRequested: editRequested2,
        config,
        status,
        onTitleInput: (value) => {
          if (!editConfig) {
            return;
          }
          editConfig.title = value;
          if ((status == null ? void 0 : status.target) !== "file") {
            folderConfig = editConfig;
            renderFolderMeta();
          }
        },
        onDescriptionInput: (value) => {
          if (!editConfig) {
            return;
          }
          editConfig.description = value;
          if ((status == null ? void 0 : status.target) !== "file") {
            folderConfig = editConfig;
            renderFolderMeta();
          }
        },
        onSubmit: async ({ elements: elements3, statusEl }) => {
          var _a, _b;
          const selection = getActiveSelection();
          const payload = {
            path: selection.path,
            title: (((_a = elements3.title) == null ? void 0 : _a.value) || "").trim(),
            description: (((_b = elements3.description) == null ? void 0 : _b.value) || "").trim()
          };
          await saveConfig(payload, statusEl);
        },
        onToggleDrawer: () => {
          drawerOpen = !drawerOpen;
          syncDrawerVisibility();
        }
      });
      const drawerState = renderEditDrawer({
        editDrawer,
        editRequested: editRequested2,
        config,
        status,
        onClose: () => {
          drawerOpen = false;
          syncDrawerVisibility();
        },
        onSubmit: async ({ elements: elements3, statusEl, treeVisible }) => {
          var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j;
          const layoutPayload = {
            name: (((_a = elements3.work_layout) == null ? void 0 : _a.value) || "").trim(),
            engine: "lightncandy",
            template: (_c = (_b = elements3.work_template) == null ? void 0 : _b.value) != null ? _c : "",
            css: (_e = (_d = elements3.layout_css) == null ? void 0 : _d.value) != null ? _e : "",
            js: (_g = (_f = elements3.layout_js) == null ? void 0 : _f.value) != null ? _g : ""
          };
          const selection = getActiveSelection();
          const payload = {
            path: selection.path,
            link: (((_h = elements3.link) == null ? void 0 : _h.value) || "").trim(),
            url: (((_i = elements3.url) == null ? void 0 : _i.value) || "").trim(),
            work: {
              type: (((_j = elements3.work_type) == null ? void 0 : _j.value) || "").trim()
            },
            layout: layoutPayload
          };
          if ((status == null ? void 0 : status.target) !== "file") {
            payload.treeVisible = treeVisible;
          }
          await saveConfig(payload, statusEl);
        }
      });
      if (panelState.promptRoot) {
        bindPromptWindow({
          root: panelState.promptRoot,
          statusEl: panelState.statusEl,
          drawerForm: drawerState.drawerForm,
          getActiveSelection,
          getConfig: () => editConfig,
          requestPromptTemplate,
          saveConfig
        });
      }
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
    const initialQueryPath = new URLSearchParams(window.location.search).get("path") || "";
    function showNavLoading() {
      if (!navList) return;
      navList.innerHTML = `
            <div id="navLoading" class="loading-row" style="display:flex;align-items:center;">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
        `;
    }
    function loadNav(relPath = "") {
      if (!navList) return;
      showNavLoading();
      fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery2}`).then((response) => response.text()).then((html) => {
        const extracted = extractNavHtml(html) || "";
        if (extracted.trim()) {
          navList.innerHTML = extracted;
          navList.dataset.loaded = "1";
        } else {
          navList.dataset.stale = "1";
        }
      }).catch(() => {
        navList.dataset.error = "1";
      });
    }
    function loadCurrentFolderInIframe() {
      if (contentFrame && currentPathForIframe2 !== null && currentPathForIframe2 !== void 0) {
        const isFile = /\.[^\\/]+$/.test(currentPathForIframe2);
        contentFrame.src = isFile ? `?view=1&file=${encodeURIComponent(currentPathForIframe2)}` : `?view=1&path=${encodeURIComponent(currentPathForIframe2)}`;
        if (activeLink) {
          activeLink.classList.remove("nav-link-active");
          activeLink = null;
        }
      }
      if (navList && !navList.dataset.loaded) {
        loadNav(initialQueryPath);
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
      contentFrame.src = isFile ? `?view=1&file=${encodeURIComponent(hashPath)}` : `?view=1&path=${encodeURIComponent(hashPath)}`;
      if (!hashPath || !navList) {
        return;
      }
      const parts = hashPath.split("/");
      const folderPath = isFile ? parts.slice(0, parts.length - 1).join("/") : hashPath;
      const fileName = isFile ? parts[parts.length - 1] : "";
      if (sidebarLoading) {
        sidebarLoading.style.display = "block";
      }
      fetch(`?ajax=1&path=${encodeURIComponent(folderPath)}${editQuery2}`).then((response) => response.text()).then((html) => {
        const extracted = extractNavHtml(html) || "";
        if (extracted.trim()) {
          navList.innerHTML = extracted;
          navList.dataset.loaded = "1";
        } else {
          navList.dataset.stale = "1";
        }
        if (isFile) {
          const fileEls = navList.querySelectorAll("a[data-file]");
          fileEls.forEach((el) => {
            el.classList.remove("nav-link-active");
            if (el.getAttribute("data-file") === fileName) {
              el.classList.add("nav-link-active");
            }
          });
        }
        if (sidebarLoading) {
          sidebarLoading.style.display = "none";
        }
      }).catch(() => {
        navList.dataset.error = "1";
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
      let resolvedPath = false;
      if (target.hasAttribute("href") && target.getAttribute("href").startsWith("?path=")) {
        const href = target.getAttribute("href") || "";
        const params = new URLSearchParams(href.replace(/^\?/, ""));
        relPath = params.get("path") || "";
        resolvedPath = true;
      } else if (target.dataset.src) {
        relPath = target.dataset.src;
        resolvedPath = true;
      }
      if (!resolvedPath) {
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
          const extracted = extractNavHtml(html) || "";
          if (extracted.trim()) {
            navList.innerHTML = extracted;
            navList.dataset.loaded = "1";
          } else {
            navList.dataset.stale = "1";
          }
          contentFrame.src = `?view=1&path=${encodeURIComponent(relPath)}`;
          window.location.hash = "/" + relPath.replace(/^\/+/, "");
          if (initEditMode) {
            initEditMode();
          }
        }).catch(() => {
          navList.dataset.error = "1";
          contentFrame.src = `?view=1&path=${encodeURIComponent(relPath)}`;
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
        activeLink.classList.remove("nav-link-active");
      }
      target.classList.add("nav-link-active");
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
