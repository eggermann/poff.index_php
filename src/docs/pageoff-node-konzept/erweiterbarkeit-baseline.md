# PageOff extensibility baseline

Audit status: static source and test review, 20 November 2026. No runtime entrypoint or build was run for this audit. Paths below are repository-relative.

## Architecture decision

Worktypes, layouts and converters are first-class capability areas, but current code does not expose one shared registration lifecycle. Keep their contracts separate. Do not add a generic plugin manager. If explicit registration is needed later, begin with small capability-specific descriptors and preserve current filesystem definitions as adapters.

Keep these concepts distinct:

1. **Resource detection** identifies file kind, extension and MIME. It must not render or convert the resource.
2. **Rendering capability** maps a detected resource and work metadata to a Worktype template and render context.
3. **Layout package** supplies a wrapper, section partials and optional CSS/JS. It changes presentation, not Work identity or source bytes.
4. **Conversion capability** declares accepted input/output formats and performs an explicit transformation that may create a derived Work.
5. **Remote service capability** advertises a converter contract and endpoint. Discovery does not authorize local execution of provider code.

## Worktype and resource detection

`MediaType::classifyExtension()` classifies `.htaccess` specially, then uses fixed extension lists for image, video, audio, link, text and PDF; unknown extensions become `other`. `MediaType::detectMimeType()` returns `text/plain` for `.htaccess`, prefers the fixed extension-to-MIME map, then tries `finfo`, then returns `null`. A known extension therefore wins over file-content sniffing. Detection is separate from the selected rendering template. Example: `photo.jpg` → MIME `image/jpeg` and kind `image`.

The file viewer uses extension classification as its resource kind. A configured kind only changes this to `link` for link-like tree entries. For work metadata, a non-empty file-level `work` object is selected before the folder-level `work` object; defaults fill missing fields later.

Worktype definitions are loaded from `src/includes/worktypes/*.worktype.php`, normally through `src/includes/worktypes/worktypes.php`. Loader precedence is per-kind file definition, bundle definition, embedded definition, then `other` fallbacks, finally a minimal `{type: kind}` definition. Definitions carry model fields such as type, label, categories, layout and template. Worktype templates may be returned beside the model as `template`, stored in the bundle template map, or supplied as `.hbs` / `.tpl` files. There is no public `registerWorktype()` API. Adding a source definition file is the existing extension mechanism.

Template selection contract:

1. A valid explicit `work.template` overrides suggestions.
2. Otherwise `templateMap` selection wins.
3. Otherwise Worktype catalog suggestion is used, falling back to detected kind and then `other`.
4. Only keys in `Worktype::availableKinds()` are valid template keys. Unknown map values are discarded.

`templateMap` entries merge ancestor folder configs from root to child; local entries replace the same normalized key. Lookup candidate order is exact MIME, `major/*`, `major/.*`, detected kind, then filename extension. If none match exactly, remaining MIME patterns are scanned in normalized key order. `image/*` and `image/.*` both act as MIME wildcards. The final selected Worktype may enable the QuickTime/MOV autoplay default.

Worktype files can declare top-level `mimes`, `mimeTypes`, `extensions`, `suffixes`, `label`, `preferred` and `description`. These are now retained alongside model fields by the per-kind loader and additive `metadata` map in the bundle; explicit model fields win if duplicated. The Builder merges bundle metadata into embedded definitions and applies the same merge in its per-file fallback. The catalog matches exact MIME plus `/*` and `/.*` patterns; extensions match bare values and dotted suffixes. Characterization tests cover image MIME patterns, PDF metadata, `.htaccess` label/preference/suffix and bundle metadata serialization.

## Template and layout resolution

LightnCandy is provided by Composer at source runtime. The builder embeds its PHP renderer into the single-file artifact. `Worktype::render()` chooses a wrapper, builds context and partials, renders it, retries with the default wrapper on failure, then uses kind-specific fallback HTML if that also fails.

Wrapper resolution order:

1. `none` layout uses the built-in `work` or `works` partial directly.
2. Non-empty inline `layout.template`.
3. Matching shared package when layout metadata marks a shared package.
4. Template matching `layout.name`.
5. Default bundled wrapper; unknown names also fall through here.

The Work's `template` is normally a section partial, not a wrapper. If `layout.sectionTemplate` is empty, the renderer resolves `work.template` and installs it as the `work` or `works` partial for this render. A non-empty section partial replaces the matching partial from the shared partial map. If a custom wrapper lacks a matching partial, compatibility logic rewrites a lone work/works include to a file/folder conditional or inserts the active partial before a recognized closing element; unusual wrappers can therefore behave differently.

Named Worktype template lookup order is per-kind file template, bundle template, direct `.hbs`, direct `.tpl`, then embedded template. File templates and runtime filesystem layouts may therefore override bundle defaults. The source `templates()` aggregate has its own merge rules; do not assume its order is identical to `template(name)`.

Folder layout resolution uses local `.layout` first, then nearest ancestor `.layout`. A file may override through `.works/<filename>.layout`; otherwise it inherits nearest ancestor `.layout`. A per-file section partial is used only when that file also has local wrapper files; otherwise the parent section partial remains active. An implicit default child layout may inherit explicitly configured parent layout. Explicit `none` clears inherited wrapper, CSS, JS and section partial.

Shared layout package lookup checks filesystem layout collections before bundled/shared packages. A filesystem package needs `template.hbs`; `work.hbs` / `works.hbs`, `style.css` and `script.js` are optional. Converter packages use their own `.layout` files. Presentation overrides do not alter underlying resource path, MIME, bytes or Work identity.

