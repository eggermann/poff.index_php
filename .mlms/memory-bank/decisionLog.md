# Decision Log
## Decisions
### D-0001: Use Memory Bank + Scrum/Vibe Mini-Loop
Status: Accepted
Context:
Project needs persistent context across sessions and repeatable development rhythm.
Decision:
Use a memory-bank directory for durable context and a Scrum/Vibe mini-loop for planning, implementation, review, retrospective, and memory updates.
Consequences:
Codex must read and update memory-bank files during each loop.
### D-0002: Active-work folder naming should avoid existing directories
Status: Accepted
Context:
The active-work folder helper only checked for file collisions, which could reuse a name that already existed as a directory.
Decision:
Treat any existing path as a collision when selecting the work-folder name.
Consequences:
Folder naming now safely skips already-present directories as well as files.
### D-0003: node-1 and node-2 must start empty and independent
Status: Accepted
Context:
The sibling localhost folders were accidentally turned into aliases or cloned trees.
Decision:
Keep `node-1` and `node-2` as real independent directories and clear their duplicated starter content so they begin empty.
Consequences:
Their initial browser state is an empty directory listing instead of a copy of `MAUSMAUS`.

### D-0004: Bulk conversion preserves files and changes tree visibility
Status: Accepted
Context:
Editors need “converted only”, “originals only”, and “both” views without risking source loss.
Decision:
Bulk conversion never deletes or replaces source media. It writes generated works, then updates `tree[].visible` according to an explicit visibility mode.
Consequences:
Visibility is reversible through existing editor controls. Re-running bulk conversion replaces the same generated output path instead of multiplying files.

### D-0005: Provider choice uses presets plus the generic Poff node protocol
Status: Accepted
Context:
Editors could not tell whether conversion should happen locally, on Pageoff, or on another compatible node.
Decision:
Offer “This node” and `pageoff.de` as explicit presets while retaining an arbitrary Poff node URL field. All remote providers use the same converter-offer and inline-result protocol.
Consequences:
Pageoff is convenient without becoming a hardcoded special protocol, and every Poff node can offer converters after deploying the same route implementation.

### D-0006: External takeover must expose intent before transfer
Status: Accepted
Context:
Current UI conflates copying a name, linking a work, importing remote metadata, and copying media.
Decision:
Use one “Use this work” entry point with three explicit modes: Follow live source, Embed remote work, and Copy locally. Local copy should optionally create a suitable work such as a looping playlist.
Consequences:
Users understand ownership and update behavior before acting. Binary copying requires a guarded extension to the remote import API.

### D-0007: Local copy uses a guarded same-host export contract
Status: Accepted
Context:
Remote manifests can otherwise point a receiving node at arbitrary hosts or oversized content.
Decision:
Copy mode accepts only public HTTP(S), disables redirects, requires asset hosts to match the export host, limits file count and bytes, sanitizes names, stages in a hidden temporary folder, and never overwrites an existing destination.
Consequences:
Any Poff node can offer a portable work while the receiver retains clear resource and SSRF boundaries.

### D-0008: Three persona nodes remain independent and visually authored
Status: Accepted
Context:
The study must demonstrate decentralization rather than three mirrored copies of one site.
Decision:
Create separate MAMP roots for an artist, a writer, and a curator-engineer. Share selected work or layout assets explicitly while preserving each node's own layout and attribution.
Consequences:
The demo can expose exchange UX honestly, compare three looks, and keep each node portable as one entrypoint plus filesystem content.

### D-0009: Distribution targets belong in one shared build config
Status: Accepted
Context:
PHP build and JavaScript watcher duplicated hardcoded MAMP targets, and the normal build missed the project-root target already supported by the watcher.
Decision:
Store MAMP root, standalone targets, and additional-index cleanup policy in `BuildConfig.shared.json`; both build paths consume the exported config.
Consequences:
Adding a node requires one config edit, builder and watcher stay aligned, and nested cleanup can be disabled for a parent target that must retain child entrypoints.

### D-0010: The single-file entrypoint is never content
Status: Accepted
Context:
New node roots exposed generated `index.php` as a public work card.
Decision:
Treat `index.php` as a hidden system entry during Poff tree scans.
Consequences:
Visitors see authored works only; single-file implementation remains portable but invisible.

### D-0011: Public navigation is progressive, editor navigation stays immediate
Status: Accepted
Context:
The public root displayed the same work tree in both the sidebar and the main surface, while each route also made a separate slow navigation request.
Decision:
Start public visitors on the work surface with an explicit Explore work control. Fetch the sidebar only when it opens; preserve the always-open sidebar for editor mode. Keep a small in-session preview cache for backtracking.
Consequences:
The first route renders fewer competing layers and follow-up visits are immediate when cached. Editor workflows retain their full navigation tree.

### D-0012: Layout source stays static across the single-file build
Status: Accepted
Context:
The entrypoint builder strips PHP tags from `src/includes/layout.php`, so PHP branching in that source rendered literal PHP variables in the generated output.
Decision:
Keep the public layout markup static and let client JavaScript open the sidebar when editor mode is active.
Consequences:
The generated entrypoint is valid in both public and editor paths without depending on PHP execution inside this build-time template.

### D-0013: Production owner story is host-scoped and layout-native
Status: Accepted
Context:
The production root's persisted folder name did not identify the site, and preview extraction discarded story markup placed before the layout wrapper.
Decision:
Show the story for the production hostname (or an explicitly matching stored folder name), and pass it into the default layout main as trusted rendered markup.
Consequences:
The public domain receives the intended entry story while nested works and other nodes remain unchanged; client preview navigation retains the story.
