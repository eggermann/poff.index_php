# Prompt Context And Layout CSS

This note explains where the prompt context in the edit UI comes from and where the visible layout CSS is resolved from.

## Two Different Things

There are two related but different data flows:

1. The prompt context shown in the browser UI
2. The prompt/context payload built on the server for the model

They overlap, but they are not the same object.

## UI Prompt Context

The visible `Prompt context` panel on the right side is rendered in the browser.

Main file:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/assets/js/edit/prompt/render.js`

Relevant functions:

- `buildPromptContext(...)`
- `renderPromptContext(...)`

`buildPromptContext(...)` uses:

- `getActiveSelection()`
- `getConfig()`

It builds UI-facing values such as:

- `path`
- `virtualPath`
- `templateTarget`
- `layoutTemplateTarget`
- `sectionTemplateTarget`
- `layoutBaseHref`
- `inheritedLayoutDirectory`
- `layoutAssetsPreview`
- `workData`
- `refPreview`

Important:

- `workData` in the UI comes directly from `config.work`
- if the UI shows `work.layout.css`, that value is coming from the already hydrated config

The UI panel is therefore a readable visualization, not the exact raw model prompt.

## Server Prompt Context

The actual prompt context for prompt requests is built server-side in:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/edit.php`

Relevant functions:

- `cmsBuildPromptContext(...)`
- `cmsPromptCompactContext(...)`
- `cmsPromptCompactConfig(...)`
- `cmsPromptHistoryText(...)`

### `cmsBuildPromptContext(...)`

This function builds the structured context object.

Main location:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/viewer/edit.php`

It fills `current` with values such as:

- `targetType`
- `subjectType`
- `layoutPreset`
- `sectionPartial`
- `name`
- `path`
- `virtualPath`
- `pageLink`
- `srcUrl`
- `templateTarget`
- `layoutTemplateTarget`
- `sectionTemplateTarget`
- `layoutBaseHref`
- `inheritedLayoutDirectory`
- `layoutSectionBaseHref`
- `layoutAssets`

For folders it also builds:

- `items`
- `allItems`
- `allFiles`
- `allFolders`
- `allImages`
- `allVideos`
- `allAudio`
- `allPdfs`
- `allTexts`
- `allLinks`
- `allOther`

### `cmsPromptCompactConfig(...)`

This function creates the compact config summary sent with prompt requests.

Important behavior:

- `config.work.layout.css` is not normally sent as full CSS text in the compact config
- instead the compact config records metadata such as `cssLength`
- the same is true for `template`, `sectionTemplate`, and `js`

So the server prompt typically receives:

- compact config summary
- compact prompt context
- prompt history
- user prompt

The UI may still show the full hydrated `work.layout.css` because it renders from `config.work`, not from the compact server payload.

## Where `config.work.layout.css` Comes From

The hydrated layout data comes from `PoffConfig`.

Main file:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/PoffConfig.php`

Relevant flow:

1. `PoffConfig::ensure(...)` for folders
2. `PoffConfig::ensureFileConfig(...)` for files
3. `hydrateConfigLayout(...)`
4. `hydrateLayoutFilesystem(...)`

### Exact CSS Load Point

The concrete place where layout CSS is loaded is:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/PoffConfig.php`

Inside `hydrateLayoutFilesystem(...)`:

- if a real layout directory is found
- and `style.css` exists there
- then its contents are loaded into `work.layout.css`

That means:

- `template.hbs` becomes `work.layout.template`
- `style.css` becomes `work.layout.css`
- `script.js` becomes `work.layout.js`
- `work.hbs` or `works.hbs` becomes `work.layout.sectionTemplate`

## Why Default Layout CSS Is Not Always The Source

This file exists:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/worktypes/templates/layout/default/style.css`

But it is only the bundled default preset asset.

It is not always the source of the CSS shown in prompt context.

There are two main cases:

### 1. Default/Preset Layout

If the layout is a preset layout, CSS can come from the bundled preset files under:

- `/Users/eggermann/Desktop/speedProjects/poff.index_php/src/includes/worktypes/templates/layout/default/`

This path is used through `Worktype::layoutBundleAsset(...)`.

### 2. Filesystem Layout

If the resolved layout uses filesystem storage, CSS comes from the actual layout directory of the current item:

For folders:

- `.layout/style.css`

For files:

- `.works/<filename>.layout/style.css`

Or from an inherited parent `.layout/style.css`.

In that case, the prompt context UI shows the hydrated filesystem CSS, not the bundled default preset CSS.

## `filesystem-layout` Does Not Mean A Built-In CSS Folder

`filesystem-layout` is a layout mode/name.

It means:

- resolve wrapper files from the real item layout directory

It does not mean:

- load CSS from a populated built-in template folder named `file-system`

So an empty preset folder for filesystem mode is not a contradiction.

The actual source for the CSS is usually one of:

- local `.layout/style.css`
- local `.works/<filename>.layout/style.css`
- inherited parent `.layout/style.css`

## Short Summary

- The visible prompt context panel is built in the browser from current selection plus hydrated config.
- The actual model prompt context is built separately on the server.
- The CSS shown in the UI prompt context usually comes from `config.work.layout.css`.
- `config.work.layout.css` is hydrated in `PoffConfig::hydrateLayoutFilesystem(...)`.

Related guide:

- [Prompt Layout Root And Work Vars](./prompt-layout-root-work-vars.md)
- If filesystem layout is active, the CSS comes from the real `.layout/style.css` or `.works/<file>.layout/style.css`, not necessarily from the bundled default preset CSS.
