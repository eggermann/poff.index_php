<?php

function cmsHandleEditConfigAction(array $ctx): void
{
    $config = cmsAnnotateConfigWorktypeCatalog(
        $ctx['config'],
        $ctx['subjectType'],
        $ctx['targetDir'],
        $ctx['targetFile'],
        $ctx['folderViewData']
    );

    cmsJsonResponse([
        'allowed' => true,
        'target' => $ctx['isLayoutTarget'] ? 'layout' : $ctx['subjectType'],
        'subjectTarget' => $ctx['subjectType'],
        'config' => $config,
        'uploadLimits' => cmsUploadLimits(),
        'auth' => cmsBuildEditorAuthView($ctx['rootDir'], (bool) ($ctx['editModeAllowed'] ?? true)),
    ]);
}
