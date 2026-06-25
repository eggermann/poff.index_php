# Decision Log

This file records architectural and implementation decisions using a list format.
2026-04-14 23:49:15 - Log of updates made.

*

## Decision

*
*  [2026-06-09] - Remove the guest external-link submission UI from public edit mode and keep guest access on a minimal password prompt instead.
*  [2026-06-09] - Replace the mistaken dodi2 movie-link assumption with a plain current-state audit note.
*  [2026-04-17 20:28:56] - Refactored the edit stylesheet into feature-scoped partials instead of keeping one monolithic SCSS file.
*  [2026-06-09] - Use the existing deploy-target suffix model to support `dodi1` and document its remote image-conversion workflow.
*  [2026-06-09] - Treat `dodi1` as an alias for the existing `/dodi/` public webspace and keep the local fixture folder under `poff/test-converter/dodi1/`.
*  [2026-06-09] - Add an explicit editor auth cookie fallback so `/dodi/` can restore login state after reload even when PHP sessions do not persist as expected.
*  [2026-06-09] - Preserve converter definition public URLs instead of always replacing them with local viewer URLs.
*  [2026-06-09] - Prefix remote-node generated image targets with `external-` to distinguish outputs created by external converter services.
*  [2026-06-23] - Use a non-mutating `PoffConfig::read()` snapshot for edit config/upload reads so live editor requests do not rewrite configs unnecessarily.

## Rationale 

*
*  The public guest edit state should not advertise a submission workflow the user does not need.
*  Keeping panel, layout, drawer, prompt, and responsive rules separate makes the edit stylesheet easier to navigate and maintain.
*  The suffix model keeps dodi1 consistent with the current deploy script and avoids introducing a separate naming scheme.
*  The alias keeps the user-facing URL stable while still giving the repo a distinct dodi1 workflow label.
*  Converter definitions need both a user-facing editor URL and a machine endpoint; keeping them separate lets the selector point users to Pageoff while conversion still posts to the endpoint.
*  The `external-` prefix makes generated remote files visibly different from source-adjacent local outputs.

## Implementation Details

*
*  `src/assets/js/edit/panel/inline.js` now renders only the minimal guest edit message when auth is unavailable, with no external submission section or button.
*  The current-state doc records that `dodi2` has no film uploads and `dodi` is the eggermann copy tree.
*  The entry partial now only composes the edit feature partials with `@use`, while the actual rules live under `src/assets/scss/partials/edit/`.
*  dodi1 will reuse the same deploy script, converter architecture, and remote PHP endpoint style already used elsewhere in the repo.
*  A local `scan-001.tiff` fixture now exists in `poff/test-converter/dodi1/` to make the workflow concrete in-repo.
*  The auth cookie stores a per-root bearer token derived from the current password hash and is restored on each request before the edit UI renders.
*  Pageoff converter JSON now includes nested Pageoff editor URLs under `#/converter/convert-pageoff-remote-*`.
*  `Converter::buildPayload()` now proposes `external-scan-001.png` for Pageoff remote TIFF-to-PNG conversion.
*  Edit context, upload responses, and worktype catalog reads now use the read-only snapshot path; write-heavy config reconstruction is reserved for explicit write operations.
