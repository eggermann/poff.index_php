# Sprint Review
## Loop Reviews
No loop reviews recorded yet for the dodi current-state audit.

## Loop 7 Review
### What Changed
- Removed the guest-facing external-link submission panel from the edit panel source.
- Updated the inline panel test to expect a minimal guest edit message instead of the submission UI.
- Refreshed the project brief and memory bank to match the new goal.
### Acceptance Criteria Result
- [x] The guest external-link submission section is removed from source.
- [x] The inline edit panel test reflects the new guest behavior.
- [x] Verification ran successfully.
### Verification Result
- `npm run test:mcp`
- `npx jest --watchman=false tests/edit-panel-inline.test.js`
### Issues / Gaps
- None for this slice.
### User Demo Notes
- Guest edit mode should now show only a minimal password prompt instead of the external submission form.

## Loop 7 Retrospective
### What Worked
- The change was isolated to one source branch in the edit panel.
### What Was Confusing
- The source originally mixed the guest prompt with the external submission workflow.
### What To Improve Next Loop
- Verify the test suite and then decide whether the guest prompt should be hidden completely in a follow-up.
### Process Decision
- Stop

## Loop 6 Review
### What Changed
- Preserved converter definition public URLs instead of forcing local `?view=1&path=...` URLs.
- Added nested Pageoff editor URLs to the remote image and text converter definitions.
- Made remote-node image payloads default generated names to `external-<source>.<format>`.
- Added MCP regression coverage for Pageoff URL metadata and `external-scan-001.png` naming.
### Acceptance Criteria Result
- [x] Pageoff converter option carries a Pageoff editor URL in source.
- [x] TIFF remote image conversion payload proposes an `external-` prefixed PNG.
- [x] Relevant PHP lint and MCP tests pass.
- [~] Playwright verified public routes, but live Pageoff preview content and live dodi deployment still need alignment.
### Verification Result
- `php -l src/includes/Converter.php`
- `php -l tests/php_converter_routes.php`
- `npm run test:mcp`
- Playwright opened `https://pageoff.de/?edit=true#/converter/convert-pageoff-remote-image`
- Playwright opened `https://eggman4.uber.space/dodi/?edit=true#/scan-001-tiff`
### Issues / Gaps
- The live Pageoff image converter route appears in navigation, but `?view=1&path=converter%2Fconvert-pageoff-remote-image` returns 404 on the current deployment.
- The live dodi page still presents the eggermann copy tree, so it does not yet prove the local source change in production.
### User Demo Notes
- In source, selecting the Pageoff remote image converter for `scan-001.tiff` now prepares `external-scan-001.png`.

## Loop 6 Retrospective
### What Worked
- Automated tests caught the URL/name behavior without touching generated `pages/` output.
- Playwright exposed the remaining deployment mismatch instead of hiding it behind green local tests.
### What Was Confusing
- The user example used the text converter URL, but the TIFF workflow needs the image converter path; both now use the nested Pageoff converter folder convention.
### What To Improve Next Loop
- Rebuild and deploy Pageoff/dodi when live proof is required.
### Process Decision
- Stop

## Loop 1 Review
### What Changed
- Added a short doc describing the live `dodi2` and `dodi` state.
- Removed the mistaken movie-link regression test.
### Acceptance Criteria Result
- [x] The live state is documented accurately.
- [x] The false movie-link assumption is removed from the repo notes.
- [x] The existing guest pending-link behavior remains intact.
### Verification Result
- `npm run test:mcp`
### Issues / Gaps
- No code path changed; this was a correction of the repo notes to match live state.
### User Demo Notes
- The repo now says what the live pages actually show instead of implying a film workflow that is not there.

## Loop 1 Retrospective
### What Worked
- Keeping the audit anchored to the live pages prevented the wrong workflow from being documented.
### What Was Confusing
- The earlier assumption about movie links was not supported by the live page state.
### What To Improve Next Loop
- If a film workflow is needed later, define it explicitly instead of inferring it from the current copy tree.
### Process Decision
- Continue

