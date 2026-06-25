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
