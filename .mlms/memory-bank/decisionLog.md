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