## Loop 1 Review
### What Changed
- Initialized `projectBrief.md`.
- Added dodi1-specific entries to the memory bank.
- Documented the dodi1 deploy target and remote image converter flow.
### Acceptance Criteria Result
- [x] Project brief created.
- [x] Memory bank initialized for the current goal.
- [x] Dodi1 scope documented.
### Verification Result
- No code/runtime checks were needed for the memory initialization slice.
### Issues / Gaps
- The exact next implementation slice is still the dodi1 deploy/converter wiring.
### User Demo Notes
- The repo now has a durable record of the current goal.

## Loop 1 Retrospective
### What Worked
- Preserving the existing memory-bank history avoided losing prior refactor context.
- Appending a new goal entry kept the setup low-risk.
### What Was Confusing
- The repo already had legacy memory-bank content, so a full rewrite would have been wrong.
### What To Improve Next Loop
- Move from memory initialization into a single concrete code slice.
### Process Decision
- Continue

## Loop 2 Review
### What Changed
- Aligned `dodi1` to the existing `/dodi/` webspace.
- Added a local `poff/test-converter/dodi1/` fixture with `scan-001.tiff`.
- Updated the docs to reflect the alias and local fixture.
### Acceptance Criteria Result
- [x] `dodi1` is documented as a real alias workflow.
- [x] The repo contains a concrete TIFF source fixture.
- [x] Target resolution tests still pass.
### Verification Result
- `npx jest --watchman=false tests/deploy-targets.test.js`
- `node --check scripts/deploy-targets.js`
### Issues / Gaps
- The next concrete step is deploy-side verification for the alias if you want the remote webspace updated.
### User Demo Notes
- The repo now contains a named dodi1 fixture folder and a single public URL target.

## Loop 2 Retrospective
### What Worked
- Keeping `dodi1` as an alias avoided splitting the public webspace into a second URL.
- Creating a local fixture folder made the upload source explicit.
### What Was Confusing
- The user-facing name and URL are not the same thing here; the docs need to keep that clear.
### What To Improve Next Loop
- Decide whether to push the alias deploy to the remote site or keep the flow local-only.
### Process Decision
- Continue

## Loop 3 Review
### What Changed
- Deployed `dodi1` to the existing remote `/dodi/` public webspace.
- Verified the public endpoint returns HTTP 200 at `https://eggman4.uber.space/dodi/`.
- Re-ran the required PHP lint checks and the MCP test suite.
### Acceptance Criteria Result
- [x] The alias deploy landed on the intended remote path.
- [x] The public URL is reachable.
- [x] Touched PHP files still lint clean.
- [x] The MCP test suite still passes.
### Verification Result
- `npm run deploy:dodi1`
- `curl -I -L --max-redirs 5 https://eggman4.uber.space/dodi/`
- `php -l src/includes/Converter.php`
- `php -l src/includes/worktypes/templates/converter/convert-pageoff-remote-image/converter.php`
- `php -l tests/php_pageoff_remote_image_converter.php`
- `npm run test:mcp`
### Issues / Gaps
- The repo still does not manage the remote `~/html/copilot` workspace config; that remains a server-side concern.
### User Demo Notes
- `dodi1` now points at the same public webspace as `/dodi/`, and the public page responds successfully.

## Loop 3 Retrospective
### What Worked
- Deploying the alias directly confirmed the real public path instead of relying on repo-only assumptions.
- The converter test coverage stayed stable after the remote upload.
### What Was Confusing
- The public URL and the deploy label are separate concepts; keeping them documented together is necessary.
### What To Improve Next Loop
- If the user wants Copilot/web-code-server behavior changed, handle that as a separate remote-server task.
### Process Decision
- Continue

## Loop 4 Review
### What Changed
- Uploaded `scan-001.tiff` into the live `/dodi/` folder.
- Ran the pageoff remote-image converter flow on that TIFF and placed the converted `scan-001.png` into the same folder.
- Verified the PNG renders in the browser on the live dodi page.
### Acceptance Criteria Result
- [x] TIFF source exists in the live dodi folder.
- [x] Converted PNG exists in the live dodi folder.
- [x] The converted PNG renders in the browser.
### Verification Result
- `scp` to `/var/www/virtual/eggman4/html/pages/dodi/actual/scan-001.tiff`
- `php tests/php_pageoff_remote_image_converter.php <payload>`
- `scp` to `/var/www/virtual/eggman4/html/pages/dodi/actual/scan-001.png`
- Browser refresh and open of `scan-001.png`
### Issues / Gaps
- The browser-side upload dialog still required a fallback because the UI path was not exposing the upload control cleanly.
### User Demo Notes
- The live dodi folder now shows both the TIFF source and the generated PNG result.

