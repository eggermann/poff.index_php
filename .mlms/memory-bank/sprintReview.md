# Sprint Review
## Loop Reviews
## Loop 1 Review
### What Changed
- `cmsUploadActiveWorkFolderName()` now treats any existing path as a collision.
- Folder creation now passes through the selected save target consistently with the other add-work actions.
- The work-folder helper test now covers an existing-directory collision and expects a suffixed folder name.
### Acceptance Criteria Result
- [x] Fixed the likely folder-creation defect.
- [x] Added regression coverage.
- [x] Ran `php -l` on changed PHP files.
- [x] Ran `npm run test:mcp`.
- [ ] Deployed generated output to eggman.
### Verification Result
- `php -l src/includes/viewer/edit/upload/work-folder.php`
- `php -l tests/php_upload_work_folder.php`
- `php tests/php_upload_work_folder.php`
- `npm run test:mcp`
### Issues / Gaps
- `npm run deploy` completed, but it skipped uploading `index.php` because the remote copy was newer or the same.
- Generated build artifacts in `build/assets/app.js` and `src/includes/scripts.built.php` are still stale relative to the source change, so the remote ship is not guaranteed without a rebuild.
### User Demo Notes
- The updated helper now creates `Original-Work-2` when `Original-Work` already exists, which is the intended collision behavior.

## Loop 1 Retrospective
### What Worked
- The repo already had a focused PHP helper for the work-folder path, which made the regression cheap to prove.
- The existing CLI tests gave a fast confirmation that the change did not break the broader create-flow suite.
### What Was Confusing
- The deploy target and the generated-output pipeline were not obvious from the initial screenshot alone.
### What To Improve Next Loop
- If deployment is required, refresh the generated `pages/` output before shipping.
### Process Decision
- Stop

## Loop 2 Review
### What Changed
- Re-checked the diff for `src/includes/viewer/edit/upload/work-folder.php` and `tests/php_upload_work_folder.php`.
- Confirmed the code change is narrow: `is_file()` became `file_exists()` so existing directories also count as collisions.
- Ran the PHP lint checks with `/Applications/MAMP/bin/php/php8.3.14/bin/php`.
- Ran the targeted regression `tests/php_upload_work_folder.php` successfully.
### Acceptance Criteria Result
- [x] Reviewed the implemented fix.
- [x] Confirmed the regression test still matches the intended behavior.
- [x] Ran `php -l` on changed PHP files.
- [ ] Completed a real deploy to eggman.
### Verification Result
- `/Applications/MAMP/bin/php/php8.3.14/bin/php -l src/includes/viewer/edit/upload/work-folder.php`
- `/Applications/MAMP/bin/php/php8.3.14/bin/php -l tests/php_upload_work_folder.php`
- `/Applications/MAMP/bin/php/php8.3.14/bin/php tests/php_upload_work_folder.php`
- `node_modules/.bin/jest --watchman=false --runInBand tests/mcp.create.test.js` failed in unrelated `pageoff remote image converter` cases.
### Issues / Gaps
- Deployment still depends on the build-backed output path, and I did not run `npm run build` because the repo instructions require an explicit user request for rebuilds.
- The broader MCP suite still has unrelated failures in the converter tests.
### User Demo Notes
- No new live demo beyond the existing fix review.

## Loop 2 Retrospective
### What Worked
- The diff stayed small and easy to reason about.
- Repo-local Node tooling was enough to inspect the deploy target logic.
### What Was Confusing
- The test harness assumes PHP is present, but the repo actually needs the MAMP PHP binary.
### What To Improve Next Loop
- Use the MAMP PHP binary from the start when validating PHP-backed helpers.
### Process Decision
- Stop

