# Designer UI/UX Review

## Outcome

Pageoff now has a fourth local node, `pageoff-civic`, that presents its folders and political texts as a Windows XP-style file explorer. Its visual rules live only in `/Applications/MAMP/htdocs/pageoff-civic/.layout`; the artist, writer, and curator nodes keep their own layouts.

## Code reduction

Static reference checks found code with definitions but no callers. Removed:

- two obsolete `FileCopier` distribution methods
- one redundant private converter wrapper
- one unused navigation badge renderer
- one unused prompt-context transformer
- two unused prompt compaction helpers
- one unused JavaScript status module

Three snake_case MCP wrappers remain despite having no internal callers. Their comments identify them as compatibility entry points for older external callers; deleting them would be a breaking API change, not safe cleanup.

## UI changes

- Layout editor summary now uses `Source`, `Mode`, and `Content template` instead of implementation-heavy phrases.
- `Advanced` replaces `More...`; `Save changes` replaces `Save layout`.
- Built-in and local layout source labels now describe user choices instead of PHP internals.
- Empty persisted file titles fall back to the filename, preventing blank page headings.
- Login form includes username metadata for password-manager and browser accessibility heuristics.
- Navigation starts collapsed below 45rem and opens as an overlay, so mobile content is usable immediately.

## Civic node design

- Familiar file list: Name, Type, Contents.
- Real folders and files remain primary navigation; decorative window controls are not fake buttons.
- Folder counts were removed because the old runtime reported false zero values.
- Local `index.php` is hidden from the content tree.
- Eight original political study texts cover democracy, commons, rights, care, assembly, and readable government.
- Neighbour links connect civic, artist, writer, and curator nodes without merging ownership.

## Verification

- Desktop root, nested folder, file reading view, and narrow viewport tested in a real browser.
- No browser errors. Current copied runtime still reports the password-form metadata warning; source fix awaits normal distribution build.
- PHP lint, focused UI/build tests, and full MCP suite cover source changes.

Source changes require the normal project build before every distributed single-file `index.php` receives the shell fixes. Node-local `.layout` and political texts are already live without that rebuild.
