<?php
/**
 * Viewer bootstrap: loads edit endpoints and render helpers.
 */

require_once __DIR__ . '/viewer/utils.php';
require_once __DIR__ . '/viewer/edit.php';
require_once __DIR__ . '/viewer/render.php';

// Handle edit/prompt requests if present.
cmsHandleEditAction();
