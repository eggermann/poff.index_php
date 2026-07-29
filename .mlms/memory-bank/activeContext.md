# Active Context
## Current Goal
Obvious folder-level HEIC bulk conversion using local or Pageoff Poff nodes, with original/generated visibility policy and final screenshot.
## Current Loop
Loop: 3
Phase: Complete
## Current Focus
Bulk conversion implementation and live proof are complete.
## Assumptions
- Default bulk mode should show converted works and hide originals.
- Local converter should require no URL.
- Pageoff should be a one-click preset, while arbitrary Poff URLs remain possible.
## Risks / Unknowns
- Existing dirty files belong to user or prior work and must remain untouched unless directly in converter scope.
- `pageoff.de` still serves an older index without the generic converter-offer route.
- Source UI changes require the user's later explicit build/deployment before Pageoff can advertise converters.
## Next Action
Deploy the newly built `index.php` to `pageoff.de` when the user explicitly requests a build.
