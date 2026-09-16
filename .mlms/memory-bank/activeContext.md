# Active Context
## Current Goal
Make dominikeggermann.com a fast, clear, clickable portfolio: one calm navigation layer and an owner-led story that makes the work easy to discover.
## Current Loop
Loop: 3
Phase: Complete and deployed
## Current Focus
The rebuilt index is deployed to eggman1 and verified on dominikeggermann.com. The owner story is inside the preview-preserved layout main, Explore work lazy-loads navigation, and HRMueller-2/auswahl is reachable with 11 images.
## Assumptions
- The existing selected-work route is `HRMueller-2`.
- The owner story copy is a first draft based on the visible image/film archive.
- Existing generated `index.php` remains unedited until an explicit rebuild.
## Risks / Unknowns
- The standard `npm run deploy` target resolves to the old eggman4/dodi path, not eggman1. The live deployment used the dedicated eggman1 connection config and checksum verification.
- Production has two pre-existing benign 404s: favicon and `.layout/poff.profile.jpg`.
## Next Action
Await owner feedback on story copy and any desired cleanup of the legacy deployment target.

## Previous Goal
The three-node Pageoff study is complete. Its detailed record remains in progress and sprint review history.
