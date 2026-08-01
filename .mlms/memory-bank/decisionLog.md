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