## Loop 4 Retrospective
### What Worked
- Using the existing converter handler kept the PNG generation aligned with the pageoff converter logic.
- Browser verification on the live folder confirmed the visible result.
### What Was Confusing
- The folder edit UI exposed a lot of layout/auth state before it exposed the practical upload controls.
### What To Improve Next Loop
- If we revisit this, wire the upload dialog path more directly or document the hidden upload affordance.
### Process Decision
- Continue

## Loop 5 Review
### What Changed
- Added a reload-safe editor auth cookie fallback in `src/includes/auth.php`.
- Added a direct regression test that restores editor auth from the cookie value on a fresh request.
- Kept the existing login flow intact while making persistence independent of PHP session cookie behavior.
### Acceptance Criteria Result
- [x] Login state can be restored after a fresh request using the auth cookie path.
- [x] The auth change is covered by automated tests.
- [x] PHP lint and the MCP suite pass locally.
### Verification Result
- `php -l src/includes/auth.php`
- `php -l tests/php_edit_auth_cookie.php`
- `npm run test:mcp`
### Issues / Gaps
- The live `/dodi/` site still needs a rebuild and redeploy so the generated public `index.php` picks up the source fix.
### User Demo Notes
- The repo now has the auth persistence fix and a test proving the reload path.

## Loop 5 Retrospective
### What Worked
- Making auth restoration explicit avoided depending on a session cookie that was not persisting in the live path.
- A focused PHP probe test kept the regression small and fast.
### What Was Confusing
- CLI PHP does not expose `Set-Cookie` headers the same way the live site does, so the test had to verify the cookie payload and restore path directly.
### What To Improve Next Loop
- Rebuild and redeploy the generated public output, then re-check the live `/dodi/` login/reload path.
### Process Decision
- Continue

## Loop 8 Review
### What Changed
- Added a read-only `PoffConfig::read()` snapshot path.
- Switched edit request context, upload responses, and worktype catalog annotation to use the non-mutating snapshot path.
- Kept write-heavy `PoffConfig::ensure()` behavior for explicit mutation flows.
### Acceptance Criteria Result
- [x] The editor read paths no longer force config rewrites.
- [x] The folder-create flow was verified locally against a read-only config file.
- [x] PHP syntax checks passed for all touched files.
- [ ] Live deployment still needs to happen.
### Verification Result
- `php -l src/includes/PoffConfig/core-helpers.php`
- `php -l src/includes/viewer/edit/action/context.php`
- `php -l src/includes/viewer/edit/action/upload-action.php`
- `php -l src/includes/viewer/edit/core/config.php`
- `POFF_TEST_AUTH_BYPASS=1 php tests/php_edit_request.php <tempdir> config HrMueller '{}'`
- `POFF_TEST_AUTH_BYPASS=1 php tests/php_edit_request.php <tempdir> upload HrMueller '{"source":"folder","fileName":"stiefring",...}'`
### Issues / Gaps
- The live eggman host still needs the updated code deployed.
- Existing unrelated working-tree edits remain untouched.
### User Demo Notes
- In a read-only temp-folder reproduction, the edit `config` and `upload` actions both complete while the config file remains non-writable.

## Loop 8 Retrospective
### What Worked
- Testing against a read-only `poff.config.json` mirrored the fragile live-folder failure mode well.
- Isolating the snapshot helper let the fix stay small and focused.
### What Was Confusing
- Several edit endpoints share the same config-resolution path, so the failure looked broader than the upload dialog at first.
### What To Improve Next Loop
- If the live host still misbehaves after deploy, inspect the remaining write paths separately.
### Process Decision
- Continue
