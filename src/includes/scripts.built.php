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
  async function requestEditUpload(payload) {
    const url = buildCmsUrl("upload", payload.path || "");
    const formData = new FormData();
    formData.set("source", payload.source || "upload");
    for (const file of payload.files || []) {
      formData.append("files[]", file);
    }
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json"
        },
        body: formData
      });
      if (!res.ok) {
        const data = await res.json().catch(() => null);
        return data || { allowed: false, error: "Upload endpoint unavailable." };
      }
      return await res.json();
    } catch (err) {
      return { allowed: false, error: "Upload endpoint unavailable." };
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
        const data = await res.json().catch(() => null);
        return data || { error: `Prompt endpoint failed (HTTP ${res.status}).` };
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
  function inferFilePath(path = "") {
    return /\.[^\\/]+$/.test(path);
  }
  function normalizeSelectionPath(path = "") {
    return String(path || "").replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
  }
  function isVirtualLayoutPath(path = "") {
    const normalized = normalizeSelectionPath(path);
    return normalized === ".layout" || normalized.endsWith("/.layout");
  }
  function subjectPathFromVirtualLayout(path = "") {
    const normalized = normalizeSelectionPath(path);
    if (!isVirtualLayoutPath(normalized)) {
      return normalized;
    }
    if (normalized === ".layout") {
      return "";
    }
    return normalized.slice(0, -"/.layout".length);
  }
  function buildVirtualLayoutPath(path = "") {
    const normalized = normalizeSelectionPath(path);
    return normalized ? `${normalized}/.layout` : ".layout";
  }
  function getSelectionFromPath(path = "") {
    const normalized = normalizeSelectionPath(path);
    const isLayout = isVirtualLayoutPath(normalized);
    const previewPath = isLayout ? subjectPathFromVirtualLayout(normalized) : normalized;
    const previewIsFile = inferFilePath(previewPath);
    return {
      path: normalized,
      isFile: !isLayout && previewIsFile,
      isLayout,
      layoutPath: isLayout ? previewPath : "",
      layoutIsFile: isLayout ? previewIsFile : false,
      previewPath,
      previewIsFile
    };
  }
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
      return getSelectionFromPath(hashPath);
    }
    const params = new URLSearchParams(window.location.search);
    return getSelectionFromPath(params.get("path") || "");
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
    var _a, _b;
    const layoutValue = (_a = config == null ? void 0 : config.work) == null ? void 0 : _a.layout;
    const normalizePreset = (value) => {
      const preset = String(value || "").trim();
      return ["actual", "none", "custom"].includes(preset) ? preset : "";
    };
    const inferredSection = layoutValue && typeof layoutValue === "object" && !Array.isArray(layoutValue) && layoutValue.section ? String(layoutValue.section) : (config == null ? void 0 : config.type) === "folder" || ((_b = config == null ? void 0 : config.work) == null ? void 0 : _b.type) === "folder" && !(config == null ? void 0 : config.name) ? "works" : "work";
    const normalize = (state) => {
      const rawMode = state.mode || state.name || "poff-layout";
      const mode = rawMode === "poff" ? "poff-layout" : rawMode === "filesystem" ? "filesystem-layout" : rawMode;
      const storage = state.storage || "";
      const directory = state.directory || "";
      let preset = normalizePreset(state.preset) || "actual";
      if (!normalizePreset(state.preset)) {
        if (mode === "none") {
          preset = "none";
        } else if (storage === "filesystem" && (directory === ".layout" || directory.startsWith(".works/"))) {
          preset = "custom";
        }
      }
      const sourceLabel = mode === "none" ? "No outer layout" : storage === "filesystem" ? `Filesystem: ${directory || ".layout"}` : storage === "default" ? "Built-in poff-layout" : "Current resolved layout";
      return {
        ...state,
        mode,
        storage,
        directory,
        inheritedDirectory: state.inheritedDirectory || "",
        section: state.section || inferredSection,
        sectionTemplate: state.sectionTemplate || "",
        sectionDirectory: state.sectionDirectory || "",
        phpTemplate: state.phpTemplate || "",
        preset,
        sourceLabel
      };
    };
    if (layoutValue && typeof layoutValue === "object" && !Array.isArray(layoutValue)) {
      return normalize({
        mode: layoutValue.name || layoutValue.mode || layoutValue.value || "",
        template: layoutValue.template || "",
        css: layoutValue.css || "",
        js: layoutValue.js || "",
        model: layoutValue.model || "",
        engine: layoutValue.engine || "lightncandy",
        directory: layoutValue.directory || "",
        storage: layoutValue.storage || "",
        inheritedDirectory: layoutValue.inheritedDirectory || "",
        section: layoutValue.section || inferredSection,
        sectionTemplate: layoutValue.sectionTemplate || "",
        sectionDirectory: layoutValue.sectionDirectory || "",
        phpTemplate: layoutValue.phpTemplate || "",
        preset: layoutValue.preset || "",
        assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : []
      });
    }
    if (typeof layoutValue === "string") {
      return normalize({ mode: layoutValue, template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", storage: "", section: inferredSection, sectionTemplate: "", sectionDirectory: "", assets: [] });
    }
    return normalize({ mode: "poff-layout", template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", storage: "", section: inferredSection, sectionTemplate: "", sectionDirectory: "", assets: [] });
  }

  // src/assets/js/edit/prompt/constants.js
  var promptSettingsKey = "poffEditPromptSettings";
  var promptHistoryKey = "poffEditPromptHistory";
  var workSystemPrompt = [
    "You are a Handlebars (HBS) template generator for this single-page CMS.",
    "Transform the user description into one HBS template string for the wrapped inner section partial rendered by LightnCandy.",
    'Return a JSON object with a required "template" string and optional "title", "description", and "work" object.',
    'When the user asks to change work.* values such as autoplay, loop, muted, poster, type, or layout, include those updates in "work".',
    "The prompt edits the wrapped content partial, not the outer layout wrapper. Save target is work.hbs for files and works.hbs for folders inside the active item layout folder.",
    "Keep the current outer layout chain active unless the user explicitly changes layout mode separately. Do not return the outer wrapper template here.",
    "Important: return only the inner partial fragment. Do not return <html>, <body>, full page shells, app sidebars, or an outer wrapper that duplicates template.hbs.",
    "For files, work.hbs should render only the inner media/content block that the wrapper inserts into {{> work}}.",
    "For folders, works.hbs should render only the inner listing/content block that the wrapper inserts into {{> works}}. Do not replace the folder wrapper unless the user is explicitly editing layout mode.",
    "Default layout technique: the outer layout stays in template.hbs and wraps {{> works}} for folders or {{> work}} for files. Built-in wrapper partials are {{> poff-layout}} and {{> filesystem-layout}}.",
    "Theme overrides can target .poff-default-layout from top to down with CSS variables like --poff-shell-bg, --poff-shell-color, --poff-shell-title-color, --poff-shell-description-color, --poff-shell-footer-color, --poff-shell-header-border, --poff-shell-footer-border, --poff-shell-card-bg, and --poff-shell-card-border.",
    "Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.",
    "Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.",
    "Inputs available: {{pageLink}} / {{pageUrl}} / {{workUrl}} / {{viewUrl}} / {{viewerHref}} for the templated CMS viewer URL, {{srcUrl}} / {{sourceUrl}} / {{assetUrl}} / {{assetLink}} / {{rawHref}} for direct source URLs, {{path}} for the raw relative file path, plus {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.",
    "Prompt context JSON includes current.templateTarget for the wrapped partial save target and current.layoutTemplateTarget for the outer wrapper path. Edit the wrapped partial target by default.",
    "Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.",
    "For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.",
    "Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.",
    "You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.",
    "If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled."
  ].join("\n");
  var layoutSystemPrompt = [
    "You are a Handlebars (HBS) layout generator for this single-page CMS.",
    "Transform the user description into one HBS template string for the outer layout wrapper rendered by LightnCandy.",
    'Return a JSON object with a required "template" string and optional "work" object.',
    "The prompt edits the outer layout wrapper template, not the wrapped inner work.hbs or works.hbs partial.",
    "Keep the wrapped content chain active: use {{> works}} for folders and {{> work}} for files inside the layout wrapper unless the user explicitly asks to remove or replace it.",
    "The wrapper owns the page shell and must wrap the inner partial. Return one outer template that includes {{> works}} or {{> work}} exactly once unless the user explicitly asks for a different structure.",
    "Use current.templateTarget as the active save target for this layout page. It follows the current layout mode: the resolved active wrapper for Actual, the local custom wrapper for Custom, and never the inner partial by default.",
    "current.layoutTemplateTarget is the local custom wrapper path if you explicitly switch to Custom. current.sectionTemplateTarget is the advanced inner partial path, not the default save target here.",
    "For images, icons, CSS backgrounds, or other assets owned by the layout wrapper, do not build URLs from {{path}}. {{path}} points to the current folder/file, not the layout asset folder.",
    "Use runtime layout URLs such as {{layout.baseHref}}/file.ext for local or inherited folder layout assets. Reusing the bundled default profile image should look like {{layout.baseHref}}/eggman_profile-image.jpg when the active wrapper comes from the built-in default layout bundle.",
    "Prompt context JSON includes current.layoutBaseHref, current.inheritedLayoutDirectory, and current.layoutAssets so you can choose the right asset path and understand whether the wrapper comes from a parent folder .layout.",
    "Theme overrides can target .poff-default-layout from top to down with CSS variables like --poff-shell-bg, --poff-shell-color, --poff-shell-title-color, --poff-shell-description-color, --poff-shell-footer-color, --poff-shell-header-border, --poff-shell-footer-border, --poff-shell-card-bg, and --poff-shell-card-border.",
    "Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.",
    "Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.",
    "Inputs available: {{pageLink}} / {{pageUrl}} / {{workUrl}} / {{viewUrl}} / {{viewerHref}} for the templated CMS viewer URL, {{srcUrl}} / {{sourceUrl}} / {{assetUrl}} / {{assetLink}} / {{rawHref}} for direct source URLs, {{path}} for the raw relative file path, plus {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.",
    "Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available.",
    "You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.",
    "If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled."
  ].join("\n");
  function getDefaultSystemPrompt(mode = "work") {
    return mode === "layout" ? layoutSystemPrompt : workSystemPrompt;
  }
  var defaultSystemPrompt = getDefaultSystemPrompt("work");
  var defaultPromptSettings = {
    provider: "openai",
    model: "gpt-4o-mini",
    endpoint: "",
    apiKey: "",
    systemPrompt: getDefaultSystemPrompt("work"),
    streamPreview: true
  };

  // src/assets/js/edit/prompt/storage.js
  function loadPromptSettings() {
    try {
      const stored = JSON.parse(localStorage.getItem(promptSettingsKey) || "{}");
      if (typeof stored.systemPrompt === "string") {
        const looksLikeLegacyDefault = stored.systemPrompt.includes("saved to .layout/template.hbs") || stored.systemPrompt.includes("Built-in wrapper partials are {{> poff-layout}} and {{> filesystem-layout}}");
        if (looksLikeLegacyDefault) {
          stored.systemPrompt = defaultPromptSettings.systemPrompt;
        }
      }
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
  function compactValue(value) {
    return String(value != null ? value : "").toLowerCase().replace(/[^a-z0-9]+/g, "");
  }
  function parseBooleanToken(token) {
    if (["true", "on", "yes", "1"].includes(token)) {
      return true;
    }
    if (["false", "off", "no", "0"].includes(token)) {
      return false;
    }
    return null;
  }
  function inferWorkChangesFromPrompt(prompt, config) {
    const work = config && typeof config === "object" && config.work && typeof config.work === "object" ? config.work : {};
    const compactPrompt = compactValue(prompt);
    if (!compactPrompt) {
      return null;
    }
    const nextWork = {};
    Object.entries(work).forEach(([key, value]) => {
      if (typeof value !== "boolean") {
        return;
      }
      const compactKey = compactValue(key);
      if (!compactKey) {
        return;
      }
      const tokenPatterns = [
        new RegExp(`set${compactKey}(?:to|=)?(true|false|on|off|yes|no|1|0)`),
        new RegExp(`(?:make|set)?${compactKey}(true|false|on|off|yes|no|1|0)`),
        new RegExp(`turn${compactKey}(on|off)`)
      ];
      for (const pattern of tokenPatterns) {
        const match = compactPrompt.match(pattern);
        if (match) {
          const parsed = parseBooleanToken(match[1]);
          if (parsed !== null) {
            nextWork[key] = parsed;
            return;
          }
        }
      }
      if (compactPrompt.includes(`enable${compactKey}`)) {
        nextWork[key] = true;
        return;
      }
      if (compactPrompt.includes(`disable${compactKey}`)) {
        nextWork[key] = false;
      }
    });
    return Object.keys(nextWork).length ? nextWork : null;
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
    var _a, _b, _c;
    const selection = typeof getActiveSelection2 === "function" ? getActiveSelection2() : { path: "", isFile: false };
    const config = typeof getConfig === "function" ? getConfig() || {} : {};
    const isLayout = !!(selection == null ? void 0 : selection.isLayout);
    const path = (_b = (_a = selection == null ? void 0 : selection.previewPath) != null ? _a : selection == null ? void 0 : selection.path) != null ? _b : "";
    const virtualPath = (selection == null ? void 0 : selection.path) || "";
    const name = path ? path.split(/[\\/]/).pop() : "";
    const isFile = isLayout ? !!(selection == null ? void 0 : selection.layoutIsFile) : (_c = selection == null ? void 0 : selection.isFile) != null ? _c : /\.[^\\/]+$/.test(path);
    const viewUrl = isFile ? `?view=1&file=${encodeURIComponent(path)}` : `?view=1&path=${encodeURIComponent(path)}`;
    const localLayoutDirectory = isFile ? `.works/${name || "item"}.layout` : ".layout";
    const sectionTemplateTarget = isFile ? `${localLayoutDirectory}/work.hbs` : `${localLayoutDirectory}/works.hbs`;
    const layoutTemplateTarget = `${localLayoutDirectory}/template.hbs`;
    const work = config && typeof config === "object" && config.work ? config.work : {};
    const layout = work && typeof work.layout === "object" ? work.layout : {};
    const layoutStorage = typeof (layout == null ? void 0 : layout.storage) === "string" ? String(layout.storage) : "";
    const resolvedLayoutDirectory = (layout == null ? void 0 : layout.directory) ? String(layout.directory) : "";
    const inheritedLayoutDirectory = (layout == null ? void 0 : layout.inheritedDirectory) ? String(layout.inheritedDirectory) : "";
    const presetEl = isLayout ? document.getElementById("edit-layout-preset") : null;
    const layoutPreset = isLayout && presetEl ? String(presetEl.value || "").trim() : "";
    const activeLayoutDirectory = (() => {
      if (!isLayout) {
        return resolvedLayoutDirectory || localLayoutDirectory;
      }
      if (layoutPreset === "custom") {
        return localLayoutDirectory;
      }
      if (layoutStorage === "filesystem" && resolvedLayoutDirectory) {
        return resolvedLayoutDirectory;
      }
      return localLayoutDirectory;
    })();
    const templateTarget = isLayout ? `${activeLayoutDirectory}/template.hbs` : sectionTemplateTarget;
    const tree = Array.isArray(config == null ? void 0 : config.tree) ? config.tree : [];
    const folderBasePath = ((selection == null ? void 0 : selection.isFile) ? path.split("/").slice(0, -1).join("/") : path).replace(/^\/+|\/+$/g, "");
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
    const refPreview = tree.slice(0, 4).map((item) => {
      const itemName = (item == null ? void 0 : item.name) || (item == null ? void 0 : item.path) || "";
      if (!itemName) {
        return "";
      }
      const rawItemPath = (item == null ? void 0 : item.path) || itemName;
      const itemPath = folderBasePath ? String(rawItemPath).startsWith(`${folderBasePath}/`) ? String(rawItemPath) : `${folderBasePath}/${rawItemPath}` : String(rawItemPath);
      const isItemFile = ((item == null ? void 0 : item.type) || "file") !== "folder";
      const itemPageLink = isItemFile ? `?view=1&file=${encodeURIComponent(itemPath)}` : `?view=1&path=${encodeURIComponent(itemPath)}`;
      const itemAssetUrl = isItemFile ? itemPath.split("/").map((part) => encodeURIComponent(part)).join("/") : `?path=${encodeURIComponent(itemPath)}`;
      return `${itemName} -> pageLink: ${itemPageLink}, srcUrl: ${itemAssetUrl}`;
    }).filter(Boolean).join(" | ");
    const layoutBaseHref = activeLayoutDirectory;
    const layoutAssetsPreview = Array.isArray(layout == null ? void 0 : layout.assets) ? layout.assets.slice(0, 4).map((asset) => {
      const assetPath = (asset == null ? void 0 : asset.path) ? String(asset.path) : "";
      if (!assetPath) {
        return "";
      }
      return `${assetPath} -> ${layoutBaseHref}/${assetPath}`;
    }).filter(Boolean).join(" | ") : "";
    return {
      path,
      virtualPath,
      isLayout,
      layoutPreset,
      name,
      pageLink: viewUrl,
      viewUrl,
      templateTarget,
      layoutTemplateTarget,
      sectionTemplateTarget,
      layoutBaseHref,
      inheritedLayoutDirectory,
      layoutAssetsPreview,
      workPreview,
      refPreview
    };
  }
  function renderPromptContext(contextEl, context) {
    if (!contextEl) {
      return;
    }
    const path = (context == null ? void 0 : context.path) || "";
    const virtualPath = (context == null ? void 0 : context.virtualPath) || "";
    const layoutPreset = (context == null ? void 0 : context.layoutPreset) || "";
    const name = (context == null ? void 0 : context.name) || "";
    const pageLink = (context == null ? void 0 : context.pageLink) || (context == null ? void 0 : context.viewUrl) || "";
    const viewUrl = (context == null ? void 0 : context.viewUrl) || "";
    const templateTarget = (context == null ? void 0 : context.templateTarget) || "";
    const layoutTemplateTarget = (context == null ? void 0 : context.layoutTemplateTarget) || "";
    const sectionTemplateTarget = (context == null ? void 0 : context.sectionTemplateTarget) || "";
    const layoutBaseHref = (context == null ? void 0 : context.layoutBaseHref) || "";
    const inheritedLayoutDirectory = (context == null ? void 0 : context.inheritedLayoutDirectory) || "";
    const layoutAssetsPreview = (context == null ? void 0 : context.layoutAssetsPreview) || "";
    const workPreview = (context == null ? void 0 : context.workPreview) || "";
    const refPreview = (context == null ? void 0 : context.refPreview) || "";
    contextEl.innerHTML = `
        ${(context == null ? void 0 : context.isLayout) ? `<div class="prompt-context-row"><strong>virtualPath</strong>: ${escapeHtml(virtualPath)}</div>` : ""}
        ${(context == null ? void 0 : context.isLayout) && layoutPreset ? `<div class="prompt-context-row"><strong>layoutPreset</strong>: ${escapeHtml(layoutPreset)}</div>` : ""}
        <div class="prompt-context-row"><strong>pageLink</strong>: ${escapeHtml(pageLink)}</div>
        <div class="prompt-context-row"><strong>path</strong>: ${escapeHtml(path)}</div>
        <div class="prompt-context-row"><strong>name</strong>: ${escapeHtml(name)}</div>
        <div class="prompt-context-row"><strong>viewUrl</strong>: ${escapeHtml(viewUrl)}</div>
        ${templateTarget ? `<div class="prompt-context-row"><strong>templateTarget</strong>: ${escapeHtml(templateTarget)}</div>` : ""}
        ${layoutTemplateTarget ? `<div class="prompt-context-row"><strong>layoutTemplateTarget (custom)</strong>: ${escapeHtml(layoutTemplateTarget)}</div>` : ""}
        ${sectionTemplateTarget ? `<div class="prompt-context-row"><strong>sectionTemplateTarget</strong>: ${escapeHtml(sectionTemplateTarget)}</div>` : ""}
        ${layoutBaseHref ? `<div class="prompt-context-row"><strong>layoutBaseHref</strong>: ${escapeHtml(layoutBaseHref)}</div>` : ""}
        ${inheritedLayoutDirectory ? `<div class="prompt-context-row"><strong>inheritedLayoutDirectory</strong>: ${escapeHtml(inheritedLayoutDirectory)}</div>` : ""}
        <div class="prompt-context-row"><strong>partials</strong>: ${escapeHtml("poff-layout, filesystem-layout, works, work")}</div>
        ${layoutAssetsPreview ? `<div class="prompt-context-row"><strong>layoutAssets</strong>: ${escapeHtml(layoutAssetsPreview)}</div>` : ""}
        ${refPreview ? `<div class="prompt-context-row"><strong>refs</strong>: ${escapeHtml(refPreview)}</div>` : ""}
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
  var summarizePromptRequest = (payload) => ({
    path: typeof (payload == null ? void 0 : payload.path) === "string" ? payload.path : "",
    provider: typeof (payload == null ? void 0 : payload.provider) === "string" ? payload.provider : "local",
    model: typeof (payload == null ? void 0 : payload.model) === "string" ? payload.model : "",
    endpoint: typeof (payload == null ? void 0 : payload.endpoint) === "string" ? payload.endpoint : "",
    promptLength: typeof (payload == null ? void 0 : payload.prompt) === "string" ? payload.prompt.length : 0,
    historyCount: Array.isArray(payload == null ? void 0 : payload.history) ? payload.history.length : 0,
    hasApiKey: typeof (payload == null ? void 0 : payload.apiKey) === "string" ? payload.apiKey.trim() !== "" : false,
    hasImage: !!(payload == null ? void 0 : payload.image),
    systemPromptLength: typeof (payload == null ? void 0 : payload.systemPrompt) === "string" ? payload.systemPrompt.length : 0
  });
  var summarizePromptResponse = (response, requestSummary) => ({
    path: (requestSummary == null ? void 0 : requestSummary.path) || "",
    provider: (response == null ? void 0 : response.provider) || (requestSummary == null ? void 0 : requestSummary.provider) || "local",
    model: (response == null ? void 0 : response.model) || (requestSummary == null ? void 0 : requestSummary.model) || "",
    allowed: (response == null ? void 0 : response.allowed) === true,
    hasTemplate: typeof (response == null ? void 0 : response.template) === "string" && response.template.trim() !== "",
    templateLength: typeof (response == null ? void 0 : response.template) === "string" ? response.template.trim().length : 0,
    error: typeof (response == null ? void 0 : response.error) === "string" ? response.error : ""
  });
  var summarizePromptError = (err, requestSummary) => ({
    path: (requestSummary == null ? void 0 : requestSummary.path) || "",
    provider: (requestSummary == null ? void 0 : requestSummary.provider) || "local",
    model: (requestSummary == null ? void 0 : requestSummary.model) || "",
    name: typeof (err == null ? void 0 : err.name) === "string" ? err.name : "Error",
    message: typeof (err == null ? void 0 : err.message) === "string" ? err.message : String(err || "Prompt failed.")
  });
  var updatePromptEditorFields = ({ templateText, nextTitle, nextDescription, nextWork, isLayoutTarget }) => {
    const templateSelectors = isLayoutTarget ? ["#edit-layout-primary-template"] : ["#edit-content-template"];
    templateSelectors.forEach((selector) => {
      document.querySelectorAll(selector).forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = templateText;
        }
      });
    });
    if (nextTitle !== null) {
      document.querySelectorAll("#edit-title").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = nextTitle;
        }
      });
    }
    if (nextDescription !== null) {
      document.querySelectorAll("#edit-description").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = nextDescription;
        }
      });
    }
    if (nextWork && typeof nextWork.type === "string") {
      document.querySelectorAll("#edit-work-type").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = nextWork.type;
        }
      });
    }
  };
  var debugPromptLog = (label, payload) => {
    try {
      console.info(`[prompt] ${label}`, payload);
    } catch (err) {
    }
  };
  var builtInSystemPrompts = /* @__PURE__ */ new Set([
    getDefaultSystemPrompt("work"),
    getDefaultSystemPrompt("layout")
  ]);
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
    const promptTemplateLabelEl = root.querySelector("#promptTemplateLabel");
    const promptTemplateCodeEl = root.querySelector("#promptTemplateCode");
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
    const currentPromptMode = () => (getActiveSelection2 == null ? void 0 : getActiveSelection2().isLayout) ? "layout" : "work";
    const currentDefaultSystemPrompt = () => getDefaultSystemPrompt(currentPromptMode());
    const syncModeAwareSystemPrompt = () => {
      if (!systemPromptEl) {
        return;
      }
      const currentValue = (systemPromptEl.value || "").trim();
      if (currentValue !== "" && !builtInSystemPrompts.has(currentValue)) {
        return;
      }
      const nextValue = currentDefaultSystemPrompt();
      if (systemPromptEl.value !== nextValue) {
        systemPromptEl.value = nextValue;
        savePromptSettings(readSettings());
      }
    };
    const setHistory = (nextHistory) => {
      const list = Array.isArray(nextHistory) ? nextHistory : [];
      promptHistory = tagHistory(list);
    };
    const renderHistory = (options = {}) => {
      renderPromptHistory(promptMessagesEl, promptHistory, stream.state, options);
    };
    const getCurrentTemplateField = () => {
      const selection = getActiveSelection2 ? getActiveSelection2() : null;
      const selector = (selection == null ? void 0 : selection.isLayout) ? "#edit-layout-primary-template" : "#edit-content-template";
      return document.querySelector(selector);
    };
    const renderTemplatePreview = () => {
      if (!promptTemplateCodeEl) {
        return;
      }
      const selection = getActiveSelection2 ? getActiveSelection2() : null;
      const templateField = getCurrentTemplateField();
      promptTemplateCodeEl.value = templateField && typeof templateField.value === "string" ? templateField.value : "";
      if (promptTemplateLabelEl) {
        promptTemplateLabelEl.textContent = (selection == null ? void 0 : selection.isLayout) ? "Current layout wrapper template" : "Current wrapped partial template";
      }
    };
    const renderContext = () => {
      const context = buildPromptContext({ getActiveSelection: getActiveSelection2, getConfig });
      activePath = context.path;
      renderPromptContext(promptContextEl, context);
      renderTemplatePreview();
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
      systemPromptEl.value = settings.systemPrompt || currentDefaultSystemPrompt();
    }
    if (streamToggleEl) {
      streamToggleEl.checked = settings.streamPreview !== false;
    }
    const readSettings = () => ({
      provider: providerEl ? providerEl.value : "local",
      model: modelEl ? modelEl.value : "",
      endpoint: endpointEl ? endpointEl.value : "",
      apiKey: apiKeyEl ? apiKeyEl.value : "",
      systemPrompt: ((systemPromptEl == null ? void 0 : systemPromptEl.value) || "").trim() || currentDefaultSystemPrompt(),
      streamPreview: streamToggleEl ? !!streamToggleEl.checked : true
    });
    let suppressSave = false;
    const applySettingsToUi = (s) => {
      suppressSave = true;
      if (providerEl) providerEl.value = s.provider || defaultPromptSettings.provider;
      if (modelEl) modelEl.value = s.model || "";
      if (endpointEl) endpointEl.value = s.endpoint || "";
      if (apiKeyEl) apiKeyEl.value = s.apiKey || "";
      if (systemPromptEl) systemPromptEl.value = s.systemPrompt || currentDefaultSystemPrompt();
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
        systemPromptEl.value = currentDefaultSystemPrompt();
        savePromptSettings(readSettings());
      });
    }
    if (settingsResetEl) {
      settingsResetEl.addEventListener("click", () => {
        const nextSettings = {
          ...defaultPromptSettings,
          systemPrompt: currentDefaultSystemPrompt()
        };
        applySettingsToUi(nextSettings);
        savePromptSettings(nextSettings);
        renderContext();
      });
    }
    updateProviderUi();
    syncModeAwareSystemPrompt();
    setHistory(readStoredHistory(activePath));
    renderHistory();
    renderContext();
    renderSummary("Waiting for response...");
    updateAttachmentUi();
    const reloadViewer = () => {
      const frame = document.getElementById("contentFrame");
      const selection = getActiveSelection2 ? getActiveSelection2() : { path: "", isFile: false };
      const selectionPath = selection && Object.prototype.hasOwnProperty.call(selection, "previewPath") ? selection.previewPath : void 0;
      const activeViewerPath = selectionPath != null ? selectionPath : activePath;
      if (frame && activeViewerPath !== null && activeViewerPath !== void 0) {
        window.dispatchEvent(new CustomEvent("poff:content-updated", {
          detail: {
            path: activeViewerPath,
            target: (selection == null ? void 0 : selection.previewIsFile) ? "file" : "folder"
          }
        }));
      }
    };
    const syncHistoryForPath = () => {
      const selection = getActiveSelection2 ? getActiveSelection2() : { path: "" };
      const nextPath = (selection == null ? void 0 : selection.path) || "";
      if (nextPath !== activePath) {
        activePath = nextPath;
        setHistory(readStoredHistory(activePath));
        syncModeAwareSystemPrompt();
        renderHistory();
        renderContext();
        renderSummary("Waiting for response...");
      }
    };
    window.addEventListener("hashchange", syncHistoryForPath);
    document.addEventListener("input", (event) => {
      const target = event.target;
      if (!(target instanceof HTMLTextAreaElement || target instanceof HTMLInputElement)) {
        return;
      }
      if (target.matches("#edit-content-template, #edit-layout-primary-template")) {
        renderTemplatePreview();
      }
    });
    const layoutPresetEl = document.getElementById("edit-layout-preset");
    if (layoutPresetEl) {
      layoutPresetEl.addEventListener("change", () => {
        renderContext();
      });
    }
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
        if (isSending || !promptInputEl.value.trim() && !imageAttachment) {
          return;
        }
        isSending = true;
        setGeneratingState(true, "Generating answer...");
        stopStreaming(stream);
        let pendingAssistantIndex = null;
        let settled = false;
        let requestSummary = null;
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
          const selection = getActiveSelection2 ? getActiveSelection2() : { path: activePath, previewPath: activePath, previewIsFile: false, isLayout: false };
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
          if (selection == null ? void 0 : selection.isLayout) {
            const layoutPresetEl2 = document.getElementById("edit-layout-preset");
            if (layoutPresetEl2 && typeof layoutPresetEl2.value === "string" && layoutPresetEl2.value.trim() !== "") {
              payload.layoutPreset = layoutPresetEl2.value.trim();
            }
          }
          if (imageAttachment) {
            payload.image = { ...imageAttachment };
          }
          requestSummary = summarizePromptRequest(payload);
          debugPromptLog("request", requestSummary);
          const response = await requestPromptTemplate2(payload);
          settled = true;
          debugPromptLog("response", summarizePromptResponse(response, requestSummary));
          const templateText = response && typeof response.template === "string" ? response.template.trim() : "";
          const nextTitle = typeof response.title === "string" ? response.title.trim() : null;
          const nextDescription = typeof response.description === "string" ? response.description.trim() : null;
          const isLayoutTarget = !!selection.isLayout;
          const currentConfig = getConfig ? getConfig() : null;
          const inferredWork = inferWorkChangesFromPrompt(userPrompt, currentConfig);
          const mergedWork = {
            ...inferredWork || {},
            ...response && response.work && typeof response.work === "object" ? response.work : {}
          };
          const nextWork = filterAllowedWork(mergedWork, currentConfig);
          const nextLayoutValue = nextWork && Object.prototype.hasOwnProperty.call(nextWork, "layout") ? nextWork.layout : null;
          const persistedWork = nextWork && typeof nextWork === "object" ? { ...nextWork } : null;
          if (persistedWork && Object.prototype.hasOwnProperty.call(persistedWork, "layout")) {
            delete persistedWork.layout;
          }
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
          updatePromptEditorFields({
            templateText,
            nextTitle,
            nextDescription,
            nextWork,
            isLayoutTarget
          });
          if (drawerForm) {
            const templateField = drawerForm.querySelector("#edit-content-template");
            if (!isLayoutTarget && templateField) {
              templateField.value = templateText;
            }
            const layoutNameField = drawerForm.querySelector("#edit-work-layout");
            if (layoutNameField && !layoutNameField.value.trim()) {
              layoutNameField.value = "poff-layout";
            }
            if (nextWork && typeof nextWork.type === "string") {
              const workTypeField = drawerForm.querySelector("#edit-work-type");
              if (workTypeField) {
                workTypeField.value = nextWork.type;
              }
            }
          }
          const elements2 = drawerForm ? drawerForm.elements : null;
          const resolvedLayoutName = (() => {
            var _a, _b, _c;
            if (typeof nextLayoutValue === "string" && nextLayoutValue.trim()) {
              return nextLayoutValue.trim();
            }
            if (nextLayoutValue && typeof nextLayoutValue === "object") {
              const candidate = nextLayoutValue.name || nextLayoutValue.mode || nextLayoutValue.value || "";
              if (typeof candidate === "string" && candidate.trim()) {
                return candidate.trim();
              }
            }
            return (((_a = elements2 == null ? void 0 : elements2.work_layout) == null ? void 0 : _a.value) || ((_c = (_b = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _b.layout) == null ? void 0 : _c.name) || "poff-layout").trim();
          })();
          const layoutPayload = {
            name: resolvedLayoutName,
            engine: "lightncandy"
          };
          if (nextLayoutValue && typeof nextLayoutValue === "object") {
            if (typeof nextLayoutValue.engine === "string" && nextLayoutValue.engine.trim()) {
              layoutPayload.engine = nextLayoutValue.engine.trim();
            }
            if (typeof nextLayoutValue.model === "string" && nextLayoutValue.model.trim()) {
              layoutPayload.model = nextLayoutValue.model.trim();
            }
          }
          if (response.model) {
            layoutPayload.model = response.model;
          }
          if (isLayoutTarget) {
            const layoutState = getLayoutState(currentConfig || {});
            const layoutPresetEl2 = document.getElementById("edit-layout-preset");
            const preset = ((layoutPresetEl2 == null ? void 0 : layoutPresetEl2.value) || layoutState.preset || "actual").trim();
            layoutPayload.preset = preset;
            const layoutPathName = (selection.previewPath || "").split("/").pop() || "item";
            const localLayoutDirectory = selection.layoutIsFile ? `.works/${layoutPathName}.layout` : ".layout";
            const resolvedLayoutDirectory = typeof layoutState.directory === "string" ? layoutState.directory.trim() : "";
            const canEditResolvedFilesystemTarget = layoutState.storage === "filesystem" && resolvedLayoutDirectory !== "";
            const shouldPersistToLocalWrapper = preset === "custom" || !canEditResolvedFilesystemTarget || resolvedLayoutDirectory === localLayoutDirectory;
            layoutPayload.name = preset === "none" ? "none" : preset === "custom" ? "custom-layout" : canEditResolvedFilesystemTarget ? "filesystem-layout" : "poff-layout";
            if (shouldPersistToLocalWrapper) {
              layoutPayload.template = templateText;
            } else if (canEditResolvedFilesystemTarget) {
              layoutPayload.originalTarget = resolvedLayoutDirectory;
              layoutPayload.originalTemplate = templateText;
            } else {
              layoutPayload.name = "custom-layout";
              layoutPayload.template = templateText;
            }
          } else {
            layoutPayload.sectionTemplate = templateText;
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
          if (persistedWork && Object.keys(persistedWork).length) {
            savePayload.work = persistedWork;
          }
          await saveConfig(savePayload, statusEl);
          renderContext();
          if (statusEl) {
            const providerLabel2 = response.provider || payload.provider;
            const modelLabel2 = response.model || payload.model;
            statusEl.textContent = `${isLayoutTarget ? "Layout" : "Template"} updated via ${providerLabel2}${modelLabel2 ? ` \xB7 ${modelLabel2}` : ""}`;
            statusEl.className = "edit-status edit-status-success";
          }
          const providerLabel = response.provider || payload.provider;
          const modelLabel = response.model || payload.model || "";
          const extra = [];
          if (nextTitle !== null) extra.push("title");
          if (nextDescription !== null) extra.push("description");
          if (persistedWork && Object.keys(persistedWork).length) extra.push(`work: ${Object.keys(persistedWork).join(", ")}`);
          if (nextLayoutValue) extra.push("layout");
          const summaryText = `Saved ${templateText.length} ${isLayoutTarget ? "layout " : ""}HBS chars via ${providerLabel}${modelLabel ? ` \xB7 ${modelLabel}` : ""}${extra.length ? ` \xB7 updated ${extra.join("; ")}` : ""}`;
          renderSummary(summaryText);
          clearAttachment();
          reloadViewer();
        } catch (err) {
          settled = true;
          stopStreaming(stream);
          setGeneratingState(false);
          debugPromptLog("error", summarizePromptError(err, requestSummary || (activePath ? { path: activePath } : null)));
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
                <div class="small-note">Use <strong>Change layout</strong> for layout source, inheritance, and wrapper/work template editing.</div>
            </div>
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
  function renderPromptWindow(settings = {}, options = {}) {
    const mode = options.mode === "layout" ? "layout" : "work";
    const promptTargetCopy = mode === "layout" ? "Prompt edits the outer layout wrapper target for this virtual .layout page." : "Prompt edits the wrapped work.hbs / works.hbs partial. The outer layout wrapper stays active.";
    const footerCopy = mode === "layout" ? `Template responses are saved to the current active layout wrapper target shown in Prompt context. The wrapped inner partial stays separate at <code>${escapeHtml(options.sectionTarget || "work.hbs")}</code>.` : "Template responses are saved to the wrapped partial: <code>work.hbs</code> for files and <code>works.hbs</code> for folders. The current outer layout stays active.";
    const contextCopy = mode === "layout" ? `<div>Prompt edits the outer layout wrapper. <code>current.templateTarget</code> is the active wrapper target. <code>current.layoutTemplateTarget</code> is the local custom wrapper path if you switch to <code>Custom</code>. <code>current.sectionTemplateTarget</code> is the advanced inner partial.</div><div>For wrapper-owned images/assets, do not use <code>{{path}}</code>. Use <code>{{layout.baseHref}}</code> in the HBS and use <code>current.layoutBaseHref</code> plus <code>current.inheritedLayoutDirectory</code> in the prompt context to understand whether the wrapper came from a parent folder.</div>` : "<div>Prompt edits the wrapped <code>{{> work}}</code> / <code>{{> works}}</code> partial. The outer layout wrapper stays active.</div>";
    const editableCopy = mode === "layout" ? '<span class="prompt-dot"></span> Editable via prompt: <strong>layout.template</strong>, optional <strong>work.*</strong>' : '<span class="prompt-dot"></span> Editable via prompt: <strong>title</strong>, <strong>description</strong>, <strong>work.*</strong>';
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
                ${editableCopy}
            </div>
            <div class="prompt-messages" id="promptMessages"></div>
            <div class="prompt-context" id="promptContext">
                <div class="prompt-context-title">Placeholders</div>
                <div class="prompt-context-body">
                    <div>{{pageLink}}, {{pageUrl}}, {{workUrl}}, {{viewUrl}}, {{srcUrl}}, {{assetUrl}}, {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}</div>
                    <div><code>{{pageLink}}</code> is for navigation. <code>{{srcUrl}}</code> is for direct sources like <code>src=</code>, <code>poster</code>, downloads, and CSS <code>url(...)</code>.</div>
                    ${contextCopy}
                    <div>{{> poff-layout}}, {{> filesystem-layout}}, {{> works}}, {{> work}}, {{work.key}}, {{layout.baseHref}}, {{layout.sectionBaseHref}}</div>
                    <div>Theme shell: <code>.poff-default-layout</code> with <code>--poff-shell-*</code> CSS vars</div>
                </div>
            </div>
            <details class="prompt-template-viewer" id="promptTemplateViewer">
                <summary class="prompt-template-viewer-summary">Current template code</summary>
                <div class="prompt-template-viewer-body">
                    <div class="small-note" id="promptTemplateLabel">Current target template</div>
                    <textarea class="form-textarea prompt-template-code" id="promptTemplateCode" readonly spellcheck="false" placeholder="No template loaded yet."></textarea>
                </div>
            </details>
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
            <div class="small-note">${promptTargetCopy}</div>
            <div class="small-note">${footerCopy}</div>
        </div>
    `;
  }

  // src/assets/js/edit/panel.js
  function layoutOverlayState(config, status) {
    const layoutState = getLayoutState(config);
    const isFile = (status == null ? void 0 : status.target) === "file";
    const sectionName = layoutState.section || (isFile ? "work" : "works");
    const localLayoutDirectory = isFile ? `.works/${config.name || config.path || "item"}.layout` : ".layout";
    const wrapperTarget = `${localLayoutDirectory}/template.hbs`;
    const sectionTarget = `${localLayoutDirectory}/${sectionName}.hbs`;
    const wrapperWasLocal = layoutState.directory === localLayoutDirectory;
    const sectionWasLocal = layoutState.sectionDirectory === localLayoutDirectory;
    const hasInheritedLayout = !!layoutState.inheritedDirectory;
    const originalTarget = layoutState.storage === "filesystem" ? layoutState.directory || localLayoutDirectory : layoutState.inheritedDirectory || "";
    const originalEditable = originalTarget !== "";
    const originalUsesLocal = originalTarget === localLayoutDirectory;
    const localWrapperTemplate = wrapperWasLocal ? layoutState.template || "" : "";
    const localWrapperCss = wrapperWasLocal ? layoutState.css || "" : "";
    const localWrapperJs = wrapperWasLocal ? layoutState.js || "" : "";
    let originalTemplate = "";
    let originalCss = "";
    let originalJs = "";
    if (originalEditable && layoutState.storage === "filesystem") {
      originalTemplate = layoutState.template || "";
      originalCss = layoutState.css || "";
      originalJs = layoutState.js || "";
    } else if (!originalEditable) {
      originalTemplate = layoutState.phpTemplate || "";
    }
    const wrapperSourceLabel = layoutState.storage === "filesystem" ? `Filesystem: ${layoutState.directory || localLayoutDirectory}` : "PHP built-in poff-layout";
    const inheritedLayoutLabel = hasInheritedLayout ? layoutState.inheritedDirectory : "No parent .layout found";
    const originalLabel = originalEditable ? `Editable source: ${originalTarget}` : "PHP built-in poff-layout is read-only until a parent .layout exists";
    return {
      layoutState,
      sectionName,
      localLayoutDirectory,
      wrapperTarget,
      sectionTarget,
      wrapperWasLocal,
      sectionWasLocal,
      hasInheritedLayout,
      originalTarget,
      originalEditable,
      originalUsesLocal,
      localWrapperTemplate,
      localWrapperCss,
      localWrapperJs,
      originalTemplate,
      originalCss,
      originalJs,
      wrapperSourceLabel,
      inheritedLayoutLabel,
      originalLabel
    };
  }
  function renderEditLayoutPanel({
    editPanel,
    config,
    status,
    onSubmitLayout,
    onReturnToWork
  }) {
    const settings = loadPromptSettings();
    const subjectStatus = {
      ...status,
      target: (status == null ? void 0 : status.subjectTarget) || (status == null ? void 0 : status.target)
    };
    const overlayState = layoutOverlayState(config, subjectStatus);
    const {
      layoutState,
      sectionName,
      wrapperTarget,
      sectionTarget,
      sectionWasLocal,
      originalTarget,
      originalEditable,
      originalUsesLocal,
      localWrapperTemplate,
      localWrapperCss,
      localWrapperJs,
      originalTemplate,
      originalCss,
      originalJs,
      wrapperSourceLabel,
      inheritedLayoutLabel,
      originalLabel
    } = overlayState;
    const subjectLabel = subjectStatus.target === "file" ? "file" : "folder";
    const layoutPresetOptions = [
      { value: "actual", label: "Actual" },
      { value: "none", label: "None" },
      { value: "custom", label: "Custom" }
    ];
    const hasVirtualSource = !overlayState.wrapperWasLocal && !originalUsesLocal;
    editPanel.innerHTML = `
        <h3 class="edit-panel-title">Edit layout (${subjectLabel})</h3>
        <div class="small-note">Virtual <code>.layout</code> target for this ${escapeHtml(subjectLabel)}. The preview stays on the current work while you edit the wrapper.</div>
        <div class="edit-status" id="editLayoutStatus"></div>
        <form id="editLayoutPanelForm" class="edit-inline edit-layout-panel">
            <div class="edit-layout-launch edit-layout-summary">
                <div class="edit-layout-copy">
                    <div class="edit-layout-title">Layout</div>
                    <div class="edit-layout-summary-line">Editing source: <code id="edit-layout-source-preview">${escapeHtml(wrapperSourceLabel)}</code></div>
                    <div class="edit-layout-summary-line">Current mode: <code id="edit-layout-mode-preview">${escapeHtml(layoutState.mode)}</code></div>
                    <div class="edit-layout-summary-line">Inner section stays at <code>${escapeHtml(sectionTarget)}</code> unless you change it in <strong>More...</strong></div>
                </div>
                <div class="edit-inline-actions edit-layout-header-actions">
                    <button class="btn btn-secondary" type="button" id="editLayoutBack">Back to work</button>
                    <button class="btn btn-secondary" type="button" id="editLayoutMore">More...</button>
                    <button class="btn" type="submit">Save layout</button>
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-preset">Layout select</label>
                    <select class="form-select" id="edit-layout-preset" name="layout_preset">
                        ${layoutPresetOptions.map((option) => `
                            <option value="${option.value}" ${layoutState.preset === option.value ? "selected" : ""}>${option.label}</option>
                        `).join("")}
                    </select>
                </div>
                <div class="edit-layout-copy edit-layout-section-note">
                    <div class="edit-layout-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                </div>
            </div>
        </form>
        ${renderPromptWindow(settings, {
      mode: "layout",
      subjectType: subjectLabel,
      templateTarget: wrapperTarget,
      sectionTarget
    })}
        <details class="edit-layout-advanced edit-layout-manual" id="editLayoutManual" ${sectionWasLocal ? "open" : ""}>
            <summary class="edit-layout-advanced-summary">More layout files</summary>
            <div class="edit-layout-overlay-grid">
                <div class="edit-layout-meta-card">
                    <div class="edit-layout-meta-title">Sources</div>
                    <div class="small-note">Inherited parent layout: <code>${escapeHtml(inheritedLayoutLabel)}</code></div>
                    <div class="small-note">PHP built-in: <code>poff-layout.hbs</code> from the bundled templates</div>
                    <div class="small-note">Layout target: <code>${escapeHtml(originalLabel)}</code></div>
                    <div class="small-note">Custom wrapper target: <code>${escapeHtml(wrapperTarget)}</code></div>
                    <div class="small-note">Inner section target: <code>${escapeHtml(sectionTarget)}</code></div>
                </div>
            </div>
            <div class="edit-layout-editor">
                <div class="edit-layout-editor-head">
                    <div>
                        <div class="edit-layout-meta-title">Layout template</div>
                        <div class="small-note">Manual editor for the outer wrapper template used by this virtual <code>.layout</code> page.</div>
                    </div>
                </div>
                <textarea class="form-textarea" id="edit-layout-primary-template" name="layout_primary_template"></textarea>
                <div class="edit-layout-overlay-grid">
                    <div>
                        <label class="edit-label" for="edit-layout-primary-css">Layout CSS</label>
                        <textarea class="form-textarea" id="edit-layout-primary-css" name="layout_primary_css"></textarea>
                    </div>
                    <div>
                        <label class="edit-label" for="edit-layout-primary-js">Layout JS</label>
                        <textarea class="form-textarea" id="edit-layout-primary-js" name="layout_primary_js"></textarea>
                    </div>
                </div>
            </div>

            <details class="edit-layout-advanced" ${sectionWasLocal ? "open" : ""}>
                <summary class="edit-layout-advanced-summary">Inner work section (advanced)</summary>
                <div class="edit-layout-editor">
                    <div class="edit-layout-editor-head">
                        <div>
                            <div class="edit-layout-meta-title">Inner section partial</div>
                            <div class="small-note">Edit the wrapped <code>{{> ${escapeHtml(sectionName)}}</code> partial only when you need item-specific content inside the current layout.</div>
                        </div>
                    </div>
                    <textarea class="form-textarea" id="edit-content-template" name="content_template">${escapeHtml(layoutState.sectionTemplate || "")}</textarea>
                </div>
            </details>
        </details>
    `;
    const form = editPanel.querySelector("#editLayoutPanelForm");
    const statusEl = editPanel.querySelector("#editLayoutStatus");
    const backButton = editPanel.querySelector("#editLayoutBack");
    const moreButton = editPanel.querySelector("#editLayoutMore");
    const manualDetailsEl = editPanel.querySelector("#editLayoutManual");
    const presetEl = editPanel.querySelector("#edit-layout-preset");
    const modePreviewEl = editPanel.querySelector("#edit-layout-mode-preview");
    const sourcePreviewEl = editPanel.querySelector("#edit-layout-source-preview");
    const primaryTitleEl = editPanel.querySelector("#edit-layout-primary-title");
    const primaryHintEl = editPanel.querySelector("#edit-layout-primary-hint");
    const primaryTemplateEl = editPanel.querySelector("#edit-layout-primary-template");
    const primaryCssEl = editPanel.querySelector("#edit-layout-primary-css");
    const primaryJsEl = editPanel.querySelector("#edit-layout-primary-js");
    const contentTemplateEl = editPanel.querySelector("#edit-content-template");
    const promptRoot = editPanel.querySelector("#promptWindow");
    const currentSectionTemplate = layoutState.sectionTemplate || "";
    const drafts = {
      virtualTemplate: originalTemplate || "",
      virtualCss: originalCss || "",
      virtualJs: originalJs || "",
      localTemplate: localWrapperTemplate || "",
      localCss: localWrapperCss || "",
      localJs: localWrapperJs || ""
    };
    const currentPrimaryMode = () => {
      const preset = ((presetEl == null ? void 0 : presetEl.value) || "actual").trim();
      if (preset === "custom") {
        return "local";
      }
      return hasVirtualSource ? "virtual" : "local";
    };
    const syncLayoutMode = () => {
      const preset = ((presetEl == null ? void 0 : presetEl.value) || "actual").trim();
      const nextMode = preset === "none" ? "none" : preset === "custom" ? "custom-layout" : originalEditable ? "filesystem-layout" : "poff-layout";
      const primaryMode = currentPrimaryMode();
      const isVirtual = primaryMode === "virtual";
      const sourcePreview = isVirtual ? originalEditable ? `Filesystem: ${originalTarget}` : "PHP built-in poff-layout" : `Filesystem: ${wrapperTarget.replace(/\/template\.hbs$/, "")}`;
      if (modePreviewEl) {
        modePreviewEl.textContent = nextMode;
      }
      if (sourcePreviewEl) {
        sourcePreviewEl.textContent = sourcePreview;
      }
      if (primaryTitleEl) {
        primaryTitleEl.textContent = isVirtual ? "Virtual layout" : "Custom layout";
      }
      if (primaryHintEl) {
        if (isVirtual) {
          primaryHintEl.innerHTML = originalEditable ? `Editing the inherited parent layout source <code>${escapeHtml(originalTarget)}</code>. Switch to <code>Custom</code> when you want to create a local <code>${escapeHtml(wrapperTarget)}</code>.` : "Showing the bundled poff-layout. It stays read-only until a parent .layout exists.";
        } else {
          primaryHintEl.innerHTML = `Editing the local wrapper override <code>${escapeHtml(wrapperTarget)}</code>.`;
        }
      }
      if (primaryTemplateEl) {
        primaryTemplateEl.value = isVirtual ? drafts.virtualTemplate : drafts.localTemplate;
        primaryTemplateEl.disabled = isVirtual && !originalEditable;
      }
      if (primaryCssEl) {
        primaryCssEl.value = isVirtual ? drafts.virtualCss : drafts.localCss;
        primaryCssEl.disabled = isVirtual && !originalEditable;
      }
      if (primaryJsEl) {
        primaryJsEl.value = isVirtual ? drafts.virtualJs : drafts.localJs;
        primaryJsEl.disabled = isVirtual && !originalEditable;
      }
    };
    const storePrimaryDraft = () => {
      var _a, _b, _c, _d, _e, _f;
      const primaryMode = currentPrimaryMode();
      if (primaryMode === "virtual") {
        drafts.virtualTemplate = (_a = primaryTemplateEl == null ? void 0 : primaryTemplateEl.value) != null ? _a : "";
        drafts.virtualCss = (_b = primaryCssEl == null ? void 0 : primaryCssEl.value) != null ? _b : "";
        drafts.virtualJs = (_c = primaryJsEl == null ? void 0 : primaryJsEl.value) != null ? _c : "";
        return;
      }
      drafts.localTemplate = (_d = primaryTemplateEl == null ? void 0 : primaryTemplateEl.value) != null ? _d : "";
      drafts.localCss = (_e = primaryCssEl == null ? void 0 : primaryCssEl.value) != null ? _e : "";
      drafts.localJs = (_f = primaryJsEl == null ? void 0 : primaryJsEl.value) != null ? _f : "";
    };
    if (presetEl) {
      presetEl.addEventListener("change", () => {
        storePrimaryDraft();
        syncLayoutMode();
      });
    }
    [primaryTemplateEl, primaryCssEl, primaryJsEl].forEach((field) => {
      if (field) {
        field.addEventListener("input", storePrimaryDraft);
      }
    });
    if (backButton && typeof onReturnToWork === "function") {
      backButton.addEventListener("click", () => onReturnToWork());
    }
    if (moreButton && manualDetailsEl) {
      moreButton.addEventListener("click", () => {
        manualDetailsEl.open = true;
        manualDetailsEl.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    }
    syncLayoutMode();
    if (form && typeof onSubmitLayout === "function") {
      form.addEventListener("submit", async (event) => {
        var _a;
        event.preventDefault();
        storePrimaryDraft();
        const payload = {
          layoutPreset: ((presetEl == null ? void 0 : presetEl.value) || "actual").trim()
        };
        const contentTemplateValue = (_a = contentTemplateEl == null ? void 0 : contentTemplateEl.value) != null ? _a : "";
        if (sectionWasLocal || contentTemplateValue !== currentSectionTemplate) {
          payload.contentTemplate = contentTemplateValue;
        }
        if (currentPrimaryMode() === "virtual") {
          if (originalEditable) {
            payload.originalLayoutTarget = originalTarget;
            payload.originalLayoutTemplate = drafts.virtualTemplate;
            payload.originalLayoutCss = drafts.virtualCss;
            payload.originalLayoutJs = drafts.virtualJs;
          }
        } else {
          payload.layoutTemplate = drafts.localTemplate;
          payload.layoutCss = drafts.localCss;
          payload.layoutJs = drafts.localJs;
        }
        await onSubmitLayout({
          payload,
          statusEl
        });
      });
    }
    return { statusEl, promptRoot };
  }
  function renderEditPanel({
    editPanel,
    editRequested: editRequested2,
    config,
    status,
    onTitleInput,
    onDescriptionInput,
    onSubmit,
    onToggleDrawer,
    onOpenLayoutPage,
    onReturnToWork,
    onSubmitLayout,
    onUploadFiles
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
    if ((status == null ? void 0 : status.target) === "layout") {
      return renderEditLayoutPanel({
        editPanel,
        config,
        status,
        onSubmitLayout,
        onReturnToWork
      });
    }
    const label = (status == null ? void 0 : status.target) === "file" ? "Edit mode (file)" : "Edit mode (folder)";
    const settings = loadPromptSettings();
    const overlayState = layoutOverlayState(config, status);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isEmptyFolder = (status == null ? void 0 : status.target) !== "file" && treeItems.length === 0;
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
                <div class="small-note">${escapeHtml(overlayState.wrapperSourceLabel)}</div>
                <div class="small-note">Inherited parent layout: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code></div>
                <div class="small-note">Current mode: <code>${escapeHtml(overlayState.layoutState.mode)}</code></div>
            </div>
            <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
        </div>
        ${(status == null ? void 0 : status.target) !== "file" ? `
            <div class="edit-upload-launch ${isEmptyFolder ? "edit-upload-launch-empty" : ""}">
                <div class="edit-layout-copy">
                    <div class="edit-layout-title">Add content</div>
                    <div class="small-note">${isEmptyFolder ? "This folder is empty. Upload a file to start." : "Upload files into this folder."}</div>
                </div>
                <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add files</button>
            </div>
            <dialog class="edit-upload-dialog" id="editUploadDialog">
                <form method="dialog" class="edit-upload-dialog-form">
                    <div class="drawer-header">
                        <h4 class="drawer-title">Add content</h4>
                        <button type="button" class="drawer-close" id="editUploadClose">&times;</button>
                    </div>
                    <div class="edit-grid">
                        <div>
                            <label class="edit-label" for="edit-upload-source">Source</label>
                            <select class="form-select" id="edit-upload-source" name="upload_source">
                                <option value="upload" selected>Upload</option>
                                <option value="url" disabled>From URL (disabled)</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label" for="edit-upload-files">Files</label>
                            <input class="form-input" id="edit-upload-files" type="file" name="files" multiple>
                        </div>
                    </div>
                    <div class="small-note" id="editUploadSummary">No files selected.</div>
                    <div class="edit-inline-actions">
                        <button class="btn" type="button" id="editUploadSubmit">Upload</button>
                        <button class="btn btn-secondary" type="button" id="editUploadCancel">Cancel</button>
                    </div>
                </form>
            </dialog>
        ` : ""}
        <details class="prompt-template-viewer">
            <summary class="prompt-template-viewer-summary">Current template code</summary>
            <div class="prompt-template-viewer-body">
                <div class="small-note">${(status == null ? void 0 : status.target) === "file" ? "Current wrapped file partial" : "Current wrapped folder partial"}</div>
                <textarea class="form-textarea prompt-template-code" readonly spellcheck="false" placeholder="No template loaded yet.">${escapeHtml(overlayState.layoutState.sectionTemplate || "")}</textarea>
            </div>
        </details>
        ${renderPromptWindow(settings)}
    `;
    const form = editPanel.querySelector("#inlineEditForm");
    const statusEl = editPanel.querySelector("#editInlineStatus");
    const moreToggle = editPanel.querySelector("#editMoreToggle");
    const changeLayoutButton = editPanel.querySelector("#editChangeLayout");
    const titleInput = editPanel.querySelector("#edit-title");
    const descInput = editPanel.querySelector("#edit-description");
    const promptRoot = editPanel.querySelector("#promptWindow");
    const uploadDialog = editPanel.querySelector("#editUploadDialog");
    const openUploadDialogButton = editPanel.querySelector("#editOpenUploadDialog");
    const uploadCloseButton = editPanel.querySelector("#editUploadClose");
    const uploadCancelButton = editPanel.querySelector("#editUploadCancel");
    const uploadSubmitButton = editPanel.querySelector("#editUploadSubmit");
    const uploadSourceEl = editPanel.querySelector("#edit-upload-source");
    const uploadFilesEl = editPanel.querySelector("#edit-upload-files");
    const uploadSummaryEl = editPanel.querySelector("#editUploadSummary");
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
    if (changeLayoutButton && typeof onOpenLayoutPage === "function") {
      changeLayoutButton.addEventListener("click", () => onOpenLayoutPage());
    }
    if (uploadDialog && openUploadDialogButton && typeof onUploadFiles === "function") {
      const setUploadSummary = () => {
        const files = (uploadFilesEl == null ? void 0 : uploadFilesEl.files) ? Array.from(uploadFilesEl.files) : [];
        if (!uploadSummaryEl) {
          return;
        }
        uploadSummaryEl.textContent = files.length ? files.map((file) => file.name).join(", ") : "No files selected.";
      };
      const closeUploadDialog = () => {
        if (typeof uploadDialog.close === "function") {
          uploadDialog.close();
        } else {
          uploadDialog.removeAttribute("open");
        }
      };
      const openUploadDialog = () => {
        setUploadSummary();
        if (typeof uploadDialog.showModal === "function") {
          uploadDialog.showModal();
        } else {
          uploadDialog.setAttribute("open", "open");
        }
      };
      openUploadDialogButton.addEventListener("click", openUploadDialog);
      if (uploadCloseButton) {
        uploadCloseButton.addEventListener("click", closeUploadDialog);
      }
      if (uploadCancelButton) {
        uploadCancelButton.addEventListener("click", closeUploadDialog);
      }
      if (uploadFilesEl) {
        uploadFilesEl.addEventListener("change", setUploadSummary);
      }
      if (uploadSubmitButton) {
        uploadSubmitButton.addEventListener("click", async () => {
          const files = (uploadFilesEl == null ? void 0 : uploadFilesEl.files) ? Array.from(uploadFilesEl.files) : [];
          if (files.length === 0) {
            if (statusEl) {
              statusEl.textContent = "Choose at least one file.";
              statusEl.className = "edit-status";
            }
            return;
          }
          try {
            uploadSubmitButton.disabled = true;
            await onUploadFiles({
              source: (uploadSourceEl == null ? void 0 : uploadSourceEl.value) || "upload",
              files,
              statusEl
            });
            closeUploadDialog();
          } catch (err) {
            if (statusEl) {
              statusEl.textContent = err.message || "Upload failed.";
              statusEl.className = "edit-status";
            }
          } finally {
            uploadSubmitButton.disabled = false;
          }
        });
      }
    }
    return { statusEl, promptRoot };
  }

  // src/assets/js/edit/controller.js
  function createEditController({ elements: elements2, context, editRequested: editRequested2 }) {
    const { editPanel, editDrawer, editToggle } = elements2;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, "currentPoffConfig") ? context.currentPoffConfig : null;
    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = "folder";
    let drawerOpen = false;
    function renderFolderMeta() {
      return folderConfig;
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
        if (editTarget === "folder" || editTarget === "layout" && data.subjectTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
        if (statusEl) {
          statusEl.textContent = "Config saved.";
          statusEl.className = "edit-status edit-status-success";
        }
        window.dispatchEvent(new CustomEvent("poff:content-updated", {
          detail: {
            path: (payload == null ? void 0 : payload.path) || "",
            target: editTarget,
            subjectTarget: data.subjectTarget || editTarget
          }
        }));
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
        },
        onOpenLayoutPage: () => {
          var _a;
          const selection = getActiveSelection();
          const nextPath = buildVirtualLayoutPath((_a = selection.previewPath) != null ? _a : selection.path);
          drawerOpen = false;
          syncDrawerVisibility();
          window.location.hash = `#/${nextPath}`;
        },
        onReturnToWork: () => {
          const selection = getActiveSelection();
          const nextPath = selection.previewPath || "";
          drawerOpen = false;
          syncDrawerVisibility();
          if (nextPath) {
            window.location.hash = `#/${nextPath}`;
            return;
          }
          window.history.replaceState(null, "", window.location.pathname + window.location.search);
          window.dispatchEvent(new Event("hashchange"));
        },
        onSubmitLayout: async ({ payload, statusEl }) => {
          var _a, _b, _c, _d, _e, _f, _g, _h;
          const selection = getActiveSelection();
          const layoutPreset = (payload.layoutPreset || "actual").trim();
          const layoutName = layoutPreset === "none" ? "none" : layoutPreset === "custom" ? "custom-layout" : Object.prototype.hasOwnProperty.call(payload, "originalLayoutTarget") ? "filesystem-layout" : "poff-layout";
          const layoutPayload = {
            name: layoutName,
            engine: "lightncandy",
            preset: layoutPreset
          };
          if (Object.prototype.hasOwnProperty.call(payload, "contentTemplate")) {
            layoutPayload.sectionTemplate = (_a = payload.contentTemplate) != null ? _a : "";
          }
          if (Object.prototype.hasOwnProperty.call(payload, "layoutTemplate")) {
            layoutPayload.template = (_b = payload.layoutTemplate) != null ? _b : "";
          }
          if (Object.prototype.hasOwnProperty.call(payload, "layoutCss")) {
            layoutPayload.css = (_c = payload.layoutCss) != null ? _c : "";
          }
          if (Object.prototype.hasOwnProperty.call(payload, "layoutJs")) {
            layoutPayload.js = (_d = payload.layoutJs) != null ? _d : "";
          }
          if (Object.prototype.hasOwnProperty.call(payload, "originalLayoutTarget")) {
            layoutPayload.originalTarget = (_e = payload.originalLayoutTarget) != null ? _e : "";
            layoutPayload.originalTemplate = (_f = payload.originalLayoutTemplate) != null ? _f : "";
            layoutPayload.originalCss = (_g = payload.originalLayoutCss) != null ? _g : "";
            layoutPayload.originalJs = (_h = payload.originalLayoutJs) != null ? _h : "";
          }
          await saveConfig({
            path: selection.path,
            layout: layoutPayload
          }, statusEl);
        },
        onUploadFiles: async ({ source, files, statusEl }) => {
          const selection = getActiveSelection();
          const data = await requestEditUpload({
            path: selection.previewPath || selection.path,
            source,
            files
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Upload failed.");
          }
          editConfig = data.config || editConfig;
          editTarget = data.target || editTarget;
          if (editTarget === "folder") {
            folderConfig = editConfig;
          }
          renderEditUI(editConfig, {
            allowed: data.allowed !== false,
            target: editTarget
          });
          const inlineStatus = document.getElementById("editInlineStatus");
          if (inlineStatus) {
            const count = Array.isArray(data.uploaded) ? data.uploaded.length : 0;
            inlineStatus.textContent = count === 1 ? "Uploaded 1 file." : `Uploaded ${count} files.`;
            inlineStatus.className = "edit-status edit-status-success";
          }
          window.dispatchEvent(new CustomEvent("poff:content-updated"));
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
          var _a, _b, _c;
          const selection = getActiveSelection();
          const payload = {
            path: selection.path,
            link: (((_a = elements3.link) == null ? void 0 : _a.value) || "").trim(),
            url: (((_b = elements3.url) == null ? void 0 : _b.value) || "").trim(),
            work: {
              type: (((_c = elements3.work_type) == null ? void 0 : _c.value) || "").trim()
            }
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
        if (editTarget === "folder" || editTarget === "layout" && data.subjectTarget === "folder") {
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
    let ignoreNextHashSync = false;
    let previewRequestId = 0;
    const initialQueryPath = new URLSearchParams(window.location.search).get("path") || "";
    let previewClickBound = false;
    function parseCmsLinkValue(value = "") {
      const trimmed = (value || "").trim();
      if (!trimmed) {
        return null;
      }
      if (trimmed.startsWith("?")) {
        const params = new URLSearchParams(trimmed.replace(/^\?/, ""));
        if (params.get("view") === "1") {
          if (params.has("file")) {
            return {
              path: params.get("file") || "",
              isFile: true
            };
          }
          if (params.has("path")) {
            return {
              path: params.get("path") || "",
              isFile: false
            };
          }
        }
        if (params.has("path")) {
          return {
            path: params.get("path") || "",
            isFile: false
          };
        }
        return null;
      }
      return null;
    }
    function normalizeCmsRelativePath(value = "") {
      const trimmed = (value || "").trim();
      if (!trimmed || trimmed.startsWith("#")) {
        return "";
      }
      if (/^(data|blob|mailto|tel):/i.test(trimmed)) {
        return "";
      }
      const parsedLink = parseCmsLinkValue(trimmed);
      if (parsedLink == null ? void 0 : parsedLink.path) {
        return parsedLink.path;
      }
      if (/^[a-z]+:\/\//i.test(trimmed)) {
        try {
          const url = new URL(trimmed, window.location.href);
          if (url.origin !== window.location.origin) {
            return "";
          }
          const rootPath = window.location.pathname.replace(/\/?$/, "/");
          if (!url.pathname.startsWith(rootPath)) {
            return "";
          }
          return decodeURIComponent(url.pathname.slice(rootPath.length));
        } catch (err) {
          return "";
        }
      }
      return decodeURIComponent(trimmed.replace(/^\.\//, "").replace(/^\/+/, ""));
    }
    function extractFallbackAnchorPath(anchor) {
      if (!anchor) {
        return null;
      }
      const currentSelection = getSelectionFromPath(readHashPath() || initialQueryPath || "");
      const currentFolderPath = currentSelection.previewIsFile ? currentSelection.previewPath.split("/").slice(0, -1).join("/") : currentSelection.previewPath;
      const candidates = [
        anchor.getAttribute("data-page-link"),
        anchor.getAttribute("data-work-url"),
        anchor.getAttribute("data-view-url"),
        anchor.getAttribute("data-path"),
        anchor.getAttribute("data-src")
      ];
      anchor.querySelectorAll("[data-page-link],[data-work-url],[data-view-url],[data-path],[data-src],[src],[poster]").forEach((node) => {
        candidates.push(
          node.getAttribute("data-page-link"),
          node.getAttribute("data-work-url"),
          node.getAttribute("data-view-url"),
          node.getAttribute("data-path"),
          node.getAttribute("data-src"),
          node.getAttribute("src"),
          node.getAttribute("poster")
        );
      });
      for (const candidate of candidates) {
        const normalized = normalizeCmsRelativePath(candidate || "");
        if (!normalized) {
          continue;
        }
        if (inferFilePath(normalized)) {
          return {
            path: normalized,
            isFile: true
          };
        }
        if (currentFolderPath && inferFilePath(`${currentFolderPath}/${normalized}`)) {
          return {
            path: `${currentFolderPath}/${normalized}`.replace(/^\/+/, ""),
            isFile: true
          };
        }
      }
      return null;
    }
    function readHashPath() {
      const rawHashPath = window.location.hash.replace(/^#\/?/, "");
      if (!rawHashPath) {
        return "";
      }
      try {
        return decodeURIComponent(rawHashPath);
      } catch (err) {
        return rawHashPath;
      }
    }
    function clearActiveLink() {
      if (activeLink) {
        activeLink.classList.remove("nav-link-active");
        activeLink = null;
      }
      if (!navList) {
        return;
      }
      navList.querySelectorAll(".nav-link-active").forEach((link) => {
        if (link !== activeLink) {
          link.classList.remove("nav-link-active");
        }
      });
    }
    function setActiveFileLink(fileName = "") {
      clearActiveLink();
      if (!navList || !fileName) {
        return;
      }
      const fileEls = navList.querySelectorAll("a[data-file]");
      fileEls.forEach((el) => {
        if (el.getAttribute("data-file") === fileName) {
          el.classList.add("nav-link-active");
          activeLink = el;
        }
      });
    }
    function setActiveLayoutLink(layoutPath = "") {
      clearActiveLink();
      if (!navList || !layoutPath) {
        return;
      }
      const layoutEls = navList.querySelectorAll("a[data-layout-path]");
      layoutEls.forEach((el) => {
        if (el.getAttribute("data-layout-path") === layoutPath) {
          el.classList.add("nav-link-active");
          activeLink = el;
        }
      });
    }
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
      return fetch(`?ajax=1&path=${encodeURIComponent(relPath)}${editQuery2}`).then((response) => response.text()).then((html) => {
        const extracted = extractNavHtml(html) || "";
        if (extracted.trim()) {
          navList.innerHTML = extracted;
          navList.dataset.loaded = "1";
        } else {
          navList.dataset.stale = "1";
        }
        return extracted;
      }).catch(() => {
        navList.dataset.error = "1";
        return "";
      });
    }
    function buildViewerUrl(path, isFile = false, forceRefresh = false) {
      const url = new URL(window.location.href);
      url.search = "";
      url.hash = "";
      url.searchParams.set("view", "1");
      url.searchParams.set(isFile ? "file" : "path", path);
      if (forceRefresh) {
        url.searchParams.set("_refresh", String(Date.now()));
      }
      return url.pathname + url.search;
    }
    function writeHashPath(path = "") {
      const nextHash = path ? `#/${path.replace(/^\/+/, "")}` : "";
      if (window.location.hash === nextHash) {
        return;
      }
      if (!nextHash) {
        const nextUrl = window.location.pathname + window.location.search;
        ignoreNextHashSync = true;
        window.history.replaceState(null, "", nextUrl);
        return;
      }
      ignoreNextHashSync = true;
      window.location.hash = nextHash;
    }
    function syncSidebarSelection(path = "", isFile = false, isLayout = false) {
      if (isLayout) {
        setActiveLayoutLink(path);
        return;
      }
      if (!isFile) {
        clearActiveLink();
        return;
      }
      const parts = path.split("/");
      const fileName = parts[parts.length - 1] || "";
      setActiveFileLink(fileName);
    }
    function navigateToPath(path = "", options = {}) {
      const selection = getSelectionFromPath(path);
      navigateToSelection(selection, options);
    }
    function navigateToSelection(selectionInput, options = {}) {
      const selection = selectionInput && typeof selectionInput === "object" && Object.prototype.hasOwnProperty.call(selectionInput, "path") ? selectionInput : getSelectionFromPath(selectionInput || "");
      const {
        updateHash = true,
        forceRefresh = false
      } = options;
      const previewPath = selection.previewPath || "";
      const previewIsFile = !!selection.previewIsFile;
      const folderPath = previewIsFile ? previewPath.split("/").slice(0, -1).join("/") : previewPath;
      if (iframeLoading) {
        iframeLoading.style.display = "block";
      }
      if (contentFrame) {
        renderPreview(buildViewerUrl(previewPath, previewIsFile, forceRefresh));
      }
      if (updateHash) {
        writeHashPath(selection.path || "");
      }
      if (navList) {
        if (sidebarLoading) {
          sidebarLoading.style.display = "block";
        }
        loadNav(folderPath).then(() => {
          syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
          if (sidebarLoading) {
            sidebarLoading.style.display = "none";
          }
        }).catch(() => {
          syncSidebarSelection(selection.path || previewPath, previewIsFile, !!selection.isLayout);
          if (sidebarLoading) {
            sidebarLoading.style.display = "none";
          }
        });
      }
      if (initEditMode) {
        initEditMode();
      }
    }
    function resolvePreviewTarget(anchor) {
      if (!anchor) {
        return null;
      }
      const targetAttr = (anchor.getAttribute("target") || "").trim();
      if (targetAttr && targetAttr !== "_self") {
        return null;
      }
      let url;
      try {
        url = new URL(anchor.getAttribute("href") || anchor.href, window.location.href);
      } catch (err) {
        return null;
      }
      if (url.origin !== window.location.origin || url.pathname !== window.location.pathname) {
        return null;
      }
      if (url.searchParams.get("view") === "1") {
        if (url.searchParams.has("file")) {
          return {
            path: url.searchParams.get("file") || "",
            isFile: true
          };
        }
        if (url.searchParams.has("path")) {
          const path = url.searchParams.get("path") || "";
          const target = {
            path,
            isFile: false
          };
          if (path && !path.includes("/") && !inferFilePath(path)) {
            return extractFallbackAnchorPath(anchor) || target;
          }
          return target;
        }
      }
      if (url.searchParams.has("path")) {
        const path = url.searchParams.get("path") || "";
        const target = {
          path,
          isFile: false
        };
        if (path && !path.includes("/") && !inferFilePath(path)) {
          return extractFallbackAnchorPath(anchor) || target;
        }
        return target;
      }
      const fallback = extractFallbackAnchorPath(anchor);
      if (fallback) {
        return fallback;
      }
      return null;
    }
    function bindPreviewNavigation() {
      if (!contentFrame || previewClickBound) {
        return;
      }
      previewClickBound = true;
      contentFrame.addEventListener("click", (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
          return;
        }
        let target = event.target;
        while (target && target.tagName !== "A") {
          target = target.parentElement;
        }
        if (!target || target.tagName !== "A") {
          return;
        }
        const nextTarget = resolvePreviewTarget(target);
        if (!nextTarget) {
          return;
        }
        event.preventDefault();
        navigateToPath(nextTarget.path, { isFile: nextTarget.isFile });
      });
    }
    async function renderPreview(url) {
      if (!contentFrame) {
        return;
      }
      const requestId = ++previewRequestId;
      try {
        const response = await fetch(url, {
          credentials: "same-origin",
          headers: {
            "X-Requested-With": "fetch-preview"
          }
        });
        const html = await response.text();
        if (requestId !== previewRequestId) {
          return;
        }
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, "text/html");
        const fragments = [];
        doc.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
          fragments.push(node.outerHTML);
        });
        doc.querySelectorAll("script").forEach((node) => node.remove());
        const bodyHtml = doc.body ? doc.body.innerHTML : html;
        contentFrame.innerHTML = `${fragments.join("")}${bodyHtml}`;
        doc.querySelectorAll("script").forEach((oldScript) => {
          const script = document.createElement("script");
          for (const attribute of oldScript.attributes) {
            script.setAttribute(attribute.name, attribute.value);
          }
          script.textContent = oldScript.textContent || "";
          contentFrame.appendChild(script);
        });
        bindPreviewNavigation();
      } catch (error) {
        if (requestId !== previewRequestId) {
          return;
        }
        contentFrame.innerHTML = '<div class="viewer-error">Preview failed to load.</div>';
      } finally {
        if (requestId === previewRequestId && iframeLoading) {
          iframeLoading.style.display = "none";
        }
      }
    }
    function loadCurrentFolderInIframe() {
      var _a;
      const selection = getSelectionFromPath((_a = currentPathForIframe2 != null ? currentPathForIframe2 : initialQueryPath) != null ? _a : "");
      navigateToSelection(selection, { updateHash: false });
      if (renderFolderMeta) {
        renderFolderMeta();
      }
    }
    function syncFromLocation(options = {}) {
      var _a;
      const { forceRefresh = false } = options;
      const hashPath = readHashPath();
      if (hashPath || window.location.hash) {
        navigateToSelection(getSelectionFromPath(hashPath), {
          updateHash: false,
          forceRefresh
        });
        return;
      }
      navigateToSelection(getSelectionFromPath((_a = currentPathForIframe2 != null ? currentPathForIframe2 : initialQueryPath) != null ? _a : ""), {
        updateHash: false,
        forceRefresh
      });
    }
    function refreshCurrentLocation() {
      syncFromLocation({ forceRefresh: true });
    }
    function handleNavClick(event) {
      if (!navList) {
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
      } else if (target.dataset.layoutPath) {
        relPath = target.dataset.layoutPath;
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
      const isFile = target.dataset.layoutPath ? false : !(target.hasAttribute("href") && target.getAttribute("href").startsWith("?path="));
      navigateToPath(relPath, { isFile });
    }
    if (navList) {
      navList.addEventListener("click", handleNavClick);
    }
    if (contentFrame) {
      bindPreviewNavigation();
    }
    return {
      consumeHashSync() {
        if (!ignoreNextHashSync) {
          return false;
        }
        ignoreNextHashSync = false;
        return true;
      },
      loadCurrentFolderInIframe,
      syncFromLocation,
      refreshCurrentLocation
    };
  }

  // src/assets/js/app.js
  if (window.location.hash === "#mcp") {
    const basePath = window.location.pathname.split("#")[0];
    window.location.href = `${basePath}?mcp=1`;
  }
  var elements = {
    appShell: document.getElementById("appShell"),
    appSidebar: document.getElementById("appSidebar"),
    navList: document.getElementById("navList"),
    contentFrame: document.getElementById("contentFrame"),
    editPanel: document.getElementById("editPanel"),
    editDrawer: document.getElementById("editDrawer"),
    editToggle: document.getElementById("editToggle"),
    sidebarToggle: document.getElementById("sidebarToggle"),
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
  function bindSidebarToggle() {
    const { appShell, appSidebar, sidebarToggle } = elements;
    if (!appShell || !appSidebar || !sidebarToggle) {
      return;
    }
    const syncSidebarState = (isOpen) => {
      appShell.classList.toggle("sidebar-collapsed", !isOpen);
      appSidebar.hidden = !isOpen;
      appSidebar.setAttribute("aria-hidden", isOpen ? "false" : "true");
      sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      sidebarToggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
      sidebarToggle.setAttribute("title", isOpen ? "Close navigation" : "Open navigation");
    };
    syncSidebarState(true);
    sidebarToggle.addEventListener("click", () => {
      const isOpen = !appShell.classList.contains("sidebar-collapsed");
      syncSidebarState(!isOpen);
    });
  }
  document.addEventListener("DOMContentLoaded", () => {
    bindSidebarToggle();
    editController.syncEditToggle();
    editController.bindEditToggle();
    if (window.location.hash && window.location.hash.length > 1) {
      navigation.syncFromLocation();
    } else {
      navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
  });
  window.addEventListener("hashchange", () => {
    if (!navigation.consumeHashSync()) {
      navigation.syncFromLocation();
    }
    if (editRequested) {
      editController.initEditMode();
    }
  });
  window.addEventListener("poff:content-updated", () => {
    navigation.refreshCurrentLocation();
    if (editRequested) {
      editController.initEditMode();
    }
  });
})();
/* POFF_SCRIPT_END */
</script>
