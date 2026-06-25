# Progress

This file tracks the project's progress using a task list format.
2026-04-14 23:49:11 - Log of updates made.

*

## Completed Tasks

*   
*  [2026-06-09] - Guest edit mode no longer renders the external-link submission section, and the inline panel test now verifies the minimal guest state.

## Current Tasks

*  [2026-06-09] - Pageoff remote converter source behavior is implemented locally: converter URLs are preserved, Pageoff remote text/image URLs are defined, and remote image outputs use the `external-` prefix.
*  [2026-06-09] - The dodi2/dodi current-state audit has replaced the mistaken movie-link assumption.
*  [2026-04-17 06:25:22] - viewer/render.php refactor completed; tests passing.
*  [2026-04-17 20:28:56] - SCSS edit stylesheet refactor completed; partials split and MCP tests passed.
*  [2026-04-18 11:03:31] - JS app bootstrap cleanup completed with shared constants/helpers, and SCSS entry order was normalized.
*  [2026-06-09] - The dodi editor auth reload bug is being fixed with a cookie-backed persistence path.
*  [2026-06-09] - Source auth changes and tests have been updated locally and now pass.
*  [2026-06-23] - Read-only config snapshots are wired into the edit config/upload read paths and verified against a read-only temp config.

## Next Steps

*  [2026-06-09] - Rebuild/deploy `/dodi/` and the Pageoff converter folders if live browser conversion must show the new URL metadata and `external-` output naming.
*  [2026-06-09] - Decide whether any further live verification is needed or whether the corrected docs are enough.
*  [2026-04-17 06:25:22] - Choose next large PHP file (viewer/edit.php or PoffConfig).
*  [2026-04-17 20:28:56] - Resume the next refactor target or review the new SCSS partial structure if further tuning is needed.
*  [2026-06-09] - Rebuild the generated public output and redeploy `/dodi/` so the new auth cookie logic reaches the live site.
*  [2026-06-23] - Deploy the read-only config snapshot fix to the live eggman host and re-test the folder create flow.
