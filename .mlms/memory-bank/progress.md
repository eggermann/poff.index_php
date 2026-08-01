# Progress
## Done
- Initialized project brief.
- Initialized memory bank.
- Reviewed the folder-creation fix diff.
- Verified PHP lint with the MAMP runtime.
- Confirmed the targeted work-folder regression passes.
- Verified the live browser flow creates and shows `stiefring`.
- Added sibling localhost mirror targets for `node-1`, `node-2`, and `pageoff`.
- Created a live `success-story` folder with `note.txt` on `node-2`.
- Verified the live browser sidebar/path shows `success-story`.
- Captured a browser screenshot of the success state.
- Removed duplicated cloned content from `node-1` and `node-2`.
- Verified `node-2` now shows an empty directory index.
- Implemented and tested generic Poff converter offer/discovery protocol.
- Implemented single-file inline upload to provider and returned-result saving.
- Implemented folder bulk conversion for local and remote Poff converters.
- Added local, pageoff.de, and arbitrary-node choices to the folder editor.
- Added converted-only, originals-only, and both visibility policies.
- Added browser-readable image thumbnails to the default folder cards.
- Converted all 16 live HEIC files to PNG; originals remain preserved and hidden.
- Captured a Playwright proof screenshot containing all 16 converted images.
- Verified 166 MCP tests, 16 related UI tests, PHP lint, and diff whitespace.
## In Progress
- None.
## Next
- Rebuild/deploy only when explicitly requested.
- Commit only when explicitly requested.

## External Player Study
- Located six physical H.264 MP4 files under `/Users/eggermann/Projekte/dominikeggermann.com/page/Prothagonia`.
- Confirmed each file already has a standard video work config.
- Confirmed folder render context exposes `allVideos`, each with `srcUrl`.
- Confirmed custom folder layouts support HBS, CSS, and JS.
- Confirmed “Copy + paste” copies only the item name into prompt/clipboard.
- Confirmed “Add work → Poff link” creates a link/iframe, not a local media copy.
- Confirmed `export-content` and `import-remote` exist, but no editor UI calls them.
- Confirmed `import-remote` imports remote metadata/links only.
- Player source totals six 448×336 H.264 videos; originals remain untouched.

## External Player Implementation
- Copied all six Prothagonia MP4 files into `/Users/eggermann/Projekte/dominikeggermann.com/page/player`.
- Created a polished folder layout with one autoplaying muted player, clickable queue, previous/next controls, and continuous playlist loop.
- Verified in Playwright that item 1 is playing and a synthetic `ended` event from item 6 returns to item 1.
- Fixed local filesystem layout asset URLs so nested `.layout` CSS/JS resolve against the item path.
- Enabled same-origin folder layout scripts in the fetch-mounted preview.
- Added “Poff work” to Add work with explicit Copy locally, Follow source, and Embed view modes.
- Extended `import-remote` with guarded local copying: public HTTP(S), same source host, redirects disabled, file/count/total limits, safe names, temporary import folder, and no overwrite.
- Added optional looping-player generation for copied video folders.
- Made `export-content` a public read-only interoperability surface for public Poff works.
- Captured `player-local-1440.png` and `external-work-takeover-ui.png`.
- Verified 168/168 MCP tests, 2/2 upload UI tests, 7/7 preview tests, PHP lint, and JS syntax.
