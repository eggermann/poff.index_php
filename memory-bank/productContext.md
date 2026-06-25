# Product Context

This file provides a high-level overview of the project and the expected product that will be created. Initially it is based upon projectBrief.md (if provided) and all other available project-related information in the working directory. This file is intended to be updated as the project evolves, and should be used to inform all other modes of the project's goals and context.
2026-04-14 23:49:01 - Log of updates made will be appended as footnotes to the end of this file.

*

## Project Goal

*   
*  [2026-06-09] - Keep guest edit mode minimal by removing the public external-link submission panel.
*  [2026-06-09] - Let dodi simulate a user converting TIFF assets through a Pageoff remote converter while clearly marking external generated outputs.
*  [2026-06-09] - Keep the docs honest about the live pages: `dodi2` has no film uploads, and `dodi` is the eggermann copy tree.

## Key Features

*   
*  [2026-06-09] - Guest edit mode shows a minimal password prompt instead of a submission form.
*  [2026-06-09] - Remote Pageoff converter options expose public Pageoff editor URLs and endpoint metadata separately.
*  [2026-06-09] - Remote image conversion targets use an `external-` filename prefix.
*  [2026-06-09] - Current-state audit notes for the live `dodi2` and `dodi` pages.
*  [2026-06-09] - Copy-tree browsing behavior on `dodi`.
*  [2026-06-09] - No film-upload workflow implied where none exists.

## Overall Architecture

*   
*  [2026-06-09] - Public guest edit states can be reduced to a simple message without the upload/submission dialog.
*  [2026-06-09] - Converter definitions are the source of truth for both UI-facing URLs and backend remote endpoints.
*  [2026-06-09] - Treat the public pages as copy-tree/browser surfaces unless an explicit new workflow is added later.
