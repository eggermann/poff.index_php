# Layout Runtime Architecture

This document describes the exact runtime layout flow for the PHP + HBS viewer.

## 1. Entry: Viewer Request

1. Request reaches `renderViewer($baseDir, $requestedPath)`.
2. Path is sanitized and resolved.
3. Branches to:
   - `renderFolderViewer(...)` for folders
   - `renderFileViewer(...)` for files

Files:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/render/entry.php`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/render/folder.php`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/render/file.php`

## 2. Config + Layout Hydration

Before rendering, config is loaded and layout is hydrated.

Folder path:
- `PoffConfig::ensure($dir)`

File path:
- `PoffConfig::ensureFileConfig($dir, $fileName)`

Both call:
- `hydrateConfigLayout(...)`
- `hydrateLayoutFilesystem(...)`

Hydration resolves:
- local folder layout: `.layout/*`
- local file layout: `.works/<filename>.layout/*`
- inherited parent layout: nearest parent `.layout/*`

Loaded mapping:
- `template.hbs` -> `work.layout.template`
- `style.css` -> `work.layout.css`
- `script.js` -> `work.layout.js`
- `work.hbs`/`works.hbs` -> `work.layout.sectionTemplate`

File:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/PoffConfig.php`

## 3. Special Mode: `none`

If layout mode is `none` (or preset `none`), hydration short-circuits.

Behavior:
- `.layout` and inherited layout files are ignored
- wrapper template/css/js are cleared
- section overrides are cleared
- storage becomes `none`

This ensures preview uses the direct inner content path instead of filesystem wrapper assets.

File:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/PoffConfig.php`

## 4. Worktype Rendering (HBS Composition)

Rendering is driven by `Worktype::render($kind, $ctx)`.

Core steps:
1. Normalize layout (`Worktype::normalizeLayout`).
2. Resolve outer wrapper via `layoutTemplate(...)`.
3. Build partial map (`templates()` plus optional `sectionTemplate`).
4. Compile/render through LightnCandy.

Wrapper behavior:
- `name/mode = none` -> wrapper is bypassed (`{{> work}}` or `{{> works}}`).
- otherwise wrapper template is used (filesystem or bundled preset).

File:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/Worktype.php`

## 5. Built-in Layout Sources

Bundled wrapper templates are discovered from:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/worktypes/templates/layout/default/`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/worktypes/templates/layout/file-system/`

Default wrapper files:
- `template.hbs`
- `style.css`
- `script.js`

## 6. Final HTML Shell Injection

`renderViewerShell(...)` wraps body content and injects layout CSS/JS.

Rules:
- if `cssHref/jsHref` exist, use linked assets
- else use inline `layout.css/layout.js`

File:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/render/shell.php`

## 7. Edit Mode Save Flow (Layout Authoring)

Frontend layout panel sends `layout` payload through edit API.

Frontend:
- builds payload with `name/mode/preset/template/css/js/sectionTemplate`

Backend (`?edit=save`):
1. parses layout payload
2. normalizes layout
3. persists through:
   - `persistLayoutFiles(...)` (wrapper + section)
   - or `persistSectionTemplate(...)` (section-only)
4. rehydrates config and returns it

Files:
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/assets/js/edit/controller.js`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/assets/js/edit/panel.js`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/edit.php`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/PoffConfig.php`

## 8. Quick Data Flow Summary

1. Request -> viewer entry (`entry.php`)
2. Config load (`PoffConfig::ensure*`)
3. Layout hydration (`hydrateLayoutFilesystem`)
4. Worktype render (`Worktype::render` + HBS partials)
5. Viewer shell injects layout CSS/JS (`shell.php`)
6. Browser displays merged layout + work content

## 9. Related Docs

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/docs/prompt-context-and-layout-css.md`
- `/Users/eggermann/Desktop/speedProjects/poff.index_php/README.md`
