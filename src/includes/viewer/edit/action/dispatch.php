<?php
/**
 * Edit action dispatcher.
 */

function cmsHandleEditAction(): void
{
    $action = $_GET['edit'] ?? '';
    if (!in_array($action, ['auth', 'config', 'save', 'prompt', 'upload', 'delete', 'reset', 'models'], true)) {
        return;
    }

    $ctx = cmsBuildEditActionContext();
    switch ((string) ($ctx['action'] ?? '')) {
        case 'auth':
            cmsHandleEditAuthAction($ctx);
            return;
        case 'config':
            cmsHandleEditConfigAction($ctx);
            return;
        case 'upload':
            cmsHandleEditUploadAction($ctx);
            return;
        case 'delete':
            cmsHandleEditDeleteAction($ctx);
            return;
        case 'reset':
            cmsHandleEditResetAction($ctx);
            return;
        case 'save':
            cmsHandleEditSaveAction($ctx);
            return;
        case 'models':
            cmsHandleEditModelsAction($ctx);
            return;
        case 'prompt':
            cmsHandleEditPromptAction($ctx);
            return;
    }
}