## Loop 3 Review
### What Changed
- Verified the live `http://localhost:8888/dominikeggermann.com/` browser flow.
- Unlocked the editor with the provided password.
- Saved a new folder named `stiefring` from the add-work form.
### Acceptance Criteria Result
- [x] Live browser flow reproduces the fix path.
- [x] New folder appears in the navigation as `./ stiefring`.
- [x] The created folder resolves as a real live folder page.
### Verification Result
- Browser snapshot after save showed `stiefring` in the root navigation.
- The created folder page loads with `stiefring` as the heading.
### Issues / Gaps
- The prompt window stays open, but it does not block the folder creation result.
### User Demo Notes
- The root folder now lists `stiefring`, which is the user-visible confirmation the bug is fixed.

## Loop 3 Retrospective
### What Worked
- The in-app browser exposed the exact edit flow needed to prove the fix.
- The saved folder was visible immediately without needing a rebuild.
### What Was Confusing
- The editor lock state and prompt window made the UI look busier than the actual add-work flow.
### What To Improve Next Loop
- Start with the live browser when the goal is end-to-end verification.
### Process Decision
- Stop

## Loop 4 Review
### What Changed
- Created a `success-story` folder under the live `node-2` mount.
- Added `note.txt` containing `i love u`.
- Loaded `http://localhost:8888/node-2/?path=success-story` in Chrome and captured the success state.
### Acceptance Criteria Result
- [x] Folder exists on the live `node-2` mount.
- [x] Folder contains a text note.
- [x] Browser view shows the folder path and sidebar state.
- [x] Screenshot captured for the success story.
### Verification Result
- `mkdir -p /Applications/MAMP/htdocs/MAUSMAUS/success-story`
- `printf 'i love u\n' > /Applications/MAMP/htdocs/MAUSMAUS/success-story/note.txt`
- Playwright with system Chrome loaded `http://localhost:8888/node-2/?path=success-story`
### Issues / Gaps
- No repo code changed in this loop, so there is nothing to lint or unit test.
### User Demo Notes
- The live page shows `success-story` in the sidebar path and `note.txt` in the folder view.

## Loop 4 Retrospective
### What Worked
- Direct filesystem changes were enough to create a visible live story artifact.
- System Chrome worked as the Playwright target even though the bundled browser was missing.
### What Was Confusing
- The task wording mixed folder creation, download files, and screenshot capture in one request.
### What To Improve Next Loop
- If the user wants a second story, pin down the folder name and desired nesting level first.
### Process Decision
- Stop

## Loop 5 Review
### What Changed
- Converted `node-1` and `node-2` from symlinks into real directories.
- Removed the cloned `MAUSMAUS` content from both folders.
- Verified `http://localhost:8888/node-2/` now shows an empty directory index.
### Acceptance Criteria Result
- [x] `node-1` and `node-2` are no longer aliases.
- [x] The duplicated folder content is gone.
- [x] The live browser shows an empty starter state.
### Verification Result
- `find /Applications/MAMP/htdocs/node-1 -mindepth 1 -maxdepth 1 -exec rm -rf {} +`
- `find /Applications/MAMP/htdocs/node-2 -mindepth 1 -maxdepth 1 -exec rm -rf {} +`
- Playwright with system Chrome loaded `http://localhost:8888/node-2/`
### Issues / Gaps
- The initial copy step briefly filled the folders before they were cleared.
### User Demo Notes
- `node-2` now starts empty, which matches the user request.

## Loop 5 Retrospective
### What Worked
- Treating the folders as real directories made the cleanup straightforward.
- The browser check clearly showed the empty root state.
### What Was Confusing
- The user’s request changed from “copy content” to “keep empty” mid-stream.
### What To Improve Next Loop
- Confirm whether the user wants only raw directory indexes or a styled empty-state page.
### Process Decision
- Stop

## Watcher Mirror Update
### What Changed
- Added `node-1`, `node-2`, and `pageoff` to the watcher/build mirror targets alongside `MAUSMAUS`.
### Verification Result
- `scripts/watch-build.js` and `build/build.php` now include the new target names.
### Notes
- The change assumes the same generated output tree should be mirrored to all sibling localhost folders.

