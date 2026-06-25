# System Patterns *Optional*

This file documents recurring patterns and standards used in the project.
It is optional, but recommended to be updated as the project evolves.
2026-04-14 23:49:18 - Log of updates made.

*

## Coding Patterns

*   

## Architectural Patterns

*   
*  [2026-04-17 20:28:56] - Large SCSS feature areas can be split into feature-scoped partials and composed from a small entry partial using `@use`.
*  [2026-06-09] - Deploy targets can be named with a shared suffix resolver, letting `dodi1` map to `destinationPath1` while preserving the existing deploy flow.
*  [2026-06-09] - Remote converters can be implemented as small PHP endpoints that accept base64 source bytes and return `ok`, `output`, and `generatedBy` JSON.
*  [2026-06-09] - A named deploy alias can point at an existing public webspace when the user wants one label (`dodi1`) and one URL (`/dodi/`).
*  [2026-06-09] - Guest external links stay pending only for Poff-to-Poff submissions; authenticated editors can add plain film URLs directly.

## Testing Patterns

*
*  [2026-06-09] - Use PHP lint, focused Jest tests, and a browser harness when the live app runtime is stale or unavailable.
*  [2026-06-09] - When the production runtime is stale, validate the feature flow with a local harness that exercises the real converter endpoint.