Render context starts from derived fields and URL defaults; top-level context values override defaults, then scalar/boolean work values override top-level values. Work fields, category aliases, and item/tree URL fallbacks receive special normalization. Treat these names and precedence as a compatibility contract.

## Converter discovery, matching and execution

Converter definitions are JSON in a converter folder. Discovery checks `poff/converters`, `converters`, then `.converters`; later same-ID folder definitions overwrite earlier ones. Bundled converter grains and legacy static definitions fill IDs that are still absent. Folder basename determines normalized converter ID. Discovery is filesystem/static data; no generic converter or engine registration API exists.

`availableFor(mime, kind, extension)` infers MIME from extension when MIME is empty. Matching then prefers MIME matching exclusively when MIME is available. With no MIME, it checks accepted kind (`kind` or a `kind/...` pattern), then literal extension, then extension-derived MIME. MIME patterns support exact values and `type/*`. Matching and execution availability differ: `enabled=false` definitions can still match/list, while `canOffer()` and execution apply engine availability and enabled checks.

Current engines are explicit branches: `remote-node`, `text-copy`, and local image conversion (Imagick or configured ImageMagick CLI). There is no pluggable engine registry; unknown engine strings currently reach local image conversion. Treat this as current behavior to characterize, not as a desired extension contract. Output MIME must be in the converter's declared outputs. Successful conversion returns output metadata and Base64 bytes; save route writes a generated file beside the source and stores hidden/generated-work metadata.

MCP transport is an adapter boundary, even though business rules currently sit in `Converter` plus route helpers. List, convert, save and create routes use editor authorization and node-root path checks. Converter creation writes a visible converter folder, definition and starter `.layout` package. The viewer's converter preview uses a read-only viewer path.

Local converter definitions and packages execute on their own node. Remote discovery uses the `poff-converter/1` `converter-offers` catalog; `convert-offered` asks the remote node to execute a converter it offers. Offers contain descriptive ID, accepted MIME patterns, output formats/defaults and UI metadata. Local node validates endpoint, protocol, offered ID/match, response success and output MIME. Remote discovery never imports provider PHP or runs arbitrary remote code locally. Existing bundled PHP endpoint grains are special remote HTTP flows; their PHP runs on the serving node that receives the request.

**Security/compatibility risks for later slices:** convert accepts caller-supplied source size when present; generated output is written directly to target and can overwrite; stream transport for remote conversion may follow redirects; endpoint validation and resolved connection destination need a separate trust-boundary review. Do not broaden remote endpoint policy during refactoring.

## Single-file builder contract

At build time, `build/build.php` orders and concatenates `MediaType.php`, `Converter.php`, Worktype traits and class, and `PoffConfig`/viewer pieces. It prefers `src/includes/worktypes/worktypes.php`, with a `*.worktype.php` fallback if no embedded definitions were read. Worktype definitions, `.hbs` templates and layout CSS/JS are exported into `Worktype::setEmbedded*()` maps. LightnCandy source is embedded. Default/file-system layout profile assets are also embedded/copied. Converter grain files are copied as sidecars under `worktypes/templates/converter` in build output and standalone copies.

Build-time inputs: Worktype PHP definitions and template/metadata bundle, direct template files used by fallback, built-in layout wrapper/assets, LightnCandy source, converter JSON/grain files, and ordered PHP source pieces. Runtime filesystem inputs: node-local `.layout`, `.works/<file>.layout`, shared layout collections, and node-local converter definitions/packages. A single PHP artifact cannot discover arbitrary development source folders unless those assets are deployed beside it. Runtime package discovery must not be described as bundled discovery.

The production build was not run. A development bundle was generated under a disposable `tests/` output directory with distribution copies disabled, then syntax-checked and inspected for embedded Worktype metadata, image template content and default layout CSS. Converter definitions and grain PHP/JSON remain runtime sidecars copied by the normal distribution-copy stage; this isolated artifact check did not execute that copy stage. Full source/build parity is therefore still open.

## Regression coverage and next safe slices

Existing `tests/mcp.create.test.js` covers Worktype catalog choices, QuickTime behavior, inherited/wildcard maps, custom file/folder layouts, shared package hydration, converter discovery, MIME acceptance, preview, local/remote mocks, generated Work metadata and endpoint rejection. New tests pin Worktype metadata retention through source and bundle loaders, wildcard MIME/dotted suffix catalog behavior, resource MIME/kind detection (`photo.jpg`), exact Worktype `templateMap` candidate priority, inherited/local map merging, explicit-template override, Converter MIME/kind/extension matching, folder discovery collision precedence, disabled converter offer filtering, required source inputs in the Builder recipe, and rejection of remote conversion results whose MIME type was not offered. A temporary development-bundle test now verifies emitted Worktype metadata/template/layout asset content and PHP syntax; distribution sidecar copying is still verified only by Builder wiring assertions.

Prioritized follow-up:

1. Add isolated source/build output parity checks for metadata, custom Worktype definitions, templates, layout assets and converter sidecars. Do not write generated output into `pages/`.
2. Before moving Converter business logic out of MCP transport, define whether output collision behavior should tighten. Tests confirm `new-hidden-work` and `replace-generated-work` both overwrite an existing same-name target; `temporary-preview-only` avoids the save call in the editor, and `replace-source-file` has its own text-file branch. `saveGeneratedWork()` otherwise writes directly and upserts metadata. HTTP POST and GET download both disable redirects; a redirect response is rejected without requesting its destination. Source-size validation uses resolved in-root `filesize()` before the 100 MiB check; inline offered sources also have a decoded-byte limit.
3. Define capability-specific Core contracts and extract one operation at a time. Preserve current folder formats and builder input paths as adapters.
