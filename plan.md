# Build and Modularization Plan

## Overview
This plan outlines the steps to achieve the following objectives:
1. Copy pages/dominikeggermann.com/index.php to src/index.php.
2. Modularize the file into components (header.php, menu.php, footer.php, etc.) located under src/includes/.
3. Develop a build process (likely modifying build/build.php) to concatenate the modular files to create pages/dominikeggermann.com/index.php while preserving the relative path structure.
4. Integrate SSH-upload by automatically triggering SSH-upload.node.js after the build.

## Detailed Steps

### 1. Copy Original File
- File: pages/dominikeggermann.com/index.php
- Destination: src/index.php

### 2. Modularization
- Split src/index.php into modular files:
  - header.php
  - menu.php
  - footer.php
  - Additional components as needed
- Organize subfiles in src/includes/ and ensure the relative paths in the menu structure remain intact.

### 3. Build Process
- Use/modify build/build.php to:
  - Concatenate the modular files to generate a complete file.
  - Output the built file to pages/dominikeggermann.com/index.php.
  - Preserve relative paths that work with server sub-folders.

### 4. SSH-upload Integration
- Automatically trigger SSH-upload.node.js after the build to upload pages/dominikeggermann.com/index.php via SSH.

## Flowchart

```mermaid
flowchart TD
    A[Start]
    B[Copy pages/dominikeggermann.com/index.php to src/index.php]
    C[Split src/index.php into modular files<br/>(header.php, menu.php, footer.php, etc.)]
    D[Organize modular files in src/includes/]
    E[Develop/Update build/build.php script]
    F[Concatenate modular files to generate build file<br/>at pages/dominikeggermann.com/index.php]
    G[Ensure relative paths are preserved<br/>for server subfolders]
    H[Invoke SSH-upload.node.js automatically]
    I[End]
    A --> B
    B --> C
    C --> D
    D --> E
    E --> F
    F --> G
    G --> H
    H --> I
```

## Summary
This plan will:
- Copy the original index.php to src/index.php.
- Modularize development files for easier management.
- Provide an automated build process to reassemble the index.php.
- Automatically upload the build via SSH after a successful build.