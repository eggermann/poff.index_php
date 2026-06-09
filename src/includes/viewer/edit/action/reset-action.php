<?php

function cmsHandleEditResetAction(array $ctx): void
{
    cmsEditRequirePost('Reset');

    $resetResult = cmsResetFolderTarget($ctx['rootDir'], $ctx['path']);
    if (($resetResult['errors'] ?? []) !== []) {
        cmsJsonResponse(['allowed' => true, 'error' => $resetResult['errors'][0] ?? 'Reset failed.'], 400);
    }

    cmsJsonResponse([
        'allowed' => true,
        'reset' => $resetResult['reset'],
        'config' => $resetResult['config'],
    ]);
}
