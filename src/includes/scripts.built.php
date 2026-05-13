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
  var __defProp = Object.defineProperty;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __esm = (fn, res) => function __init() {
    return fn && (res = (0, fn[__getOwnPropNames(fn)[0]])(fn = 0)), res;
  };
  var __export = (target, all) => {
    for (var name in all)
      __defProp(target, name, { get: all[name], enumerable: true });
  };

  // src/assets/js/edit/prompt/image.js
  var image_exports = {};
  __export(image_exports, {
    isSupportedImageFile: () => isSupportedImageFile,
    readImageFile: () => readImageFile
  });
  function isSupportedImageFile(file) {
    return !!file && typeof file.type === "string" && file.type.startsWith("image/");
  }
  function readImageFile(file) {
    return new Promise((resolve, reject) => {
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
  }
  var init_image = __esm({
    "src/assets/js/edit/prompt/image.js"() {
    }
  });

  // src/assets/js/api/edit.js
  var PROMPT_REQUEST_TIMEOUT_MS = 3e5;
  function buildCmsUrl(action, path) {
    const url = new URL(window.location.pathname, window.location.origin);
    url.searchParams.set("edit", action);
    if (path) {
      url.searchParams.set("path", path);
    }
    return url.toString();
  }
  async function requestPromptModels({ provider = "local", endpoint = "", apiKey = "" } = {}) {
    const url = buildCmsUrl("models", "");
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify({
          provider,
          endpoint,
          apiKey
        })
      });
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (err) {
        data = null;
      }
      if (!res.ok) {
        return {
          error: (data && typeof data.error === "string" ? data.error : "") || responseText.trim() || `Local models proxy failed (HTTP ${res.status}).`,
          models: []
        };
      }
      return {
        error: typeof (data == null ? void 0 : data.error) === "string" ? data.error : void 0,
        models: Array.isArray(data == null ? void 0 : data.models) ? data.models : []
      };
    } catch (err) {
      return {
        error: (err == null ? void 0 : err.message) || "Local models endpoint unavailable.",
        models: []
      };
    }
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
    if (typeof payload.fileName === "string") {
      formData.set("fileName", payload.fileName);
    }
    if (typeof payload.contents === "string") {
      formData.set("contents", payload.contents);
    }
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
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (err) {
        data = null;
      }
      if (!res.ok) {
        return data || {
          allowed: false,
          error: responseText.trim() || `Upload endpoint failed (HTTP ${res.status}).`
        };
      }
      return data || {
        allowed: false,
        error: responseText.trim() || "Upload endpoint returned invalid JSON."
      };
    } catch (err) {
      return {
        allowed: false,
        error: (err == null ? void 0 : err.message) || "Upload endpoint unavailable."
      };
    }
  }
  async function requestEditDelete(payload) {
    const url = buildCmsUrl("delete", payload.path || "");
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
      });
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (err) {
        data = null;
      }
      if (!res.ok) {
        return data || {
          allowed: false,
          error: responseText.trim() || `Delete endpoint failed (HTTP ${res.status}).`
        };
      }
      return data || {
        allowed: false,
        error: responseText.trim() || "Delete endpoint returned invalid JSON."
      };
    } catch (err) {
      return {
        allowed: false,
        error: (err == null ? void 0 : err.message) || "Delete endpoint unavailable."
      };
    }
  }
  async function requestEditReset(payload) {
    const url = buildCmsUrl("reset", payload.path || "");
    try {
      const res = await fetch(url, {
        method: "POST",
        headers: {
          "Accept": "application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
      });
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (err) {
        data = null;
      }
      if (!res.ok) {
        return data || {
          allowed: false,
          error: responseText.trim() || `Reset endpoint failed (HTTP ${res.status}).`
        };
      }
      return data || {
        allowed: false,
        error: responseText.trim() || "Reset endpoint returned invalid JSON."
      };
    } catch (err) {
      return {
        allowed: false,
        error: (err == null ? void 0 : err.message) || "Reset endpoint unavailable."
      };
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
      const responseText = await res.text();
      let data = null;
      try {
        data = responseText ? JSON.parse(responseText) : null;
      } catch (err) {
        data = null;
      }
      if (!res.ok) {
        return data || {
          error: responseText.trim() || `Prompt endpoint failed (HTTP ${res.status}).`
        };
      }
      return data || {
        error: responseText.trim() || "Prompt endpoint returned invalid JSON."
      };
    } catch (err) {
      clearTimeout(timeout);
      if ((err == null ? void 0 : err.name) === "AbortError") {
        return { error: "Prompt request timed out after 5 minutes." };
      }
      return { error: "Prompt endpoint unavailable." };
    }
  }
  function parsePromptStreamEventBlock(block) {
    const lines = String(block || "").replace(/\r\n/g, "\n").split("\n");
    let eventName = "message";
    const dataLines = [];
    for (const line of lines) {
      if (line.startsWith("event:")) {
        eventName = line.slice(6).trim() || "message";
        continue;
      }
      if (line.startsWith("data:")) {
        dataLines.push(line.slice(5).replace(/^\s/, ""));
      }
    }
    return {
      event: eventName,
      data: dataLines.join("\n")
    };
  }
  function emitPromptStreamDelta(data, onDelta) {
    var _a, _b, _c, _d, _e, _f;
    if (typeof onDelta !== "function" || !data || data === "[DONE]") {
      return;
    }
    try {
      const decoded = JSON.parse(data);
      const delta = (_c = (_b = (_a = decoded == null ? void 0 : decoded.choices) == null ? void 0 : _a[0]) == null ? void 0 : _b.delta) == null ? void 0 : _c.content;
      if (typeof delta === "string" && delta !== "") {
        onDelta(delta);
        return;
      }
      const content = (_f = (_e = (_d = decoded == null ? void 0 : decoded.choices) == null ? void 0 : _d[0]) == null ? void 0 : _e.message) == null ? void 0 : _f.content;
      if (typeof content === "string" && content !== "") {
        onDelta(content);
      }
    } catch (err) {
      if (data !== "") {
        onDelta(data);
      }
    }
  }
  function parsePromptStreamFallbackPayload(rawText) {
    const trimmed = String(rawText || "").trim();
    if (trimmed === "") {
      return null;
    }
    const candidates = [trimmed];
    const firstBrace = trimmed.indexOf("{");
    const lastBrace = trimmed.lastIndexOf("}");
    if (firstBrace !== -1 && lastBrace !== -1 && lastBrace > firstBrace) {
      const jsonCandidate = trimmed.slice(firstBrace, lastBrace + 1).trim();
      if (jsonCandidate !== "") {
        candidates.push(jsonCandidate);
      }
    }
    const uniqueCandidates = [];
    for (const candidate of candidates) {
      if (!uniqueCandidates.includes(candidate)) {
        uniqueCandidates.push(candidate);
      }
    }
    for (const candidate of uniqueCandidates) {
      try {
        const decoded = JSON.parse(candidate);
        if (decoded && typeof decoded === "object") {
          return decoded;
        }
      } catch (err) {
      }
    }
    return null;
  }
  async function requestPromptTemplateStream(payload, { onDelta } = {}) {
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
          "Accept": "text/event-stream, application/json",
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ ...payload, stream: true }),
        signal: controller ? controller.signal : void 0
      });
      clearTimeout(timeout);
      const contentType = (res.headers.get("content-type") || "").toLowerCase();
      const isJsonResponse = contentType.includes("application/json") || contentType.includes("application/problem+json");
      if (!res.ok) {
        const responseText = await res.text();
        let data = null;
        try {
          data = responseText ? JSON.parse(responseText) : null;
        } catch (err) {
          data = null;
        }
        return data || {
          error: responseText.trim() || `Prompt endpoint failed (HTTP ${res.status}).`
        };
      }
      if (isJsonResponse || !res.body || typeof res.body.getReader !== "function") {
        const responseText = await res.text();
        let data = null;
        try {
          data = responseText ? JSON.parse(responseText) : null;
        } catch (err) {
          data = null;
        }
        return data || {
          error: responseText.trim() || "Prompt endpoint returned invalid JSON."
        };
      }
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      let finalPayload = null;
      let streamedText = "";
      const handleDelta = (chunk) => {
        if (typeof chunk === "string" && chunk !== "") {
          streamedText += chunk;
        }
        if (typeof onDelta === "function") {
          onDelta(chunk);
        }
      };
      while (true) {
        const { done, value } = await reader.read();
        if (done) {
          break;
        }
        buffer += decoder.decode(value, { stream: true }).replace(/\r\n/g, "\n");
        let splitIndex = buffer.indexOf("\n\n");
        while (splitIndex !== -1) {
          const eventBlock = buffer.slice(0, splitIndex).trim();
          buffer = buffer.slice(splitIndex + 2);
          if (eventBlock !== "") {
            const event = parsePromptStreamEventBlock(eventBlock);
            if (event.event === "final") {
              try {
                finalPayload = JSON.parse(event.data);
              } catch (err) {
                finalPayload = {
                  error: event.data || "Prompt stream returned invalid final payload."
                };
              }
            } else {
              emitPromptStreamDelta(event.data, handleDelta);
            }
          }
          splitIndex = buffer.indexOf("\n\n");
        }
      }
      buffer += decoder.decode().replace(/\r\n/g, "\n");
      const trailing = buffer.trim();
      if (trailing !== "") {
        const event = parsePromptStreamEventBlock(trailing);
        if (event.event === "final") {
          try {
            finalPayload = JSON.parse(event.data);
          } catch (err) {
            finalPayload = {
              error: event.data || "Prompt stream returned invalid final payload."
            };
          }
        } else {
          emitPromptStreamDelta(event.data, handleDelta);
        }
      }
      if (!finalPayload) {
        const fallbackPayload = parsePromptStreamFallbackPayload(streamedText);
        if (fallbackPayload) {
          finalPayload = {
            allowed: true,
            ...fallbackPayload
          };
          if (typeof finalPayload.template !== "string" && typeof finalPayload.content === "string") {
            finalPayload.template = finalPayload.content;
          }
          if (typeof finalPayload.template !== "string" && typeof finalPayload.response === "string") {
            finalPayload.template = finalPayload.response;
          }
        }
      }
      return finalPayload || {
        error: "Prompt stream ended without a final response."
      };
    } catch (err) {
      clearTimeout(timeout);
      if ((err == null ? void 0 : err.name) === "AbortError") {
        return { error: "Prompt request timed out after 5 minutes." };
      }
      return { error: (err == null ? void 0 : err.message) || "Prompt endpoint unavailable." };
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
  function getSelectionFromPath(path = "", options = {}) {
    const normalized = normalizeSelectionPath(path);
    const isLayout = isVirtualLayoutPath(normalized);
    const previewPath = isLayout ? subjectPathFromVirtualLayout(normalized) : normalized;
    const hasFileHint = typeof (options == null ? void 0 : options.isFile) === "boolean";
    const previewIsFile = hasFileHint ? !!options.isFile : inferFilePath(previewPath);
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
    const params = new URLSearchParams(window.location.search);
    const filePath = params.get("file") || "";
    const folderPath = params.get("path") || "";
    if (rawHash) {
      try {
        hashPath = decodeURIComponent(rawHash);
      } catch (err) {
        hashPath = rawHash;
      }
    }
    if (hashPath) {
      let resolvedIsFile;
      if (typeof window.POFF_RESOLVE_HASH_PATH === "function") {
        const resolvedHash = window.POFF_RESOLVE_HASH_PATH(hashPath);
        if (resolvedHash && typeof resolvedHash === "object") {
          hashPath = resolvedHash.path || hashPath;
          if (typeof resolvedHash.isFile === "boolean") {
            resolvedIsFile = resolvedHash.isFile;
          }
        } else {
          hashPath = resolvedHash;
        }
      }
      const hashMatchesFileParam = filePath !== "" && hashPath === filePath;
      const isFileHint = typeof resolvedIsFile === "boolean" ? resolvedIsFile : hashMatchesFileParam ? true : void 0;
      return getSelectionFromPath(hashPath, { isFile: isFileHint });
    }
    if (filePath) {
      return getSelectionFromPath(filePath, { isFile: true });
    }
    return getSelectionFromPath(folderPath);
  }

  // src/assets/js/edit/prompt/shared-work-prompt.json
  var shared_work_prompt_default = {
    lead: 'Return strict JSON with a required "template" string and an optional "work" object for structured work config updates.',
    lines: [
      "Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.",
      "Treat root.* as the outer layout shell vars and work.* as the inner content vars. Use root.title for the wrapper title and work.title for the nested item title.",
      'Example context JSON: {"root":{"title":"dominikeggermann.com"},"work":{"title":"tests"}}',
      "Extra fields added below Description are stored as work.fields metadata and also flattened into work.<name> values.",
      "When the user refers to a custom work field, bind that field in HBS with {{work.<name>}} or the matching variable name instead of hardcoding the visible text into markup.",
      "Treat work fields as structured data for template values, labels, placeholders, alt text, captions, and conditional rendering.",
      "Use config/title/description, layout name/template, and work type/template when relevant; prefer existing worktypes and template variants that match the current MIME. For example, video files should favor video templates or explicit MIME-bound overrides such as video/* or video/.*.",
      "Use work.type for the base family and work.template for the exact template override. Use work.templateMap as the inherited MIME => template defaults for child items, and only apply a mapped template when the item MIME matches the key or its family wildcard. When a worktype exposes fields such as autoplay, loop, muted, poster, fit, background, or caption, set those as config values on work instead of hardcoding them into the template unless the user explicitly asks for one-off markup.",
      "Use work.categories as the main filter and grouping hint when it exists; prefer existing categories instead of inventing new ones.",
      "Use work.templateMap as the inherited MIME => template defaults from folder/layout parents. work.template is the exact override for the current item. MIME keys may be exact values like video/quicktime or family wildcards like video/* and video/.*.",
      "Prompt context JSON current.parentWork contains the immediate parent folder/work. siblingWorks and siblingImages/siblingVideos/siblingLinks/etc contain only same-folder siblings, excluding the current item and without recursive children.",
      'Use sibling srcUrl/pageLink/linkUrl refs directly for prompts like "use the image in this folder as background" or "overlay the video in the center".',
      'If the user asks to hide used sibling works, return "treeVisible" as the full list of parent tree item names/paths that should remain visible. Include the current item unless the user explicitly asks to hide it.',
      "Use variables exactly as they exist in the current HBS scope. Prefer direct references like {{description}} when the variable is top-level.",
      "Only use parent lookups like {{../description}} when you are actually inside a nested Handlebars block such as {{#each}}, {{#with}}, or another scope-changing block.",
      "Do not invent alternate variable paths. Follow the variable path that exists in the provided HBS context.",
      "Use semantic HTML and stable readable class names. Do not use Tailwind utility classes in generated runtime templates.",
      'Do not return "css" or "js" for work prompts. Work prompts update only the inner HBS partial; layout prompts own wrapper CSS and JS.',
      "Do not put <style> tags inside template and do not use inline style attributes.",
      "Template sources live in .layout and .works layout folders; keep the source files as the authoring target."
    ],
    fileLines: [
      "Save target is work.hbs for the current file inside the active item layout folder.",
      "Template sources live in .layout and .works layout folders; keep the source files as the authoring target.",
      "Focus on a single file view. Do not assume folder tree loops or folder aggregate lists unless the user explicitly asks for them.",
      "Prefer file-relevant fields such as {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.*.",
      "Prompt context JSON current.outerWrapper contains a compact summary of the active outer layout wrapper, with template/css/js excerpts. Use it as structure and styling reference only.",
      "Align your inner partial with the current outer wrapper semantics and class language when useful, but do not return or rewrite the wrapper itself.",
      "Do not return the outer layout wrapper, page shell, navigation chrome, or a full page template.",
      "Never return {{> work}}, {{> works}}, {{> poff-layout}}, {{> filesystem-layout}}, or a poff-default-layout wrapper from this file prompt.",
      'Never emit outer shell blocks like <header class="poff-default-layout__header">, <main class="poff-default-layout__main">, footer/nav/sidebar chrome, or wrapper-only include chains from this file prompt.',
      'The JSON "template" must contain only the inner file partial content rendered inside the existing layout wrapper.'
    ],
    folderLines: [
      "Save target is works.hbs for the current folder inside the active item layout folder.",
      "Template sources live in .layout and .works layout folders; keep the source files as the authoring target.",
      "Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available.",
      "Use work.categories as the main filter and grouping hint when it exists; prefer existing categories instead of inventing new ones.",
      "Use work.templateMap as the inherited MIME => template defaults from folder/layout parents. work.template is the exact override for the current item.",
      "Folder items expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.",
      "For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.",
      "Use folder tree data and resolved refs when relevant instead of inventing paths.",
      "Prompt context JSON current.outerWrapper contains a compact summary of the active outer layout wrapper, with template/css/js excerpts. Use it as structure and styling reference only.",
      "When the current folder is root or otherwise sparse, use current.outerWrapper as the main visual grounding instead of inventing a generic standalone page.",
      "Align your inner partial with the current outer wrapper semantics and class language when useful, but do not return or rewrite the wrapper itself.",
      "Do not return the outer layout wrapper, page shell, navigation chrome, or a full page template.",
      "Never return {{> work}}, {{> works}}, {{> poff-layout}}, {{> filesystem-layout}}, or a poff-default-layout wrapper from this folder prompt.",
      'Never emit outer shell blocks like <header class="poff-default-layout__header">, <main class="poff-default-layout__main">, footer/nav/sidebar chrome, or wrapper-only include chains from this folder prompt.',
      'The JSON "template" must contain only the inner folder partial content rendered inside the existing layout wrapper.'
    ],
    layoutLines: [
      'Return a JSON object with a required "template" string and optional "css" and "js" fields.',
      "Do not return work.hbs, works.hbs, sectionTemplate, or any inner partial content for layout prompts.",
      "Transform the user description into an updated outer layout wrapper rendered by LightnCandy.",
      "Treat the currently resolved active wrapper as your primary reference. Prompt context JSON current.activeLayout and Config JSON work.layout contain the actual active template, css, and js after filesystem, inheritance, and preset resolution.",
      "Use current.root.title for the outer wrapper title and current.work.title for the nested item title. Keep shell vars and work vars separate when naming or copying content.",
      'Example context JSON: {"root":{"title":"dominikeggermann.com"},"work":{"title":"tests"}}',
      "When the active layout is empty or too minimal, fall back to the built-in default wrapper shape from src/includes/worktypes/templates/layout/default/template.hbs.",
      "The prompt edits the outer layout wrapper template only; do not add or preserve an inner work/works partial chain.",
      "Return the wrapper as real Handlebars template code. Use the same runtime fields, partials, conditionals, and folder/file context that the active template already uses when they are still relevant.",
      "Template sources live in .layout and .works layout folders; keep the source files as the authoring target.",
      "Use semantic HTML and stable readable class names. Do not use Tailwind utility classes in generated runtime templates.",
      'Put all wrapper-specific styling in the JSON "css" field as plain CSS that works without a build step.',
      "Scope CSS under a unique root class used by the returned wrapper. Do not define global selectors like body, a, img, h1 unless nested under that root class.",
      "Do not put <style> tags inside template and do not use inline style attributes.",
      "Use the actual resolved template/css/js as style and structure cues. Redesign them when requested, but keep useful Handlebars structure, routing fields, and wrapper semantics unless the user explicitly asks for a break.",
      "Use current.templateTarget as the active save target for this layout page. It follows the current layout mode: the resolved active wrapper for Inherit, the local custom wrapper for Custom, and never the inner partial by default.",
      "When layoutPreset is shared, treat current.work.layout.sharedName as the marketplace layout source and keep it within the same worktype family.",
      "current.layoutTemplateTarget is the local custom wrapper path if you explicitly switch to Custom.",
      "Prompt context JSON current.activeLayout.template is the active outer wrapper, and current.activeLayout.css/js are the currently active style and script sources.",
      "If Prompt context JSON current.editorDraft is present, treat those unsaved draft template/css/js values as the latest version to evolve from before falling back to current.activeLayout.",
      "For images, icons, CSS backgrounds, or other assets owned by the layout wrapper, do not build URLs from {{path}}. {{path}} points to the current folder/file, not the layout asset folder.",
      "Use runtime layout URLs such as {{layout.baseHref}}/file.ext for local or inherited folder layout assets. Reusing the bundled default profile image should look like {{layout.baseHref}}/eggman_profile-image.jpg when the active wrapper comes from the built-in default layout bundle.",
      "Prompt context JSON includes current.layoutBaseHref, current.inheritedLayoutDirectory, and current.layoutAssets to help you choose the right asset path and understand whether the wrapper comes from a parent folder .layout.",
      "Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.",
      "Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.",
      "If a provided item/pageLink/path/linkUrl value already contains a full CMS viewer URL like ?view=1&path=... or ?view=1&file=..., or an external URL, use it verbatim. Never prepend another ?view=1&path= or ?view=1&file= around it.",
      "Configured tree items may be virtual navigation links without a backing local file or folder. Respect their provided pageLink/linkUrl instead of forcing them into a filesystem path.",
      'JS belongs in the JSON "js" field only. Guard DOM readiness, avoid network calls, and degrade gracefully if JS is disabled.'
    ]
  };

  // src/assets/js/edit/prompt/constants.js
  var promptSettingsKey = "poffEditPromptSettings";
  var promptHistoryKey = "poffEditPromptHistory";
  var defaultLocalPromptEndpoint = "http://127.0.0.1:1234/v1/chat/completions";
  function getDefaultModelForProvider(provider = "local") {
    if (provider === "openai") {
      return "gpt-4o-mini";
    }
    if (provider === "gemini") {
      return "gemini-1.5-flash";
    }
    return "gemma4";
  }
  var sharedWorkSystemPromptLead = shared_work_prompt_default.lead;
  var sharedWorkSystemPrompt = [
    "You are a Handlebars (HBS) template generator for this single-page CMS.",
    sharedWorkSystemPromptLead,
    ...shared_work_prompt_default.lines
  ].join("\n");
  var defaultFileSystemPrompt = [
    sharedWorkSystemPrompt,
    ...shared_work_prompt_default.fileLines
  ].join("\n");
  var defaultFolderSystemPrompt = [
    sharedWorkSystemPrompt,
    ...shared_work_prompt_default.folderLines
  ].join("\n");
  var defaultLayoutSystemPrompt = [
    "You are a Handlebars (HBS) layout generator for this single-page CMS.",
    ...shared_work_prompt_default.layoutLines
  ].join("\n");
  var defaultPromptSettings = {
    provider: "local",
    model: getDefaultModelForProvider("local"),
    endpoint: defaultLocalPromptEndpoint,
    apiKey: "",
    systemPrompt: defaultFileSystemPrompt,
    systemPromptFile: defaultFileSystemPrompt,
    systemPromptFolder: defaultFolderSystemPrompt,
    systemPromptLayout: defaultLayoutSystemPrompt,
    streamPreview: true
  };

  // src/assets/js/edit/prompt/storage.js
  function normalizeHistoryScope(path, mode = "") {
    const normalizedPath = String(path != null ? path : "").trim().replace(/^\/+|\/+$/g, "");
    const normalizedMode = String(mode != null ? mode : "").trim() || "folder";
    return `${normalizedMode}:${normalizedPath || "__root__"}`;
  }
  function loadPromptSettings() {
    try {
      const rawStored = JSON.parse(localStorage.getItem(promptSettingsKey) || "{}");
      const stored = {
        provider: rawStored.provider,
        model: rawStored.model,
        endpoint: rawStored.endpoint,
        apiKey: rawStored.apiKey,
        streamPreview: rawStored.streamPreview
      };
      const looksLikeLegacyProviderDefault = !stored.provider && (!stored.model || stored.model === "gpt-4o-mini") && !stored.endpoint;
      if (looksLikeLegacyProviderDefault) {
        stored.provider = defaultPromptSettings.provider;
        stored.model = defaultPromptSettings.model;
        stored.endpoint = defaultPromptSettings.endpoint;
      }
      if ((stored.provider === "local" || !stored.provider) && !stored.endpoint) {
        stored.endpoint = defaultPromptSettings.endpoint;
      }
      return { ...defaultPromptSettings, ...stored };
    } catch (err) {
      return defaultPromptSettings;
    }
  }
  function savePromptSettings(settings) {
    try {
      const persisted = {
        provider: (settings == null ? void 0 : settings.provider) || defaultPromptSettings.provider,
        model: (settings == null ? void 0 : settings.model) || "",
        endpoint: (settings == null ? void 0 : settings.endpoint) || "",
        apiKey: (settings == null ? void 0 : settings.apiKey) || "",
        streamPreview: (settings == null ? void 0 : settings.streamPreview) !== false
      };
      localStorage.setItem(promptSettingsKey, JSON.stringify(persisted));
    } catch (err) {
    }
  }
  function readStoredHistory(path, mode = "") {
    try {
      const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || "{}");
      const scopedKey = normalizeHistoryScope(path, mode);
      const legacyKey = String(path != null ? path : "").trim();
      const list = stored[scopedKey] || stored[legacyKey] || [];
      return Array.isArray(list) ? list : [];
    } catch (err) {
      return [];
    }
  }
  function writeStoredHistory(path, history, mode = "") {
    try {
      const stored = JSON.parse(localStorage.getItem(promptHistoryKey) || "{}");
      stored[normalizeHistoryScope(path, mode)] = history;
      localStorage.setItem(promptHistoryKey, JSON.stringify(stored));
    } catch (err) {
    }
  }

  // src/assets/js/edit/prompt/settings.js
  function bindPromptSettings({
    providerEl,
    modelEl,
    modelSelectEl,
    endpointRow,
    endpointEl,
    apiKeyRow,
    apiKeyEl,
    systemPromptEl,
    systemResetEl,
    settingsResetEl,
    streamToggleEl,
    defaultPromptSettings: defaultPromptSettings2,
    currentDefaultSystemPrompt,
    currentPromptMode,
    currentSystemPromptSettingKey,
    getDefaultModelForProvider: getDefaultModelForProvider2,
    requestPromptModels: requestPromptModels2,
    loadPromptSettings: loadPromptSettings2,
    savePromptSettings: savePromptSettings2,
    onRenderContext
  }) {
    const settings = loadPromptSettings2();
    let suppressSave = false;
    let promptModelsRequestId = 0;
    const openAiFallbackModels = [
      "gpt-4o-mini",
      "gpt-4o",
      "gpt-4.1-mini",
      "gpt-4.1",
      "o4-mini",
      "o3-mini"
    ];
    const geminiFallbackModels = [
      "gemini-2.5-flash",
      "gemini-2.5-pro",
      "gemini-2.0-flash",
      "gemini-1.5-flash",
      "gemini-1.5-pro"
    ];
    const resolvePreferredModel = (provider, models, currentValue) => {
      const list = Array.isArray(models) ? models : [];
      const value = String(currentValue || "").trim();
      if (value && list.includes(value)) {
        return value;
      }
      if (provider !== "local") {
        return list[0] || value || "";
      }
      const aliases = {
        gemma4: ["google/gemma-4-e4b", "google/gemma-4-31b", "google/gemma-4-e2b"],
        qwen3_vl: ["qwen/qwen3-vl-4b", "qwen3-vl-32b-instruct-mlx"],
        mistral3: ["mistralai/ministral-3-3b", "mistralai/ministral-3-14b-reasoning"]
      };
      const aliasMatches = aliases[value] || [];
      for (const candidate of aliasMatches) {
        if (list.includes(candidate)) {
          return candidate;
        }
      }
      return list[0] || value || "";
    };
    const syncModelField = (value) => {
      if (modelEl) {
        modelEl.value = value || "";
      }
      if (modelSelectEl) {
        modelSelectEl.value = value || "";
      }
    };
    const setPromptModelOptions = (provider, models, selectedValue, placeholder = "No models found") => {
      if (!modelSelectEl) {
        return;
      }
      const list = Array.isArray(models) ? models.filter((value) => typeof value === "string" && value.trim() !== "") : [];
      const resolvedValue = resolvePreferredModel(provider, list, selectedValue);
      const currentValue = String(selectedValue || "").trim();
      const options = [];
      if (list.length === 0) {
        options.push({ value: currentValue, label: currentValue || placeholder });
      } else {
        for (const value of list) {
          options.push({ value, label: value });
        }
      }
      modelSelectEl.innerHTML = options.map(({ value, label }) => `<option value="${value}">${label}</option>`).join("");
      syncModelField(resolvedValue || currentValue);
    };
    const providerUsesRemoteModelList = () => (/* @__PURE__ */ new Set(["local", "openai", "gemini"])).has((providerEl == null ? void 0 : providerEl.value) || "local");
    const refreshPromptModelOptions = async () => {
      if (!modelSelectEl || !requestPromptModels2 || !providerUsesRemoteModelList()) {
        return;
      }
      const requestId = ++promptModelsRequestId;
      const currentValue = modelEl ? modelEl.value.trim() : "";
      const provider = (providerEl == null ? void 0 : providerEl.value) || "local";
      const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : "";
      if (provider === "openai" && apiKeyValue === "") {
        setPromptModelOptions(provider, openAiFallbackModels, currentValue, "OpenAI models");
        persistSettings();
        return;
      }
      if (provider === "gemini" && apiKeyValue === "") {
        setPromptModelOptions(provider, geminiFallbackModels, currentValue, "Gemini models");
        persistSettings();
        return;
      }
      const waitingLabel = provider === "openai" ? "Loading OpenAI models..." : provider === "gemini" ? "Loading Gemini models..." : "Loading local models...";
      modelSelectEl.innerHTML = `<option value="${currentValue || ""}">${waitingLabel}</option>`;
      modelSelectEl.value = currentValue || "";
      const result = await requestPromptModels2({
        provider,
        endpoint: endpointEl ? endpointEl.value.trim() : "",
        apiKey: apiKeyValue
      });
      if (requestId !== promptModelsRequestId) {
        return;
      }
      if (provider === "openai" && (!result.models || result.models.length === 0)) {
        setPromptModelOptions(provider, openAiFallbackModels, currentValue, result.error || "OpenAI models");
        if (!result.error) {
          persistSettings();
        }
        return;
      }
      if (provider === "gemini" && (!result.models || result.models.length === 0)) {
        setPromptModelOptions(provider, geminiFallbackModels, currentValue, result.error || "Gemini models");
        if (!result.error) {
          persistSettings();
        }
        return;
      }
      const emptyLabel = provider === "openai" ? result.error || "No OpenAI models found" : provider === "gemini" ? result.error || "No Gemini models found" : result.error || "No local models found";
      setPromptModelOptions(provider, result.models || [], currentValue, emptyLabel);
      if (!result.error) {
        persistSettings();
      }
    };
    const readModelValue = () => {
      if (providerUsesRemoteModelList() && modelSelectEl) {
        return modelSelectEl.value || (modelEl == null ? void 0 : modelEl.value) || "";
      }
      return modelEl ? modelEl.value : "";
    };
    const readSettings = () => {
      const systemPrompt = ((systemPromptEl == null ? void 0 : systemPromptEl.value) || "").trim() || currentDefaultSystemPrompt();
      const nextSettings = {
        provider: providerEl ? providerEl.value : "local",
        model: readModelValue(),
        endpoint: endpointEl ? endpointEl.value : "",
        apiKey: apiKeyEl ? apiKeyEl.value : "",
        systemPrompt,
        systemPromptFile: settings.systemPromptFile || defaultPromptSettings2.systemPromptFile,
        systemPromptFolder: settings.systemPromptFolder || defaultPromptSettings2.systemPromptFolder,
        systemPromptLayout: settings.systemPromptLayout || defaultPromptSettings2.systemPromptLayout,
        streamPreview: streamToggleEl ? !!streamToggleEl.checked : true
      };
      nextSettings[currentSystemPromptSettingKey()] = systemPrompt;
      return nextSettings;
    };
    const persistSettings = () => {
      if (!suppressSave) {
        savePromptSettings2(readSettings());
      }
    };
    const updateProviderUi = ({ resetModel = false } = {}) => {
      const provider = providerEl ? providerEl.value : "local";
      if (endpointRow) {
        endpointRow.hidden = provider !== "local";
      }
      if (apiKeyRow) {
        apiKeyRow.hidden = provider === "local";
      }
      if (modelSelectEl) {
        modelSelectEl.hidden = !providerUsesRemoteModelList();
      }
      if (modelEl) {
        modelEl.hidden = providerUsesRemoteModelList();
      }
      if (modelEl && resetModel && !modelEl.value.trim()) {
        modelEl.value = getDefaultModelForProvider2(provider);
      }
      if (providerUsesRemoteModelList()) {
        void refreshPromptModelOptions();
      }
      persistSettings();
    };
    const applySettingsToUi = (nextSettings) => {
      suppressSave = true;
      if (providerEl) providerEl.value = nextSettings.provider || defaultPromptSettings2.provider;
      if (modelEl) modelEl.value = nextSettings.model || "";
      if (modelSelectEl) modelSelectEl.value = nextSettings.model || "";
      if (endpointEl) endpointEl.value = nextSettings.endpoint || "";
      if (apiKeyEl) apiKeyEl.value = nextSettings.apiKey || "";
      if (systemPromptEl) {
        const mode = currentPromptMode();
        systemPromptEl.value = mode === "layout" ? nextSettings.systemPromptLayout || nextSettings.systemPrompt || currentDefaultSystemPrompt() : mode === "folder" ? nextSettings.systemPromptFolder || nextSettings.systemPrompt || currentDefaultSystemPrompt() : nextSettings.systemPromptFile || nextSettings.systemPrompt || currentDefaultSystemPrompt();
      }
      if (streamToggleEl) streamToggleEl.checked = nextSettings.streamPreview !== false;
      suppressSave = false;
      updateProviderUi();
    };
    const syncModeAwareSystemPrompt = () => {
      if (!systemPromptEl) {
        return;
      }
      const currentValue = (systemPromptEl.value || "").trim();
      if (currentValue !== "" && !(/* @__PURE__ */ new Set([settings.systemPromptFile, settings.systemPromptFolder, settings.systemPromptLayout])).has(currentValue)) {
        return;
      }
      const nextValue = currentDefaultSystemPrompt();
      if (systemPromptEl.value !== nextValue) {
        systemPromptEl.value = nextValue;
        settings[currentSystemPromptSettingKey()] = nextValue;
        savePromptSettings2(readSettings());
      }
    };
    if (providerEl) {
      providerEl.addEventListener("change", () => updateProviderUi({ resetModel: false }));
    }
    if (modelEl) {
      modelEl.addEventListener("input", persistSettings);
    }
    if (modelSelectEl) {
      modelSelectEl.addEventListener("change", () => {
        syncModelField(modelSelectEl.value || "");
        persistSettings();
      });
    }
    if (endpointEl) {
      endpointEl.addEventListener("input", () => {
        persistSettings();
        if ((providerEl == null ? void 0 : providerEl.value) === "local") {
          void refreshPromptModelOptions();
        }
      });
    }
    if (apiKeyEl) {
      apiKeyEl.addEventListener("input", () => {
        persistSettings();
        if ((providerEl == null ? void 0 : providerEl.value) === "openai" || (providerEl == null ? void 0 : providerEl.value) === "gemini") {
          void refreshPromptModelOptions();
        }
      });
    }
    if (systemPromptEl) {
      systemPromptEl.addEventListener("input", () => {
        settings[currentSystemPromptSettingKey()] = (systemPromptEl.value || "").trim() || currentDefaultSystemPrompt();
        savePromptSettings2(readSettings());
      });
    }
    if (streamToggleEl) {
      streamToggleEl.addEventListener("change", () => {
        savePromptSettings2(readSettings());
      });
    }
    if (systemResetEl && systemPromptEl) {
      systemResetEl.addEventListener("click", () => {
        systemPromptEl.value = currentDefaultSystemPrompt();
        settings[currentSystemPromptSettingKey()] = systemPromptEl.value;
        savePromptSettings2(readSettings());
      });
    }
    if (settingsResetEl) {
      settingsResetEl.addEventListener("click", () => {
        const nextSettings = {
          ...defaultPromptSettings2,
          systemPrompt: currentDefaultSystemPrompt()
        };
        applySettingsToUi(nextSettings);
        savePromptSettings2(nextSettings);
        if (typeof onRenderContext === "function") {
          onRenderContext();
        }
      });
    }
    return {
      settings,
      readSettings,
      applySettingsToUi,
      updateProviderUi,
      syncModeAwareSystemPrompt
    };
  }

  // src/assets/js/edit/prompt/layer.js
  function createPromptLayerController({
    root,
    windowEl,
    closeEl,
    openEl,
    storageKey,
    storage
  }) {
    const readState = () => {
      try {
        const stored = JSON.parse(storage.getItem(storageKey) || "{}");
        return !!stored.collapsed;
      } catch (err) {
        return false;
      }
    };
    const writeState = (collapsed) => {
      try {
        storage.setItem(storageKey, JSON.stringify({ collapsed: !!collapsed }));
      } catch (err) {
      }
    };
    const applyState = (collapsed, options = {}) => {
      const nextCollapsed = !!collapsed;
      root.classList.toggle("prompt-layer-collapsed", nextCollapsed);
      if (windowEl) {
        windowEl.hidden = nextCollapsed;
      }
      if (closeEl) {
        closeEl.hidden = nextCollapsed;
      }
      if (openEl) {
        openEl.hidden = !nextCollapsed;
      }
      if (!options.skipPersist) {
        writeState(nextCollapsed);
      }
    };
    if (closeEl) {
      closeEl.addEventListener("click", () => applyState(true));
    }
    if (openEl) {
      openEl.addEventListener("click", () => applyState(false));
    }
    if (typeof document !== "undefined" && document && typeof document.addEventListener === "function") {
      document.addEventListener("keydown", (event) => {
        if (!event || event.key !== "Escape") {
          return;
        }
        applyState(true);
      });
    }
    return {
      readState,
      writeState,
      applyState
    };
  }

  // src/assets/js/edit/prompt/actions.js
  function bindPromptActions({
    promptClearEl,
    promptTemplateResetEl,
    promptSendEl,
    promptInputEl,
    promptAttachEl,
    promptInsertNameEl,
    promptImageInputEl,
    promptAttachmentRemoveEl,
    layoutPresetEl,
    onClearChat,
    onResetTemplate,
    onSendPrompt,
    onAttachImage,
    onInsertName,
    onRemoveImage,
    onTemplateInput,
    onLayoutPresetChange
  }) {
    if (layoutPresetEl && typeof onLayoutPresetChange === "function") {
      layoutPresetEl.addEventListener("change", onLayoutPresetChange);
    }
    if (promptClearEl && typeof onClearChat === "function") {
      promptClearEl.addEventListener("click", onClearChat);
    }
    if (promptTemplateResetEl && typeof onResetTemplate === "function") {
      promptTemplateResetEl.addEventListener("click", onResetTemplate);
    }
    if (promptSendEl && promptInputEl && typeof onSendPrompt === "function") {
      promptSendEl.addEventListener("click", () => {
        void onSendPrompt();
      });
      promptInputEl.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !event.shiftKey && !event.altKey && !event.ctrlKey && !event.metaKey && !event.isComposing) {
          event.preventDefault();
          void onSendPrompt();
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
        if (typeof onAttachImage === "function") {
          void onAttachImage(file);
        }
      });
    }
    if (promptInsertNameEl && typeof onInsertName === "function") {
      promptInsertNameEl.addEventListener("click", () => {
        void onInsertName();
      });
    }
    if (promptAttachEl && promptImageInputEl) {
      promptAttachEl.addEventListener("click", () => {
        promptImageInputEl.click();
      });
      promptImageInputEl.addEventListener("change", async () => {
        const file = promptImageInputEl.files && promptImageInputEl.files[0] ? promptImageInputEl.files[0] : null;
        if (!file || typeof onAttachImage !== "function") {
          return;
        }
        await onAttachImage(file);
      });
    }
    if (promptAttachmentRemoveEl && typeof onRemoveImage === "function") {
      promptAttachmentRemoveEl.addEventListener("click", onRemoveImage);
    }
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
    const normalizePath = (value = "") => String(value || "").replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
    const localLayoutDirectoryForConfig = (state = {}) => {
      const hasAnnotatedPath = Object.prototype.hasOwnProperty.call(config || {}, "__poffRelativePath");
      const relativePath = normalizePath(hasAnnotatedPath ? config.__poffRelativePath : "");
      const isFile = (config == null ? void 0 : config.__poffIsFile) === true;
      if (!isFile) {
        return relativePath ? `${relativePath}/.layout` : ".layout";
      }
      const parts = relativePath.split("/").filter(Boolean);
      const fileName = parts.pop() || (config == null ? void 0 : config.name) || "item";
      const dirName = parts.join("/");
      const inferredDirectory = `${dirName ? `${dirName}/` : ""}.works/${fileName}.layout`;
      return inferredDirectory || normalizePath(state.localDirectory || "");
    };
    const normalizePreset = (value) => {
      const preset = String(value || "").trim();
      if (preset === "inherit") {
        return "actual";
      }
      return ["actual", "none", "custom", "shared"].includes(preset) ? preset : "";
    };
    const inferredSection = layoutValue && typeof layoutValue === "object" && !Array.isArray(layoutValue) && layoutValue.section ? String(layoutValue.section) : (config == null ? void 0 : config.type) === "folder" || ((_b = config == null ? void 0 : config.work) == null ? void 0 : _b.type) === "folder" && !(config == null ? void 0 : config.name) ? "works" : "work";
    const normalize = (state) => {
      const rawMode = state.mode || state.name || "poff-layout";
      const mode = rawMode === "poff" ? "poff-layout" : rawMode === "filesystem" ? "filesystem-layout" : rawMode;
      const storage = state.storage || "";
      const directory = state.directory || "";
      const localLayoutDirectory = localLayoutDirectoryForConfig(state);
      let preset = normalizePreset(state.preset) || "actual";
      if (!normalizePreset(state.preset)) {
        if (mode === "none") {
          preset = "none";
        } else if (storage === "shared" || state.source === "shared") {
          preset = "shared";
        } else if (storage === "filesystem" && directory === localLayoutDirectory) {
          preset = "custom";
        }
      }
      const sourceLabel = mode === "none" ? "No outer layout" : preset === "shared" || storage === "shared" || state.source === "shared" ? `Collection: ${state.sharedName || state.name || "shared"}` : storage === "filesystem" ? `Filesystem: ${directory || ".layout"}` : storage === "default" ? "Built-in poff-layout" : "Current resolved layout";
      const displayMode = preset === "shared" || storage === "shared" || state.source === "shared" ? "collection-layout" : mode;
      return {
        ...state,
        mode: displayMode,
        resolvedMode: mode,
        storage,
        directory,
        localLayoutDirectory,
        localDirectory: state.localDirectory || localLayoutDirectory,
        inheritedDirectory: state.inheritedDirectory || "",
        section: state.section || inferredSection,
        sectionTemplate: state.sectionTemplate || "",
        sectionDirectory: state.sectionDirectory || "",
        phpTemplate: state.phpTemplate || "",
        preset,
        source: state.source || "",
        sharedName: state.sharedName || "",
        sharedLayouts: Array.isArray(state.sharedLayouts) ? state.sharedLayouts : [],
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
        localDirectory: layoutValue.localDirectory || "",
        storage: layoutValue.storage || "",
        inheritedDirectory: layoutValue.inheritedDirectory || "",
        section: layoutValue.section || inferredSection,
        sectionTemplate: layoutValue.sectionTemplate || "",
        sectionDirectory: layoutValue.sectionDirectory || "",
        phpTemplate: layoutValue.phpTemplate || "",
        preset: layoutValue.preset || "",
        source: layoutValue.source || "",
        sharedName: layoutValue.sharedName || "",
        sharedLayouts: Array.isArray(layoutValue.sharedLayouts) ? layoutValue.sharedLayouts : [],
        assets: Array.isArray(layoutValue.assets) ? layoutValue.assets : []
      });
    }
    if (typeof layoutValue === "string") {
      return normalize({ mode: layoutValue, template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", storage: "", section: inferredSection, sectionTemplate: "", sectionDirectory: "", assets: [] });
    }
    return normalize({ mode: "poff-layout", template: "", css: "", js: "", model: "", engine: "lightncandy", directory: "", storage: "", section: inferredSection, sectionTemplate: "", sectionDirectory: "", assets: [] });
  }

  // src/assets/js/edit/prompt/mode.js
  function getPromptMode(selection2 = null) {
    if (selection2 == null ? void 0 : selection2.isLayout) {
      return "layout";
    }
    return (selection2 == null ? void 0 : selection2.previewIsFile) ? "file" : "folder";
  }
  function getDefaultSystemPromptForMode(mode, prompts = {}) {
    if (mode === "layout") {
      return prompts.layout || "";
    }
    if (mode === "folder") {
      return prompts.folder || "";
    }
    return prompts.file || "";
  }
  function getSystemPromptSettingKeyForMode(mode) {
    if (mode === "layout") {
      return "systemPromptLayout";
    }
    if (mode === "folder") {
      return "systemPromptFolder";
    }
    return "systemPromptFile";
  }
  function getPromptPlaceholderForMode(mode, defaultPlaceholder = "Describe the component you want...") {
    if (mode === "layout") {
      return "Describe the layout you want...";
    }
    if (mode === "folder") {
      return "Describe the folder component you want...";
    }
    return defaultPlaceholder;
  }

  // src/assets/js/edit/prompt/history.js
  function tagHistory(history) {
    return history.map((msg, idx) => ({ ...msg, _index: idx }));
  }
  function isPendingAssistantHistory(item) {
    return (item == null ? void 0 : item.role) === "assistant" && String((item == null ? void 0 : item.content) || "").trim() === "Generating answer..." && !(item == null ? void 0 : item.templateSnapshot);
  }
  function cleanPersistedHistory(history) {
    return Array.isArray(history) ? history.filter((item) => !isPendingAssistantHistory(item)).slice(-12) : [];
  }
  function shouldUsePersistedPromptHistory(config, mode = "folder") {
    var _a;
    const layout = ((_a = config == null ? void 0 : config.work) == null ? void 0 : _a.layout) && typeof config.work.layout === "object" ? config.work.layout : {};
    if (mode !== "layout" && typeof layout.sectionTemplate === "string" && layout.sectionTemplate.trim() === "") {
      return false;
    }
    return true;
  }
  function trimHistoryText(value, maxLength = 600) {
    const normalized = String(value != null ? value : "").replace(/\s+/g, " ").trim();
    if (!normalized) {
      return "";
    }
    if (normalized.length <= maxLength) {
      return normalized;
    }
    return `${normalized.slice(0, Math.max(0, maxLength - 3))}...`;
  }
  function buildTemplateHistorySnapshot({
    templateText = "",
    nextCss = null,
    nextJs = null,
    nextTitle = null,
    nextDescription = null,
    nextWork = null,
    isLayoutTarget = false
  } = {}) {
    const template = trimHistoryText(templateText, isLayoutTarget ? 1e3 : 800);
    if (!template) {
      return null;
    }
    const snapshot = {
      targetType: isLayoutTarget ? "layout" : "partial",
      template,
      templateLength: String(templateText || "").trim().length
    };
    if (typeof nextCss === "string" && nextCss.trim() !== "") {
      snapshot.css = trimHistoryText(nextCss, 400);
      snapshot.cssLength = nextCss.length;
    }
    if (typeof nextJs === "string" && nextJs.trim() !== "") {
      snapshot.js = trimHistoryText(nextJs, 320);
      snapshot.jsLength = nextJs.length;
    }
    if (typeof nextTitle === "string" && nextTitle.trim() !== "") {
      snapshot.title = trimHistoryText(nextTitle, 160);
    }
    if (typeof nextDescription === "string" && nextDescription.trim() !== "") {
      snapshot.description = trimHistoryText(nextDescription, 220);
    }
    if (nextWork && typeof nextWork === "object") {
      try {
        snapshot.workSnapshot = JSON.parse(JSON.stringify(nextWork));
      } catch (e) {
        snapshot.workSnapshot = { ...nextWork };
      }
      const keys = Object.keys(nextWork).filter((key) => key !== "layout").slice(0, 6);
      if (keys.length) {
        snapshot.workKeys = keys;
      }
      if (Array.isArray(nextWork.fields) && nextWork.fields.length) {
        snapshot.workFieldNames = nextWork.fields.map((field) => field && typeof field.name === "string" ? field.name.trim() : "").filter(Boolean).slice(0, 8);
      }
      if (nextWork.layout && typeof nextWork.layout === "object") {
        const layoutCandidate = nextWork.layout.name || nextWork.layout.mode || nextWork.layout.value || "";
        if (typeof layoutCandidate === "string" && layoutCandidate.trim() !== "") {
          snapshot.layoutName = layoutCandidate.trim();
        }
      } else if (typeof nextWork.layout === "string" && nextWork.layout.trim() !== "") {
        snapshot.layoutName = nextWork.layout.trim();
      }
    }
    return snapshot;
  }
  function serializeHistoryForRequest(history, options = {}) {
    const list = Array.isArray(history) ? history : [];
    const serialized = list.map((item) => {
      const role = (item == null ? void 0 : item.role) || "user";
      let content = String((item == null ? void 0 : item.content) || "");
      const snapshot = item == null ? void 0 : item.templateSnapshot;
      if (snapshot && typeof snapshot === "object") {
        const lines = [];
        if (typeof snapshot.targetType === "string" && snapshot.targetType) {
          lines.push(`Template snapshot target: ${snapshot.targetType}`);
        }
        if (typeof snapshot.template === "string" && snapshot.template) {
          lines.push(`Template snapshot:
${snapshot.template}`);
        }
        if (typeof snapshot.css === "string" && snapshot.css) {
          lines.push(`CSS snapshot:
${snapshot.css}`);
        }
        if (typeof snapshot.js === "string" && snapshot.js) {
          lines.push(`JS snapshot:
${snapshot.js}`);
        }
        if (typeof snapshot.title === "string" && snapshot.title) {
          lines.push(`Title snapshot: ${snapshot.title}`);
        }
        if (typeof snapshot.description === "string" && snapshot.description) {
          lines.push(`Description snapshot: ${snapshot.description}`);
        }
        if (Array.isArray(snapshot.workKeys) && snapshot.workKeys.length) {
          lines.push(`Work keys updated: ${snapshot.workKeys.join(", ")}`);
        }
        if (Array.isArray(snapshot.workFieldNames) && snapshot.workFieldNames.length) {
          lines.push(`Work fields snapshot: ${snapshot.workFieldNames.join(", ")}`);
        }
        if (typeof snapshot.layoutName === "string" && snapshot.layoutName) {
          lines.push(`Layout name snapshot: ${snapshot.layoutName}`);
        }
        if (lines.length) {
          content = content ? `${content}

${lines.join("\n\n")}` : lines.join("\n\n");
        }
      }
      return { role, content };
    });
    const seedTemplateText = typeof (options == null ? void 0 : options.initialTemplateText) === "string" ? options.initialTemplateText : "";
    const seedRole = typeof (options == null ? void 0 : options.initialTemplateRole) === "string" && options.initialTemplateRole.trim() === "system" ? "system" : "assistant";
    if (seedTemplateText.trim() !== "" && !serialized.some((item) => item && item.role === "assistant")) {
      serialized.unshift({
        role: seedRole,
        content: seedTemplateText
      });
    }
    return serialized;
  }
  function summarizeSerializedHistory(history) {
    const serialized = serializeHistoryForRequest(history);
    return serialized.reduce((summary, item) => ({
      count: summary.count + 1,
      chars: summary.chars + String((item == null ? void 0 : item.content) || "").length
    }), { count: 0, chars: 0 });
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
    const booleanKeys = /* @__PURE__ */ new Set([
      ...Object.entries(work).filter(([, value]) => typeof value === "boolean").map(([key]) => key),
      "autoplay",
      "loop",
      "muted"
    ]);
    booleanKeys.forEach((key) => {
      const value = work[key];
      if (typeof value !== "boolean" && !["autoplay", "loop", "muted"].includes(key)) {
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
      "model",
      "categories",
      "category",
      "autoplay",
      "loop",
      "muted",
      "poster"
    ]);
    const filtered = {};
    Object.entries(work).forEach(([key, value]) => {
      if (allowedKeys.has(key)) {
        filtered[key] = value;
      }
    });
    return filtered;
  }

  // src/assets/js/edit/work-fields.js
  var RESERVED_WORK_FIELD_NAMES = /* @__PURE__ */ new Set(["fields", "layout", "type", "model", "engine", "syntax", "mimeType", "categories", "category", "templateMap"]);
  var SUPPORTED_WORK_FIELD_TYPES = /* @__PURE__ */ new Set(["text", "textarea", "number", "checkbox", "select", "color", "date", "url", "email"]);
  var SCHEMA_NUMBER_KEYS = ["minimum", "maximum", "exclusiveMinimum", "exclusiveMaximum", "multipleOf", "minLength", "maxLength", "minItems", "maxItems", "minProperties", "maxProperties", "step"];
  var WORK_FIELD_SCHEMA_PROFILES = {
    text: {
      defaults: {},
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "format", "pattern", "minLength", "maxLength", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    textarea: {
      defaults: {},
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "format", "pattern", "minLength", "maxLength", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    number: {
      defaults: { step: 1 },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "const", "default", "minimum", "maximum", "exclusiveMinimum", "exclusiveMaximum", "multipleOf", "step", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    checkbox: {
      defaults: { default: false },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "const", "default", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    select: {
      defaults: {},
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "const", "default", "enum", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    color: {
      defaults: { format: "color" },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    date: {
      defaults: { format: "date" },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    url: {
      defaults: { format: "uri" },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    },
    email: {
      defaults: { format: "email" },
      visibleControls: /* @__PURE__ */ new Set(["title", "description", "placeholder", "const", "default", "examples", "required", "readOnly", "writeOnly", "deprecated", "nullable"])
    }
  };
  function getWorkFieldSchemaProfile(type = "text") {
    const normalizedType = normalizeFieldType(type);
    return WORK_FIELD_SCHEMA_PROFILES[normalizedType] || WORK_FIELD_SCHEMA_PROFILES.text;
  }
  function normalizeFieldName(value, fallbackIndex = 1) {
    const trimmed = String(value != null ? value : "").trim().toLowerCase();
    const compact = trimmed.replace(/[^a-z0-9]+/g, "");
    return compact || `text${fallbackIndex}`;
  }
  function normalizeFieldLabel(value, fallbackIndex = 1) {
    const trimmed = String(value != null ? value : "").trim();
    if (!trimmed) {
      return `Text ${fallbackIndex}`;
    }
    return trimmed.replace(/([a-z0-9])([A-Z])/g, "$1 $2").replace(/([a-zA-Z])([0-9])/g, "$1 $2").replace(/([0-9])([a-zA-Z])/g, "$1 $2").replace(/[_-]+/g, " ").replace(/\s+/g, " ").trim().replace(/(^|\s)\w/g, (match) => match.toUpperCase());
  }
  function normalizeFieldType(value) {
    const type = String(value != null ? value : "").trim().toLowerCase();
    return SUPPORTED_WORK_FIELD_TYPES.has(type) ? type : "text";
  }
  function normalizeBoolean(value, fallback = false) {
    if (value === null || value === void 0 || value === "") {
      return !!fallback;
    }
    if (typeof value === "boolean") {
      return value;
    }
    const token = String(value).trim().toLowerCase();
    if (["true", "1", "yes", "on"].includes(token)) {
      return true;
    }
    if (["false", "0", "no", "off"].includes(token)) {
      return false;
    }
    return !!fallback;
  }
  function normalizeNullableNumber(value) {
    if (value === null || value === void 0 || value === "") {
      return null;
    }
    const number = Number(value);
    return Number.isFinite(number) ? number : null;
  }
  function normalizeText(value) {
    return String(value != null ? value : "");
  }
  function normalizeList(value) {
    if (Array.isArray(value)) {
      return value.map((item) => String(item != null ? item : "").trim()).filter(Boolean);
    }
    const normalized = String(value != null ? value : "").trim();
    if (!normalized) {
      return [];
    }
    return normalized.split(/\r?\n|,/).map((item) => item.trim()).filter(Boolean);
  }
  function normalizeFieldValue(type, value) {
    if (type === "checkbox") {
      return normalizeBoolean(value);
    }
    if (type === "number") {
      const number = normalizeNullableNumber(value);
      return number === null ? "" : number;
    }
    return normalizeText(value);
  }
  function normalizePreservedExtras(candidate = {}) {
    const extras = {};
    Object.keys(candidate || {}).forEach((key) => {
      if (key === "type" || key === "name" || key === "key" || key === "label" || key === "title" || key === "description" || key === "placeholder" || key === "value" || key === "default" || key === "const" || key === "text" || key === "fields" || key === "options" || key === "enum" || key === "examples" || key === "required" || key === "readOnly" || key === "writeOnly" || key === "deprecated" || key === "nullable" || key === "uniqueItems" || key === "format" || key === "pattern" || key === "contentMediaType" || key === "contentEncoding" || SCHEMA_NUMBER_KEYS.includes(key) || RESERVED_WORK_FIELD_NAMES.has(String(key).trim().toLowerCase())) {
        return;
      }
      extras[key] = candidate[key];
    });
    return extras;
  }
  function isReservedWorkFieldName(name = "") {
    return RESERVED_WORK_FIELD_NAMES.has(String(name || "").trim().toLowerCase());
  }
  function createDefaultWorkField(fields = []) {
    const index = Array.isArray(fields) ? fields.length + 1 : 1;
    const base = {
      type: "text",
      name: `text${index}`,
      title: `Text ${index}`,
      label: `Text ${index}`,
      description: "",
      placeholder: "",
      const: "",
      value: "",
      default: "",
      required: false,
      readOnly: false,
      writeOnly: false,
      nullable: false,
      deprecated: false,
      uniqueItems: false,
      format: "",
      pattern: "",
      enum: [],
      examples: []
    };
    return applyWorkFieldTypeDefaults(base, base.type);
  }
  function applyWorkFieldTypeDefaults(field = {}, type = (field == null ? void 0 : field.type) || "text") {
    const profile = getWorkFieldSchemaProfile(type);
    const nextField = { ...field || {}, type: normalizeFieldType(type) };
    Object.entries(profile.defaults || {}).forEach(([key, value]) => {
      if (nextField[key] === void 0 || nextField[key] === null || nextField[key] === "") {
        nextField[key] = value;
      }
    });
    return nextField;
  }
  function normalizeWorkField(entry = {}, index = 0) {
    var _a, _b, _c, _d, _e, _f, _g, _h, _i, _j, _k, _l, _m, _n, _o, _p;
    const candidate = entry && typeof entry === "object" ? entry : {};
    const type = normalizeFieldType(candidate.type);
    const name = normalizeFieldName((_c = (_b = (_a = candidate.name) != null ? _a : candidate.key) != null ? _b : candidate.label) != null ? _c : candidate.title, index + 1);
    const title = normalizeFieldLabel((_f = (_e = (_d = candidate.title) != null ? _d : candidate.label) != null ? _e : candidate.name) != null ? _f : name, index + 1);
    const description = normalizeText((_g = candidate.description) != null ? _g : "");
    const placeholder = normalizeText((_h = candidate.placeholder) != null ? _h : "");
    const valueSource = Object.prototype.hasOwnProperty.call(candidate, "value") ? candidate.value : Object.prototype.hasOwnProperty.call(candidate, "text") ? candidate.text : Object.prototype.hasOwnProperty.call(candidate, "const") ? candidate.const : candidate.default;
    const normalized = {
      type,
      name,
      title,
      label: title,
      description,
      placeholder,
      const: normalizeText((_i = candidate.const) != null ? _i : ""),
      value: normalizeFieldValue(type, valueSource),
      default: Object.prototype.hasOwnProperty.call(candidate, "default") ? normalizeFieldValue(type, candidate.default) : normalizeFieldValue(type, valueSource),
      required: normalizeBoolean(candidate.required, false),
      readOnly: normalizeBoolean(candidate.readOnly, false),
      writeOnly: normalizeBoolean(candidate.writeOnly, false),
      nullable: normalizeBoolean(candidate.nullable, false),
      deprecated: normalizeBoolean(candidate.deprecated, false),
      uniqueItems: normalizeBoolean(candidate.uniqueItems, false),
      format: normalizeText((_j = candidate.format) != null ? _j : ""),
      pattern: normalizeText((_k = candidate.pattern) != null ? _k : ""),
      contentMediaType: normalizeText((_l = candidate.contentMediaType) != null ? _l : ""),
      contentEncoding: normalizeText((_m = candidate.contentEncoding) != null ? _m : "")
    };
    SCHEMA_NUMBER_KEYS.forEach((key) => {
      const numberValue = normalizeNullableNumber(candidate[key]);
      if (numberValue !== null) {
        normalized[key] = numberValue;
      }
    });
    const enumValues = normalizeList((_o = (_n = candidate.enum) != null ? _n : candidate.options) != null ? _o : []);
    if (enumValues.length) {
      normalized.enum = enumValues;
      normalized.options = enumValues.slice();
    } else {
      normalized.enum = [];
      normalized.options = [];
    }
    const examples = normalizeList((_p = candidate.examples) != null ? _p : []);
    normalized.examples = examples;
    const extras = normalizePreservedExtras(candidate);
    Object.assign(normalized, extras);
    return normalized;
  }
  function extractWorkFields(work = {}) {
    if (!work || typeof work !== "object" || !Array.isArray(work.fields)) {
      return [];
    }
    return work.fields.map((field, index) => normalizeWorkField(field, index)).filter((field) => field.name && !isReservedWorkFieldName(field.name));
  }
  function materializeWorkFields(work = {}, fields = null) {
    const nextWork = { ...work || {} };
    const sourceFields = Array.isArray(fields) ? fields : extractWorkFields(work);
    const hasFieldsInput = Array.isArray(fields) || Array.isArray(work == null ? void 0 : work.fields);
    const allowTopLevelOverrides = fields === null;
    const previousFields = extractWorkFields(work);
    const previousNames = new Set(previousFields.map((field) => field.name));
    const normalized = sourceFields.map((field, index) => {
      const normalizedField = normalizeWorkField(field, index);
      if (allowTopLevelOverrides && Object.prototype.hasOwnProperty.call(nextWork, normalizedField.name)) {
        normalizedField.value = normalizeFieldValue(normalizedField.type, nextWork[normalizedField.name]);
      }
      return normalizedField;
    }).filter((field) => field.name && !isReservedWorkFieldName(field.name));
    const normalizedNames = new Set(normalized.map((field) => field.name));
    previousNames.forEach((name) => {
      if (!normalizedNames.has(name)) {
        delete nextWork[name];
      }
    });
    if (normalized.length || hasFieldsInput) {
      nextWork.fields = normalized.map((field) => ({ ...field }));
    } else {
      delete nextWork.fields;
    }
    normalized.forEach((field) => {
      nextWork[field.name] = field.value;
    });
    return nextWork;
  }
  function summarizeWorkFieldValue(field = {}) {
    var _a, _b, _c, _d;
    const normalized = normalizeWorkField(field);
    if (normalized.type === "checkbox") {
      return normalized.value ? "true" : "false";
    }
    if (normalized.type === "select" && Array.isArray(normalized.enum) && normalized.enum.length) {
      return String((_c = (_b = (_a = normalized.value) != null ? _a : normalized.default) != null ? _b : normalized.enum[0]) != null ? _c : "").trim() || "-";
    }
    const text = String((_d = normalized.value) != null ? _d : "").trim();
    return text || "-";
  }
  function summarizeWorkFields(fields = []) {
    const normalized = (Array.isArray(fields) ? fields : []).map((field, index) => normalizeWorkField(field, index)).filter((field) => field.name && !isReservedWorkFieldName(field.name));
    if (!normalized.length) {
      return "";
    }
    return normalized.slice(0, 6).map((field) => {
      const nameLabel = field.title || field.label || field.name;
      return `${nameLabel}: ${summarizeWorkFieldValue(field)}`;
    }).join(" | ");
  }
  function schemaFieldTypeOptions() {
    return ["text", "textarea", "number", "checkbox", "select", "color", "date", "url", "email"];
  }

  // src/assets/js/edit/prompt/draft.js
  function readFieldValue(root, selector) {
    if (!root || typeof root.querySelector !== "function") {
      return null;
    }
    const field = root.querySelector(selector);
    if (!field || typeof field.value !== "string") {
      return null;
    }
    return field.value;
  }
  function readPromptEditorDraft(selection2 = {}, root = document) {
    const isLayout = !!(selection2 == null ? void 0 : selection2.isLayout);
    const template = readFieldValue(root, isLayout ? "#edit-layout-primary-template" : "#edit-content-template");
    if (template === null) {
      return null;
    }
    const draft = {
      template
    };
    if (isLayout) {
      const sectionTemplate = readFieldValue(root, "#edit-content-template");
      const css = readFieldValue(root, "#edit-layout-primary-css");
      const js = readFieldValue(root, "#edit-layout-primary-js");
      if (sectionTemplate !== null) {
        draft.sectionTemplate = sectionTemplate;
      }
      if (css !== null) {
        draft.css = css;
      }
      if (js !== null) {
        draft.js = js;
      }
    }
    return draft;
  }

  // src/assets/js/edit/prompt/build/context.js
  function isExternalPromptLink(value = "") {
    const trimmed = String(value || "").trim();
    if (!trimmed) {
      return false;
    }
    if (trimmed.startsWith("//")) {
      return true;
    }
    return /^[a-z][a-z0-9+.-]*:/i.test(trimmed);
  }
  function isCmsPromptLink(value = "") {
    const trimmed = String(value || "").trim();
    if (!trimmed.startsWith("?")) {
      return false;
    }
    const params = new URLSearchParams(trimmed.replace(/^\?/, ""));
    return params.has("file") || params.has("path") || params.get("view") === "1";
  }
  function isSpecialPromptLink(value = "") {
    const trimmed = String(value || "").trim();
    return trimmed.startsWith("#") || isCmsPromptLink(trimmed) || isExternalPromptLink(trimmed);
  }
  function getPromptItemExplicitLink(item = {}) {
    const keys = ["pageLink", "pageUrl", "viewUrl", "workUrl", "viewerHref", "linkUrl", "link", "url"];
    for (const key of keys) {
      const value = String((item == null ? void 0 : item[key]) || "").trim();
      if (value) {
        return value;
      }
    }
    const rawPath = String((item == null ? void 0 : item.path) || (item == null ? void 0 : item.relativePath) || "").trim();
    return isSpecialPromptLink(rawPath) ? rawPath : "";
  }
  function getPromptItemDisplayPath(folderBasePath = "", item = {}) {
    const rawPath = String((item == null ? void 0 : item.path) || (item == null ? void 0 : item.relativePath) || "").trim();
    if (rawPath) {
      if (isCmsPromptLink(rawPath)) {
        const params = new URLSearchParams(rawPath.replace(/^\?/, ""));
        return params.get(params.has("file") ? "file" : "path") || "";
      }
      if (isSpecialPromptLink(rawPath)) {
        return rawPath;
      }
      return folderBasePath && !rawPath.startsWith(`${folderBasePath}/`) && rawPath !== folderBasePath ? `${folderBasePath}/${rawPath}` : rawPath;
    }
    const explicitLink = getPromptItemExplicitLink(item);
    if (isCmsPromptLink(explicitLink)) {
      const params = new URLSearchParams(explicitLink.replace(/^\?/, ""));
      return params.get(params.has("file") ? "file" : "path") || "";
    }
    if (explicitLink) {
      return explicitLink;
    }
    const fallbackName = String((item == null ? void 0 : item.name) || "").trim();
    if (!fallbackName) {
      return "";
    }
    return folderBasePath ? `${folderBasePath}/${fallbackName}` : fallbackName;
  }
  function getDefaultWorkCategories(type = "") {
    const normalizedType = String(type || "").trim().toLowerCase();
    if (normalizedType === "image") {
      return ["image", "media", "visual"];
    }
    if (normalizedType === "video") {
      return ["video", "media", "motion"];
    }
    if (normalizedType === "audio") {
      return ["audio", "media", "sound"];
    }
    if (normalizedType === "pdf") {
      return ["pdf", "document"];
    }
    if (normalizedType === "text") {
      return ["text", "document"];
    }
    if (normalizedType === "link") {
      return ["link", "reference"];
    }
    if (normalizedType === "folder") {
      return ["folder", "collection"];
    }
    return ["other"];
  }
  function normalizeWorkCategories(work = {}) {
    var _a, _b;
    const rawValue = Array.isArray(work == null ? void 0 : work.categories) ? work.categories : Array.isArray(work == null ? void 0 : work.category) ? work.category : (_b = (_a = work == null ? void 0 : work.categories) != null ? _a : work == null ? void 0 : work.category) != null ? _b : [];
    const sourceValues = Array.isArray(rawValue) ? rawValue : String(rawValue || "").trim() ? String(rawValue).split(/\r?\n|,/) : [];
    const categories = [];
    const append = (value) => {
      const normalized = String(value || "").trim().toLowerCase();
      if (!normalized || categories.includes(normalized)) {
        return;
      }
      categories.push(normalized);
    };
    getDefaultWorkCategories(work == null ? void 0 : work.type).forEach(append);
    sourceValues.forEach(append);
    return categories;
  }
  function buildPromptContext({ getActiveSelection: getActiveSelection2, getConfig }) {
    var _a, _b, _c, _d;
    const selection2 = typeof getActiveSelection2 === "function" ? getActiveSelection2() : { path: "", isFile: false };
    const config = typeof getConfig === "function" ? getConfig() || {} : {};
    const isLayout = !!(selection2 == null ? void 0 : selection2.isLayout);
    const path = (_b = (_a = selection2 == null ? void 0 : selection2.previewPath) != null ? _a : selection2 == null ? void 0 : selection2.path) != null ? _b : "";
    const virtualPath = (selection2 == null ? void 0 : selection2.path) || "";
    const name = path ? path.split(/[\\/]/).pop() : "";
    const isFile = isLayout ? !!(selection2 == null ? void 0 : selection2.layoutIsFile) : (_c = selection2 == null ? void 0 : selection2.isFile) != null ? _c : /\.[^\\/]+$/.test(path);
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
    const sharedPresetEl = isLayout ? document.getElementById("edit-layout-shared") : null;
    const layoutPreset = isLayout && presetEl ? String(presetEl.value || "").trim() : "";
    const layoutSharedName = isLayout && sharedPresetEl ? String(sharedPresetEl.value || "").trim() : "";
    const activeLayoutDirectory = (() => {
      if (!isLayout) {
        return resolvedLayoutDirectory || localLayoutDirectory;
      }
      if (layoutPreset === "custom") {
        return localLayoutDirectory;
      }
      if (layoutStorage === "shared" && resolvedLayoutDirectory) {
        return resolvedLayoutDirectory;
      }
      if (layoutStorage === "filesystem" && resolvedLayoutDirectory) {
        return resolvedLayoutDirectory;
      }
      return localLayoutDirectory;
    })();
    const templateTarget = isLayout ? `${activeLayoutDirectory}/template.hbs` : sectionTemplateTarget;
    const tree = Array.isArray(config == null ? void 0 : config.tree) ? config.tree : [];
    const folderBasePath = ((selection2 == null ? void 0 : selection2.isFile) ? path.split("/").slice(0, -1).join("/") : path).replace(/^\/+|\/+$/g, "");
    const ellipsis = "\u2026";
    const workConfig = config && typeof config === "object" && config.work && typeof config.work === "object" ? config.work : {};
    const rootTitle = String((config == null ? void 0 : config.title) || name || "").trim();
    const rootDescription = String((config == null ? void 0 : config.description) || "").trim();
    const rootFolderName = String((config == null ? void 0 : config.folderName) || name || "").trim();
    const rootSlug = String((config == null ? void 0 : config.slug) || "").trim();
    const workFields = extractWorkFields(workConfig);
    const workWithCategories = {
      ...workConfig,
      categories: normalizeWorkCategories(workConfig)
    };
    const workPreview = Object.entries(workConfig || {}).slice(0, 6).map(([key, value]) => {
      if (key === "fields" && Array.isArray(value)) {
        const summary = summarizeWorkFields(value);
        return summary ? `fields: ${summary}` : `fields: ${value.length} item(s)`;
      }
      if (typeof value === "boolean") {
        return `${key}: ${value ? "true" : "false"}`;
      }
      if (Array.isArray(value)) {
        return `${key}: [${value.length} item(s)]`;
      }
      if (value && typeof value === "object") {
        const keys = Object.keys(value).slice(0, 4);
        return `${key}: {${keys.join(", ")}}`;
      }
      if (value === null || value === void 0) {
        return `${key}: null`;
      }
      const str = String(value);
      return `${key}: ${str.length > 28 ? `${str.slice(0, 25)}${ellipsis}` : str}`;
    }).join(", ");
    const workFieldsPreview = summarizeWorkFields(workFields);
    const refPreview = tree.slice(0, 4).map((item) => {
      const itemName = (item == null ? void 0 : item.name) || (item == null ? void 0 : item.path) || "";
      if (!itemName) {
        return "";
      }
      const itemPath = getPromptItemDisplayPath(folderBasePath, item);
      const isItemFile = ((item == null ? void 0 : item.type) || "file") !== "folder";
      const itemPageLink = getPromptItemExplicitLink(item) || (isItemFile ? `?view=1&file=${encodeURIComponent(itemPath)}` : `?view=1&path=${encodeURIComponent(itemPath)}`);
      const itemAssetUrl = getPromptItemExplicitLink(item) || (isItemFile ? itemPath.split("/").map((part) => encodeURIComponent(part)).join("/") : `?path=${encodeURIComponent(itemPath)}`);
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
    const editorDraft = readPromptEditorDraft(selection2);
    return {
      path,
      virtualPath,
      isLayout,
      layoutPreset,
      layoutSharedName,
      name,
      title: rootTitle,
      pageLink: viewUrl,
      viewUrl,
      templateTarget,
      layoutTemplateTarget,
      sectionTemplateTarget,
      layoutBaseHref,
      inheritedLayoutDirectory,
      layoutAssetsPreview,
      editorDraft,
      root: {
        title: rootTitle,
        name: rootFolderName,
        folderName: rootFolderName,
        path,
        slug: rootSlug,
        description: rootDescription,
        type: (selection2 == null ? void 0 : selection2.isFile) ? "file" : "folder",
        sectionPartial: isLayout ? "layout" : isFile ? "work" : "works"
      },
      work: {
        title: String((workConfig == null ? void 0 : workConfig.title) || name || rootTitle || "").trim(),
        name: String((workConfig == null ? void 0 : workConfig.name) || name || "").trim(),
        path,
        slug: String((workConfig == null ? void 0 : workConfig.slug) || rootSlug || "").trim(),
        description: String((workConfig == null ? void 0 : workConfig.description) || rootDescription || "").trim(),
        type: String((workConfig == null ? void 0 : workConfig.type) || ((selection2 == null ? void 0 : selection2.isFile) ? "file" : "folder") || "").trim(),
        kind: String((workConfig == null ? void 0 : workConfig.kind) || ((selection2 == null ? void 0 : selection2.isFile) ? "file" : "folder") || "").trim(),
        categories: normalizeWorkCategories(workConfig),
        fields: workFields,
        layout: (_d = workWithCategories.layout) != null ? _d : null
      },
      workData: workWithCategories,
      workFields,
      workFieldsPreview,
      workPreview,
      refPreview
    };
  }

  // src/assets/js/edit/prompt/render/context.js
  function renderValue(value = "", depth = 0) {
    if (value === null || value === void 0 || value === "") {
      return '<code class="prompt-context-code">-</code>';
    }
    if (Array.isArray(value)) {
      const filtered = value.filter((item) => item !== null && item !== void 0 && item !== "");
      if (!filtered.length) {
        return '<code class="prompt-context-code">[]</code>';
      }
      return `
            <div class="prompt-context-list prompt-context-list--nested">
                ${filtered.map((item) => `<div class="prompt-context-list-item">${renderValue(item, depth + 1)}</div>`).join("")}
            </div>
        `;
    }
    if (typeof value === "object") {
      const entries = Object.entries(value).filter(([, entryValue]) => entryValue !== void 0);
      if (!entries.length) {
        return '<code class="prompt-context-code">{}</code>';
      }
      return `
            <div class="prompt-context-object${depth > 0 ? " prompt-context-object--nested" : ""}">
                ${entries.map(([entryKey, entryValue]) => `
                    <div class="prompt-context-object-row">
                        <div class="prompt-context-object-key">${escapeHtml(entryKey)}</div>
                        <div class="prompt-context-object-value">${renderValue(entryValue, depth + 1)}</div>
                    </div>
                `).join("")}
            </div>
        `;
    }
    return `<code class="prompt-context-code">${escapeHtml(String(value))}</code>`;
  }
  function renderRow(label, value, className = "") {
    return `
        <div class="prompt-context-item${className ? ` ${className}` : ""}">
            <div class="prompt-context-key">${escapeHtml(label)}</div>
            <div class="prompt-context-value">${renderValue(value)}</div>
        </div>
    `;
  }
  function renderList(label, values = []) {
    const filtered = Array.isArray(values) ? values.filter(Boolean) : [];
    if (!filtered.length) {
      return "";
    }
    return `
        <div class="prompt-context-item">
            <div class="prompt-context-key">${escapeHtml(label)}</div>
            <div class="prompt-context-value">
                <div class="prompt-context-list">
                    ${filtered.map((value) => `<div class="prompt-context-list-item">${renderValue(value)}</div>`).join("")}
                </div>
            </div>
        </div>
    `;
  }
  function renderPromptContext(contextEl, context) {
    if (!contextEl) {
      return;
    }
    const path = (context == null ? void 0 : context.path) || "";
    const virtualPath = (context == null ? void 0 : context.virtualPath) || "";
    const layoutPreset = (context == null ? void 0 : context.layoutPreset) || "";
    const layoutSharedName = (context == null ? void 0 : context.layoutSharedName) || "";
    const name = (context == null ? void 0 : context.name) || "";
    const pageLink = (context == null ? void 0 : context.pageLink) || (context == null ? void 0 : context.viewUrl) || "";
    const viewUrl = (context == null ? void 0 : context.viewUrl) || "";
    const templateTarget = (context == null ? void 0 : context.templateTarget) || "";
    const layoutTemplateTarget = (context == null ? void 0 : context.layoutTemplateTarget) || "";
    const sectionTemplateTarget = (context == null ? void 0 : context.sectionTemplateTarget) || "";
    const layoutBaseHref = (context == null ? void 0 : context.layoutBaseHref) || "";
    const inheritedLayoutDirectory = (context == null ? void 0 : context.inheritedLayoutDirectory) || "";
    const layoutAssetsPreview = (context == null ? void 0 : context.layoutAssetsPreview) || "";
    const editorDraft = (context == null ? void 0 : context.editorDraft) && typeof context.editorDraft === "object" ? context.editorDraft : null;
    const rootData = (context == null ? void 0 : context.root) && typeof context.root === "object" ? context.root : {};
    const workData = (context == null ? void 0 : context.work) && typeof context.work === "object" ? context.work : (context == null ? void 0 : context.workData) && typeof context.workData === "object" ? context.workData : {};
    const workFields = Array.isArray(context == null ? void 0 : context.workFields) ? context.workFields : [];
    const workFieldsPreview = (context == null ? void 0 : context.workFieldsPreview) || "";
    const refPreview = (context == null ? void 0 : context.refPreview) || "";
    const partials = ["poff-layout", "filesystem-layout", "works", "work"];
    const refItems = refPreview ? refPreview.split(" | ").filter(Boolean) : [];
    const layoutAssetItems = layoutAssetsPreview ? layoutAssetsPreview.split(" | ").filter(Boolean) : [];
    contextEl.innerHTML = `
        <div class="prompt-context-grid">
            ${Object.keys(rootData).length ? renderRow("root", rootData, "prompt-context-item--accent") : ""}
            ${Object.keys(workData).length ? renderRow("work", workData, "prompt-context-item--accent") : ""}
            ${(context == null ? void 0 : context.isLayout) ? renderRow("virtualPath", virtualPath) : ""}
            ${(context == null ? void 0 : context.isLayout) && layoutPreset ? renderRow("layoutPreset", layoutPreset) : ""}
            ${(context == null ? void 0 : context.isLayout) && layoutSharedName ? renderRow("layoutSharedName", layoutSharedName) : ""}
            ${renderRow("pageLink", pageLink)}
            ${renderRow("path", path)}
            ${renderRow("name", name)}
            ${(context == null ? void 0 : context.title) ? renderRow("title", context.title) : ""}
            ${renderRow("viewUrl", viewUrl)}
            ${templateTarget ? renderRow("templateTarget", templateTarget) : ""}
            ${layoutTemplateTarget ? renderRow("layoutTemplateTarget", layoutTemplateTarget) : ""}
            ${sectionTemplateTarget ? renderRow("sectionTemplateTarget", sectionTemplateTarget) : ""}
            ${layoutBaseHref ? renderRow("layoutBaseHref", layoutBaseHref) : ""}
            ${inheritedLayoutDirectory ? renderRow("inheritedLayoutDirectory", inheritedLayoutDirectory) : ""}
            ${editorDraft ? renderRow("editorDraft", editorDraft) : ""}
        </div>
        ${renderList("partials", partials)}
        ${renderList("layoutAssets", layoutAssetItems)}
        ${renderList("refs", refItems)}
        ${workFieldsPreview ? renderRow("work.fields", workFields) : ""}
        ${Object.keys(workData).length ? renderRow("work.*", workData) : ""}
    `;
  }

  // src/assets/js/edit/prompt/render/history.js
  function renderPromptHistory(container, history, streamState, options = {}) {
    if (!container) {
      return;
    }
    const { forceScroll = false } = options;
    const stickToBottom = container.scrollHeight - container.clientHeight - container.scrollTop < 24;
    const historyStats = summarizeSerializedHistory(history);
    if (!history || !history.length) {
      container.innerHTML = '<div class="small-note">History payload: 0 messages, 0 chars.</div><div class="small-note">No messages yet.</div>';
      return;
    }
    container.innerHTML = history.map((msg) => {
      const role = (msg.role || "user").toLowerCase();
      const isStreaming = streamState && streamState.index === msg._index;
      const content = isStreaming ? streamState.text : msg.content;
      const safeContent = content || "";
      const snapshot = (msg == null ? void 0 : msg.templateSnapshot) && typeof msg.templateSnapshot === "object" ? msg.templateSnapshot : null;
      const snapshotParts = [];
      const canReset = role === "assistant" && !!snapshot;
      if (snapshot) {
        if (typeof snapshot.targetType === "string" && snapshot.targetType) {
          snapshotParts.push(`target: ${snapshot.targetType}`);
        }
        if (typeof snapshot.templateLength === "number" && snapshot.templateLength > 0) {
          snapshotParts.push(`template: ${snapshot.templateLength} chars`);
        }
        if (Array.isArray(snapshot.workFieldNames) && snapshot.workFieldNames.length) {
          snapshotParts.push(`fields: ${snapshot.workFieldNames.join(", ")}`);
        }
        if (typeof snapshot.cssLength === "number" && snapshot.cssLength > 0) {
          snapshotParts.push(`css: ${snapshot.cssLength}`);
        }
        if (typeof snapshot.jsLength === "number" && snapshot.jsLength > 0) {
          snapshotParts.push(`js: ${snapshot.jsLength}`);
        }
      }
      const snapshotMeta = snapshotParts.length ? `<span>${escapeHtml(snapshotParts.join(" | "))}</span>` : "";
      const snapshotAction = canReset ? `<button type="button" class="btn btn-secondary prompt-message-reset" data-history-reset-index="${msg._index}" title="Reset template to this stage">reset</button>` : "";
      return `
            <div class="prompt-message prompt-message-${role}">
                <span class="prompt-message-role">${escapeHtml(role)}:</span>
                <span class="prompt-message-content">${escapeHtml(safeContent)}${isStreaming ? '<span class="stream-cursor"></span>' : ""}</span>
                ${snapshotMeta || snapshotAction ? `<div class="small-note prompt-message-meta" style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">${snapshotMeta}${snapshotAction}</div>` : ""}
            </div>
        `;
    }).join("");
    container.innerHTML = `<div class="small-note">History payload: ${historyStats.count} messages, ${historyStats.chars} chars.</div>${container.innerHTML}`;
    if (forceScroll || stickToBottom) {
      container.scrollTop = container.scrollHeight;
    }
  }

  // src/assets/js/edit/prompt/render/summary.js
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

  // src/assets/js/edit/prompt/stream.js
  function createStreamState() {
    return { state: null };
  }
  function stopStreaming(stream) {
    if (!stream) {
      return;
    }
    stream.state = null;
  }
  function beginStreaming({ stream, targetIndex, history, renderHistory }) {
    if (!stream || typeof renderHistory !== "function") {
      return;
    }
    stopStreaming(stream);
    if (history && history[targetIndex]) {
      history[targetIndex].content = "";
    }
    stream.state = { index: targetIndex, text: "" };
    renderHistory();
  }
  function appendStreamingChunk({ stream, chunk = "", history, renderHistory }) {
    if (!stream || !stream.state || typeof renderHistory !== "function" || chunk === "") {
      return;
    }
    stream.state.text += chunk;
    if (history && history[stream.state.index]) {
      history[stream.state.index].content = stream.state.text;
    }
    renderHistory();
  }
  function finishStreaming({ stream, history, fullText = "", renderHistory }) {
    if (!stream || typeof renderHistory !== "function" || !stream.state) {
      return;
    }
    const nextText = fullText || stream.state.text || "";
    if (history && history[stream.state.index]) {
      history[stream.state.index].content = nextText;
    }
    stopStreaming(stream);
    renderHistory();
  }

  // src/assets/js/edit/selection.js
  function getSelectionOrFallback(getActiveSelection2, fallback = {}) {
    return typeof getActiveSelection2 === "function" ? getActiveSelection2() || fallback : fallback;
  }
  function getLayoutPresetValue(defaultValue = "actual") {
    const presetEl = document.getElementById("edit-layout-preset");
    if (!presetEl || typeof presetEl.value !== "string") {
      return defaultValue;
    }
    const value = presetEl.value.trim();
    return value || defaultValue;
  }

  // src/assets/js/edit/status.js
  function setStatusMessage(statusEl, message, success = false) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message;
    statusEl.className = success ? "edit-status edit-status-success" : "edit-status";
  }

  // src/assets/js/edit/prompt/editor-fields.js
  function updatePromptEditorFields({ templateText, nextTitle, nextDescription, nextWork, isLayoutTarget, nextCss = null, nextJs = null }) {
    const workUpdates = nextWork && typeof nextWork === "object" ? nextWork : null;
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
    if (workUpdates && typeof workUpdates.type === "string") {
      document.querySelectorAll("#edit-work-type").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = workUpdates.type;
        }
      });
    }
    document.querySelectorAll("[data-work-config-field]").forEach((field) => {
      if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return;
      }
      const key = String(field.dataset.workConfigKey || "").trim();
      if (!key || !workUpdates || !Object.prototype.hasOwnProperty.call(workUpdates, key)) {
        return;
      }
      const value = workUpdates[key];
      if (field instanceof HTMLInputElement && field.type === "checkbox") {
        field.checked = !!value;
        return;
      }
      if (field.dataset.workConfigKind === "json") {
        field.value = value === null || value === void 0 ? "" : typeof value === "string" ? value : JSON.stringify(value, null, 2);
        return;
      }
      field.value = value === null || value === void 0 ? "" : String(value);
    });
    if (isLayoutTarget && nextCss !== null) {
      document.querySelectorAll("#edit-layout-primary-css").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = nextCss;
        }
      });
    }
    if (isLayoutTarget && nextJs !== null) {
      document.querySelectorAll("#edit-layout-primary-js").forEach((field) => {
        if (field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement) {
          field.value = nextJs;
        }
      });
    }
  }
  function focusPromptTemplateField(isLayoutTarget) {
    const selector = isLayoutTarget ? "#edit-layout-primary-template" : "#edit-content-template";
    const field = document.querySelector(selector);
    if (!(field instanceof HTMLTextAreaElement || field instanceof HTMLInputElement)) {
      return;
    }
    field.focus({ preventScroll: true });
    if (typeof field.select === "function") {
      field.select();
    } else if (typeof field.setSelectionRange === "function") {
      const value = field.value || "";
      field.setSelectionRange(value.length, value.length);
    }
    field.scrollIntoView({ behavior: "smooth", block: "center" });
  }
  function syncWorkFieldEditors(nextWork = null) {
    if (!nextWork || typeof nextWork !== "object") {
      return;
    }
    const fields = Array.isArray(nextWork.fields) ? nextWork.fields : [];
    const fieldsByName = /* @__PURE__ */ new Map();
    fields.forEach((field) => {
      if (!field || typeof field !== "object" || typeof field.name !== "string" || !field.name.trim()) {
        return;
      }
      fieldsByName.set(field.name.trim(), field);
    });
    document.querySelectorAll("[data-work-field-row]").forEach((row) => {
      const nameInput = row.querySelector("[data-work-field-name]");
      const typeInput = row.querySelector("[data-work-field-type]");
      const valueInput = row.querySelector("[data-work-field-value]");
      const currentName = nameInput && typeof nameInput.value === "string" ? nameInput.value.trim() : "";
      if (!currentName) {
        return;
      }
      const nextField = fieldsByName.get(currentName) || (Object.prototype.hasOwnProperty.call(nextWork, currentName) ? { name: currentName, type: "text", value: nextWork[currentName] } : null);
      if (!nextField) {
        return;
      }
      const setText = (selector, value) => {
        const input = row.querySelector(selector);
        if (input instanceof HTMLTextAreaElement || input instanceof HTMLInputElement || input instanceof HTMLSelectElement) {
          input.value = Array.isArray(value) ? value.join("\n") : String(value != null ? value : "");
        }
      };
      const setChecked = (selector, value) => {
        const input = row.querySelector(selector);
        if (input instanceof HTMLInputElement && input.type === "checkbox") {
          input.checked = !!value;
        }
      };
      setText("[data-work-field-type]", nextField.type);
      setText("[data-work-field-name]", nextField.name);
      setText("[data-work-field-value]", nextField.value);
      setText("[data-work-field-title]", nextField.title);
      setText("[data-work-field-description]", nextField.description);
      setText("[data-work-field-placeholder]", nextField.placeholder);
      setText("[data-work-field-const]", nextField.const);
      setText("[data-work-field-default]", nextField.default);
      setText("[data-work-field-format]", nextField.format);
      setText("[data-work-field-contentMediaType]", nextField.contentMediaType);
      setText("[data-work-field-contentEncoding]", nextField.contentEncoding);
      setText("[data-work-field-pattern]", nextField.pattern);
      setText("[data-work-field-minLength]", nextField.minLength);
      setText("[data-work-field-maxLength]", nextField.maxLength);
      setText("[data-work-field-minimum]", nextField.minimum);
      setText("[data-work-field-maximum]", nextField.maximum);
      setText("[data-work-field-step]", nextField.step);
      setText("[data-work-field-minProperties]", nextField.minProperties);
      setText("[data-work-field-maxProperties]", nextField.maxProperties);
      setText("[data-work-field-enum]", Array.isArray(nextField.enum) ? nextField.enum : []);
      setText("[data-work-field-examples]", Array.isArray(nextField.examples) ? nextField.examples : []);
      setChecked("[data-work-field-required]", nextField.required);
      setChecked("[data-work-field-readOnly]", nextField.readOnly);
      setChecked("[data-work-field-writeOnly]", nextField.writeOnly);
      setChecked("[data-work-field-deprecated]", nextField.deprecated);
      setChecked("[data-work-field-nullable]", nextField.nullable);
      setChecked("[data-work-field-uniqueItems]", nextField.uniqueItems);
    });
  }

  // src/assets/js/edit/prompt/runtime.js
  function createPromptRuntime({
    root,
    statusEl,
    drawerForm,
    getActiveSelection: getActiveSelection2,
    getConfig,
    requestEditConfig: requestEditConfig2,
    promptInputEl,
    promptMessagesEl,
    promptContextEl,
    promptSummaryEl,
    promptGenerationEl,
    promptGenerationLabelEl,
    promptTemplateLabelEl,
    promptTemplateCodeEl,
    promptAttachmentEl,
    promptAttachEl,
    promptAttachmentPreviewEl,
    promptAttachmentNameEl,
    promptImageInputEl,
    promptSendEl,
    promptClearEl,
    promptAttachmentRemoveEl
  }) {
    const stream = createStreamState();
    const state = {
      promptHistory: [],
      activePath: getSelectionOrFallback(getActiveSelection2, { path: "" }).path,
      activePromptMode: getSelectionOrFallback(getActiveSelection2, {}).isLayout ? "layout" : getSelectionOrFallback(getActiveSelection2, {}).previewIsFile ? "file" : "folder",
      imageAttachment: null,
      isSending: false
    };
    const defaultPromptPlaceholder = (promptInputEl == null ? void 0 : promptInputEl.getAttribute("placeholder")) || "Describe the component you want...";
    const imageContextPattern = /\.(avif|bmp|gif|heic|heif|jpe?g|png|svg|webp)$/i;
    const currentSelection = (fallback = {}) => getSelectionOrFallback(getActiveSelection2, fallback);
    const currentPromptMode = () => getPromptMode(currentSelection());
    const currentPromptPlaceholder = () => getPromptPlaceholderForMode(currentPromptMode(), defaultPromptPlaceholder);
    const currentDefaultSystemPrompt = () => getDefaultSystemPromptForMode(currentPromptMode(), {
      file: defaultFileSystemPrompt,
      folder: defaultFolderSystemPrompt,
      layout: defaultLayoutSystemPrompt
    });
    const currentSystemPromptSettingKey = () => getSystemPromptSettingKeyForMode(currentPromptMode());
    const setPromptStatus = (message, success = false) => setStatusMessage(statusEl, message, success);
    const getLayoutPreset = () => getLayoutPresetValue();
    const forceLayoutPromptToCustom = () => {
      const presetEl = document.getElementById("edit-layout-preset");
      if (presetEl && presetEl.value !== "custom") {
        presetEl.value = "custom";
      }
      return "custom";
    };
    const currentHasImageContext = () => {
      const selection2 = currentSelection(null);
      const selectionPath = typeof (selection2 == null ? void 0 : selection2.previewPath) === "string" && selection2.previewPath.trim() !== "" ? selection2.previewPath : typeof (selection2 == null ? void 0 : selection2.path) === "string" ? selection2.path : "";
      return imageContextPattern.test(selectionPath);
    };
    const currentSelectionName = () => {
      const selection2 = currentSelection(null);
      const selectionPath = typeof (selection2 == null ? void 0 : selection2.previewPath) === "string" && selection2.previewPath.trim() !== "" ? selection2.previewPath : typeof (selection2 == null ? void 0 : selection2.path) === "string" ? selection2.path : "";
      const normalizedPath = String(selectionPath || "").replace(/\\/g, "/").replace(/^\/+|\/+$/g, "");
      if (!normalizedPath) {
        return "";
      }
      const parts = normalizedPath.split("/").filter(Boolean);
      return parts.length ? parts[parts.length - 1] : "";
    };
    const insertPromptText2 = async (text) => {
      var _a;
      if (!promptInputEl || !text) {
        return false;
      }
      const start = typeof promptInputEl.selectionStart === "number" ? promptInputEl.selectionStart : promptInputEl.value.length;
      const end = typeof promptInputEl.selectionEnd === "number" ? promptInputEl.selectionEnd : promptInputEl.value.length;
      const before = promptInputEl.value.slice(0, start);
      const after = promptInputEl.value.slice(end);
      const separator = before && !/\s$/.test(before) ? " " : "";
      const nextValue = `${before}${separator}${text}${after}`;
      promptInputEl.value = nextValue;
      const caret = (before + separator + text).length;
      if (typeof promptInputEl.setSelectionRange === "function") {
        promptInputEl.setSelectionRange(caret, caret);
      }
      promptInputEl.focus({ preventScroll: true });
      renderSummary(`Inserted ${text} into the prompt.`);
      try {
        if ((_a = navigator.clipboard) == null ? void 0 : _a.writeText) {
          await navigator.clipboard.writeText(text);
        }
        setPromptStatus(`Inserted and copied: ${text}`, true);
      } catch (e) {
        setPromptStatus(`Inserted: ${text}`);
      }
      return true;
    };
    const setHistory = (nextHistory) => {
      const list = Array.isArray(nextHistory) ? nextHistory : [];
      state.promptHistory = tagHistory(list);
    };
    const getHistoryScope = (selection2 = null) => {
      const selected = selection2 || currentSelection({ path: "" });
      return {
        path: (selected == null ? void 0 : selected.path) || "",
        mode: (selected == null ? void 0 : selected.isLayout) ? "layout" : (selected == null ? void 0 : selected.previewIsFile) ? "file" : "folder"
      };
    };
    const readHistoryForSelection = (selection2 = null) => {
      const scope = getHistoryScope(selection2);
      const config = getConfig ? getConfig() || {} : {};
      if (config && Object.prototype.hasOwnProperty.call(config, "promptHistory")) {
        if (!shouldUsePersistedPromptHistory(config, scope.mode)) {
          return [];
        }
        return cleanPersistedHistory(config.promptHistory);
      }
      return cleanPersistedHistory(readStoredHistory(scope.path, scope.mode));
    };
    const writeHistoryForSelection = (history, selection2 = null, options = {}) => {
      const scope = getHistoryScope(selection2);
      writeStoredHistory(scope.path, Array.isArray(history) ? history.slice(-12) : [], scope.mode);
      if (options.persistRemote === false) {
        return Promise.resolve(null);
      }
      const persistedHistory = cleanPersistedHistory(history);
      writeStoredHistory(scope.path, persistedHistory, scope.mode);
      return requestEditConfig2("save", {
        path: scope.path,
        promptHistory: persistedHistory
      });
    };
    const renderHistory = (options = {}) => {
      renderPromptHistory(promptMessagesEl, state.promptHistory, stream.state, options);
    };
    const getCurrentTemplateField = () => {
      const selection2 = currentSelection(null);
      const selector = (selection2 == null ? void 0 : selection2.isLayout) ? "#edit-layout-primary-template" : "#edit-content-template";
      return document.querySelector(selector);
    };
    const getCurrentTemplateText = () => {
      var _a;
      const selection2 = currentSelection(null);
      const templateField = getCurrentTemplateField();
      const currentConfig = getConfig ? getConfig() || {} : {};
      const layout = ((_a = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _a.layout) && typeof currentConfig.work.layout === "object" ? currentConfig.work.layout : {};
      const explicitTemplate = templateField && typeof templateField.value === "string" ? templateField.value : "";
      const fallbackTemplate = (selection2 == null ? void 0 : selection2.isLayout) ? typeof layout.template === "string" && layout.template || (typeof layout.phpTemplate === "string" ? layout.phpTemplate : "") : typeof layout.sectionTemplate === "string" && layout.sectionTemplate || (typeof layout.defaultSectionTemplate === "string" ? layout.defaultSectionTemplate : "");
      return explicitTemplate || fallbackTemplate || "";
    };
    const renderTemplatePreview = () => {
      var _a;
      if (!promptTemplateCodeEl) {
        return;
      }
      const selection2 = currentSelection(null);
      const templateField = getCurrentTemplateField();
      const currentConfig = getConfig ? getConfig() || {} : {};
      const layout = ((_a = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _a.layout) && typeof currentConfig.work.layout === "object" ? currentConfig.work.layout : {};
      const explicitTemplate = templateField && typeof templateField.value === "string" ? templateField.value : "";
      const fallbackTemplate = (selection2 == null ? void 0 : selection2.isLayout) ? typeof layout.template === "string" && layout.template || (typeof layout.phpTemplate === "string" ? layout.phpTemplate : "") : typeof layout.sectionTemplate === "string" && layout.sectionTemplate || (typeof layout.defaultSectionTemplate === "string" ? layout.defaultSectionTemplate : "");
      promptTemplateCodeEl.value = explicitTemplate || fallbackTemplate || "";
      if (promptTemplateLabelEl) {
        promptTemplateLabelEl.textContent = (selection2 == null ? void 0 : selection2.isLayout) ? "Current layout wrapper template" : "Current wrapped partial template";
      }
    };
    const renderContext = () => {
      const context = buildPromptContext({ getActiveSelection: getActiveSelection2, getConfig });
      state.activePath = context.isLayout ? context.virtualPath || context.path : context.path;
      state.activePromptMode = currentPromptMode();
      if (promptInputEl && !state.isSending) {
        promptInputEl.placeholder = currentPromptPlaceholder();
      }
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
      const hasImageContext = currentHasImageContext() || !!state.imageAttachment;
      const hasAttachment = !!state.imageAttachment;
      root.classList.toggle("prompt-has-image-context", hasImageContext);
      promptAttachmentEl.hidden = !hasAttachment;
      if (promptAttachEl) {
        promptAttachEl.hidden = !hasImageContext;
      }
      promptInputEl.classList.toggle("prompt-input-has-attachment", hasAttachment);
      if (!hasAttachment) {
        promptAttachmentPreviewEl.removeAttribute("src");
        promptAttachmentNameEl.textContent = "Image attached";
        return;
      }
      promptAttachmentPreviewEl.src = state.imageAttachment.dataUrl;
      promptAttachmentNameEl.textContent = state.imageAttachment.name || "clipboard-image.png";
    };
    const clearAttachment = () => {
      state.imageAttachment = null;
      if (promptImageInputEl) {
        promptImageInputEl.value = "";
      }
      updateAttachmentUi();
    };
    const clearPromptHistory = () => {
      state.promptHistory = [];
      renderHistory();
    };
    const attachImageFile = async (file) => {
      const { readImageFile: readImageFile2 } = await Promise.resolve().then(() => (init_image(), image_exports));
      try {
        state.imageAttachment = await readImageFile2(file);
        updateAttachmentUi();
        setPromptStatus(`Attached image: ${state.imageAttachment.name}`, true);
      } catch (err) {
        setPromptStatus(err.message || "Failed to attach image.");
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
    const reloadViewer = () => {
      const frame = document.getElementById("contentFrame");
      const selection2 = currentSelection({ path: "", isFile: false });
      const selectionPath = selection2 && Object.prototype.hasOwnProperty.call(selection2, "previewPath") ? selection2.previewPath : void 0;
      const activeViewerPath = selectionPath != null ? selectionPath : state.activePath;
      if (frame && activeViewerPath !== null && activeViewerPath !== void 0) {
        window.dispatchEvent(new CustomEvent("poff:content-updated", {
          detail: {
            path: activeViewerPath,
            target: (selection2 == null ? void 0 : selection2.previewIsFile) ? "file" : "folder"
          }
        }));
      }
    };
    const syncHistoryForPath = () => {
      const selection2 = currentSelection({ path: "" });
      const nextPath = (selection2 == null ? void 0 : selection2.path) || "";
      const nextPromptMode = (selection2 == null ? void 0 : selection2.isLayout) ? "layout" : (selection2 == null ? void 0 : selection2.previewIsFile) ? "file" : "folder";
      if (nextPath !== state.activePath || nextPromptMode !== state.activePromptMode) {
        state.activePath = nextPath;
        state.activePromptMode = nextPromptMode;
        setHistory(readHistoryForSelection(selection2));
        renderHistory();
        renderContext();
        renderSummary("Waiting for response...");
        updateAttachmentUi();
      }
    };
    const restoreHistorySnapshot = async (snapshot, { saveConfig, drawerForm: nextDrawerForm, updatePromptEditorFields: updateFields = updatePromptEditorFields, buildPromptLayoutPayload: buildPromptLayoutPayload2, focusPromptTemplateField: focusField = focusPromptTemplateField } = {}) => {
      if (!snapshot || typeof snapshot !== "object") {
        return false;
      }
      const isLayoutTarget = snapshot.targetType === "layout";
      const templateText = typeof snapshot.template === "string" ? snapshot.template : "";
      if (!templateText) {
        return false;
      }
      const restoredWork = snapshot.workSnapshot && typeof snapshot.workSnapshot === "object" ? snapshot.workSnapshot : {};
      updateFields({
        templateText,
        nextTitle: typeof snapshot.title === "string" ? snapshot.title : null,
        nextDescription: typeof snapshot.description === "string" ? snapshot.description : null,
        nextWork: restoredWork,
        isLayoutTarget,
        nextCss: typeof snapshot.css === "string" ? snapshot.css : null,
        nextJs: typeof snapshot.js === "string" ? snapshot.js : null
      });
      renderContext();
      const selection2 = currentSelection({ path: state.activePath, previewPath: state.activePath, previewIsFile: false, isLayout: isLayoutTarget });
      const currentConfig = getConfig ? getConfig() || {} : {};
      const { layoutPayload } = buildPromptLayoutPayload2({
        selection: selection2,
        currentConfig,
        drawerForm: nextDrawerForm,
        templateText,
        nextCss: typeof snapshot.css === "string" ? snapshot.css : null,
        nextJs: typeof snapshot.js === "string" ? snapshot.js : null,
        layoutPreset: isLayoutTarget ? forceLayoutPromptToCustom() : getLayoutPreset()
      });
      const savePayload = {
        path: state.activePath,
        layout: layoutPayload
      };
      if (typeof snapshot.title === "string" && snapshot.title.trim() !== "") {
        savePayload.title = snapshot.title.trim();
      }
      if (typeof snapshot.description === "string" && snapshot.description.trim() !== "") {
        savePayload.description = snapshot.description.trim();
      }
      await saveConfig(savePayload, statusEl);
      renderSummary(`Restored ${isLayoutTarget ? "layout" : "template"} from assistant stage.`);
      reloadViewer();
      focusField(isLayoutTarget);
      setPromptStatus("Restored template from assistant stage.", true);
      return true;
    };
    return {
      stream,
      state,
      setPromptStatus,
      currentSelection,
      currentPromptMode,
      currentPromptPlaceholder,
      currentDefaultSystemPrompt,
      currentSystemPromptSettingKey,
      currentHasImageContext,
      currentSelectionName,
      insertPromptText: insertPromptText2,
      setHistory,
      getHistoryScope,
      readHistoryForSelection,
      writeHistoryForSelection,
      renderHistory,
      getCurrentTemplateField,
      getCurrentTemplateText,
      renderTemplatePreview,
      renderContext,
      renderSummary,
      updateAttachmentUi,
      clearAttachment,
      clearPromptHistory,
      attachImageFile,
      setGeneratingState,
      reloadViewer,
      syncHistoryForPath,
      restoreHistorySnapshot,
      getLayoutPreset,
      forceLayoutPromptToCustom,
      updatePromptEditorFields,
      focusPromptTemplateField,
      syncWorkFieldEditors,
      setActivePath: (path) => {
        state.activePath = path;
      }
    };
  }

  // src/assets/js/edit/prompt/summary.js
  function summarizePromptRequest(payload) {
    return {
      path: typeof (payload == null ? void 0 : payload.path) === "string" ? payload.path : "",
      provider: typeof (payload == null ? void 0 : payload.provider) === "string" ? payload.provider : "local",
      model: typeof (payload == null ? void 0 : payload.model) === "string" ? payload.model : "",
      endpoint: typeof (payload == null ? void 0 : payload.endpoint) === "string" ? payload.endpoint : "",
      promptLength: typeof (payload == null ? void 0 : payload.prompt) === "string" ? payload.prompt.length : 0,
      historyCount: Array.isArray(payload == null ? void 0 : payload.history) ? payload.history.length : 0,
      hasApiKey: typeof (payload == null ? void 0 : payload.apiKey) === "string" ? payload.apiKey.trim() !== "" : false,
      hasImage: !!(payload == null ? void 0 : payload.image),
      systemPromptLength: typeof (payload == null ? void 0 : payload.systemPrompt) === "string" ? payload.systemPrompt.length : 0
    };
  }
  function summarizePromptResponse(response, requestSummary) {
    return {
      path: (requestSummary == null ? void 0 : requestSummary.path) || "",
      provider: (response == null ? void 0 : response.provider) || (requestSummary == null ? void 0 : requestSummary.provider) || "local",
      model: (response == null ? void 0 : response.model) || (requestSummary == null ? void 0 : requestSummary.model) || "",
      allowed: (response == null ? void 0 : response.allowed) === true,
      hasTemplate: typeof (response == null ? void 0 : response.template) === "string" && response.template.trim() !== "",
      templateLength: typeof (response == null ? void 0 : response.template) === "string" ? response.template.trim().length : 0,
      error: typeof (response == null ? void 0 : response.error) === "string" ? response.error : ""
    };
  }
  function summarizePromptError(err, requestSummary) {
    return {
      path: (requestSummary == null ? void 0 : requestSummary.path) || "",
      provider: (requestSummary == null ? void 0 : requestSummary.provider) || "local",
      model: (requestSummary == null ? void 0 : requestSummary.model) || "",
      name: typeof (err == null ? void 0 : err.name) === "string" ? err.name : "Error",
      message: typeof (err == null ? void 0 : err.message) === "string" ? err.message : String(err || "Prompt failed.")
    };
  }

  // src/assets/js/edit/prompt/log.js
  function debugPromptLog(label, payload) {
    try {
      console.info(`[prompt] ${label}`, payload);
    } catch (err) {
    }
  }

  // src/assets/js/edit/prompt/layout-payload.js
  function resolveLayoutName(nextLayoutValue, drawerForm, currentConfig) {
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
    const elements2 = drawerForm ? drawerForm.elements : null;
    return (((_a = elements2 == null ? void 0 : elements2.work_layout) == null ? void 0 : _a.value) || ((_c = (_b = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _b.layout) == null ? void 0 : _c.name) || "poff-layout").trim();
  }
  function buildPromptLayoutPayload({
    selection: selection2,
    currentConfig,
    drawerForm,
    templateText,
    responseSectionTemplate = null,
    responseWorkTemplate = null,
    responseWorksTemplate = null,
    nextCss = null,
    nextJs = null,
    nextLayoutValue = null,
    responseModel = "",
    layoutPreset = getLayoutPresetValue()
  }) {
    var _a, _b;
    const layoutState = getLayoutState(currentConfig || {});
    if (!(selection2 == null ? void 0 : selection2.isLayout)) {
      return {
        layoutPayload: {
          sectionTemplate: templateText
        },
        layoutState,
        resolvedLayoutName: resolveLayoutName(nextLayoutValue, drawerForm, currentConfig)
      };
    }
    const resolvedLayoutName = resolveLayoutName(nextLayoutValue, drawerForm, currentConfig);
    const layoutPayload = {
      name: resolvedLayoutName,
      engine: "lightncandy"
    };
    const resolvedSharedName = typeof nextLayoutValue === "object" && nextLayoutValue && typeof nextLayoutValue.sharedName === "string" ? nextLayoutValue.sharedName.trim() : String(((_b = (_a = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _a.layout) == null ? void 0 : _b.sharedName) || "").trim();
    if (nextLayoutValue && typeof nextLayoutValue === "object") {
      if (typeof nextLayoutValue.engine === "string" && nextLayoutValue.engine.trim()) {
        layoutPayload.engine = nextLayoutValue.engine.trim();
      }
      if (typeof nextLayoutValue.model === "string" && nextLayoutValue.model.trim()) {
        layoutPayload.model = nextLayoutValue.model.trim();
      }
    }
    if (responseModel) {
      layoutPayload.model = responseModel;
    }
    const preset = (layoutPreset || layoutState.preset || "actual").trim();
    layoutPayload.preset = preset;
    if (preset === "shared") {
      layoutPayload.source = "shared";
      layoutPayload.sharedName = resolvedSharedName || resolvedLayoutName;
    }
    const layoutPathName = (selection2.previewPath || "").split("/").pop() || "item";
    const localLayoutDirectory = selection2.layoutIsFile ? `.works/${layoutPathName}.layout` : ".layout";
    const resolvedLayoutDirectory = typeof layoutState.directory === "string" ? layoutState.directory.trim() : "";
    const canEditResolvedFilesystemTarget = layoutState.storage === "filesystem" && resolvedLayoutDirectory !== "";
    const shouldPersistToLocalWrapper = preset === "custom" || !canEditResolvedFilesystemTarget || resolvedLayoutDirectory === localLayoutDirectory;
    layoutPayload.name = preset === "none" ? "none" : preset === "custom" ? "custom-layout" : preset === "shared" ? resolvedSharedName || resolvedLayoutName || "poff-layout" : canEditResolvedFilesystemTarget ? "filesystem-layout" : "poff-layout";
    if (shouldPersistToLocalWrapper) {
      layoutPayload.template = templateText;
      if (nextCss !== null) {
        layoutPayload.css = nextCss;
      }
      if (nextJs !== null) {
        layoutPayload.js = nextJs;
      }
    } else if (canEditResolvedFilesystemTarget) {
      layoutPayload.originalTarget = resolvedLayoutDirectory;
      layoutPayload.originalTemplate = templateText;
      if (nextCss !== null) {
        layoutPayload.originalCss = nextCss;
      }
      if (nextJs !== null) {
        layoutPayload.originalJs = nextJs;
      }
    } else {
      layoutPayload.name = "custom-layout";
      layoutPayload.template = templateText;
      if (nextCss !== null) {
        layoutPayload.css = nextCss;
      }
      if (nextJs !== null) {
        layoutPayload.js = nextJs;
      }
    }
    return { layoutPayload, layoutState, resolvedLayoutName };
  }

  // src/assets/js/edit/prompt/workflows.js
  function buildPromptResetSavePayload(path, layoutPayload) {
    return {
      path,
      layout: layoutPayload,
      promptHistory: []
    };
  }
  function createPromptWindowWorkflows({
    root,
    statusEl,
    drawerForm,
    getConfig,
    saveConfig,
    promptInputEl,
    providerEl,
    modelEl,
    endpointEl,
    apiKeyEl,
    systemPromptEl,
    streamToggleEl,
    currentSelection,
    currentSelectionName,
    setPromptStatus,
    setHistory,
    writeHistoryForSelection,
    readHistoryForSelection,
    renderHistory,
    renderContext,
    renderSummary,
    setGeneratingState,
    clearAttachment,
    attachImageFile,
    getCurrentTemplateText,
    getLayoutPreset,
    forceLayoutPromptToCustom,
    updatePromptEditorFields: updatePromptEditorFields2,
    syncWorkFieldEditors: syncWorkFieldEditors2,
    focusPromptTemplateField: focusPromptTemplateField2,
    reloadViewer,
    state,
    stream
  }) {
    const dropPendingAssistantHistory = (pendingAssistantIndex) => {
      if (pendingAssistantIndex === null || pendingAssistantIndex < 0 || pendingAssistantIndex >= state.promptHistory.length) {
        return state.promptHistory;
      }
      const nextHistory = state.promptHistory.slice();
      nextHistory.splice(pendingAssistantIndex, 1);
      return nextHistory;
    };
    const onClearChat = () => {
      stopStreaming(stream);
      setHistory([]);
      writeHistoryForSelection(state.promptHistory);
      renderHistory();
    };
    const onResetTemplate = async () => {
      var _a, _b, _c, _d;
      if (state.isSending) {
        return;
      }
      const selection2 = currentSelection({ path: state.activePath, isLayout: false, previewPath: state.activePath, layoutIsFile: false });
      const isLayoutTarget = !!(selection2 == null ? void 0 : selection2.isLayout);
      const resetLabel = isLayoutTarget ? "current layout wrapper template" : "current wrapped partial template";
      if (!window.confirm(`Reset the ${resetLabel} to the inherited/default version?`)) {
        return;
      }
      try {
        state.isSending = true;
        setGeneratingState(true, "Resetting template...");
        setPromptStatus("Resetting template...");
        const currentConfig = getConfig ? getConfig() || {} : {};
        const layoutState = getLayoutState(currentConfig || {});
        const layoutPayload = {
          name: ((_b = (_a = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _a.layout) == null ? void 0 : _b.name) || "poff-layout",
          engine: ((_d = (_c = currentConfig == null ? void 0 : currentConfig.work) == null ? void 0 : _c.layout) == null ? void 0 : _d.engine) || "lightncandy"
        };
        if (isLayoutTarget) {
          const preset = (getLayoutPreset() || layoutState.preset || "actual").trim();
          const layoutPathName = (selection2.previewPath || "").split("/").pop() || "item";
          const localLayoutDirectory = selection2.layoutIsFile ? `.works/${layoutPathName}.layout` : ".layout";
          const resolvedLayoutDirectory = typeof layoutState.directory === "string" ? layoutState.directory.trim() : "";
          const canEditResolvedFilesystemTarget = layoutState.storage === "filesystem" && resolvedLayoutDirectory !== "";
          const shouldPersistToLocalWrapper = preset === "custom" || !canEditResolvedFilesystemTarget || resolvedLayoutDirectory === localLayoutDirectory;
          layoutPayload.preset = preset;
          layoutPayload.name = preset === "none" ? "none" : preset === "custom" ? "custom-layout" : canEditResolvedFilesystemTarget ? "filesystem-layout" : "poff-layout";
          if (shouldPersistToLocalWrapper) {
            layoutPayload.template = "";
          } else if (canEditResolvedFilesystemTarget) {
            layoutPayload.originalTarget = resolvedLayoutDirectory;
            layoutPayload.originalTemplate = "";
          } else {
            layoutPayload.template = "";
          }
          updatePromptEditorFields2({
            templateText: "",
            nextTitle: null,
            nextDescription: null,
            nextWork: null,
            isLayoutTarget: true
          });
        } else {
          layoutPayload.sectionTemplate = "";
          updatePromptEditorFields2({
            templateText: "",
            nextTitle: null,
            nextDescription: null,
            nextWork: null,
            isLayoutTarget: false
          });
        }
        setHistory([]);
        writeHistoryForSelection(state.promptHistory, selection2, { persistRemote: false });
        await saveConfig(buildPromptResetSavePayload(state.activePath, layoutPayload), statusEl);
        renderHistory();
        renderContext();
        renderSummary(`Reset ${isLayoutTarget ? "layout wrapper" : "wrapped partial"} to inherited/default template.`);
        reloadViewer();
        setPromptStatus(`${isLayoutTarget ? "Layout wrapper" : "Wrapped partial"} reset to inherited/default template.`, true);
      } catch (err) {
        setPromptStatus((err == null ? void 0 : err.message) || "Template reset failed.");
        renderSummary("Template reset failed.");
      } finally {
        setGeneratingState(false);
        state.isSending = false;
      }
    };
    const onSendPrompt = async () => {
      if (state.isSending || !promptInputEl.value.trim() && !state.imageAttachment) {
        return;
      }
      state.isSending = true;
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
        const errMsg = "Prompt timed out after 5 minutes.";
        setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
        renderHistory({ forceScroll: true });
        setPromptStatus(errMsg);
        state.isSending = false;
      }, 305e3);
      try {
        const userPrompt = promptInputEl.value.trim();
        const providerValue = providerEl ? providerEl.value : "local";
        const apiKeyValue = apiKeyEl ? apiKeyEl.value.trim() : "";
        const modelValue = modelEl ? modelEl.value.trim() : "";
        const selection2 = currentSelection({ path: state.activePath, previewPath: state.activePath, previewIsFile: false, isLayout: false });
        if ((providerValue === "openai" || providerValue === "gemini") && apiKeyValue === "") {
          setGeneratingState(false);
          setPromptStatus(providerValue === "openai" ? "Add an OpenAI API key to send prompts." : "Add a Gemini API key to send prompts.");
          state.isSending = false;
          return;
        }
        setHistory([...state.promptHistory, { role: "user", content: userPrompt }].slice(-12));
        setHistory([...state.promptHistory, { role: "assistant", content: "Generating answer..." }].slice(-12));
        pendingAssistantIndex = state.promptHistory.length - 1;
        writeHistoryForSelection(state.promptHistory, selection2, { persistRemote: false });
        renderHistory({ forceScroll: true });
        renderContext();
        promptInputEl.value = "";
        setPromptStatus("Generating answer...");
        renderSummary("Generating answer...");
        const historyForRequest = serializeHistoryForRequest(state.promptHistory.slice(0, -1), {
          initialTemplateText: getCurrentTemplateText()
        });
        const systemPromptValue = ((systemPromptEl == null ? void 0 : systemPromptEl.value) || "").trim();
        const payload = {
          path: state.activePath,
          provider: providerValue,
          model: modelValue,
          endpoint: endpointEl ? endpointEl.value.trim() : "",
          apiKey: providerValue === "local" ? "" : apiKeyValue,
          prompt: userPrompt,
          history: historyForRequest,
          systemPrompt: systemPromptValue
        };
        const editorDraft = readPromptEditorDraft(selection2);
        if (editorDraft) {
          payload.draft = editorDraft;
        }
        if (selection2 == null ? void 0 : selection2.isLayout) {
          const layoutPreset = getLayoutPreset();
          if (layoutPreset) {
            payload.layoutPreset = layoutPreset;
          }
        }
        if (state.imageAttachment) {
          payload.image = { ...state.imageAttachment };
        }
        requestSummary = summarizePromptRequest(payload);
        debugPromptLog("request", requestSummary);
        const useStreaming = !!(streamToggleEl && streamToggleEl.checked);
        if (useStreaming) {
          beginStreaming({
            stream,
            targetIndex: pendingAssistantIndex,
            history: state.promptHistory,
            renderHistory: () => renderHistory({ forceScroll: true })
          });
        }
        const response = useStreaming ? await requestPromptTemplateStream(payload, {
          onDelta: (chunk) => appendStreamingChunk({
            stream,
            chunk,
            history: state.promptHistory,
            renderHistory: () => renderHistory({ forceScroll: true })
          })
        }) : await requestPromptTemplate(payload);
        settled = true;
        debugPromptLog("response", summarizePromptResponse(response, requestSummary));
        const templateText = response && typeof response.template === "string" ? response.template.trim() : "";
        const nextTitle = typeof response.title === "string" ? response.title.trim() : null;
        const nextDescription = typeof response.description === "string" ? response.description.trim() : null;
        const nextCss = typeof response.css === "string" ? response.css : null;
        const nextJs = typeof response.js === "string" ? response.js : null;
        const isLayoutTarget = !!selection2.isLayout;
        const currentConfig = getConfig ? getConfig() : null;
        const layoutSectionKey = isLayoutTarget ? (selection2 == null ? void 0 : selection2.previewIsFile) || (selection2 == null ? void 0 : selection2.layoutIsFile) ? "work.hbs" : "works.hbs" : "";
        const rawResponseWork = response && response.work && typeof response.work === "object" ? response.work : null;
        const responseSectionTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork[layoutSectionKey] === "string" ? rawResponseWork[layoutSectionKey] : null;
        const responseWorkTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork["work.hbs"] === "string" ? rawResponseWork["work.hbs"] : null;
        const responseWorksTemplate = isLayoutTarget && rawResponseWork && typeof rawResponseWork["works.hbs"] === "string" ? rawResponseWork["works.hbs"] : null;
        const inferredWork = inferWorkChangesFromPrompt(userPrompt, currentConfig);
        const mergedWork = {
          ...inferredWork || {},
          ...rawResponseWork || {}
        };
        const nextWork = filterAllowedWork(mergedWork, currentConfig);
        const nextLayoutValue = nextWork && Object.prototype.hasOwnProperty.call(nextWork, "layout") ? nextWork.layout : null;
        const persistedWork = nextWork && typeof nextWork === "object" ? materializeWorkFields(nextWork) : null;
        if (persistedWork && Object.prototype.hasOwnProperty.call(persistedWork, "layout")) {
          delete persistedWork.layout;
        }
        if (response.error || !templateText) {
          stopStreaming(stream);
          setGeneratingState(false);
          const errMsg = response.error || "Prompt returned no content.";
          setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
          writeHistoryForSelection(state.promptHistory, selection2);
          renderHistory({ forceScroll: true });
          setPromptStatus(errMsg);
          renderSummary(errMsg);
          return;
        }
        const templateSnapshot = buildTemplateHistorySnapshot({
          templateText,
          nextCss,
          nextJs,
          nextTitle,
          nextDescription,
          nextWork: persistedWork || nextWork,
          isLayoutTarget
        });
        if (pendingAssistantIndex !== null && state.promptHistory[pendingAssistantIndex]) {
          state.promptHistory[pendingAssistantIndex].content = templateText;
          state.promptHistory[pendingAssistantIndex].templateSnapshot = templateSnapshot;
          setHistory(state.promptHistory);
        } else {
          setHistory([...state.promptHistory, {
            role: "assistant",
            content: templateText,
            templateSnapshot
          }].slice(-12));
          pendingAssistantIndex = state.promptHistory.length - 1;
        }
        if (useStreaming) {
          finishStreaming({
            stream,
            history: state.promptHistory,
            fullText: templateText,
            renderHistory: () => renderHistory({ forceScroll: true })
          });
        }
        writeHistoryForSelection(state.promptHistory, selection2);
        renderHistory({ forceScroll: true });
        renderContext();
        if (response.systemPrompt && systemPromptEl && !systemPromptEl.value.trim()) {
          systemPromptEl.value = response.systemPrompt;
        }
        updatePromptEditorFields2({
          templateText,
          nextTitle,
          nextDescription,
          nextWork,
          isLayoutTarget,
          nextCss,
          nextJs
        });
        syncWorkFieldEditors2(nextWork);
        focusPromptTemplateField2(isLayoutTarget);
        if (drawerForm) {
          const templateField = drawerForm.querySelector("#edit-content-template");
          if (!isLayoutTarget && templateField) {
            templateField.value = templateText;
          }
          const layoutNameField = drawerForm.querySelector("#edit-work-layout");
          if (layoutNameField && !layoutNameField.value.trim()) {
            layoutNameField.value = "poff-layout";
          }
          if (nextWork && (typeof nextWork.template === "string" || typeof nextWork.type === "string")) {
            const workTypeField = drawerForm.querySelector("#edit-work-type");
            if (workTypeField) {
              workTypeField.value = nextWork.template || nextWork.type;
            }
          }
        }
        const effectiveLayoutPreset = isLayoutTarget ? forceLayoutPromptToCustom() : getLayoutPreset();
        const { layoutPayload } = buildPromptLayoutPayload({
          selection: selection2,
          currentConfig,
          drawerForm,
          templateText,
          responseSectionTemplate,
          responseWorkTemplate,
          responseWorksTemplate,
          nextCss,
          nextJs,
          nextLayoutValue,
          responseModel: response.model || "",
          layoutPreset: effectiveLayoutPreset
        });
        const savePayload = {
          path: state.activePath,
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
        if (Array.isArray(response == null ? void 0 : response.treeVisible)) {
          savePayload.treeVisible = response.treeVisible;
        }
        savePayload.promptHistory = state.promptHistory.slice(-12);
        await saveConfig(savePayload, statusEl);
        renderContext();
        const providerLabel = response.provider || payload.provider;
        const modelLabel = response.model || payload.model || "";
        setPromptStatus(`${isLayoutTarget ? "Layout" : "Template"} updated via ${providerLabel}${modelLabel ? ` \xB7 ${modelLabel}` : ""}`, true);
        const extra = [];
        if (nextTitle !== null) extra.push("title");
        if (nextDescription !== null) extra.push("description");
        if (persistedWork && Object.keys(persistedWork).length) extra.push(`work: ${Object.keys(persistedWork).join(", ")}`);
        if (nextLayoutValue) extra.push("layout");
        if (isLayoutTarget && nextCss !== null) extra.push("css");
        if (isLayoutTarget && nextJs !== null) extra.push("js");
        const summaryText = `Saved ${templateText.length} ${isLayoutTarget ? "layout " : ""}HBS chars via ${providerLabel}${modelLabel ? ` \xB7 ${modelLabel}` : ""}${extra.length ? ` \xB7 updated ${extra.join("; ")}` : ""}`;
        renderSummary(summaryText);
        clearAttachment();
        reloadViewer();
      } catch (err) {
        settled = true;
        stopStreaming(stream);
        setGeneratingState(false);
        debugPromptLog("error", summarizePromptError(err, requestSummary || (state.activePath ? { path: state.activePath } : null)));
        setPromptStatus("Prompt failed.");
        const errMsg = "Prompt failed.";
        setHistory(dropPendingAssistantHistory(pendingAssistantIndex));
        writeHistoryForSelection(state.promptHistory, selection);
        renderHistory({ forceScroll: true });
        renderSummary(errMsg);
      } finally {
        window.clearTimeout(fallbackTimer);
        setGeneratingState(false);
        state.isSending = false;
        promptInputEl.focus();
      }
    };
    return {
      onClearChat,
      onResetTemplate,
      onSendPrompt,
      onAttachImage: attachImageFile,
      onInsertName: async () => {
        const name = currentSelectionName();
        if (!name) {
          setPromptStatus("No file name selected.");
          return;
        }
        await insertPromptText(name);
      },
      onRemoveImage: () => {
        clearAttachment();
        setPromptStatus("Image removed.");
      },
      onTemplateInput: () => {
        if (typeof renderContext === "function") {
          renderContext();
        }
      },
      onLayoutPresetChange: renderContext,
      restoreHistorySnapshot: async (snapshot) => {
        if (!snapshot || typeof snapshot !== "object") {
          return false;
        }
        const isLayoutTarget = snapshot.targetType === "layout";
        const templateText = typeof snapshot.template === "string" ? snapshot.template : "";
        if (!templateText) {
          return false;
        }
        const restoredWork = snapshot.workSnapshot && typeof snapshot.workSnapshot === "object" ? snapshot.workSnapshot : {};
        updatePromptEditorFields2({
          templateText,
          nextTitle: typeof snapshot.title === "string" ? snapshot.title : null,
          nextDescription: typeof snapshot.description === "string" ? snapshot.description : null,
          nextWork: restoredWork,
          isLayoutTarget,
          nextCss: typeof snapshot.css === "string" ? snapshot.css : null,
          nextJs: typeof snapshot.js === "string" ? snapshot.js : null
        });
        renderContext();
        const selection2 = currentSelection({ path: state.activePath, previewPath: state.activePath, previewIsFile: false, isLayout: isLayoutTarget });
        const currentConfig = getConfig ? getConfig() || {} : {};
        const { layoutPayload } = buildPromptLayoutPayload({
          selection: selection2,
          currentConfig,
          drawerForm,
          templateText,
          nextCss: typeof snapshot.css === "string" ? snapshot.css : null,
          nextJs: typeof snapshot.js === "string" ? snapshot.js : null,
          layoutPreset: isLayoutTarget ? forceLayoutPromptToCustom() : getLayoutPreset()
        });
        const savePayload = {
          path: state.activePath,
          layout: layoutPayload
        };
        if (typeof snapshot.title === "string" && snapshot.title.trim() !== "") {
          savePayload.title = snapshot.title.trim();
        }
        if (typeof snapshot.description === "string" && snapshot.description.trim() !== "") {
          savePayload.description = snapshot.description.trim();
        }
        await saveConfig(savePayload, statusEl);
        renderSummary(`Restored ${isLayoutTarget ? "layout" : "template"} from assistant stage.`);
        reloadViewer();
        focusPromptTemplateField2(isLayoutTarget);
        setPromptStatus("Restored template from assistant stage.", true);
        return true;
      }
    };
  }

  // src/assets/js/edit/prompt.js
  var PROMPT_LAYER_STATE_KEY = "poffEditPromptLayerState";
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
    const modelSelectEl = root.querySelector("#prompt-model-local");
    const endpointRow = root.querySelector("#prompt-endpoint-row");
    const endpointEl = root.querySelector("#prompt-endpoint");
    const apiKeyRow = root.querySelector("#prompt-api-key-row");
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
    const promptTemplateResetEl = root.querySelector("#prompt-template-reset");
    const promptTemplateCodeEl = root.querySelector("#promptTemplateCode");
    const promptAttachmentEl = root.querySelector("#promptAttachment");
    const promptWindowEl = root.querySelector("#promptWindow");
    const promptLayerCloseEl = root.querySelector("#promptLayerClose");
    const promptLayerOpenEl = root.querySelector("#promptLayerOpen");
    const promptInputEl = root.querySelector("#prompt-input");
    const promptSendEl = root.querySelector("#prompt-send");
    const promptAttachEl = root.querySelector("#prompt-attach");
    const promptInsertNameEl = root.querySelector("#prompt-insert-name");
    const promptClearEl = root.querySelector("#prompt-clear");
    const promptImageInputEl = root.querySelector("#prompt-image-input");
    const promptAttachmentPreviewEl = root.querySelector("#promptAttachmentPreview");
    const promptAttachmentNameEl = root.querySelector("#promptAttachmentName");
    const promptAttachmentRemoveEl = root.querySelector("#prompt-attachment-remove");
    const runtime = createPromptRuntime({
      root,
      statusEl,
      drawerForm,
      getActiveSelection: getActiveSelection2,
      getConfig,
      requestEditConfig,
      promptInputEl,
      promptMessagesEl,
      promptContextEl,
      promptSummaryEl,
      promptGenerationEl,
      promptGenerationLabelEl,
      promptTemplateLabelEl,
      promptTemplateCodeEl,
      promptAttachmentEl,
      promptAttachEl,
      promptAttachmentPreviewEl,
      promptAttachmentNameEl,
      promptImageInputEl,
      promptSendEl,
      promptClearEl,
      promptAttachmentRemoveEl
    });
    const {
      stream,
      state,
      setPromptStatus,
      currentSelection,
      currentHasImageContext,
      currentSelectionName,
      insertPromptText: insertPromptText2,
      setHistory,
      readHistoryForSelection,
      writeHistoryForSelection,
      renderHistory,
      getCurrentTemplateText,
      renderTemplatePreview,
      renderContext,
      renderSummary,
      updateAttachmentUi,
      clearAttachment,
      clearPromptHistory,
      attachImageFile,
      setGeneratingState,
      reloadViewer,
      syncHistoryForPath,
      currentDefaultSystemPrompt,
      currentPromptMode,
      currentSystemPromptSettingKey,
      getLayoutPreset,
      forceLayoutPromptToCustom,
      updatePromptEditorFields: updatePromptEditorFields2,
      focusPromptTemplateField: focusPromptTemplateField2,
      syncWorkFieldEditors: syncWorkFieldEditors2
    } = runtime;
    const workflows = createPromptWindowWorkflows({
      root,
      statusEl,
      drawerForm,
      getConfig,
      saveConfig,
      promptInputEl,
      providerEl,
      modelEl,
      endpointEl,
      apiKeyEl,
      systemPromptEl,
      streamToggleEl,
      currentSelection,
      currentHasImageContext,
      currentSelectionName,
      currentPromptPlaceholder: runtime.currentPromptPlaceholder,
      currentDefaultSystemPrompt: runtime.currentDefaultSystemPrompt,
      setPromptStatus,
      setHistory,
      writeHistoryForSelection,
      readHistoryForSelection,
      renderHistory,
      renderContext,
      renderSummary,
      setGeneratingState,
      clearAttachment,
      attachImageFile,
      currentPromptMode: runtime.currentPromptMode,
      getCurrentTemplateText,
      getLayoutPreset,
      forceLayoutPromptToCustom,
      updatePromptEditorFields: updatePromptEditorFields2,
      syncWorkFieldEditors: syncWorkFieldEditors2,
      focusPromptTemplateField: focusPromptTemplateField2,
      reloadViewer,
      state,
      stream
    });
    bindPromptActions({
      promptClearEl,
      promptTemplateResetEl,
      promptSendEl,
      promptInputEl,
      promptAttachEl,
      promptInsertNameEl,
      promptImageInputEl,
      promptAttachmentRemoveEl,
      layoutPresetEl: document.getElementById("edit-layout-preset"),
      onClearChat: () => {
        syncHistoryForPath();
        workflows.onClearChat();
        clearAttachment();
        setPromptStatus("Chat cleared.");
      },
      onResetTemplate: workflows.onResetTemplate,
      onSendPrompt: workflows.onSendPrompt,
      onAttachImage: workflows.onAttachImage,
      onInsertName: workflows.onInsertName,
      onRemoveImage: workflows.onRemoveImage,
      onTemplateInput: renderTemplatePreview,
      onLayoutPresetChange: renderContext
    });
    const layerController = createPromptLayerController({
      root,
      windowEl: promptWindowEl,
      closeEl: promptLayerCloseEl,
      openEl: promptLayerOpenEl,
      storageKey: PROMPT_LAYER_STATE_KEY,
      storage: localStorage
    });
    layerController.applyState(layerController.readState(), { skipPersist: true });
    const settingsController = bindPromptSettings({
      providerEl,
      modelEl,
      modelSelectEl,
      endpointRow,
      endpointEl,
      apiKeyRow,
      apiKeyEl,
      systemPromptEl,
      systemResetEl,
      settingsResetEl,
      streamToggleEl,
      defaultPromptSettings,
      currentDefaultSystemPrompt,
      currentPromptMode,
      currentSystemPromptSettingKey,
      getDefaultModelForProvider,
      requestPromptModels,
      loadPromptSettings,
      savePromptSettings,
      onRenderContext: renderContext
    });
    const { readSettings, updateProviderUi, syncModeAwareSystemPrompt } = settingsController;
    updateProviderUi({ resetModel: false });
    syncModeAwareSystemPrompt();
    setHistory(readHistoryForSelection(currentSelection(null)));
    renderHistory();
    renderContext();
    renderSummary("Waiting for response...");
    updateAttachmentUi();
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
    if (promptMessagesEl) {
      promptMessagesEl.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }
        const button = target.closest("[data-history-reset-index]");
        if (!(button instanceof HTMLButtonElement)) {
          return;
        }
        const index = Number.parseInt(button.dataset.historyResetIndex || "", 10);
        if (!Number.isInteger(index)) {
          return;
        }
        const historyEntry = state.promptHistory.find((item) => item && item._index === index);
        if (!historyEntry || !historyEntry.templateSnapshot) {
          return;
        }
        event.preventDefault();
        event.stopPropagation();
        void workflows.restoreHistorySnapshot(historyEntry.templateSnapshot);
      });
    }
  }

  // src/assets/js/edit/drawer/render.js
  function groupWorktypeChoices(choices = []) {
    return Array.isArray(choices) ? choices.reduce((groups, choice) => {
      const group = String((choice == null ? void 0 : choice.kind) || "other").trim() || "other";
      if (!groups[group]) {
        groups[group] = [];
      }
      groups[group].push(choice);
      return groups;
    }, {}) : {};
  }
  function renderGroupedSelectOptions(choices = [], selectedValue = "", includeInherit = false) {
    const groups = groupWorktypeChoices(choices);
    const groupEntries = Object.entries(groups);
    if (!groupEntries.length) {
      return includeInherit ? `<option value="" ${selectedValue === "" ? "selected" : ""}>Inherit default</option>` : "";
    }
    const inheritOption = includeInherit ? `<option value="" ${selectedValue === "" ? "selected" : ""}>Inherit default</option>` : "";
    return `
        ${inheritOption}
        ${groupEntries.map(([group, groupChoices]) => `
            <optgroup label="${escapeHtml(group)}">
                ${groupChoices.map((choice) => `
                    <option value="${escapeHtml(choice.value || "")}" data-kind="${escapeHtml(choice.kind || group)}" ${String(choice.value || "") === selectedValue ? "selected" : ""}>
                        ${escapeHtml(choice.label || choice.value || group)}
                    </option>
                `).join("")}
            </optgroup>
        `).join("")}
    `;
  }
  function renderWorktypeSelect(config = {}) {
    var _a, _b;
    const catalog = (config == null ? void 0 : config.workTemplateCatalog) && typeof config.workTemplateCatalog === "object" ? config.workTemplateCatalog : null;
    const choices = Array.isArray(catalog == null ? void 0 : catalog.choices) ? catalog.choices : [];
    const selectedValue = String(((_a = config == null ? void 0 : config.work) == null ? void 0 : _a.template) || (catalog == null ? void 0 : catalog.selected) || ((_b = config == null ? void 0 : config.work) == null ? void 0 : _b.type) || "").trim();
    if (!choices.length) {
      return `<input class="form-input" id="edit-work-type" type="text" name="work_template" value="${escapeHtml(selectedValue)}">`;
    }
    return `
        <select class="form-select" id="edit-work-type" name="work_template">
            ${renderGroupedSelectOptions(choices, selectedValue, false)}
        </select>
        <div class="small-note">
            ${(catalog == null ? void 0 : catalog.detectedMime) ? `Detected ${escapeHtml(catalog.detectedMime)}${catalog.detectedExtension ? ` \xB7 .${escapeHtml(catalog.detectedExtension)}` : ""} \xB7 showing ${escapeHtml(catalog.detectedKind || "current")} templates` : "Template is picked from the available registry."}
        </div>
    `;
  }
  function renderTemplateMapSelect(row = {}) {
    const catalog = (row == null ? void 0 : row.catalog) && typeof row.catalog === "object" ? row.catalog : null;
    const choices = Array.isArray(catalog == null ? void 0 : catalog.choices) ? catalog.choices : [];
    const selectedValue = String((row == null ? void 0 : row.selected) || "").trim();
    const mime = String((row == null ? void 0 : row.mime) || "").trim();
    const safeMimeId = mime.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "mime";
    return `
        <label class="edit-label" for="template-map-${escapeHtml(safeMimeId)}">
            ${escapeHtml(mime || "mime")}
        </label>
        <select
            class="form-select edit-template-map-select"
            id="template-map-${escapeHtml(safeMimeId)}"
            name="work_template_map[${escapeHtml(mime)}]"
            data-template-map-mime="${escapeHtml(mime)}"
            data-template-map-selected="${escapeHtml(selectedValue)}"
        >
            ${renderGroupedSelectOptions(choices, selectedValue, true)}
        </select>
        <div class="small-note">
            ${escapeHtml((row == null ? void 0 : row.kind) || "other")} \xB7 ${escapeHtml((row == null ? void 0 : row.count) ? `${row.count} item${row.count === 1 ? "" : "s"}` : "no items")}
            ${(row == null ? void 0 : row.sampleName) ? `\xB7 ${escapeHtml(row.sampleName)}` : ""}
        </div>
    `;
  }
  function renderDrawerTreeHtml(config, status) {
    if ((status == null ? void 0 : status.target) === "file") {
      return "";
    }
    const treeItems = Array.isArray(config == null ? void 0 : config.tree) ? config.tree : [];
    return treeItems.length ? treeItems.map((item) => {
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
  function renderDrawerTreeBulkToggle(treeItems = [], status) {
    if ((status == null ? void 0 : status.target) === "file" || !Array.isArray(treeItems) || treeItems.length === 0) {
      return "";
    }
    const visibleCount = treeItems.reduce((count, item) => count + ((item == null ? void 0 : item.visible) !== false ? 1 : 0), 0);
    return `
        <div class="edit-tree-bulk">
            <label class="edit-tree-item edit-tree-item-bulk">
                <input type="checkbox" id="editTreeVisibleAll" data-tree-visible-all ${visibleCount > 0 ? "checked" : ""}>
                <span>Select all visible items <span class="opacity-60">(${visibleCount}/${treeItems.length})</span></span>
            </label>
        </div>
    `;
  }
  function renderEditDrawerMarkup({ config, status, treeHtml, treeItems }) {
    var _a;
    return `
        <div class="drawer-header">
            <h4 class="drawer-title">More settings</h4>
            <button type="button" class="drawer-close" id="editDrawerClose">&times;</button>
        </div>
        <div class="edit-status" id="editDrawerStatus"></div>
        <form id="editDrawerForm" class="edit-form">
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-link">Link</label>
                    <input class="form-input" id="edit-link" type="text" name="link" value="${escapeHtml((config == null ? void 0 : config.link) || "")}">
                </div>
                <div>
                    <label class="edit-label" for="edit-url">URL</label>
                    <input class="form-input" id="edit-url" type="text" name="url" value="${escapeHtml((config == null ? void 0 : config.url) || "")}">
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work Template</label>
                    ${renderWorktypeSelect(config)}
                </div>
                <div class="small-note">Use <strong>Change layout</strong> for wrapper editing. This selector chooses the active work template for the current item.</div>
            </div>
            ${Array.isArray((_a = config == null ? void 0 : config.workTemplateMapCatalog) == null ? void 0 : _a.rows) && config.workTemplateMapCatalog.rows.length ? `
            <div class="edit-fieldset">
                <div class="edit-fieldset-title">Template defaults by MIME</div>
                <div class="small-note">Set the inherited default template for each MIME family in this folder or layout. Leave the entry on <em>Inherit default</em> to use the parent value.</div>
                <div class="edit-template-map-list">
                    ${config.workTemplateMapCatalog.rows.map((row) => `
                        <div class="edit-template-map-row">
                            ${renderTemplateMapSelect(row)}
                        </div>
                    `).join("")}
                </div>
            </div>
            ` : ""}
            ${(status == null ? void 0 : status.target) !== "file" ? `
            <div>
                <label class="edit-label">Visible items</label>
                ${renderDrawerTreeBulkToggle(treeItems, status)}
                <div class="edit-tree">${treeHtml}</div>
            </div>
            ` : ""}
            <div class="edit-actions">
                <button class="btn" type="submit">Save advanced</button>
            </div>
        </form>
    `;
  }

  // src/assets/js/edit/drawer/bind.js
  function bindEditDrawerInteractions({ editDrawer, status, onClose, onSubmit }) {
    const drawerClose = editDrawer.querySelector("#editDrawerClose");
    if (drawerClose && typeof onClose === "function") {
      drawerClose.addEventListener("click", () => onClose());
    }
    const drawerStatus = editDrawer.querySelector("#editDrawerStatus");
    const drawerForm = editDrawer.querySelector("#editDrawerForm");
    const treeBulkToggle = editDrawer.querySelector("#editTreeVisibleAll");
    const treeVisibleInputs = () => Array.from(editDrawer.querySelectorAll('input[name="tree_visible"]'));
    const syncTreeBulkToggle = () => {
      if (!treeBulkToggle) {
        return;
      }
      const inputs = treeVisibleInputs();
      const checkedCount = inputs.filter((input) => input.checked).length;
      treeBulkToggle.checked = inputs.length > 0 && checkedCount === inputs.length;
      treeBulkToggle.indeterminate = checkedCount > 0 && checkedCount < inputs.length;
    };
    if (treeBulkToggle) {
      treeBulkToggle.addEventListener("change", () => {
        const inputs = treeVisibleInputs();
        inputs.forEach((input) => {
          input.checked = treeBulkToggle.checked;
        });
        syncTreeBulkToggle();
      });
      treeVisibleInputs().forEach((input) => {
        input.addEventListener("change", syncTreeBulkToggle);
      });
      syncTreeBulkToggle();
    }
    if (drawerForm && drawerStatus && typeof onSubmit === "function") {
      drawerForm.addEventListener("submit", (event) => {
        event.preventDefault();
        const treeVisible = (status == null ? void 0 : status.target) !== "file" ? Array.from(editDrawer.querySelectorAll('input[name="tree_visible"]:checked')).map((input) => input.value) : [];
        onSubmit({
          elements: drawerForm.elements,
          drawerForm,
          statusEl: drawerStatus,
          treeVisible
        });
      });
    }
    return { drawerForm, drawerStatus };
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
    if (!config || (status == null ? void 0 : status.error) || !(status == null ? void 0 : status.allowed)) {
      editDrawer.innerHTML = "";
      return { drawerForm: null, drawerStatus: null };
    }
    const treeHtml = renderDrawerTreeHtml(config, status);
    editDrawer.innerHTML = renderEditDrawerMarkup({ config, status, treeHtml, treeItems: (config == null ? void 0 : config.tree) || [] });
    return bindEditDrawerInteractions({
      editDrawer,
      status,
      onClose,
      onSubmit
    });
  }

  // src/assets/js/edit/prompt-window.js
  function resolvePromptWindowMode(mode = "") {
    if (mode === "layout") {
      return "layout";
    }
    if (mode === "folder") {
      return "folder";
    }
    return "file";
  }
  function buildPromptWindowModeConfig(mode, settings, sectionTarget) {
    const isLayout = mode === "layout";
    const isFolder = mode === "folder";
    const currentSystemPrompt = isLayout ? settings.systemPromptLayout || settings.systemPrompt || defaultLayoutSystemPrompt : isFolder ? settings.systemPromptFolder || settings.systemPrompt || defaultFolderSystemPrompt : settings.systemPromptFile || settings.systemPrompt || defaultFileSystemPrompt;
    const promptTargetCopy = isLayout ? "Prompt edits the outer layout wrapper target for this virtual .layout page." : isFolder ? "Prompt edits the wrapped works.hbs partial for the current folder." : "Prompt edits the wrapped work.hbs partial for the current file.";
    const footerCopy = isLayout ? `Template responses are saved to the current active layout wrapper target shown in Prompt context. The wrapped inner partial stays separate at <code>${escapeHtml(sectionTarget || "work.hbs")}</code>.` : isFolder ? "Template responses are saved to the wrapped partial: <code>works.hbs</code> for folders." : "Template responses are saved to the wrapped partial: <code>work.hbs</code> for files.";
    const insertNameLabel = isLayout ? "Insert layout name" : isFolder ? "Insert item name" : "Insert file name";
    const contextCopy = isLayout ? `<div>Prompt edits the outer layout wrapper. <code>root.*</code> is the shell-level layout data and <code>work.*</code> is the inner item data. Use <code>root.title</code> for the wrapper title and <code>work.title</code> for the item title.</div><div><code>current.templateTarget</code> is the active wrapper target. <code>current.layoutTemplateTarget</code> is the local custom wrapper path if you switch to <code>Custom</code>. <code>current.sectionTemplateTarget</code> is the advanced inner partial.</div><div>For wrapper-owned images/assets, do not use <code>{{path}}</code>. Use <code>{{layout.baseHref}}</code> in the HBS and use <code>current.layoutBaseHref</code> plus <code>current.inheritedLayoutDirectory</code> in the prompt context to understand whether the wrapper came from a parent folder.</div>` : isFolder ? "<div>Prompt edits the wrapped <code>{{> works}}</code> partial and can use folder tree data, helper lists, and item refs.</div>" : "<div>Prompt edits the wrapped <code>{{> work}}</code> partial for one file view.</div>";
    const editableCopy = isLayout ? '<span class="prompt-dot"></span> Editable via prompt: <strong>layout.template</strong>, optional <strong>work.&lt;name&gt;</strong>' : '<span class="prompt-dot"></span> Editable via prompt: <strong>title</strong>, <strong>description</strong>, <strong>work.&lt;name&gt;</strong>';
    const placeholderCopy = isLayout ? `<div>{{pageLink}}, {{pageUrl}}, {{workUrl}}, {{viewUrl}}, {{srcUrl}}, {{assetUrl}}, {{path}}, {{name}}, {{title}}, {{root.title}}, {{root.folderName}}, {{work.title}}, {{work.name}}, {{linkUrl}}, {{slug}}</div>
                        <div><code>{{pageLink}}</code> is for navigation. <code>{{srcUrl}}</code> is for direct sources like <code>src=</code>, <code>poster</code>, downloads, and CSS <code>url(...)</code>.</div>
                        <div>{{> poff-layout}}, {{> filesystem-layout}}, {{> works}}, {{> work}}, {{work.key}}, {{layout.baseHref}}, {{layout.sectionBaseHref}}</div>
                        <div>Example context JSON: <code>{"root":{"title":"dominikeggermann.com"},"work":{"title":"tests"}}</code></div>
                        <div>Theme shell: <code>.poff-default-layout</code> with <code>--poff-shell-*</code> CSS vars</div>` : isFolder ? `<div>{{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, {{pageLink}}, {{srcUrl}}, {{assetUrl}}</div>
                        <div>{{> works}}, {{work.key}}, tree/items, workTree, allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, allOther</div>
                        <div><code>work.templateMap</code> is the inherited MIME => template defaults. <code>work.template</code> is the exact override for the current item.</div>
                        <div>Parent/sibling prompt refs: current.parentWork, siblingWorks, siblingImages, siblingVideos, siblingLinks. Sibling refs are same-folder only.</div>` : `<div>{{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}</div>
                        <div>{{> work}}, {{work.key}}, layout.*, current.parentWork, siblingWorks, siblingImages, siblingVideos, siblingLinks</div>
                        <div><code>work.templateMap</code> is the inherited MIME => template defaults. <code>work.template</code> is the exact override for the current item.</div>`;
    const inputPlaceholder = isLayout ? "Describe the layout you want..." : isFolder ? "Describe the folder component you want..." : "Describe the file component you want...";
    return {
      systemPrompt: currentSystemPrompt,
      promptTargetCopy,
      footerCopy,
      insertNameLabel,
      contextCopy,
      editableCopy,
      placeholderCopy,
      inputPlaceholder
    };
  }
  function renderPromptWindow(settings = {}, options = {}) {
    const mode = resolvePromptWindowMode(options.mode);
    const {
      systemPrompt,
      promptTargetCopy,
      footerCopy,
      insertNameLabel,
      contextCopy,
      editableCopy,
      placeholderCopy,
      inputPlaceholder
    } = buildPromptWindowModeConfig(mode, settings, options.sectionTarget || "work.hbs");
    const provider = ["openai", "gemini"].includes(settings.provider) ? settings.provider : "local";
    return `
        <div class="prompt-layer" id="promptLayer">
            <button class="prompt-layer-toggle prompt-layer-toggle-close" type="button" id="promptLayerClose" aria-label="Hide prompt window" title="Hide prompt window">&times;</button>
            <button class="prompt-layer-toggle prompt-layer-toggle-open" type="button" id="promptLayerOpen" aria-label="Show prompt window" title="Show prompt window" hidden>poff</button>
            <div class="prompt-window prompt-inline" id="promptWindow">
                <div class="prompt-header">
                    <div>
                        <h4 class="edit-panel-title">Prompt edit window</h4>
                        <div class="small-note">Chat + completion helper</div>
                    </div>
                </div>
                <details class="prompt-system">
                    <summary class="prompt-system-summary">Connection</summary>
                    <div class="edit-grid prompt-grid">
                        <div>
                            <label class="edit-label" for="prompt-provider">Provider</label>
                            <select class="form-input" id="prompt-provider">
                                <option value="local" ${provider === "local" ? "selected" : ""}>LM Studio</option>
                                <option value="openai" ${provider === "openai" ? "selected" : ""}>OpenAI</option>
                                <option value="gemini" ${provider === "gemini" ? "selected" : ""}>Gemini</option>
                            </select>
                        </div>
                        <div>
                            <label class="edit-label" for="prompt-model">Model</label>
                            <select class="form-input" id="prompt-model-local" hidden>
                                <option value="">Loading local models...</option>
                            </select>
                            <input class="form-input" id="prompt-model" type="text" value="${escapeHtml(settings.model || "")}" placeholder="optional">
                        </div>
                        <div id="prompt-api-key-row">
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
                <details class="prompt-system">
                    <summary class="prompt-system-summary">System prompt (description &rarr; HBS component)</summary>
                    <textarea class="form-textarea prompt-textarea" id="prompt-system" placeholder="Set the instruction your model should follow.">${escapeHtml(systemPrompt)}</textarea>
                    <div class="prompt-system-footer">
                        <span class="small-note">Used for chat + completions. Not saved across reloads.</span>
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
                <details class="prompt-section prompt-section-context">
                    <summary>Prompt context</summary>
                    <div class="prompt-context" id="promptContext">
                        <div class="prompt-context-title">Placeholders</div>
                        <div class="prompt-context-body">
                        ${placeholderCopy}
                        ${contextCopy}
                    </div>
                    </div>
                </details>
                <details class="prompt-template-viewer" id="promptTemplateViewer">
                    <summary class="prompt-template-viewer-summary">Current template code</summary>
                    <div class="prompt-template-viewer-body">
                        <div class="prompt-template-viewer-head">
                            <div class="small-note" id="promptTemplateLabel">Current target template</div>
                            <button class="btn btn-secondary" type="button" id="prompt-template-reset">Reset to default template</button>
                        </div>
                        <textarea class="form-textarea prompt-template-code" id="promptTemplateCode" readonly spellcheck="false" placeholder="No template loaded yet."></textarea>
                    </div>
                </details>
                <details class="prompt-section prompt-section-messages">
                    <summary>Messages</summary>
                    <div class="prompt-messages" id="promptMessages"></div>
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
                <textarea class="prompt-input" id="prompt-input" placeholder="${escapeHtml(inputPlaceholder)}"></textarea>
                <div class="prompt-actions">
                    <div class="prompt-actions-left">
                        <button class="btn" type="button" id="prompt-send">Send</button>
                        <button class="btn btn-secondary" type="button" id="prompt-attach">Attach image</button>
                        <button class="btn btn-secondary" type="button" id="prompt-insert-name">${escapeHtml(insertNameLabel)}</button>
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
        </div>
    `;
  }

  // src/assets/js/edit/panel/categories.js
  var DEFAULT_CATEGORY_OPTIONS = ["image", "media", "visual", "video", "motion", "audio", "sound", "pdf", "document", "text", "link", "reference", "folder", "collection", "other"];
  var CATEGORY_FIELD_ID = "edit-work-categories";
  var CATEGORY_SELECT_ID = "edit-work-category-select";
  var CATEGORY_CUSTOM_ID = "edit-work-category-custom";
  var CATEGORY_PILLS_ID = "editWorkCategoryPills";
  var CATEGORY_ADD_ID = "editWorkCategoryAdd";
  var CATEGORY_CUSTOM_ADD_ID = "editWorkCategoryCustomAdd";
  var CATEGORY_MAX_LENGTH = 24;
  var CATEGORY_MAX_COUNT = 12;
  function normalizeCategoryValue(value) {
    return String(value != null ? value : "").trim().toLowerCase().slice(0, CATEGORY_MAX_LENGTH);
  }
  function normalizeWorkCategories2(value) {
    let rawValues = [];
    if (Array.isArray(value)) {
      rawValues = value;
    } else if (typeof value === "string") {
      const trimmed = value.trim();
      if (trimmed.startsWith("[")) {
        try {
          const parsed = JSON.parse(trimmed);
          if (Array.isArray(parsed)) {
            rawValues = parsed;
          } else {
            rawValues = trimmed.split(/\r?\n|,/);
          }
        } catch (e) {
          rawValues = trimmed.split(/\r?\n|,/);
        }
      } else {
        rawValues = trimmed.split(/\r?\n|,/);
      }
    }
    const categories = [];
    rawValues.forEach((candidate) => {
      const normalized = normalizeCategoryValue(candidate);
      if (!normalized || categories.includes(normalized) || categories.length >= CATEGORY_MAX_COUNT) {
        return;
      }
      categories.push(normalized);
    });
    return categories;
  }
  function getWorkCategoryOptions(catalog = null) {
    const options = Array.isArray(catalog == null ? void 0 : catalog.categories) && catalog.categories.length ? catalog.categories : DEFAULT_CATEGORY_OPTIONS;
    return Array.from(new Set(options.map((category) => normalizeCategoryValue(category)).filter(Boolean)));
  }
  function renderCategoryPill(category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
      return "";
    }
    return `
        <button type="button" class="edit-work-category-pill" data-work-category-remove="${escapeHtml(normalized)}" aria-label="Remove category ${escapeHtml(normalized)}">
            <span>${escapeHtml(normalized)}</span>
            <span aria-hidden="true">\u2212</span>
        </button>
    `;
  }
  function renderCategoryOptions(options, selectedValue = "") {
    const normalizedSelected = normalizeCategoryValue(selectedValue);
    return options.map((option) => {
      const normalized = normalizeCategoryValue(option);
      return `<option value="${escapeHtml(normalized)}"${normalized === normalizedSelected ? " selected" : ""}>${escapeHtml(normalized)}</option>`;
    }).join("");
  }
  function renderWorkCategorySection(config = {}) {
    var _a, _b;
    const work = (config == null ? void 0 : config.work) && typeof config.work === "object" ? config.work : {};
    const catalog = (config == null ? void 0 : config.workTemplateCatalog) && typeof config.workTemplateCatalog === "object" ? config.workTemplateCatalog : null;
    const categories = normalizeWorkCategories2((_b = (_a = work.categories) != null ? _a : work.category) != null ? _b : []);
    const options = Array.from(/* @__PURE__ */ new Set([
      ...getWorkCategoryOptions(catalog),
      ...categories
    ]));
    const selectedCategory = normalizeCategoryValue(options.find((option) => !categories.includes(option)) || options[0] || "");
    return `
        <div class="edit-work-category-section" data-work-category-section>
            <div class="edit-work-category-header">
                <div>
                    <div class="edit-work-fields-title">Categories</div>
                    <div class="small-note">Pick shared work categories for filtering and prompt context.</div>
                </div>
            </div>
            <div class="edit-work-category-controls">
                <div class="edit-work-category-picker">
                    <label class="edit-label" for="${CATEGORY_SELECT_ID}">Add from works</label>
                    <div class="edit-work-category-picker-row">
                        <select class="form-select" id="${CATEGORY_SELECT_ID}" name="work_category_select">
                            ${renderCategoryOptions(options, selectedCategory)}
                        </select>
                        <button class="btn btn-secondary" type="button" id="${CATEGORY_ADD_ID}" aria-label="Add selected category">+</button>
                    </div>
                    <div class="small-note">The list comes from the shared worktype categories.</div>
                </div>
                <div class="edit-work-category-picker">
                    <label class="edit-label" for="${CATEGORY_CUSTOM_ID}">Custom category</label>
                    <div class="edit-work-category-picker-row">
                        <input class="form-input" id="${CATEGORY_CUSTOM_ID}" type="text" maxlength="${CATEGORY_MAX_LENGTH}" placeholder="type a custom category">
                        <button class="btn btn-secondary" type="button" id="${CATEGORY_CUSTOM_ADD_ID}" aria-label="Add custom category">+</button>
                    </div>
                    <div class="small-note">Limit ${CATEGORY_MAX_LENGTH} chars, max ${CATEGORY_MAX_COUNT} categories.</div>
                </div>
                <div class="edit-work-category-current">
                    <div class="edit-label">Current categories</div>
                    <div class="edit-work-category-pills" id="${CATEGORY_PILLS_ID}">
                        ${categories.length ? categories.map(renderCategoryPill).join("") : '<div class="small-note">No categories selected yet.</div>'}
                    </div>
                </div>
            </div>
            <input type="hidden" id="${CATEGORY_FIELD_ID}" data-work-config-field data-work-config-key="categories" data-work-config-kind="json" value="${escapeHtml(JSON.stringify(categories))}">
        </div>
    `;
  }
  function renderCategoryPills(editPanel, categories) {
    const pillsEl = editPanel.querySelector(`#${CATEGORY_PILLS_ID}`);
    if (!pillsEl) {
      return;
    }
    const normalizedCategories = normalizeWorkCategories2(categories);
    pillsEl.innerHTML = normalizedCategories.length ? normalizedCategories.map(renderCategoryPill).join("") : '<div class="small-note">No categories selected yet.</div>';
  }
  function writeCategoriesField(editPanel, categories) {
    const field = editPanel.querySelector(`#${CATEGORY_FIELD_ID}`);
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
      return;
    }
    const normalizedCategories = normalizeWorkCategories2(categories);
    field.value = JSON.stringify(normalizedCategories);
    field.dispatchEvent(new Event("input", { bubbles: true }));
    field.dispatchEvent(new Event("change", { bubbles: true }));
  }
  function readCategoriesField(editPanel) {
    const field = editPanel.querySelector(`#${CATEGORY_FIELD_ID}`);
    if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
      return [];
    }
    return normalizeWorkCategories2(field.value);
  }
  function addCategory(editPanel, category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
      return;
    }
    const categories = readCategoriesField(editPanel);
    if (!categories.includes(normalized)) {
      categories.push(normalized);
    }
    renderCategoryPills(editPanel, categories);
    writeCategoriesField(editPanel, categories);
  }
  function removeCategory(editPanel, category) {
    const normalized = normalizeCategoryValue(category);
    if (!normalized) {
      return;
    }
    const categories = readCategoriesField(editPanel).filter((item) => item !== normalized);
    renderCategoryPills(editPanel, categories);
    writeCategoriesField(editPanel, categories);
  }
  function bindWorkCategoryControls({ editPanel, onMediaInput }) {
    if (!editPanel) {
      return () => {
      };
    }
    const selectEl = editPanel.querySelector(`#${CATEGORY_SELECT_ID}`);
    const customEl = editPanel.querySelector(`#${CATEGORY_CUSTOM_ID}`);
    const addEl = editPanel.querySelector(`#${CATEGORY_ADD_ID}`);
    const customAddEl = editPanel.querySelector(`#${CATEGORY_CUSTOM_ADD_ID}`);
    const sectionEl = editPanel.querySelector("[data-work-category-section]");
    if (!sectionEl) {
      return () => {
      };
    }
    const onAddClick = () => {
      if (!(selectEl instanceof HTMLSelectElement)) {
        return;
      }
      addCategory(editPanel, selectEl.value);
    };
    const onCustomAddClick = () => {
      if (!(customEl instanceof HTMLInputElement)) {
        return;
      }
      addCategory(editPanel, customEl.value);
      customEl.value = "";
    };
    const onSectionClick = (event) => {
      const target = event.target;
      const button = target && typeof target.closest === "function" ? target.closest("[data-work-category-remove]") : null;
      if (!button) {
        return;
      }
      removeCategory(editPanel, button.dataset.workCategoryRemove || button.getAttribute("data-work-category-remove") || "");
    };
    if (addEl) {
      addEl.addEventListener("click", onAddClick);
    }
    if (customAddEl) {
      customAddEl.addEventListener("click", onCustomAddClick);
    }
    sectionEl.addEventListener("click", onSectionClick);
    if (customEl) {
      customEl.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          onCustomAddClick();
        }
      });
    }
    renderCategoryPills(editPanel, readCategoriesField(editPanel));
    return () => {
      if (addEl) {
        addEl.removeEventListener("click", onAddClick);
      }
      if (customAddEl) {
        customAddEl.removeEventListener("click", onCustomAddClick);
      }
      sectionEl.removeEventListener("click", onSectionClick);
    };
  }

  // src/assets/js/edit/panel/shared.js
  function formatUploadBytes(value = 0) {
    const bytes = Number(value) || 0;
    if (bytes <= 0) {
      return "0 B";
    }
    const units = ["B", "KB", "MB", "GB"];
    let size = bytes;
    let index = 0;
    while (size >= 1024 && index < units.length - 1) {
      size /= 1024;
      index += 1;
    }
    const rounded = size >= 10 || index === 0 ? Math.round(size) : Math.round(size * 10) / 10;
    return `${rounded} ${units[index]}`;
  }
  function uploadValidationError(files = [], uploadLimits = null) {
    if (!Array.isArray(files) || files.length === 0) {
      return null;
    }
    const perFileLimit = Number((uploadLimits == null ? void 0 : uploadLimits.uploadMaxBytes) || 0);
    const postLimit = Number((uploadLimits == null ? void 0 : uploadLimits.postMaxBytes) || 0);
    const totalSize = files.reduce((sum, file) => sum + (Number(file == null ? void 0 : file.size) || 0), 0);
    if (perFileLimit > 0) {
      const oversizedFile = files.find((file) => (Number(file == null ? void 0 : file.size) || 0) > perFileLimit);
      if (oversizedFile) {
        return `${oversizedFile.name} is too large. Max file size is ${(uploadLimits == null ? void 0 : uploadLimits.uploadMax) || formatUploadBytes(perFileLimit)}.`;
      }
    }
    if (postLimit > 0 && totalSize > postLimit) {
      return `Selected files are too large together. Max upload payload is ${(uploadLimits == null ? void 0 : uploadLimits.postMax) || formatUploadBytes(postLimit)}.`;
    }
    return null;
  }
  function syncPromptDock(promptRoot = null) {
    const promptDock = document.querySelector("#promptDock");
    if (!promptDock) {
      return;
    }
    if (promptRoot) {
      promptDock.replaceChildren(promptRoot);
      return;
    }
    promptDock.replaceChildren();
  }
  function layoutOverlayState(config, status) {
    const layoutState = getLayoutState(config);
    const isFile = (status == null ? void 0 : status.target) === "file";
    const sectionName = layoutState.section || (isFile ? "work" : "works");
    const localLayoutDirectory = layoutState.localLayoutDirectory || (isFile ? `.works/${config.name || config.path || "item"}.layout` : ".layout");
    const wrapperTarget = `${localLayoutDirectory}/template.hbs`;
    const localSectionTarget = `${localLayoutDirectory}/${sectionName}.hbs`;
    const activeSectionDirectory = String(layoutState.sectionDirectory || "").trim();
    const sectionTarget = activeSectionDirectory ? `${activeSectionDirectory}/${sectionName}.hbs` : isFile && layoutState.storage === "filesystem" && layoutState.directory !== localLayoutDirectory ? `built-in ${sectionName}.hbs` : localSectionTarget;
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
    } else if (layoutState.storage === "shared") {
      originalTemplate = layoutState.template || "";
      originalCss = layoutState.css || "";
      originalJs = layoutState.js || "";
    } else if (!originalEditable) {
      originalTemplate = layoutState.phpTemplate || "";
    }
    const resolvedDirectory = layoutState.directory || localLayoutDirectory;
    const wrapperSourceLabel = layoutState.storage === "filesystem" ? isFile && resolvedDirectory !== localLayoutDirectory ? `Folder layout: ${resolvedDirectory}` : `${isFile ? "File layout" : "Folder layout"}: ${resolvedDirectory}` : layoutState.storage === "shared" ? layoutState.sourceLabel || `Collection: ${layoutState.sharedName || layoutState.name || "shared"}` : "PHP built-in poff-layout";
    const inheritedLayoutLabel = hasInheritedLayout ? layoutState.inheritedDirectory : "No parent .layout found";
    const originalLabel = originalEditable ? `Editable source: ${originalTarget}` : layoutState.storage === "shared" ? `Collection layout source: ${layoutState.directory || layoutState.sharedName || layoutState.name || "shared"}` : "PHP built-in poff-layout is read-only until a parent .layout exists";
    const displayMode = layoutState.mode === "filesystem-layout" ? layoutState.directory === localLayoutDirectory ? "custom-layout" : "inherit-layout" : layoutState.mode;
    return {
      layoutState,
      displayMode,
      sectionName,
      localLayoutDirectory,
      wrapperTarget,
      localSectionTarget,
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

  // src/assets/js/edit/panel/upload.js
  function renderUploadSectionHtml({ isFileTarget, isEmptyFolder }) {
    if (isFileTarget) {
      return "";
    }
    return `
        <div class="edit-upload-launch ${isEmptyFolder ? "edit-upload-launch-empty" : ""}">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Add work</div>
                <div class="small-note">${isEmptyFolder ? "This folder is empty. Upload a file, create a blank file, or create a folder to start." : "Upload files, create a blank file, or create a folder in this folder."}</div>
            </div>
            <button class="btn btn-secondary" type="button" id="editOpenUploadDialog">Add work</button>
        </div>
        <dialog class="edit-upload-dialog" id="editUploadDialog">
            <form method="dialog" class="edit-upload-dialog-form">
                <div class="drawer-header">
                    <h4 class="drawer-title">Add work</h4>
                    <button type="button" class="drawer-close" id="editUploadClose">&times;</button>
                </div>
                <div class="edit-grid">
                    <div>
                        <label class="edit-label" for="edit-upload-source">Source</label>
                        <select class="form-select" id="edit-upload-source" name="upload_source">
                            <option value="upload" selected>Upload</option>
                            <option value="blank">Blank file</option>
                            <option value="folder">Folder</option>
                            <option value="url" disabled>From URL (disabled)</option>
                        </select>
                    </div>
                    <div id="editUploadFilesWrap">
                        <label class="edit-label" for="edit-upload-files">Files</label>
                        <input class="form-input" id="edit-upload-files" type="file" name="files" multiple>
                    </div>
                    <div id="editBlankFileWrap" hidden>
                        <label class="edit-label" for="edit-blank-file-name">Blank file name</label>
                        <input class="form-input" id="edit-blank-file-name" type="text" name="blank_file_name" placeholder="notes.txt">
                    </div>
                </div>
                <div class="small-note" id="editUploadSummary">No files selected.</div>
                <div class="edit-inline-actions">
                    <button class="btn" type="button" id="editUploadSubmit">Add</button>
                    <button class="btn btn-secondary" type="button" id="editUploadCancel">Cancel</button>
                </div>
            </form>
        </dialog>
    `;
  }
  function bindUploadDialog({
    editPanel,
    statusEl,
    uploadLimits,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder
  }) {
    const uploadDialog = editPanel.querySelector("#editUploadDialog");
    const openUploadDialogButton = editPanel.querySelector("#editOpenUploadDialog");
    const uploadCloseButton = editPanel.querySelector("#editUploadClose");
    const uploadCancelButton = editPanel.querySelector("#editUploadCancel");
    const uploadSubmitButton = editPanel.querySelector("#editUploadSubmit");
    const uploadSourceEl = editPanel.querySelector("#edit-upload-source");
    const uploadFilesEl = editPanel.querySelector("#edit-upload-files");
    const uploadSummaryEl = editPanel.querySelector("#editUploadSummary");
    const uploadFilesWrapEl = editPanel.querySelector("#editUploadFilesWrap");
    const blankFileWrapEl = editPanel.querySelector("#editBlankFileWrap");
    const blankFileNameEl = editPanel.querySelector("#edit-blank-file-name");
    const blankFileLabelEl = blankFileWrapEl ? blankFileWrapEl.querySelector("label") : null;
    const uploadNameDrafts = {
      blank: "",
      folder: ""
    };
    let uploadMode = (uploadSourceEl == null ? void 0 : uploadSourceEl.value) || "upload";
    if (!uploadDialog || !openUploadDialogButton || typeof onUploadFiles !== "function" || typeof onCreateBlankFile !== "function" || typeof onCreateFolder !== "function") {
      return;
    }
    const setUploadSummary = () => {
      var _a;
      const files = (uploadFilesEl == null ? void 0 : uploadFilesEl.files) ? Array.from(uploadFilesEl.files) : [];
      if (!uploadSummaryEl) {
        return;
      }
      const mode = (uploadSourceEl == null ? void 0 : uploadSourceEl.value) || "upload";
      if (mode === "blank" || mode === "folder") {
        const name = ((_a = blankFileNameEl == null ? void 0 : blankFileNameEl.value) == null ? void 0 : _a.trim()) || "";
        uploadSummaryEl.textContent = name ? `Will create: ${name}` : mode === "folder" ? "Enter a folder name." : "Enter a file name.";
        return;
      }
      const validationError = uploadValidationError(files, uploadLimits);
      if (validationError) {
        uploadSummaryEl.textContent = validationError;
        return;
      }
      uploadSummaryEl.textContent = files.length ? files.map((file) => file.name).join(", ") : "No files selected.";
    };
    const syncUploadMode = () => {
      const mode = (uploadSourceEl == null ? void 0 : uploadSourceEl.value) || "upload";
      if ((uploadMode === "blank" || uploadMode === "folder") && blankFileNameEl) {
        uploadNameDrafts[uploadMode] = blankFileNameEl.value || "";
      }
      uploadMode = mode;
      if (uploadFilesWrapEl) {
        uploadFilesWrapEl.hidden = mode !== "upload";
      }
      if (blankFileWrapEl) {
        blankFileWrapEl.hidden = mode !== "blank" && mode !== "folder";
      }
      if (blankFileLabelEl) {
        blankFileLabelEl.textContent = mode === "folder" ? "Folder name" : "Blank file name";
      }
      if (blankFileNameEl) {
        blankFileNameEl.placeholder = mode === "folder" ? "new-folder" : "notes.txt";
        if (mode === "blank" || mode === "folder") {
          blankFileNameEl.value = uploadNameDrafts[mode] || "";
        }
      }
      if (uploadSubmitButton) {
        uploadSubmitButton.textContent = mode === "blank" ? "Create blank file" : mode === "folder" ? "Create folder" : "Upload";
      }
      setUploadSummary();
    };
    const closeUploadDialog = () => {
      if (typeof uploadDialog.close === "function") {
        uploadDialog.close();
      } else {
        uploadDialog.removeAttribute("open");
      }
    };
    const openUploadDialog = () => {
      syncUploadMode();
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
    if (uploadSourceEl) {
      uploadSourceEl.addEventListener("change", syncUploadMode);
    }
    if (uploadFilesEl) {
      uploadFilesEl.addEventListener("change", setUploadSummary);
    }
    if (blankFileNameEl) {
      blankFileNameEl.addEventListener("input", setUploadSummary);
    }
    if (uploadSubmitButton) {
      uploadSubmitButton.addEventListener("click", async () => {
        var _a, _b;
        const source = (uploadSourceEl == null ? void 0 : uploadSourceEl.value) || "upload";
        try {
          uploadSubmitButton.disabled = true;
          if (source === "blank") {
            const fileName = ((_a = blankFileNameEl == null ? void 0 : blankFileNameEl.value) == null ? void 0 : _a.trim()) || "";
            if (!fileName) {
              setStatusMessage(statusEl, "Enter a file name.");
              return;
            }
            await onCreateBlankFile({
              source,
              fileName,
              statusEl
            });
          } else if (source === "folder") {
            const folderName = ((_b = blankFileNameEl == null ? void 0 : blankFileNameEl.value) == null ? void 0 : _b.trim()) || "";
            if (!folderName) {
              setStatusMessage(statusEl, "Enter a folder name.");
              return;
            }
            await onCreateFolder({
              source,
              folderName,
              statusEl
            });
          } else {
            const files = (uploadFilesEl == null ? void 0 : uploadFilesEl.files) ? Array.from(uploadFilesEl.files) : [];
            if (files.length === 0) {
              setStatusMessage(statusEl, "Choose at least one file.");
              return;
            }
            const validationError = uploadValidationError(files, uploadLimits);
            if (validationError) {
              setStatusMessage(statusEl, validationError);
              return;
            }
            await onUploadFiles({
              source,
              files,
              statusEl
            });
          }
          closeUploadDialog();
        } catch (err) {
          setStatusMessage(statusEl, err.message || "Upload failed.");
        } finally {
          uploadSubmitButton.disabled = false;
        }
      });
    }
    syncUploadMode();
  }

  // src/assets/js/edit/panel/layout-shared.js
  function createLayoutDraftState({
    originalTemplate = "",
    originalCss = "",
    originalJs = "",
    localWrapperTemplate = "",
    localWrapperCss = "",
    localWrapperJs = ""
  }) {
    return {
      virtualTemplate: originalTemplate || "",
      virtualCss: originalCss || "",
      virtualJs: originalJs || "",
      localTemplate: localWrapperTemplate || "",
      localCss: localWrapperCss || "",
      localJs: localWrapperJs || ""
    };
  }
  function createLayoutModeController({
    presetEl,
    getSharedLayoutName = () => "",
    getSharedLayoutPackage = () => null,
    wrapperTarget,
    originalTarget,
    originalEditable,
    hasVirtualSource,
    drafts
  }) {
    const getSharedLayoutLabel = () => {
      const sharedPackage = getSharedLayoutPackage == null ? void 0 : getSharedLayoutPackage();
      const sharedName = String(getSharedLayoutName() || "").trim();
      return String((sharedPackage == null ? void 0 : sharedPackage.label) || (sharedPackage == null ? void 0 : sharedPackage.name) || sharedName || "shared").trim();
    };
    const currentPrimaryMode = () => {
      const preset = ((presetEl == null ? void 0 : presetEl.value) || "actual").trim();
      if (preset === "custom") {
        return "local";
      }
      if (preset === "actual" || preset === "shared") {
        return "virtual";
      }
      return hasVirtualSource ? "virtual" : "local";
    };
    const syncLayoutMode = ({ modePreviewEl, sourcePreviewEl, primaryTitleEl, primaryHintEl, primaryTemplateEl, primaryCssEl, primaryJsEl }) => {
      const preset = ((presetEl == null ? void 0 : presetEl.value) || "actual").trim();
      const sharedPackage = preset === "shared" ? getSharedLayoutPackage() : null;
      if (preset === "shared" && sharedPackage) {
        drafts.virtualTemplate = sharedPackage.template || drafts.virtualTemplate;
        drafts.virtualCss = sharedPackage.css || drafts.virtualCss;
        drafts.virtualJs = sharedPackage.js || drafts.virtualJs;
      }
      const nextMode = preset === "none" ? "none" : preset === "custom" ? "custom-layout" : preset === "shared" ? "collection-layout" : originalEditable ? originalTarget === wrapperTarget.replace(/\/template\.hbs$/, "") ? "custom-layout" : "inherit-layout" : "poff-layout";
      const primaryMode = currentPrimaryMode();
      const isVirtual = primaryMode === "virtual";
      const localWrapperDirectory = wrapperTarget.replace(/\/template\.hbs$/, "");
      const sharedLayoutName = getSharedLayoutLabel();
      const sourcePreview = isVirtual ? preset === "shared" ? `Collection: ${sharedLayoutName || "shared"}` : originalEditable ? `Filesystem: ${originalTarget}` : "PHP built-in poff-layout" : `Filesystem: ${localWrapperDirectory}`;
      if (modePreviewEl) {
        modePreviewEl.textContent = nextMode;
      }
      if (sourcePreviewEl) {
        sourcePreviewEl.textContent = sourcePreview;
      }
      if (primaryTitleEl) {
        primaryTitleEl.textContent = preset === "shared" ? "Collection layout" : isVirtual ? "Virtual layout" : "Custom layout";
      }
      if (primaryHintEl) {
        if (preset === "shared") {
          primaryHintEl.innerHTML = `Editing collection layout <code>${escapeHtml(sharedLayoutName || "shared")}</code>. Changes save inline unless you switch to <code>Custom</code>.`;
        } else if (isVirtual) {
          primaryHintEl.innerHTML = originalEditable ? originalTarget === localWrapperDirectory ? `Editing the resolved layout source <code>${escapeHtml(originalTarget)}</code>.` : `Editing the inherited parent layout source <code>${escapeHtml(originalTarget)}</code>. Switch to <code>Custom</code> when you want to create a local <code>${escapeHtml(wrapperTarget)}</code>.` : "Showing the bundled poff-layout. It stays read-only until a parent .layout exists.";
        } else {
          primaryHintEl.innerHTML = `Editing the local wrapper override <code>${escapeHtml(wrapperTarget)}</code>.`;
        }
      }
      if (primaryTemplateEl) {
        primaryTemplateEl.value = isVirtual ? drafts.virtualTemplate : drafts.localTemplate;
        primaryTemplateEl.disabled = isVirtual && !originalEditable && preset !== "shared";
      }
      if (primaryCssEl) {
        primaryCssEl.value = isVirtual ? drafts.virtualCss : drafts.localCss;
        primaryCssEl.disabled = isVirtual && !originalEditable && preset !== "shared";
      }
      if (primaryJsEl) {
        primaryJsEl.value = isVirtual ? drafts.virtualJs : drafts.localJs;
        primaryJsEl.disabled = isVirtual && !originalEditable && preset !== "shared";
      }
    };
    const storePrimaryDraft = ({ primaryTemplateEl, primaryCssEl, primaryJsEl }) => {
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
    return {
      currentPrimaryMode,
      syncLayoutMode,
      storePrimaryDraft
    };
  }
  function buildLayoutSubmitPayload({
    preset,
    sharedLayoutName = "",
    currentSectionTemplate,
    sectionWasLocal,
    contentTemplateEl,
    currentPrimaryMode,
    drafts,
    originalEditable,
    originalTarget,
    wrapperWasLocal
  }) {
    var _a;
    const payload = {
      layoutPreset: preset,
      layoutSharedName: sharedLayoutName
    };
    const contentTemplateValue = (_a = contentTemplateEl == null ? void 0 : contentTemplateEl.value) != null ? _a : "";
    if (sectionWasLocal || contentTemplateValue !== currentSectionTemplate) {
      payload.contentTemplate = contentTemplateValue;
    }
    if (preset === "shared") {
      payload.layoutTemplate = drafts.virtualTemplate;
      payload.layoutCss = drafts.virtualCss;
      payload.layoutJs = drafts.virtualJs;
      return payload;
    }
    if (currentPrimaryMode() === "virtual") {
      if (originalEditable) {
        payload.originalLayoutTarget = originalTarget;
        payload.originalLayoutTemplate = drafts.virtualTemplate;
        payload.originalLayoutCss = drafts.virtualCss;
        payload.originalLayoutJs = drafts.virtualJs;
      }
    } else {
      const hasLocalDraft = wrapperWasLocal || drafts.localTemplate.trim() !== "" || drafts.localCss.trim() !== "" || drafts.localJs.trim() !== "";
      if (hasLocalDraft) {
        payload.layoutTemplate = drafts.localTemplate;
        payload.layoutCss = drafts.localCss;
        payload.layoutJs = drafts.localJs;
      }
    }
    return payload;
  }

  // src/assets/js/edit/panel/layout.js
  function renderLayoutModeSummary({ subjectLabel, displayMode, wrapperSourceLabel, inheritedLayoutLabel, sectionTarget }) {
    return `
        <div class="edit-layout-launch edit-layout-summary">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="edit-layout-summary-line">Editing source: <code id="edit-layout-source-preview">${escapeHtml(wrapperSourceLabel)}</code></div>
                <div class="edit-layout-summary-line">Current mode: <code id="edit-layout-mode-preview">${escapeHtml(displayMode)}</code></div>
                <div class="edit-layout-summary-line">Inner section stays at <code>${escapeHtml(sectionTarget)}</code> unless you change it in <strong>More...</strong></div>
                <div class="edit-layout-summary-line">Prompt context separates <code>root.title</code> for the layout shell from <code>work.title</code> for the nested item.</div>
            </div>
            <div class="edit-inline-actions edit-layout-header-actions">
                <button class="btn btn-secondary" type="button" id="editLayoutBack">Back to work</button>
                <button class="btn btn-secondary" type="button" id="editLayoutMore">More...</button>
            </div>
        </div>
    `;
  }
  function renderSharedLayoutOptions(sharedLayouts = [], selectedName = "", currentLayoutDirectory = "") {
    var _a, _b;
    if (!Array.isArray(sharedLayouts) || sharedLayouts.length === 0) {
      return '<div class="small-note">No collection layouts available for this worktype.</div>';
    }
    const groupedLayouts = sharedLayouts.reduce((groups, option) => {
      const groupKey = String((option == null ? void 0 : option.source) || "collection").trim() === "bundled" ? "built-in" : "collection";
      if (!groups[groupKey]) {
        groups[groupKey] = [];
      }
      groups[groupKey].push(option);
      return groups;
    }, {});
    const isCurrentOption = (option) => String((option == null ? void 0 : option.directory) || (option == null ? void 0 : option.name) || "") === String(currentLayoutDirectory || "");
    const optionLabel = (option, fallback) => {
      const label = (option == null ? void 0 : option.label) || (option == null ? void 0 : option.name) || fallback;
      return `${label}${isCurrentOption(option) ? " (current)" : ""}`;
    };
    return `
        <label class="edit-label" for="edit-layout-shared">Collection layout</label>
        <select class="form-select" id="edit-layout-shared" name="layout_shared">
            ${((_a = groupedLayouts["built-in"]) == null ? void 0 : _a.length) ? `
                <optgroup label="Built-in">
                    ${groupedLayouts["built-in"].map((option) => `
                        <option value="${escapeHtml(option.name || "")}" ${String(selectedName || "") === String(option.name || "") ? "selected" : ""}>
                            ${escapeHtml(optionLabel(option, "built-in"))}
                        </option>
                    `).join("")}
                </optgroup>
            ` : ""}
            ${((_b = groupedLayouts.collection) == null ? void 0 : _b.length) ? `
                <optgroup label="Collection">
                    ${groupedLayouts.collection.map((option) => `
                        <option value="${escapeHtml(option.name || "")}" ${String(selectedName || "") === String(option.name || "") ? "selected" : ""}>
                            ${escapeHtml(optionLabel(option, "collection"))}
                        </option>
                    `).join("")}
                </optgroup>
            ` : ""}
        </select>
        <div class="small-note">Choose a layout from the same worktype. The visible names come from the folder names.</div>
    `;
  }
  function bindLayoutForm({
    form,
    presetEl,
    sharedLayoutEl,
    contentTemplateEl,
    currentSectionTemplate,
    sectionWasLocal,
    currentPrimaryMode,
    storePrimaryDraft,
    drafts,
    originalEditable,
    originalTarget,
    wrapperWasLocal,
    statusEl,
    onSubmitLayout,
    primaryTemplateEl,
    primaryCssEl,
    primaryJsEl
  }) {
    if (!form || typeof onSubmitLayout !== "function") {
      return;
    }
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl });
      const payload = buildLayoutSubmitPayload({
        preset: ((presetEl == null ? void 0 : presetEl.value) || "actual").trim(),
        sharedLayoutName: (sharedLayoutEl == null ? void 0 : sharedLayoutEl.value) || "",
        currentSectionTemplate,
        sectionWasLocal,
        contentTemplateEl,
        currentPrimaryMode,
        drafts,
        originalEditable,
        originalTarget,
        wrapperWasLocal
      });
      await onSubmitLayout({
        payload,
        statusEl
      });
    });
  }
  function renderEditLayoutPanel({
    editPanel,
    config,
    status,
    contentTargetLabel,
    onSubmitLayout,
    onLayoutPresetChange,
    onReturnToWork,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onResetFolderWork
  }) {
    const settings = loadPromptSettings();
    const subjectStatus = {
      ...status,
      target: (status == null ? void 0 : status.subjectTarget) || (status == null ? void 0 : status.target)
    };
    const overlayState = layoutOverlayState(config, subjectStatus);
    const {
      layoutState,
      displayMode,
      sectionName,
      wrapperTarget,
      sectionTarget,
      wrapperWasLocal,
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
      { value: "actual", label: "Inherit" },
      { value: "none", label: "None" },
      { value: "custom", label: "Custom" },
      { value: "shared", label: "Collection" }
    ];
    const hasVirtualSource = !overlayState.wrapperWasLocal && !originalUsesLocal;
    const isFileSubject = subjectStatus.target === "file";
    const sharedLayouts = Array.isArray(layoutState.sharedLayouts) ? layoutState.sharedLayouts : [];
    const sharedLayoutName = String(layoutState.sharedName || layoutState.name || "").trim();
    const uploadSectionHtml = renderUploadSectionHtml({
      isFileTarget: isFileSubject,
      isEmptyFolder: false
    });
    editPanel.innerHTML = `
        <h3 class="edit-panel-title">Edit layout (${subjectLabel})</h3>
        <div class="small-note">Virtual <code>.layout</code> target for this ${escapeHtml(subjectLabel)}. The preview stays on the current work while you edit the wrapper.</div>
        <div class="edit-status" id="editLayoutStatus"></div>
        <form id="editLayoutPanelForm" class="edit-inline edit-layout-panel">
            ${renderLayoutModeSummary({ subjectLabel, displayMode, wrapperSourceLabel, inheritedLayoutLabel, sectionTarget })}
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-layout-preset">Layout select</label>
                    <select class="form-select" id="edit-layout-preset" name="layout_preset">
                        ${layoutPresetOptions.map((option) => `
                            <option value="${option.value}" ${layoutState.preset === option.value ? "selected" : ""}>${option.label}</option>
                        `).join("")}
                    </select>
                    <div class="mt-3${layoutState.preset === "shared" ? "" : " hidden"}" id="edit-layout-shared-wrap">
                        ${renderSharedLayoutOptions(sharedLayouts, sharedLayoutName, layoutState.directory || "")}
                    </div>
                </div>
                <div class="edit-layout-copy edit-layout-section-note">
                    <div class="edit-layout-title" id="edit-layout-primary-title"></div>
                    <div class="small-note" id="edit-layout-primary-hint"></div>
                    <div class="edit-inline-actions edit-layout-select-actions">
                        <button class="btn" type="submit">Save layout</button>
                    </div>
                </div>
            </div>
        </form>
        ${uploadSectionHtml}
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
    const sharedLayoutWrapEl = editPanel.querySelector("#edit-layout-shared-wrap");
    const sharedLayoutEl = editPanel.querySelector("#edit-layout-shared");
    const modePreviewEl = editPanel.querySelector("#edit-layout-mode-preview");
    const sourcePreviewEl = editPanel.querySelector("#edit-layout-source-preview");
    const primaryTitleEl = editPanel.querySelector("#edit-layout-primary-title");
    const primaryHintEl = editPanel.querySelector("#edit-layout-primary-hint");
    const primaryTemplateEl = editPanel.querySelector("#edit-layout-primary-template");
    const primaryCssEl = editPanel.querySelector("#edit-layout-primary-css");
    const primaryJsEl = editPanel.querySelector("#edit-layout-primary-js");
    const contentTemplateEl = editPanel.querySelector("#edit-content-template");
    const promptRoot = editPanel.querySelector("#promptLayer");
    syncPromptDock(promptRoot);
    const currentSectionTemplate = layoutState.sectionTemplate || "";
    const drafts = createLayoutDraftState({
      originalTemplate,
      originalCss,
      originalJs,
      localWrapperTemplate,
      localWrapperCss,
      localWrapperJs
    });
    const { currentPrimaryMode, syncLayoutMode, storePrimaryDraft } = createLayoutModeController({
      presetEl,
      getSharedLayoutName: () => (sharedLayoutEl == null ? void 0 : sharedLayoutEl.value) || sharedLayoutName,
      getSharedLayoutPackage: () => (sharedLayouts || []).find((option) => String(option.name || "") === String((sharedLayoutEl == null ? void 0 : sharedLayoutEl.value) || sharedLayoutName)) || null,
      wrapperTarget,
      originalTarget,
      originalEditable,
      hasVirtualSource,
      drafts
    });
    if (presetEl) {
      presetEl.addEventListener("change", async () => {
        if (sharedLayoutWrapEl) {
          sharedLayoutWrapEl.classList.toggle("hidden", presetEl.value !== "shared");
        }
        storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl });
        syncLayoutMode({
          modePreviewEl,
          sourcePreviewEl,
          primaryTitleEl,
          primaryHintEl,
          primaryTemplateEl,
          primaryCssEl,
          primaryJsEl
        });
        if (typeof onLayoutPresetChange === "function") {
          await onLayoutPresetChange({
            payload: {
              layoutPreset: (presetEl.value || "actual").trim(),
              layoutSharedName: (sharedLayoutEl == null ? void 0 : sharedLayoutEl.value) || sharedLayoutName
            },
            statusEl
          });
        }
      });
    }
    if (sharedLayoutEl) {
      sharedLayoutEl.addEventListener("change", async () => {
        if (typeof onLayoutPresetChange === "function") {
          await onLayoutPresetChange({
            payload: {
              layoutPreset: ((presetEl == null ? void 0 : presetEl.value) || "actual").trim(),
              layoutSharedName: sharedLayoutEl.value
            },
            statusEl
          });
        }
        syncLayoutMode({
          modePreviewEl,
          sourcePreviewEl,
          primaryTitleEl,
          primaryHintEl,
          primaryTemplateEl,
          primaryCssEl,
          primaryJsEl
        });
      });
    }
    [primaryTemplateEl, primaryCssEl, primaryJsEl].forEach((field) => {
      if (field) {
        field.addEventListener("input", () => storePrimaryDraft({ primaryTemplateEl, primaryCssEl, primaryJsEl }));
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
    syncLayoutMode({
      modePreviewEl,
      sourcePreviewEl,
      primaryTitleEl,
      primaryHintEl,
      primaryTemplateEl,
      primaryCssEl,
      primaryJsEl
    });
    bindLayoutForm({
      form,
      presetEl,
      sharedLayoutEl,
      contentTemplateEl,
      currentSectionTemplate,
      sectionWasLocal,
      currentPrimaryMode,
      storePrimaryDraft,
      drafts,
      originalEditable,
      originalTarget,
      wrapperWasLocal,
      statusEl,
      onSubmitLayout,
      primaryTemplateEl,
      primaryCssEl,
      primaryJsEl
    });
    bindUploadDialog({
      editPanel,
      statusEl,
      uploadLimits: (status == null ? void 0 : status.uploadLimits) || null,
      onUploadFiles,
      onCreateBlankFile,
      onCreateFolder,
      onResetFolderWork
    });
    return { statusEl, promptRoot };
  }

  // src/assets/js/edit/panel/inline.js
  var RESERVED_WORK_CONFIG_KEYS = /* @__PURE__ */ new Set(["type", "template", "templateMap", "layout", "fields", "categories", "category", "kind"]);
  function readRowText(row, selector) {
    const field = row.querySelector(selector);
    return field && typeof field.value === "string" ? field.value : "";
  }
  function readRowNumber(row, selector) {
    const value = readRowText(row, selector).trim();
    return value === "" ? "" : Number(value);
  }
  function readRowList(row, selector) {
    const value = readRowText(row, selector);
    return value.split(/\r?\n|,/).map((item) => item.trim()).filter(Boolean);
  }
  function readRowBool(row, selector) {
    const field = row.querySelector(selector);
    return !!(field == null ? void 0 : field.checked);
  }
  function readRowValue(row, selector) {
    const field = row.querySelector(selector);
    if (!field) {
      return "";
    }
    if (field instanceof HTMLInputElement && field.type === "checkbox") {
      return field.checked;
    }
    if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement) {
      return field.value;
    }
    return "";
  }
  function renderValueControl(field, index) {
    const value = field.value;
    if (field.type === "checkbox") {
      return `
            <label class="edit-work-field-value-toggle">
                <input class="edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="checkbox"${value ? " checked" : ""}>
            </label>
        `;
    }
    if (field.type === "number") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="number" step="any" value="${escapeHtml(value != null ? value : "")}" placeholder="Value">`;
    }
    if (field.type === "select") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="text" value="${escapeHtml(typeof value === "string" ? value : String(value != null ? value : ""))}" placeholder="Selected value">`;
    }
    if (field.type === "color") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="color" value="${escapeHtml(value || "#000000")}">`;
    }
    if (field.type === "date") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="date" value="${escapeHtml(typeof value === "string" ? value : String(value != null ? value : ""))}">`;
    }
    if (field.type === "url") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="url" value="${escapeHtml(typeof value === "string" ? value : String(value != null ? value : ""))}" placeholder="https://example.com">`;
    }
    if (field.type === "email") {
      return `<input class="form-input edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value type="email" value="${escapeHtml(typeof value === "string" ? value : String(value != null ? value : ""))}" placeholder="name@example.com">`;
    }
    return `<textarea class="form-textarea edit-work-field-value" id="edit-work-field-value-${index}" data-work-field-value rows="${field.type === "textarea" ? "5" : "2"}" placeholder="Value">${escapeHtml(typeof value === "string" ? value : String(value != null ? value : ""))}</textarea>`;
  }
  function renderSchemaControl(field, key, html) {
    return getWorkFieldSchemaProfile(field.type).visibleControls.has(key) ? html : "";
  }
  function renderSchemaGroup(field, keys, html) {
    const visible = keys.some((key) => getWorkFieldSchemaProfile(field.type).visibleControls.has(key));
    return visible ? html : "";
  }
  function renderMediaTypeOptions(selectedValue = "", catalog = null) {
    const normalizedSelected = String(selectedValue || "").trim();
    const choices = Array.isArray(catalog == null ? void 0 : catalog.choices) ? catalog.choices : [];
    const options = choices.length ? choices.map((choice) => String((choice == null ? void 0 : choice.value) || "").trim()).filter(Boolean) : ["video", "image", "audio", "pdf", "text", "link", "folder", "other"];
    if (normalizedSelected && !options.includes(normalizedSelected)) {
      options.unshift(normalizedSelected);
    }
    return options.filter((option, index) => option && options.indexOf(option) === index).map((option) => `<option value="${escapeHtml(option)}"${option === normalizedSelected ? " selected" : ""}>${escapeHtml(option)}</option>`).join("");
  }
  function readMediaConfigFromForm(form, currentWork = {}) {
    var _a, _b, _c;
    const nextWork = { ...currentWork && typeof currentWork === "object" ? currentWork : {} };
    const typeField = (_a = form == null ? void 0 : form.elements) == null ? void 0 : _a.work_type;
    if (typeField && typeof typeField.value === "string") {
      const type = typeField.value.trim();
      if (type) {
        nextWork.type = type;
      }
    }
    const configFields = (form == null ? void 0 : form.querySelectorAll("[data-work-config-field]")) || [];
    configFields.forEach((field) => {
      if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return;
      }
      const key = String(field.dataset.workConfigKey || "").trim();
      if (!key) {
        return;
      }
      const kind = String(field.dataset.workConfigKind || "text").trim();
      const isNullable = field.dataset.workConfigNullable === "true";
      if (field instanceof HTMLInputElement && field.type === "checkbox") {
        nextWork[key] = !!field.checked;
        return;
      }
      const rawValue = field.value;
      if (kind === "number") {
        nextWork[key] = rawValue === "" ? null : Number(rawValue);
        return;
      }
      if (kind === "json") {
        const trimmed2 = String(rawValue || "").trim();
        if (trimmed2 === "") {
          nextWork[key] = null;
          return;
        }
        try {
          nextWork[key] = JSON.parse(trimmed2);
        } catch (e) {
          nextWork[key] = trimmed2;
        }
        return;
      }
      const trimmed = String(rawValue || "").trim();
      if (isNullable && trimmed === "") {
        nextWork[key] = null;
        return;
      }
      nextWork[key] = rawValue;
    });
    const categories = normalizeWorkCategories2((_c = (_b = nextWork.categories) != null ? _b : nextWork.category) != null ? _c : []);
    nextWork.categories = categories;
    nextWork.category = categories;
    return nextWork;
  }
  function renderWorkValueControl(key, value) {
    const normalizedKey = String(key || "").trim();
    const inputId = `edit-work-config-${normalizedKey}`;
    if (typeof value === "boolean") {
      return `
            <label class="edit-work-field-value-toggle">
                <input class="edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="checkbox" type="checkbox"${value ? " checked" : ""}>
            </label>
        `;
    }
    if (typeof value === "number" && Number.isFinite(value)) {
      return `<input class="form-input edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="number" type="number" step="any" value="${escapeHtml(String(value))}" placeholder="Value">`;
    }
    if (Array.isArray(value) || value && typeof value === "object") {
      const serialized = JSON.stringify(value, null, 2);
      return `<textarea class="form-textarea edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="json" rows="3" placeholder="JSON value">${escapeHtml(serialized)}</textarea>`;
    }
    const isNullable = normalizedKey === "poster";
    return `<input class="form-input edit-work-field-value" id="${escapeHtml(inputId)}" data-work-config-field data-work-config-key="${escapeHtml(normalizedKey)}" data-work-config-kind="text"${isNullable ? ' data-work-config-nullable="true"' : ""} type="text" value="${escapeHtml(value === null || value === void 0 ? "" : String(value))}" placeholder="Value">`;
  }
  function renderWorkConfigFieldsSection(config = {}) {
    var _a, _b;
    const work = (config == null ? void 0 : config.work) && typeof config.work === "object" ? config.work : {};
    const workType = String(work.type || "").trim();
    const catalog = (config == null ? void 0 : config.workTemplateCatalog) && typeof config.workTemplateCatalog === "object" ? config.workTemplateCatalog : null;
    const categoryOptions = getWorkCategoryOptions(catalog);
    const fieldNames = new Set(extractWorkFields(work).map((field) => field.name));
    const dynamicKeys = Object.keys(work).filter((key) => !RESERVED_WORK_CONFIG_KEYS.has(key) && key !== "type" && !fieldNames.has(key));
    const workTypeSummary = dynamicKeys.length ? dynamicKeys.join(", ") : "No additional work fields yet.";
    const selectedValue = String(work.template || (catalog == null ? void 0 : catalog.selected) || workType || "").trim();
    return `
        <div class="edit-work-fields edit-work-media">
            <div class="edit-work-fields-header">
                <div>
                    <div class="edit-work-fields-title">Work settings</div>
                    <div class="small-note">Derived from the current work config. Each worktype exposes its own fields here.</div>
                </div>
            </div>
            <div class="edit-grid edit-grid-cols">
                <div>
                    <label class="edit-label" for="edit-work-type">Work type</label>
                    <select class="form-select" id="edit-work-type" name="work_type">
                        ${renderMediaTypeOptions(selectedValue || "video", catalog)}
                    </select>
                    <div class="small-note">${(catalog == null ? void 0 : catalog.detectedMime) ? `Detected ${escapeHtml(catalog.detectedMime)}${catalog.detectedExtension ? ` \xB7 .${escapeHtml(catalog.detectedExtension)}` : ""} \xB7 showing ${escapeHtml(catalog.detectedKind || "current")} templates` : "Base family for the current item."}</div>
                </div>
                <div>
                    <div class="edit-label">Current work fields</div>
                    <div class="small-note">${escapeHtml(workTypeSummary)}</div>
                </div>
            </div>
            ${categoryOptions.length || normalizeWorkCategories2((_b = (_a = work.categories) != null ? _a : work.category) != null ? _b : []).length ? renderWorkCategorySection(config) : ""}
            ${dynamicKeys.length ? `
            <div class="edit-work-config-grid">
                ${dynamicKeys.map((key) => `
                    <div class="edit-work-config-field">
                        <label class="edit-label" for="edit-work-config-${escapeHtml(key)}">work.${escapeHtml(key)}</label>
                        ${renderWorkValueControl(key, work[key])}
                    </div>
                `).join("")}
            </div>
            ` : ""}
        </div>
    `;
  }
  function renderWorkFieldRows(fields = [], typeOptions = schemaFieldTypeOptions()) {
    if (!fields.length) {
      return '<div class="small-note edit-work-fields-empty">No extra work fields yet.</div>';
    }
    return fields.map((field, index) => {
      var _a, _b, _c, _d, _e, _f, _g, _h, _i;
      return `
        <div class="edit-work-field-row" data-work-field-row="${index}">
            <div class="edit-work-field-main">
                <div class="edit-work-field-head">
                    <div>
                        <label class="edit-label" for="edit-work-field-type-${index}">Type</label>
                        <select class="form-select edit-work-field-type" id="edit-work-field-type-${index}" data-work-field-type>
                            ${typeOptions.map((option) => `<option value="${option}" ${field.type === option ? "selected" : ""}>${option}</option>`).join("")}
                        </select>
                    </div>
                    <div class="edit-work-field-name-wrap">
                        <label class="edit-label" for="edit-work-field-name-${index}">Name</label>
                        <input class="form-input edit-work-field-name" id="edit-work-field-name-${index}" data-work-field-name type="text" value="${escapeHtml(field.name || "")}" placeholder="text1">
                        <div class="small-note">Use <code>work.${escapeHtml(field.name || "text1")}</code> or <code>{{${escapeHtml(field.name || "text1")}}}</code> in templates.</div>
                    </div>
                    <button class="btn btn-secondary edit-work-field-remove" type="button" data-work-field-remove aria-label="Remove work field">\xD7</button>
                </div>
                <div class="edit-work-field-value-row">
                    <div class="edit-work-field-value-wrap">
                        <label class="edit-label" for="edit-work-field-value-${index}">Value</label>
                        ${renderValueControl(field, index)}
                    </div>
                </div>
                <details class="edit-work-field-advanced">
                    <summary>Schema options</summary>
                    <div class="edit-work-field-advanced-grid">
                        ${renderSchemaGroup(field, ["title", "description", "placeholder", "const", "default"], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Text</div>
                            <div class="edit-work-field-schema-group-grid">
                                ${renderSchemaControl(field, "title", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-title-${index}">Title</label>
                                    <input class="form-input edit-work-field-title" id="edit-work-field-title-${index}" data-work-field-title type="text" value="${escapeHtml(field.title || "")}" placeholder="Label title">
                                </div>
                                `)}
                                ${renderSchemaControl(field, "description", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-description-${index}">Description</label>
                                    <textarea class="form-textarea edit-work-field-description" id="edit-work-field-description-${index}" data-work-field-description rows="2" placeholder="Short help text">${escapeHtml(field.description || "")}</textarea>
                                </div>
                                `)}
                                ${renderSchemaControl(field, "placeholder", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-placeholder-${index}">Placeholder</label>
                                    <input class="form-input edit-work-field-placeholder" id="edit-work-field-placeholder-${index}" data-work-field-placeholder type="text" value="${escapeHtml(field.placeholder || "")}" placeholder="Placeholder">
                                </div>
                                `)}
                                ${renderSchemaControl(field, "const", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-const-${index}">Const</label>
                                    ${field.type === "checkbox" ? `<input class="form-input edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const type="checkbox"${field.const ? " checked" : ""}>` : field.type === "number" ? `<input class="form-input edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const type="number" step="any" value="${escapeHtml((_a = field.const) != null ? _a : "")}" placeholder="Locked value">` : `<textarea class="form-textarea edit-work-field-const" id="edit-work-field-const-${index}" data-work-field-const rows="2" placeholder="Locked value">${escapeHtml(typeof field.const === "string" ? field.const : String((_b = field.const) != null ? _b : ""))}</textarea>`}
                                </div>
                                `)}
                                ${renderSchemaControl(field, "default", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-default-${index}">Default</label>
                                    ${field.type === "checkbox" ? `<input class="form-input edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default type="checkbox"${field.default ? " checked" : ""}>` : field.type === "number" ? `<input class="form-input edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default type="number" step="any" value="${escapeHtml((_c = field.default) != null ? _c : "")}" placeholder="Default value">` : `<textarea class="form-textarea edit-work-field-default" id="edit-work-field-default-${index}" data-work-field-default rows="2" placeholder="Default value">${escapeHtml(typeof field.default === "string" ? field.default : String((_d = field.default) != null ? _d : ""))}</textarea>`}
                                </div>
                                `)}
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ["format", "pattern", "minLength", "maxLength", "minimum", "maximum", "exclusiveMinimum", "exclusiveMaximum", "multipleOf", "step"], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Constraints</div>
                            <div class="edit-work-field-schema-group-grid">
                                ${renderSchemaControl(field, "format", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-format-${index}">Format</label>
                                    <input class="form-input edit-work-field-format" id="edit-work-field-format-${index}" data-work-field-format type="text" value="${escapeHtml(field.format || "")}" placeholder="date-time, uri, email">
                                </div>
                                `)}
                                ${renderSchemaControl(field, "pattern", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-pattern-${index}">Pattern</label>
                                    <input class="form-input edit-work-field-pattern" id="edit-work-field-pattern-${index}" data-work-field-pattern type="text" value="${escapeHtml(field.pattern || "")}" placeholder="Regex">
                                </div>
                                `)}
                                <div class="edit-work-field-small-grid">
                                    ${renderSchemaControl(field, "minLength", `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-minLength-${index}">Min length</label>
                                        <input class="form-input edit-work-field-minLength" id="edit-work-field-minLength-${index}" data-work-field-minLength type="number" step="1" value="${escapeHtml((_e = field.minLength) != null ? _e : "")}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, "maxLength", `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-maxLength-${index}">Max length</label>
                                        <input class="form-input edit-work-field-maxLength" id="edit-work-field-maxLength-${index}" data-work-field-maxLength type="number" step="1" value="${escapeHtml((_f = field.maxLength) != null ? _f : "")}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, "minimum", `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-minimum-${index}">Minimum</label>
                                        <input class="form-input edit-work-field-minimum" id="edit-work-field-minimum-${index}" data-work-field-minimum type="number" step="any" value="${escapeHtml((_g = field.minimum) != null ? _g : "")}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, "maximum", `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-maximum-${index}">Maximum</label>
                                        <input class="form-input edit-work-field-maximum" id="edit-work-field-maximum-${index}" data-work-field-maximum type="number" step="any" value="${escapeHtml((_h = field.maximum) != null ? _h : "")}" placeholder="0">
                                    </div>
                                    `)}
                                    ${renderSchemaControl(field, "step", `
                                    <div>
                                        <label class="edit-label" for="edit-work-field-step-${index}">Step</label>
                                        <input class="form-input edit-work-field-step" id="edit-work-field-step-${index}" data-work-field-step type="number" step="any" value="${escapeHtml((_i = field.step) != null ? _i : "")}" placeholder="1">
                                    </div>
                                    `)}
                                </div>
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ["enum", "examples"], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Values</div>
                            <div class="edit-work-field-schema-group-grid">
                                <div>
                                    <label class="edit-label" for="edit-work-field-enum-${index}">Enum</label>
                                    <textarea class="form-textarea edit-work-field-enum" id="edit-work-field-enum-${index}" data-work-field-enum rows="2" placeholder="One option per line">${escapeHtml(Array.isArray(field.enum) ? field.enum.join("\n") : "")}</textarea>
                                </div>
                                ${renderSchemaControl(field, "examples", `
                                <div>
                                    <label class="edit-label" for="edit-work-field-examples-${index}">Examples</label>
                                    <textarea class="form-textarea edit-work-field-examples" id="edit-work-field-examples-${index}" data-work-field-examples rows="2" placeholder="One example per line">${escapeHtml(Array.isArray(field.examples) ? field.examples.join("\n") : "")}</textarea>
                                </div>
                                `)}
                            </div>
                        </section>
                        `)}
                        ${renderSchemaGroup(field, ["required", "readOnly", "writeOnly", "deprecated", "nullable"], `
                        <section class="edit-work-field-schema-group">
                            <div class="edit-work-field-schema-group-title">Flags</div>
                            <div class="edit-work-field-bools">
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-required${field.required ? " checked" : ""}><span>required</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-readOnly${field.readOnly ? " checked" : ""}><span>readOnly</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-writeOnly${field.writeOnly ? " checked" : ""}><span>writeOnly</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-deprecated${field.deprecated ? " checked" : ""}><span>deprecated</span></label>
                                <label class="edit-work-field-check"><input type="checkbox" data-work-field-nullable${field.nullable ? " checked" : ""}><span>nullable</span></label>
                            </div>
                        </section>
                        `)}
                    </div>
                </details>
            </div>
        </div>
    `;
    }).join("");
  }
  function createWorkFieldEditor({ editPanel, onWorkFieldsInput, initialState = [] }) {
    const typeOptions = schemaFieldTypeOptions();
    let workFieldState = initialState;
    const commitWorkFieldState = () => {
      if (typeof onWorkFieldsInput !== "function") {
        return;
      }
      workFieldState = workFieldState.map((field, index) => normalizeWorkField(field, index));
      onWorkFieldsInput(workFieldState);
    };
    const syncWorkFieldsFromDom = () => {
      const rows = Array.from(editPanel.querySelectorAll("[data-work-field-row]"));
      workFieldState = rows.map((row, index) => {
        const previous = workFieldState[index] && typeof workFieldState[index] === "object" ? workFieldState[index] : {};
        const candidate = {
          ...previous,
          type: readRowText(row, "[data-work-field-type]") || "text",
          name: readRowText(row, "[data-work-field-name]") || "",
          title: readRowText(row, "[data-work-field-title]") || "",
          description: readRowText(row, "[data-work-field-description]") || "",
          placeholder: readRowText(row, "[data-work-field-placeholder]") || "",
          const: readRowText(row, "[data-work-field-const]") || "",
          value: readRowValue(row, "[data-work-field-value]"),
          default: readRowValue(row, "[data-work-field-default]"),
          format: readRowText(row, "[data-work-field-format]") || "",
          pattern: readRowText(row, "[data-work-field-pattern]") || "",
          required: readRowBool(row, "[data-work-field-required]"),
          readOnly: readRowBool(row, "[data-work-field-readOnly]"),
          writeOnly: readRowBool(row, "[data-work-field-writeOnly]"),
          deprecated: readRowBool(row, "[data-work-field-deprecated]"),
          nullable: readRowBool(row, "[data-work-field-nullable]"),
          minLength: readRowNumber(row, "[data-work-field-minLength]"),
          maxLength: readRowNumber(row, "[data-work-field-maxLength]"),
          minimum: readRowNumber(row, "[data-work-field-minimum]"),
          maximum: readRowNumber(row, "[data-work-field-maximum]"),
          step: readRowNumber(row, "[data-work-field-step]"),
          enum: readRowList(row, "[data-work-field-enum]"),
          examples: readRowList(row, "[data-work-field-examples]")
        };
        return applyWorkFieldTypeDefaults(normalizeWorkField(candidate, index), candidate.type);
      });
      commitWorkFieldState();
    };
    const rerenderWorkFields = () => {
      const listEl = editPanel.querySelector("#editWorkFieldsList");
      if (!listEl) {
        return;
      }
      listEl.innerHTML = renderWorkFieldRows(workFieldState, typeOptions);
      listEl.querySelectorAll("[data-work-field-row]").forEach((row) => {
        const typeEl = row.querySelector("[data-work-field-type]");
        const nameEl = row.querySelector("[data-work-field-name]");
        const valueEl = row.querySelector("[data-work-field-value]");
        const removeEl = row.querySelector("[data-work-field-remove]");
        const updateFromRow = () => syncWorkFieldsFromDom();
        if (typeEl) {
          typeEl.addEventListener("change", () => {
            syncWorkFieldsFromDom();
            rerenderWorkFields();
          });
          typeEl.addEventListener("input", updateFromRow);
        }
        if (nameEl) {
          nameEl.addEventListener("input", updateFromRow);
        }
        if (valueEl) {
          valueEl.addEventListener("input", updateFromRow);
        }
        if (removeEl) {
          removeEl.addEventListener("click", () => {
            const index = Number(row.getAttribute("data-work-field-row") || "0");
            workFieldState.splice(index, 1);
            rerenderWorkFields();
            commitWorkFieldState();
          });
        }
      });
    };
    const addWorkField = () => {
      workFieldState = [...workFieldState, createDefaultWorkField(workFieldState)];
      rerenderWorkFields();
      commitWorkFieldState();
    };
    return {
      getFields: () => workFieldState,
      renderRows: () => renderWorkFieldRows(workFieldState, typeOptions),
      syncWorkFieldsFromDom,
      rerenderWorkFields,
      addWorkField
    };
  }
  function renderEditPanel({
    editPanel,
    editRequested: editRequested2,
    config,
    status,
    contentTargetLabel,
    onTitleInput,
    onDescriptionInput,
    onWorkFieldsInput,
    onMediaInput,
    onSubmit,
    onToggleDrawer,
    onOpenLayoutPage,
    onReturnToWork,
    onSubmitLayout,
    onLayoutPresetChange,
    onUploadFiles,
    onCreateBlankFile,
    onCreateFolder,
    onResetFolderWork,
    onDeleteTarget
  }) {
    if (!editPanel) {
      syncPromptDock();
      return { statusEl: null, promptRoot: null };
    }
    if (!editRequested2) {
      editPanel.hidden = true;
      syncPromptDock();
      return { statusEl: null, promptRoot: null };
    }
    editPanel.hidden = false;
    if (!config || (status == null ? void 0 : status.error)) {
      const message = (status == null ? void 0 : status.error) || "Edit mode is unavailable.";
      editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">${escapeHtml(message)}</div>
        `;
      syncPromptDock();
      return { statusEl: null, promptRoot: null };
    }
    if (!(status == null ? void 0 : status.allowed)) {
      editPanel.innerHTML = `
            <h3 class="edit-panel-title">Edit mode</h3>
            <div class="edit-status">Create <code>.edit.allow</code> in this folder or an ancestor to enable edit mode. Add <code>edit.not-allow</code> to stop inheritance in a subtree.</div>
        `;
      syncPromptDock();
      return { statusEl: null, promptRoot: null };
    }
    if ((status == null ? void 0 : status.target) === "layout") {
      return renderEditLayoutPanel({
        editPanel,
        config,
        status,
        contentTargetLabel,
        onSubmitLayout,
        onLayoutPresetChange,
        onReturnToWork,
        onUploadFiles,
        onCreateBlankFile,
        onCreateFolder
      });
    }
    const label = (status == null ? void 0 : status.target) === "file" ? "Edit mode (file)" : "Edit mode (folder)";
    const settings = loadPromptSettings();
    const overlayState = layoutOverlayState(config, status);
    const treeItems = Array.isArray(config.tree) ? config.tree : [];
    const isFileTarget = (status == null ? void 0 : status.target) === "file";
    const isEmptyFolder = !isFileTarget && treeItems.length === 0;
    const initialWorkFields = extractWorkFields((config == null ? void 0 : config.work) || {}).map((field, index) => applyWorkFieldTypeDefaults(normalizeWorkField(field, index), field.type));
    const workFieldEditor = createWorkFieldEditor({
      editPanel,
      onWorkFieldsInput,
      initialState: initialWorkFields
    });
    const uploadSectionHtml = renderUploadSectionHtml({
      isFileTarget,
      isEmptyFolder
    });
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
            ${(status == null ? void 0 : status.target) === "file" || (status == null ? void 0 : status.target) === "folder" || (config == null ? void 0 : config.work) && typeof config.work === "object" && Object.keys(config.work).some((key) => !RESERVED_WORK_CONFIG_KEYS.has(key) || key === "type") ? renderWorkConfigFieldsSection(config) : ""}
            <div class="edit-work-fields">
                <div class="edit-work-fields-header">
                    <div>
                        <div class="edit-work-fields-title">Work fields</div>
                        <div class="small-note">Add extra values below Description. Saved as <code>work.&lt;name&gt;</code> and shown in prompt context.</div>
                    </div>
                    <button class="btn btn-secondary edit-work-fields-add" type="button" id="editWorkFieldAdd" aria-label="Add work field">+</button>
                </div>
                <div class="edit-work-fields-list" id="editWorkFieldsList">
                    ${workFieldEditor.renderRows()}
                </div>
            </div>
            <div class="edit-inline-actions">
                <button class="btn" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" id="editMoreToggle">More...</button>
                ${typeof onDeleteTarget === "function" ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editDeleteTarget">
                    <img class="block" src="https://cdn.jsdelivr.net/npm/heroicons@2.2.0/24/outline/trash.svg" alt="" width="16" height="16">
                    Delete
                </button>
                ` : ""}
            </div>
        </form>
        <div class="edit-layout-launch">
            <div class="edit-layout-copy">
                <div class="edit-layout-title">Layout</div>
                <div class="small-note">${escapeHtml(overlayState.wrapperSourceLabel)}</div>
                <div class="small-note">Inherited parent layout: <code>${escapeHtml(overlayState.inheritedLayoutLabel)}</code></div>
                <div class="small-note">Current mode: <code>${escapeHtml(overlayState.displayMode)}</code></div>
            </div>
            <div class="edit-inline-actions">
                ${!isFileTarget && typeof onResetFolderWork === "function" ? `
                <button class="btn border border-red-200 bg-red-50 text-red-700 hover:bg-red-100" type="button" id="editResetFolderWork">
                    Reset work
                </button>
                ` : ""}
                <button class="btn btn-secondary" type="button" id="editChangeLayout">Change layout</button>
            </div>
        </div>
        ${uploadSectionHtml}
        ${renderPromptWindow(settings, { mode: isFileTarget ? "file" : "folder" })}
    `;
    const form = editPanel.querySelector("#inlineEditForm");
    const statusEl = editPanel.querySelector("#editInlineStatus");
    const moreToggle = editPanel.querySelector("#editMoreToggle");
    const deleteTargetButton = editPanel.querySelector("#editDeleteTarget");
    const resetFolderWorkButton = editPanel.querySelector("#editResetFolderWork");
    const changeLayoutButton = editPanel.querySelector("#editChangeLayout");
    const titleInput = editPanel.querySelector("#edit-title");
    const descInput = editPanel.querySelector("#edit-description");
    const addWorkFieldButton = editPanel.querySelector("#editWorkFieldAdd");
    const promptRoot = editPanel.querySelector("#promptLayer");
    syncPromptDock(promptRoot);
    const syncMediaState = () => {
      if (typeof onMediaInput !== "function" || !form) {
        return;
      }
      onMediaInput(readMediaConfigFromForm(form, (config == null ? void 0 : config.work) || {}));
    };
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
    if (addWorkFieldButton) {
      addWorkFieldButton.addEventListener("click", () => {
        workFieldEditor.addWorkField();
      });
    }
    editPanel.querySelectorAll("[data-work-config-field], #edit-work-type").forEach((input) => {
      if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) {
        return;
      }
      input.addEventListener("input", syncMediaState);
      input.addEventListener("change", syncMediaState);
    });
    bindWorkCategoryControls({
      editPanel,
      onMediaInput
    });
    if (form && typeof onSubmit === "function") {
      form.addEventListener("submit", (event) => {
        event.preventDefault();
        workFieldEditor.syncWorkFieldsFromDom();
        onSubmit({
          elements: form.elements,
          form,
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
    if (resetFolderWorkButton && typeof onResetFolderWork === "function") {
      resetFolderWorkButton.addEventListener("click", async () => {
        const confirmed = window.prompt("Type rm -rf to reset this folder work to the default inherited layout. This removes the local .layout override.");
        if ((confirmed || "").trim() !== "rm -rf") {
          return;
        }
        await onResetFolderWork({ statusEl });
      });
    }
    if (deleteTargetButton && typeof onDeleteTarget === "function") {
      deleteTargetButton.addEventListener("click", async () => {
        const confirmed = window.confirm("Delete this item? This cannot be undone.");
        if (!confirmed) {
          return;
        }
        await onDeleteTarget({ statusEl });
      });
    }
    bindUploadDialog({
      editPanel,
      statusEl,
      uploadLimits: (status == null ? void 0 : status.uploadLimits) || null,
      onUploadFiles,
      onCreateBlankFile,
      onCreateFolder
    });
    return { statusEl, promptRoot };
  }

  // src/assets/js/edit/controller/layout.js
  function createLayoutNameForPreset(editConfig) {
    return (layoutPreset = "actual", sharedLayoutName = "") => {
      var _a, _b, _c, _d, _e;
      const preset = String(layoutPreset || "actual").trim() === "inherit" ? "actual" : String(layoutPreset || "actual").trim();
      if (preset === "none") {
        return "none";
      }
      if (preset === "shared") {
        return String(sharedLayoutName || ((_b = (_a = editConfig == null ? void 0 : editConfig.work) == null ? void 0 : _a.layout) == null ? void 0 : _b.sharedName) || ((_d = (_c = editConfig == null ? void 0 : editConfig.work) == null ? void 0 : _c.layout) == null ? void 0 : _d.name) || "poff-layout").trim() || "poff-layout";
      }
      if (preset === "custom") {
        return "custom-layout";
      }
      const currentLayout = (_e = editConfig == null ? void 0 : editConfig.work) == null ? void 0 : _e.layout;
      const hasFilesystemSource = !!(currentLayout && typeof currentLayout === "object" && (currentLayout.storage === "filesystem" || typeof currentLayout.directory === "string" && currentLayout.directory.trim() !== "" || typeof currentLayout.inheritedDirectory === "string" && currentLayout.inheritedDirectory.trim() !== ""));
      return hasFilesystemSource ? "filesystem-layout" : "poff-layout";
    };
  }
  function buildLayoutPayload(payload, layoutNameForPreset) {
    var _a, _b, _c, _d, _e, _f, _g, _h;
    const rawLayoutPreset = (payload.layoutPreset || "actual").trim();
    const layoutPreset = rawLayoutPreset === "inherit" ? "actual" : rawLayoutPreset;
    const layoutPayload = {
      name: layoutNameForPreset(layoutPreset, payload.layoutSharedName || ""),
      engine: "lightncandy",
      preset: layoutPreset
    };
    if (layoutPreset === "shared") {
      layoutPayload.source = "shared";
      layoutPayload.sharedName = payload.layoutSharedName || layoutPayload.name;
    }
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
    const hasOriginalDraftWrite = Object.prototype.hasOwnProperty.call(payload, "originalLayoutTemplate") || Object.prototype.hasOwnProperty.call(payload, "originalLayoutCss") || Object.prototype.hasOwnProperty.call(payload, "originalLayoutJs");
    if (Object.prototype.hasOwnProperty.call(payload, "originalLayoutTarget") && hasOriginalDraftWrite) {
      layoutPayload.originalTarget = (_e = payload.originalLayoutTarget) != null ? _e : "";
      layoutPayload.originalTemplate = (_f = payload.originalLayoutTemplate) != null ? _f : "";
      layoutPayload.originalCss = (_g = payload.originalLayoutCss) != null ? _g : "";
      layoutPayload.originalJs = (_h = payload.originalLayoutJs) != null ? _h : "";
    }
    return { layoutPreset, layoutPayload };
  }

  // src/assets/js/edit/controller/paths.js
  function getContentTargetPath(selection2 = getActiveSelection()) {
    if (selection2 == null ? void 0 : selection2.isLayout) {
      return selection2.path || "";
    }
    const previewPath = (selection2 == null ? void 0 : selection2.previewPath) || (selection2 == null ? void 0 : selection2.path) || "";
    if (selection2 == null ? void 0 : selection2.previewIsFile) {
      return previewPath.split("/").slice(0, -1).join("/");
    }
    return previewPath;
  }
  function getEditTargetPath(selection2 = getActiveSelection()) {
    if (selection2 == null ? void 0 : selection2.isLayout) {
      return selection2.path || "";
    }
    if (selection2 == null ? void 0 : selection2.previewIsFile) {
      const activeFileLink = document.querySelector("#navList a.nav-link-active[data-path]");
      const navPath = ((activeFileLink == null ? void 0 : activeFileLink.getAttribute("data-path")) || "").trim();
      if (navPath) {
        return navPath;
      }
    }
    return (selection2 == null ? void 0 : selection2.previewPath) || (selection2 == null ? void 0 : selection2.path) || "";
  }

  // src/assets/js/edit/controller.js
  function readMediaConfigFromElements(elements2, form, currentWork = {}) {
    const nextWork = { ...currentWork && typeof currentWork === "object" ? currentWork : {} };
    const typeField = elements2.work_type;
    if (typeField && typeof typeField.value === "string") {
      const type = typeField.value.trim();
      if (type) {
        nextWork.type = type;
      }
    }
    const configFields = (form == null ? void 0 : form.querySelectorAll("[data-work-config-field]")) || [];
    configFields.forEach((field) => {
      if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
        return;
      }
      const key = String(field.dataset.workConfigKey || "").trim();
      if (!key) {
        return;
      }
      const kind = String(field.dataset.workConfigKind || "text").trim();
      const isNullable = field.dataset.workConfigNullable === "true";
      if (field instanceof HTMLInputElement && field.type === "checkbox") {
        nextWork[key] = !!field.checked;
        return;
      }
      const rawValue = field.value;
      if (kind === "number") {
        nextWork[key] = rawValue === "" ? null : Number(rawValue);
        return;
      }
      if (kind === "json") {
        const trimmed2 = String(rawValue || "").trim();
        if (trimmed2 === "") {
          nextWork[key] = null;
          return;
        }
        try {
          nextWork[key] = JSON.parse(trimmed2);
        } catch (e) {
          nextWork[key] = trimmed2;
        }
        return;
      }
      const trimmed = String(rawValue || "").trim();
      if (isNullable && trimmed === "") {
        nextWork[key] = null;
        return;
      }
      nextWork[key] = rawValue;
    });
    return nextWork;
  }
  function createEditController({ elements: elements2, context, editRequested: editRequested2 }) {
    const { editPanel, editDrawer, editToggle } = elements2;
    const currentPoffConfig = Object.prototype.hasOwnProperty.call(context, "currentPoffConfig") ? context.currentPoffConfig : null;
    let folderConfig = currentPoffConfig;
    let editConfig = currentPoffConfig;
    let editTarget = "folder";
    let drawerOpen = false;
    function annotateConfigPath(config, selection2 = getActiveSelection(), status = {}) {
      if (!config || typeof config !== "object") {
        return config;
      }
      const relativePath = (selection2 == null ? void 0 : selection2.previewPath) || (selection2 == null ? void 0 : selection2.path) || (context == null ? void 0 : context.currentPathForIframe) || "";
      const isFile = (status == null ? void 0 : status.subjectTarget) === "file" || (status == null ? void 0 : status.target) === "file" || (selection2 == null ? void 0 : selection2.previewIsFile) === true;
      Object.defineProperties(config, {
        __poffRelativePath: {
          value: relativePath,
          configurable: true
        },
        __poffIsFile: {
          value: isFile,
          configurable: true
        }
      });
      return config;
    }
    annotateConfigPath(folderConfig, getActiveSelection(), { target: "folder" });
    annotateConfigPath(editConfig, getActiveSelection(), { target: editTarget });
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
      var _a, _b;
      try {
        setStatusMessage(statusEl, "Saving...");
        const data = await requestEditConfig("save", payload);
        if (!data || data.error) {
          throw new Error((data == null ? void 0 : data.error) || "Save failed.");
        }
        editConfig = annotateConfigPath(data.config || editConfig, getActiveSelection(), data);
        editTarget = data.target || editTarget;
        if (editTarget === "folder" || editTarget === "layout" && data.subjectTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
        setStatusMessage(statusEl, "Config saved.", true);
        window.dispatchEvent(new CustomEvent("poff:content-updated", {
          detail: {
            path: data.routePath || (payload == null ? void 0 : payload.path) || "",
            slug: data.routeSlug || ((_a = data.config) == null ? void 0 : _a.slug) || "",
            routePath: data.routePath || (payload == null ? void 0 : payload.path) || "",
            routeSlug: data.routeSlug || ((_b = data.config) == null ? void 0 : _b.slug) || "",
            target: editTarget,
            subjectTarget: data.subjectTarget || editTarget
          }
        }));
        return data.config;
      } catch (err) {
        setStatusMessage(statusEl, err.message || "Save failed.");
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
    async function refreshCurrentEditState(selection2 = getActiveSelection()) {
      const refreshed = await requestEditConfig("config", { path: getEditTargetPath(selection2) });
      if (refreshed == null ? void 0 : refreshed.config) {
        editConfig = annotateConfigPath(refreshed.config, selection2, refreshed);
        editTarget = refreshed.target || (selection2.isLayout ? "layout" : selection2.previewIsFile ? "file" : "folder");
        if (editTarget === "folder" || editTarget === "layout" && refreshed.subjectTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
      }
      renderEditUI(editConfig, {
        allowed: (refreshed == null ? void 0 : refreshed.allowed) !== false,
        error: refreshed == null ? void 0 : refreshed.error,
        target: (refreshed == null ? void 0 : refreshed.target) || editTarget,
        subjectTarget: refreshed == null ? void 0 : refreshed.subjectTarget,
        uploadLimits: refreshed == null ? void 0 : refreshed.uploadLimits
      });
    }
    function renderEditUI(config, status) {
      const layoutNameForPreset = createLayoutNameForPreset(editConfig);
      const panelState = renderEditPanel({
        editPanel,
        editRequested: editRequested2,
        config,
        status,
        contentTargetLabel: getContentTargetPath(),
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
        onWorkFieldsInput: (fields) => {
          if (!editConfig) {
            return;
          }
          const currentWork = editConfig.work && typeof editConfig.work === "object" ? editConfig.work : {};
          editConfig.work = materializeWorkFields(currentWork, fields);
          if ((status == null ? void 0 : status.target) !== "file") {
            folderConfig = editConfig;
            renderFolderMeta();
          }
        },
        onMediaInput: (mediaState) => {
          if (!editConfig || !mediaState || typeof mediaState !== "object") {
            return;
          }
          const currentWork = editConfig.work && typeof editConfig.work === "object" ? editConfig.work : {};
          editConfig.work = materializeWorkFields({ ...currentWork, ...mediaState });
        },
        onSubmit: async ({ elements: elements3, statusEl }) => {
          var _a, _b;
          const selection2 = getActiveSelection();
          const currentWork = (editConfig == null ? void 0 : editConfig.work) && typeof editConfig.work === "object" ? editConfig.work : {};
          const form = (elements3 == null ? void 0 : elements3.form) || null;
          const mediaWork = readMediaConfigFromElements(elements3, form, currentWork);
          const payload = {
            path: getEditTargetPath(selection2),
            title: (((_a = elements3.title) == null ? void 0 : _a.value) || "").trim(),
            description: (((_b = elements3.description) == null ? void 0 : _b.value) || "").trim()
          };
          if ((editConfig == null ? void 0 : editConfig.work) && typeof editConfig.work === "object") {
            payload.work = materializeWorkFields(mediaWork);
          }
          await saveConfig(payload, statusEl);
        },
        onToggleDrawer: () => {
          drawerOpen = !drawerOpen;
          syncDrawerVisibility();
        },
        onOpenLayoutPage: () => {
          var _a;
          const selection2 = getActiveSelection();
          const nextPath = buildVirtualLayoutPath((_a = selection2.previewPath) != null ? _a : selection2.path);
          drawerOpen = false;
          syncDrawerVisibility();
          window.location.hash = `#/${nextPath}`;
        },
        onDeleteTarget: async ({ statusEl }) => {
          const selection2 = getActiveSelection();
          const targetPath = getEditTargetPath(selection2);
          if (!targetPath) {
            throw new Error("Delete target unavailable.");
          }
          const data = await requestEditDelete({
            path: targetPath,
            return: selection2.previewPath || selection2.path || ""
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Delete failed.");
          }
          drawerOpen = false;
          syncDrawerVisibility();
          setStatusMessage(statusEl, "Deleted.", true);
          window.dispatchEvent(new CustomEvent("poff:content-updated"));
          const nextPath = selection2.previewPath ? selection2.previewPath.split("/").slice(0, -1).join("/") : "";
          window.location.hash = nextPath ? `#/${nextPath}` : "";
          await refreshCurrentEditState(getActiveSelection());
        },
        onResetFolderWork: async ({ statusEl }) => {
          const selection2 = getActiveSelection();
          const targetPath = getEditTargetPath(selection2);
          if (!targetPath) {
            throw new Error("Reset target unavailable.");
          }
          const data = await requestEditReset({
            path: targetPath,
            return: selection2.previewPath || selection2.path || ""
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Reset failed.");
          }
          drawerOpen = false;
          syncDrawerVisibility();
          setStatusMessage(statusEl, "Folder work reset to default.", true);
          window.dispatchEvent(new CustomEvent("poff:content-updated"));
          await refreshCurrentEditState(getActiveSelection());
        },
        onReturnToWork: () => {
          const selection2 = getActiveSelection();
          const nextPath = selection2.previewPath || "";
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
          const selection2 = getActiveSelection();
          const { layoutPayload } = buildLayoutPayload(payload, layoutNameForPreset);
          await saveConfig({
            path: getEditTargetPath(selection2),
            layout: layoutPayload
          }, statusEl);
        },
        onLayoutPresetChange: async ({ payload, statusEl }) => {
          const layoutPreset = ((payload == null ? void 0 : payload.layoutPreset) || "actual").trim() === "inherit" ? "actual" : ((payload == null ? void 0 : payload.layoutPreset) || "actual").trim();
          await saveConfig({
            path: getEditTargetPath(getActiveSelection()),
            layout: {
              name: layoutNameForPreset(layoutPreset, (payload == null ? void 0 : payload.layoutSharedName) || ""),
              engine: "lightncandy",
              preset: layoutPreset,
              ...layoutPreset === "shared" ? {
                source: "shared",
                sharedName: (payload == null ? void 0 : payload.layoutSharedName) || layoutNameForPreset(layoutPreset, (payload == null ? void 0 : payload.layoutSharedName) || "")
              } : {}
            }
          }, statusEl);
        },
        onUploadFiles: async ({ source, files }) => {
          const selection2 = getActiveSelection();
          const data = await requestEditUpload({
            path: getContentTargetPath(selection2),
            source,
            files
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Upload failed.");
          }
          await refreshCurrentEditState(selection2);
          const inlineStatus = document.getElementById("editInlineStatus");
          if (inlineStatus) {
            const count = Array.isArray(data.uploaded) ? data.uploaded.length : 0;
            setStatusMessage(inlineStatus, count === 1 ? "Uploaded 1 file." : `Uploaded ${count} files.`, true);
          }
          window.dispatchEvent(new CustomEvent("poff:content-updated"));
        },
        onCreateBlankFile: async ({ source, fileName }) => {
          var _a;
          const selection2 = getActiveSelection();
          const data = await requestEditUpload({
            path: getContentTargetPath(selection2),
            source,
            fileName,
            files: []
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Create blank file failed.");
          }
          await refreshCurrentEditState(selection2);
          const inlineStatus = document.getElementById("editInlineStatus");
          if (inlineStatus) {
            const createdName = Array.isArray(data.uploaded) && ((_a = data.uploaded[0]) == null ? void 0 : _a.name) ? data.uploaded[0].name : fileName;
            setStatusMessage(inlineStatus, `Created ${createdName}.`, true);
          }
          window.dispatchEvent(new CustomEvent("poff:content-updated"));
        },
        onCreateFolder: async ({ source, folderName }) => {
          var _a;
          const selection2 = getActiveSelection();
          const data = await requestEditUpload({
            path: getContentTargetPath(selection2),
            source,
            fileName: folderName,
            files: []
          });
          if (!data || data.error) {
            throw new Error((data == null ? void 0 : data.error) || "Create folder failed.");
          }
          await refreshCurrentEditState(selection2);
          const inlineStatus = document.getElementById("editInlineStatus");
          if (inlineStatus) {
            const createdName = Array.isArray(data.uploaded) && ((_a = data.uploaded[0]) == null ? void 0 : _a.name) ? data.uploaded[0].name : folderName;
            setStatusMessage(inlineStatus, `Created folder ${createdName}.`, true);
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
        onSubmit: async ({ elements: elements3, drawerForm, statusEl, treeVisible }) => {
          var _a, _b, _c;
          const selection2 = getActiveSelection();
          const templateField = elements3.work_template || elements3.work_type;
          const selectedTemplateOption = (templateField == null ? void 0 : templateField.selectedOptions) && templateField.selectedOptions[0] ? templateField.selectedOptions[0] : null;
          const selectedTemplate = ((templateField == null ? void 0 : templateField.value) || "").trim();
          const selectedKind = (((_a = selectedTemplateOption == null ? void 0 : selectedTemplateOption.dataset) == null ? void 0 : _a.kind) || selectedTemplate || "").trim();
          const templateMap = {};
          if (drawerForm) {
            drawerForm.querySelectorAll("select[data-template-map-mime]").forEach((select) => {
              const mime = String(select.dataset.templateMapMime || "").trim();
              if (!mime) {
                return;
              }
              const selectedValue = String(select.value || "").trim();
              const baselineValue = String(select.dataset.templateMapSelected || "").trim();
              if (selectedValue === baselineValue) {
                return;
              }
              templateMap[mime] = selectedValue;
            });
          }
          const payload = {
            path: getEditTargetPath(selection2),
            link: (((_b = elements3.link) == null ? void 0 : _b.value) || "").trim(),
            url: (((_c = elements3.url) == null ? void 0 : _c.value) || "").trim(),
            work: {
              type: selectedKind,
              template: selectedTemplate
            }
          };
          if (Object.keys(templateMap).length > 0) {
            payload.work.templateMap = templateMap;
          }
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
      const selection2 = getActiveSelection();
      const data = await requestEditConfig("config", { path: getEditTargetPath(selection2) });
      if (data.config) {
        editConfig = annotateConfigPath(data.config, selection2, data);
        editTarget = data.target || (selection2.isFile ? "file" : "folder");
        if (editTarget === "folder" || editTarget === "layout" && data.subjectTarget === "folder") {
          folderConfig = editConfig;
          renderFolderMeta();
        }
      }
      renderEditUI(editConfig, {
        allowed: data.allowed !== false,
        error: data.error,
        target: editTarget,
        subjectTarget: data.subjectTarget,
        uploadLimits: data.uploadLimits
      });
    }
    return {
      renderFolderMeta,
      syncEditToggle,
      bindEditToggle,
      initEditMode
    };
  }

  // src/assets/js/nav/preview-helpers.js
  function previewStateFromUrl(url) {
    try {
      const parsed = new URL(url, window.location.href);
      const isFile = parsed.searchParams.get("view") === "1" && parsed.searchParams.has("file");
      const path = parsed.searchParams.get(isFile ? "file" : "path") || "";
      return {
        key: `${isFile ? "file" : "path"}:${path}`,
        path,
        isFile
      };
    } catch (err) {
      return {
        key: "",
        path: "",
        isFile: false
      };
    }
  }
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
  function scopePreviewRootSelector(selector = "") {
    const trimmed = selector.trim();
    if (trimmed === "body") {
      return "#contentFrame > .viewer";
    }
    if (trimmed === "html") {
      return "#contentFrame";
    }
    if (trimmed === "html body" || trimmed === "body html") {
      return "#contentFrame > .viewer";
    }
    if (trimmed.startsWith("body.")) {
      return `#contentFrame > .viewer${trimmed.slice("body".length)}`;
    }
    if (trimmed.startsWith("html.")) {
      return `#contentFrame${trimmed.slice("html".length)}`;
    }
    return selector;
  }
  function scopePreviewStyleText(css = "") {
    return String(css || "").replace(/(^|})\s*([^@{}][^{]*)\{/g, (match, prefix, selectorList) => {
      const scopedSelectors = selectorList.split(",").map(scopePreviewRootSelector).join(", ");
      return `${prefix} ${scopedSelectors} {`;
    });
  }
  function normalizePreviewStyleNode(node) {
    if (!(node instanceof HTMLStyleElement)) {
      return node.outerHTML;
    }
    const clone = node.cloneNode(true);
    clone.textContent = scopePreviewStyleText(node.textContent || "");
    return clone.outerHTML;
  }

  // src/assets/js/nav/preview-controller.js
  function createPreviewController({
    contentFrame,
    iframeLoading,
    initialQueryPath = "",
    navigateToPath,
    setLoadingVisible,
    getCurrentSelection
  }) {
    let previewRequestId = 0;
    let previewClickBound = false;
    let previewDisabled = false;
    let lastPreviewKey = "";
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
    function syncPreviewDisabledState(disabled = false) {
      if (!contentFrame) {
        return;
      }
      previewDisabled = !!disabled;
      contentFrame.setAttribute("aria-disabled", previewDisabled ? "true" : "false");
      contentFrame.dataset.disabled = previewDisabled ? "true" : "false";
      if (!previewDisabled) {
        return;
      }
      contentFrame.querySelectorAll('a, button, input, select, textarea, summary, iframe, video, audio, [contenteditable="true"], [tabindex]').forEach((node) => {
        if (node instanceof HTMLElement) {
          node.setAttribute("tabindex", "-1");
          node.setAttribute("aria-disabled", "true");
        }
        if ("disabled" in node) {
          try {
            node.disabled = true;
          } catch (err) {
          }
        }
        if (node instanceof HTMLMediaElement) {
          node.controls = false;
        }
        if (node instanceof HTMLDetailsElement) {
          node.open = false;
        }
      });
    }
    function extractFallbackAnchorPath(anchor) {
      if (!anchor) {
        return null;
      }
      const currentSelection = (getCurrentSelection == null ? void 0 : getCurrentSelection()) || getSelectionFromPath(initialQueryPath || "");
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
        if (previewDisabled || contentFrame.getAttribute("aria-disabled") === "true") {
          event.preventDefault();
          event.stopPropagation();
          return;
        }
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
      var _a;
      if (!contentFrame) {
        return;
      }
      const requestId = ++previewRequestId;
      const nextPreview = previewStateFromUrl(url);
      const shouldPreserveScroll = !!nextPreview.key && nextPreview.key === lastPreviewKey;
      const preservedScrollTop = shouldPreserveScroll ? contentFrame.scrollTop : 0;
      const preservedScrollLeft = shouldPreserveScroll ? contentFrame.scrollLeft : 0;
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
        const scripts = Array.from(doc.querySelectorAll("script"));
        doc.querySelectorAll('style, link[rel="stylesheet"]').forEach((node) => {
          fragments.push(normalizePreviewStyleNode(node));
        });
        (_a = doc.body) == null ? void 0 : _a.querySelectorAll("style").forEach((node) => {
          node.textContent = scopePreviewStyleText(node.textContent || "");
        });
        scripts.forEach((node) => node.remove());
        const bodyHtml = doc.body ? doc.body.innerHTML : html;
        contentFrame.innerHTML = `${fragments.join("")}${bodyHtml}`;
        scripts.forEach((oldScript) => {
          const script = document.createElement("script");
          for (const attribute of oldScript.attributes) {
            script.setAttribute(attribute.name, attribute.value);
          }
          script.textContent = oldScript.textContent || "";
          contentFrame.appendChild(script);
        });
        syncPreviewDisabledState(previewDisabled);
        bindPreviewNavigation();
        lastPreviewKey = nextPreview.key;
        if (shouldPreserveScroll) {
          const restoreScroll = () => {
            contentFrame.scrollTop = preservedScrollTop;
            contentFrame.scrollLeft = preservedScrollLeft;
          };
          restoreScroll();
          requestAnimationFrame(restoreScroll);
        } else {
          contentFrame.scrollTop = 0;
          contentFrame.scrollLeft = 0;
        }
      } catch (error) {
        if (requestId !== previewRequestId) {
          return;
        }
        contentFrame.innerHTML = '<div class="viewer-error">Preview failed to load.</div>';
      } finally {
        if (requestId === previewRequestId) {
          setLoadingVisible(iframeLoading, false);
        }
      }
    }
    return {
      bindPreviewNavigation,
      buildViewerUrl,
      renderPreview,
      syncPreviewDisabledState
    };
  }

  // src/assets/js/nav/path-helpers.js
  function normalizeHashPath(value = "") {
    return String(value || "").replace(/\\/g, "/").replace(/^#\/?/, "").replace(/^\/+|\/+$/g, "");
  }
  function normalizeHashAlias(value = "") {
    return normalizeHashPath(value).toLowerCase();
  }
  function routeResolution(path = "", isFile = inferFilePath(path)) {
    return {
      path,
      isFile
    };
  }
  function findNavLinkByAttribute(navList, attributeName, value = "") {
    if (!navList || !value) {
      return null;
    }
    const normalizedValue = normalizeHashAlias(value);
    for (const link of navList.querySelectorAll(`[${attributeName}]`)) {
      if (normalizeHashAlias(link.getAttribute(attributeName) || "") === normalizedValue) {
        return link;
      }
    }
    return null;
  }
  function navTargetPath(link) {
    if (!link) {
      return "";
    }
    return link.getAttribute("data-layout-path") || link.getAttribute("data-path") || link.getAttribute("data-src") || "";
  }
  function navTargetIsFile(link, path = "") {
    if (!link) {
      return inferFilePath(path);
    }
    if (link.hasAttribute("data-layout-path")) {
      return false;
    }
    if (link.hasAttribute("data-file") || link.hasAttribute("data-src")) {
      return true;
    }
    const href = link.getAttribute("href") || "";
    if (href.startsWith("?path=")) {
      return false;
    }
    return inferFilePath(path);
  }

  // src/assets/js/nav/route-resolver.js
  function createRouteResolver({ navList } = {}) {
    const slugToPathAliases = /* @__PURE__ */ new Map();
    const pathToSlugAliases = /* @__PURE__ */ new Map();
    function rememberSlugPathAlias(detail = {}) {
      const path = normalizeHashPath((detail == null ? void 0 : detail.routePath) || (detail == null ? void 0 : detail.path) || (detail == null ? void 0 : detail.relativePath) || "");
      const slug = normalizeHashPath((detail == null ? void 0 : detail.routeSlug) || (detail == null ? void 0 : detail.slug) || "");
      if (!path || !slug || slug.includes("/")) {
        return;
      }
      slugToPathAliases.set(normalizeHashAlias(slug), path);
      pathToSlugAliases.set(normalizeHashAlias(path), slug);
    }
    function resolveHashPath(path = "") {
      const normalizedPath = normalizeHashPath(path);
      const aliasPath = slugToPathAliases.get(normalizeHashAlias(normalizedPath));
      if (aliasPath) {
        return routeResolution(aliasPath);
      }
      if (!normalizedPath.includes("/")) {
        const link = findNavLinkByAttribute(navList, "data-slug", normalizedPath);
        const targetPath = navTargetPath(link);
        if (targetPath) {
          rememberSlugPathAlias({
            path: targetPath,
            slug: normalizedPath
          });
          return routeResolution(targetPath, navTargetIsFile(link, targetPath));
        }
      }
      return routeResolution(normalizedPath);
    }
    async function resolveHashPathAsync(path = "") {
      const resolved = resolveHashPath(path);
      const normalizedPath = normalizeHashPath(path);
      if (!normalizedPath || normalizedPath.includes("/") || normalizedPath === ".layout" || normalizedPath.endsWith("/.layout") || inferFilePath(normalizedPath) || resolved.path !== normalizedPath) {
        return resolved;
      }
      try {
        const response = await fetch(`?ajax=resolve&slug=${encodeURIComponent(normalizedPath)}`, {
          credentials: "same-origin",
          headers: {
            "Accept": "application/json"
          }
        });
        if (!response.ok) {
          return resolved;
        }
        const data = await response.json();
        if (!(data == null ? void 0 : data.resolved) || !data.path) {
          return resolved;
        }
        rememberSlugPathAlias({
          path: data.path,
          slug: data.slug || normalizedPath
        });
        return routeResolution(data.path, typeof data.isFile === "boolean" ? data.isFile : data.type !== "folder");
      } catch (err) {
        return resolved;
      }
    }
    function displayHashPath(path = "") {
      const normalizedPath = normalizeHashPath(path);
      if (!normalizedPath || normalizedPath.includes("/.layout") || normalizedPath === ".layout") {
        return normalizedPath;
      }
      const aliasSlug = pathToSlugAliases.get(normalizeHashAlias(normalizedPath));
      if (aliasSlug) {
        return aliasSlug;
      }
      const link = findNavLinkByAttribute(navList, "data-path", normalizedPath);
      const slug = (link == null ? void 0 : link.getAttribute("data-slug")) || "";
      if (slug && !slug.includes("/")) {
        rememberSlugPathAlias({
          path: normalizedPath,
          slug
        });
        return slug;
      }
      return normalizedPath;
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
    return {
      displayHashPath,
      readHashPath,
      rememberSlugPathAlias,
      resolveHashPath,
      resolveHashPathAsync
    };
  }

  // src/assets/js/nav/sidebar-controller.js
  function createSidebarController({
    navList,
    sidebarLoading,
    editQuery: editQuery2,
    navigateToPath,
    setLoadingVisible
  }) {
    let activeLink = null;
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
      if (!navList) {
        return;
      }
      navList.innerHTML = `
            <div id="navLoading" class="loading-row flex items-center">
                <span class="loader"></span>
                <span class="loader-label">Loading...</span>
            </div>
        `;
    }
    function loadNav(relPath = "") {
      if (!navList) {
        return Promise.resolve("");
      }
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
      } else if (target.dataset.path) {
        relPath = target.dataset.path;
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
    function bindNavClick() {
      if (!navList) {
        return;
      }
      navList.addEventListener("click", handleNavClick);
    }
    return {
      bindNavClick,
      loadNav,
      syncSidebarSelection
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
    let ignoreNextHashSync = false;
    const initialQueryPath = new URLSearchParams(window.location.search).get("path") || "";
    const routeResolver = createRouteResolver({ navList });
    function setLoadingVisible(element, visible) {
      if (!element) {
        return;
      }
      element.classList.toggle("flex", visible);
      element.classList.toggle("items-center", visible);
    }
    function readHashPath() {
      return routeResolver.readHashPath();
    }
    function getCurrentSelection() {
      return getSelectionFromPath(readHashPath() || initialQueryPath || "");
    }
    const previewController = createPreviewController({
      contentFrame,
      iframeLoading,
      initialQueryPath,
      navigateToPath,
      setLoadingVisible,
      getCurrentSelection
    });
    const sidebarController2 = createSidebarController({
      navList,
      sidebarLoading,
      editQuery: editQuery2,
      navigateToPath,
      setLoadingVisible
    });
    function writeHashPath(path = "") {
      const hashPath = routeResolver.displayHashPath(path);
      const nextHash = hashPath ? `#/${hashPath.replace(/^\/+/, "")}` : "";
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
    function navigateToPath(path = "", options = {}) {
      const resolved = routeResolver.resolveHashPath(path);
      const selection2 = getSelectionFromPath(resolved.path, {
        isFile: typeof (options == null ? void 0 : options.isFile) === "boolean" ? options.isFile : resolved.isFile
      });
      navigateToSelection(selection2, options);
    }
    function navigateToSelection(selectionInput, options = {}) {
      const selection2 = selectionInput && typeof selectionInput === "object" && Object.prototype.hasOwnProperty.call(selectionInput, "path") ? selectionInput : getSelectionFromPath(selectionInput || "");
      const {
        updateHash = true,
        forceRefresh = false
      } = options;
      const previewPath = selection2.previewPath || "";
      const previewIsFile = !!selection2.previewIsFile;
      const folderPath = previewIsFile ? previewPath.split("/").slice(0, -1).join("/") : previewPath;
      setLoadingVisible(iframeLoading, true);
      if (contentFrame) {
        contentFrame.classList.toggle("content-frame-layout-target", !!selection2.isLayout);
        previewController.syncPreviewDisabledState(!!selection2.isLayout);
        previewController.renderPreview(previewController.buildViewerUrl(previewPath, previewIsFile, forceRefresh));
      }
      if (updateHash) {
        writeHashPath(selection2.path || "");
      }
      if (navList) {
        setLoadingVisible(sidebarLoading, true);
        sidebarController2.loadNav(folderPath).then(() => {
          sidebarController2.syncSidebarSelection(selection2.path || previewPath, previewIsFile, !!selection2.isLayout);
          setLoadingVisible(sidebarLoading, false);
        }).catch(() => {
          sidebarController2.syncSidebarSelection(selection2.path || previewPath, previewIsFile, !!selection2.isLayout);
          setLoadingVisible(sidebarLoading, false);
        });
      }
      if (initEditMode) {
        initEditMode();
      }
    }
    function loadCurrentFolderInIframe() {
      var _a;
      const selection2 = getSelectionFromPath((_a = currentPathForIframe2 != null ? currentPathForIframe2 : initialQueryPath) != null ? _a : "");
      navigateToSelection(selection2, { updateHash: false });
      if (renderFolderMeta) {
        renderFolderMeta();
      }
    }
    async function syncFromLocation(options = {}) {
      var _a;
      const { forceRefresh = false } = options;
      const hashPath = readHashPath();
      if (hashPath || window.location.hash) {
        const resolved = await routeResolver.resolveHashPathAsync(hashPath);
        navigateToSelection(getSelectionFromPath(resolved.path, { isFile: resolved.isFile }), {
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
    if (navList) {
      sidebarController2.bindNavClick();
    }
    if (contentFrame) {
      previewController.bindPreviewNavigation();
    }
    window.POFF_RESOLVE_HASH_PATH = routeResolver.resolveHashPath;
    return {
      consumeHashSync() {
        if (!ignoreNextHashSync) {
          return false;
        }
        ignoreNextHashSync = false;
        return true;
      },
      loadCurrentFolderInIframe,
      refreshCurrentLocation,
      rememberSlugPathAlias: routeResolver.rememberSlugPathAlias,
      syncFromLocation,
      writeHashPath
    };
  }

  // src/assets/js/app/constants.js
  var APP_ELEMENT_IDS = {
    appShell: "appShell",
    appSidebar: "appSidebar",
    navList: "navList",
    contentFrame: "contentFrame",
    editPanel: "editPanel",
    editDrawer: "editDrawer",
    editToggle: "editToggle",
    sidebarToggle: "sidebarToggle",
    iframeLoading: "iframeLoading",
    sidebarLoading: "sidebarLoading"
  };
  var APP_HASHES = {
    legacyMcp: "#mcp",
    preview: "#preview"
  };
  var APP_LABELS = {
    openNavigation: "Open navigation",
    closeNavigation: "Close navigation"
  };

  // src/assets/js/app/helpers.js
  function redirectLegacyMcpHash() {
    if (window.location.hash !== APP_HASHES.legacyMcp) {
      return false;
    }
    const basePath = window.location.pathname.split("#")[0];
    window.location.href = `${basePath}?mcp=1`;
    return true;
  }
  function createAppElements() {
    return Object.fromEntries(
      Object.entries(APP_ELEMENT_IDS).map(([key, id]) => [key, document.getElementById(id)])
    );
  }
  function isPreviewHashActive() {
    return window.location.hash === APP_HASHES.preview;
  }
  function scrollToPreview() {
    const previewEl = document.getElementById("preview");
    if (!previewEl) {
      return;
    }
    previewEl.scrollIntoView({ block: "start" });
  }
  function bindSidebarToggle({ appShell, appSidebar, sidebarToggle }) {
    if (!appShell || !appSidebar || !sidebarToggle) {
      return null;
    }
    const syncSidebarState = (isOpen) => {
      appShell.classList.toggle("sidebar-collapsed", !isOpen);
      appSidebar.hidden = !isOpen;
      appSidebar.setAttribute("aria-hidden", isOpen ? "false" : "true");
      sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      sidebarToggle.setAttribute("aria-label", isOpen ? APP_LABELS.closeNavigation : APP_LABELS.openNavigation);
      sidebarToggle.setAttribute("title", isOpen ? APP_LABELS.closeNavigation : APP_LABELS.openNavigation);
    };
    syncSidebarState(true);
    const onToggleClick = () => {
      const isOpen = !appShell.classList.contains("sidebar-collapsed");
      syncSidebarState(!isOpen);
    };
    sidebarToggle.addEventListener("click", onToggleClick);
    return {
      syncSidebarState,
      destroy() {
        sidebarToggle.removeEventListener("click", onToggleClick);
      }
    };
  }

  // src/assets/js/app.js
  redirectLegacyMcpHash();
  var elements = createAppElements();
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
  var sidebarController = bindSidebarToggle(elements);
  document.addEventListener("DOMContentLoaded", async () => {
    if (sidebarController) {
      sidebarController.syncSidebarState(true);
    }
    editController.syncEditToggle();
    editController.bindEditToggle();
    if (isPreviewHashActive()) {
      navigation.loadCurrentFolderInIframe();
      requestAnimationFrame(() => scrollToPreview());
    } else if (window.location.hash && window.location.hash.length > 1) {
      await navigation.syncFromLocation();
    } else {
      navigation.loadCurrentFolderInIframe();
    }
    editController.initEditMode();
  });
  window.addEventListener("hashchange", async () => {
    if (isPreviewHashActive()) {
      scrollToPreview();
      if (editRequested) {
        editController.initEditMode();
      }
      return;
    }
    if (!navigation.consumeHashSync()) {
      await navigation.syncFromLocation();
    }
    if (editRequested) {
      editController.initEditMode();
    }
  });
  window.addEventListener("poff:content-updated", async (event) => {
    var _a, _b;
    navigation.rememberSlugPathAlias(event.detail || {});
    const nextPath = ((_a = event.detail) == null ? void 0 : _a.routePath) || ((_b = event.detail) == null ? void 0 : _b.path) || "";
    if (nextPath) {
      navigation.writeHashPath(nextPath);
    }
    await navigation.refreshCurrentLocation();
    if (editRequested) {
      editController.initEditMode();
    }
  });
})();
/* POFF_SCRIPT_END */
</script>
