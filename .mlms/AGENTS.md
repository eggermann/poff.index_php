# AGENTS.md
## Codex Workflow
Before doing implementation work:
1. Read `.mlms/projectBrief.md`.
2. Read all files in `.mlms/memory-bank/`.
3. Identify current loop phase from `.mlms/memory-bank/activeContext.md`.
During work:
1. Keep changes small.
2. Prefer a vertical slice over broad unfinished edits.
3. Record important architectural or product decisions.
4. Run available tests/checks when possible.
5. Review diff before proposing commit.
After meaningful work:
1. Update `.mlms/memory-bank/activeContext.md`.
2. Update `.mlms/memory-bank/progress.md`.
3. Update `.mlms/memory-bank/decisionLog.md` if a decision was made.
4. Update `.mlms/memory-bank/sprintReview.md` after review.
5. State next recommended action.
