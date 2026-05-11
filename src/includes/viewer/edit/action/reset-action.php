<?php

function cmsHandleEditResetAction(array $ctx): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Reset requires POST.'], 405);
    }

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
