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