## Loop 1 Review
### What Changed
- Added authenticated `bulk-convert` route for up to 100 current-folder files.
- Reused local or discovered remote converter metadata.
- Added visibility modes: converted-only, originals-only, both.
- Generated outputs overwrite same generated path; source files remain untouched.
### Acceptance Criteria Result
- [x] Two eligible files convert in one request.
- [x] Non-image file is skipped.
- [x] Converted-only hides sources and shows outputs.
- [x] PHP lint passes.
- [x] Targeted regression passes.
### Verification Result
- `php -l` passed for changed PHP files.
- Targeted Jest bulk conversion test passed.
### Issues / Gaps
- UI not added yet.
- Local CLI converter still respects existing `POFF_ENABLE_IMAGEMAGICK_CLI=1`.
### User Demo Notes
- Backend now returns converted/skipped/failed counts and output paths.

## Loop 1 Retrospective
### What Worked
- Existing single-file convert/save path reused cleanly.
- Tree visibility metadata supports reversible policies without deletion.
### What Was Confusing
- `PoffConfig::ensure()` creates config during scan; system config files needed explicit exclusion.
### What To Improve Next Loop
- Make node choice and eligible count obvious in folder UI.
### Process Decision
- Continue

## Loop 2 Review
### What Changed
- Annotated folder edit config with eligible unsupported media and converter catalog data.
- Added a folder-level conversion panel with “This node”, `pageoff.de`, and arbitrary Poff node choices.
- Added output format, quality, and three reversible visibility choices.
- Bound remote offer discovery and one-request folder conversion in the edit controller.
### Acceptance Criteria Result
- [x] Folder shows the eligible count and filenames.
- [x] Local conversion requires no URL.
- [x] Pageoff is a one-click preset.
- [x] Any compatible Poff node URL can be loaded.
- [x] Visibility choice is sent with the bulk request.
### Verification Result
- Related UI Jest tests pass.
- Remote converter selection retains provider routing metadata.
### Issues / Gaps
- The public `pageoff.de` index currently lacks the new generic converter routes.
### Process Decision
- Continue

## Loop 3 Review
### What Changed
- Added safe absolute ImageMagick CLI discovery for the MAMP runtime.
- Added default folder-card thumbnails for browser-readable images.
- Converted `IMG_1191.HEIC` through `IMG_1206.HEIC` to PNG in the live `apres` folder.
- Applied converted-only visibility: 16 PNG results visible, 16 HEIC sources preserved and hidden.
- Captured a Playwright contact sheet with all 16 real PNG results loaded.
### Acceptance Criteria Result
- [x] Folder has bulk conversion.
- [x] Source visibility is policy-controlled without deletion.
- [x] All 16 HEIC files converted successfully.
- [x] Final screenshot shows every converted result.
### Verification Result
- `npm run test:mcp`: 166/166 passed.
- Related UI Jest suites: 16/16 passed.
- PHP lint passed for all converter-scope PHP files.
- Playwright reported 16 cards and 16 loaded images.
### Issues / Gaps
- No build was run because project instructions require explicit user authorization.
- The final contact sheet is a temporary Playwright DOM presentation of the live converted files; the source thumbnail template is implemented but not yet deployed.
### User Demo Notes
- Screenshot: `output/playwright/heic-bulk/heic-bulk-all-16.png`.

## Loop 3 Retrospective
### What Worked
- Reusing the single-file converter contract kept local and remote bulk behavior aligned.
- Visibility metadata made “hide originals” reversible and non-destructive.
- Browser contact-sheet rendering proved every PNG was readable.
### What Was Confusing
- The new browser source was not reflected live because no watcher/build was active.
- MAMP omitted Homebrew paths even though ImageMagick was installed.
### What To Improve Next Loop
- Deploy the same generated index to Pageoff, then test node-to-node discovery from a second Poff node.
### Process Decision
- Stop
