# PHP Refactor Plan (Scope: src/)

## Scope & Constraints
- Scope: `src/**/*.php` only.
- Exclusions: generated files `src/includes/header.built.php`, `src/includes/scripts.built.php` (no edits).
- Target: no file over 200 lines after refactor.
- Use helpers/utils for shared logic.
- Avoid changing build output behavior; minimize impact on generated `index.php` size and markup.

## Inventory (Line Counts vs 200)
**Over 200 lines (refactor targets):**
- `src/includes/PoffConfig.php` — 798
- `src/includes/Worktype.php` — 701
- `src/includes/viewer/edit.php` — 1391
- `src/includes/viewer/render.php` — 546
- `src/includes/viewer/utils.php` — 284
- `src/mcp/routes/edit-config.php` — 319
- `src/mcp/routes/prompt-template.php` — 770

**At/under 200 lines (keep stable, minor cleanups only if needed):**
- `src/includes/functions.php` — 34
- `src/includes/layout.php` — 48
- `src/includes/MediaType.php` — 94
- `src/includes/nav.php` — 123
- `src/includes/viewer.php` — 11
- `src/includes/worktypes/*.worktype.php` — 7–13
- `src/includes/worktypes/worktypes.php` — 46
- `src/mcp/helpers.php` — 101
- `src/mcp/http-server.php` — 74
- `src/mcp/routes/create.php` — 130
- `src/mcp/routes/style.php` — 15
- `src/mcp/routes/workprompt.php` — 71
- `src/mcp/server.php` — 166

## Refactor Rules
1. **Maximum 200 lines per file**: split into cohesive modules.
2. **Helpers and utilities**: extract reusable logic into `src/includes/utils/` or `src/mcp/utils/`.
3. **Public behavior preserved**: keep current function signatures or provide thin wrappers.
4. **Single responsibility**: each file handles one area (validation, IO, serialization, rendering).
5. **No build-side changes** unless explicitly requested.

## Proposed Module Breakdown
### Viewer
- Split `src/includes/viewer/render.php` into:
  - `render-viewer.php` (entry functions)
  - `render-file.php`
  - `render-folder.php`
  - `render-shell.php` (HTML shell)
  - `viewer-data.php` (data builders)
- Move shared helpers to `src/includes/viewer/utils.php` or `src/includes/utils/viewer.php`.

### Config Editing (MCP)
- Split `src/mcp/routes/edit-config.php` into:
  - `edit-config/parse.php` (request parsing)
  - `edit-config/validate.php`
  - `edit-config/layout.php` (layout persistence and normalization)
  - `edit-config/handler.php` (route entrypoint)

### Prompt Template (MCP)
- Split `src/mcp/routes/prompt-template.php` into:
  - `prompt-template/io.php`
  - `prompt-template/render.php`
  - `prompt-template/handler.php`

### Core Includes
- Split `src/includes/PoffConfig.php` into:
  - `PoffConfig.php` (public API)
  - `PoffConfig/ConfigIO.php`
  - `PoffConfig/LayoutFiles.php`
  - `PoffConfig/TreeBuilder.php`
- Split `src/includes/Worktype.php` into:
  - `Worktype.php` (public API)
  - `Worktype/Registry.php`
  - `Worktype/Layout.php`
  - `Worktype/Renderer.php`

## Test Plan (Existing + Additions)
**Existing PHP tests:**
- `tests/php_render_viewer.php` → viewer refactor
- `tests/php_render_worktype.php` → `Worktype` refactor
- `tests/php_prompt_model_parse.php` → prompt-template refactor
- `tests/php_prompt_error_helpers.php` → MCP helpers
- `tests/php_call_create.php` → create route
- `tests/php_upload_files.php` → filesystem behavior
- `tests/php_layout_filesystem.php` → layout interactions

**Additions (if needed):**
- Focused tests for new helpers to keep behavior parity.

## Branch Workflow (refactor/php-quality-plan)
1. Create branch `refactor/php-quality-plan`.
2. Commit this plan doc.
3. Refactor module-by-module (viewer → PoffConfig → Worktype → MCP routes).
4. Run checks after each module:
   - `php -l` on touched PHP files
   - `npm run test:mcp`
5. Keep changes small; each module has its own commit.

## Verification Steps (Per Change)
- `php -l` on changed PHP files.
- `npm run test:mcp`.

## Success Criteria
- No `src/**/*.php` file above 200 lines (excluding `*.built.php`).
- All tests pass.
- Output and behavior unchanged for viewer, MCP routes, and build artifacts.
