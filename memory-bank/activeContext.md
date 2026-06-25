# Active Context

  This file tracks the project's current status, including recent changes, current goals, and open questions.
  2026-04-14 23:49:08 - Log of updates made.

*

## Current Focus

*  [2026-06-09] - Removing the guest-facing "Submit external link" section from the edit panel so public edit mode no longer shows a submission form.
*  [2026-06-09] - Wiring the Pageoff remote converter URL metadata into converter definitions and making remote image outputs use an `external-` filename prefix.
*  [2026-06-09] - Correcting the repo notes to match the live `dodi2` and `dodi` state: no film uploads on `dodi2`, and `dodi` is the eggermann copy tree.
*  [2026-06-09] - Investigating the dodi-only auth reload bug and adding an explicit auth cookie so editor state survives refreshes.
*  [2026-04-17 20:28:56] - Split `src/assets/scss/partials/_edit.scss` into focused SCSS partials and kept the edit UI styles organized by feature area.
*  [2026-04-18 11:00:00] - Began cleanup of the JS app bootstrap by extracting shared constants/helpers for element lookup, legacy hash redirect, and sidebar toggle handling.
*  [2026-04-18 11:20:00] - Started decomposing `src/assets/js/edit/prompt.js` into smaller modules for mode handling, summaries, editor field syncing, logging, and image attachment IO.
*  [2026-06-09] - Previous dodi1 image-upload/converter work is complete and remains documented in the memory bank.

## Recent Changes

*  [2026-06-09] - Verified the guest external-link submission removal with `npm run test:mcp` and `npx jest --watchman=false tests/edit-panel-inline.test.js`.
*  [2026-06-09] - Removed the guest external-link submission panel from `src/assets/js/edit/panel/inline.js` and updated the inline panel test to expect the minimal guest edit message instead.
*  [2026-06-09] - Preserved converter definition `url`/`remote.publicUrl` in `Converter::converterDefinition()`, added Pageoff public URLs to the remote text/image converters, and made remote-node payloads default to `external-<source>.<format>`.
*  [2026-06-09] - Added MCP regression coverage for Pageoff converter URLs and `external-scan-001.png` target naming.
*  [2026-06-09] - Playwright confirmed the nested Pageoff converter URLs are routable in navigation, but live preview requests still return 404 until Pageoff deployment/content is aligned.
*  [2026-06-09] - Replaced the mistaken movie-link note with a current-state audit doc and removed the false regression test.
*  [2026-04-16 23:21:08] - Added src/docs/refactor-php-plan.md with inventory, rules, and workflow.
*  [2026-04-16 23:24:33] - Split edit-config route into helpers/payload/apply modules.
*  [2026-04-17 06:25:22] - Split viewer/render.php into render/* modules and updated include.
*  [2026-04-17 20:28:56] - Broke the monolithic edit SCSS into `edit/panel`, `edit/layout`, `edit/drawer`, `edit/prompt`, and `edit/responsive` partials.
*  [2026-04-18 11:00:00] - Introduced `src/assets/js/app/constants.js` and `src/assets/js/app/helpers.js` to centralize app-shell IDs, hashes, labels, and sidebar bootstrap behavior.
*  [2026-04-18 11:20:00] - Added prompt helper modules under `src/assets/js/edit/prompt/` for mode selection, prompt summaries, logging, editor-field syncing, and image file handling.
*  [2026-06-09] - Added dodi1 docs, a project brief, and a memory-bank sprint review stub for the image-upload/converter goal.
*  [2026-06-09] - Added a dodi1 fixture folder with `scan-001.tiff` and aligned the docs to the `/dodi/` alias.
*  [2026-06-09] - Ran the required PHP lint checks and the MCP test suite after the remote deploy.

## Open Questions/Issues

*
*  [2026-06-09] - Confirm whether the guest edit panel should stay as a minimal password prompt or be hidden entirely in a later pass.
*  [2026-06-09] - Live `/dodi/` still presents the eggermann copy tree; the source fix needs a rebuild/deploy before live dodi can show the new converter URL/name behavior.
*  [2026-06-09] - Pageoff currently returns 404 for `?view=1&path=converter%2Fconvert-pageoff-remote-image`; the converter folder/preview needs to be deployed or repaired on Pageoff for full live browser completion.
*  [2026-06-09] - The live fix likely needs a rebuild of generated `pages/` output before redeploying `/dodi/`.
*  [2026-06-23] - Added a read-only `PoffConfig::read()` snapshot path and switched edit config/upload reads to it so live folder creation stops forcing config rewrites.
  
