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

## Watcher Mirror Update
### What Changed
- Added `node-1`, `node-2`, and `pageoff` to the watcher/build mirror targets alongside `MAUSMAUS`.
### Verification Result
- `scripts/watch-build.js` and `build/build.php` now include the new target names.
### Notes
- The change assumes the same generated output tree should be mirrored to all sibling localhost folders.
