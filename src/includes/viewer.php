<?php
/**
 * Viewer bootstrap: loads edit endpoints and render helpers.
 */

require_once __DIR__ . '/viewer/utils.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/viewer/edit.php';
require_once __DIR__ . '/viewer/render.php';

// Handle edit/prompt requests if present.
$viewerEditAction = $_GET['edit'] ?? '';
if (in_array($viewerEditAction, ['auth', 'config', 'save', 'prompt', 'upload', 'delete', 'reset', 'models'], true)) {
    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int) ($error['type'] ?? 0), $fatalTypes, true) || headers_sent()) {
            return;
        }
        cmsJsonResponse([
            'allowed' => true,
            'error' => sprintf(
                'Edit endpoint fatal error: %s in %s:%d',
                (string) ($error['message'] ?? 'Unknown error'),
                (string) ($error['file'] ?? 'unknown'),
                (int) ($error['line'] ?? 0)
            ),
        ], 500);
    });

    try {
        cmsHandleEditAction();
    } catch (Throwable $error) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => sprintf(
                'Edit endpoint error: %s in %s:%d',
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            ),
        ], 500);
    }
} else {
    cmsHandleEditAction();
}
