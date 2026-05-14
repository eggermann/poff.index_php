<?php

function cmsHandleEditAuthAction(array $ctx): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $rootDir = (string) ($ctx['rootDir'] ?? getcwd() ?: '.');
    $editModeAllowed = (bool) ($ctx['editModeAllowed'] ?? false);

    if ($method === 'GET') {
        cmsJsonResponse([
            'allowed' => $editModeAllowed,
            'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
            'error' => $editModeAllowed ? null : cmsEditorAuthError($rootDir, false),
        ]);
    }

    if ($method !== 'POST') {
        cmsJsonResponse(['allowed' => false, 'error' => 'Auth requires GET or POST.'], 405);
    }

    $intent = trim((string) ($ctx['data']['intent'] ?? $ctx['data']['action'] ?? 'status'));
    if ($intent === 'logout') {
        cmsSetEditorAuthenticated($rootDir, false);
        cmsJsonResponse([
            'allowed' => $editModeAllowed,
            'authenticated' => false,
            'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
        ]);
    }

    if ($intent === 'login') {
        if (!$editModeAllowed) {
            cmsJsonResponse([
                'allowed' => false,
                'error' => cmsEditorAuthError($rootDir, false),
                'auth' => cmsBuildEditorAuthView($rootDir, false),
            ], 403);
        }

        $password = (string) ($ctx['data']['password'] ?? '');
        if (!cmsAttemptEditorLogin($rootDir, $password)) {
            cmsJsonResponse([
                'allowed' => false,
                'error' => cmsEditorAuthError($rootDir, true),
                'auth' => cmsBuildEditorAuthView($rootDir, true),
            ], 403);
        }

        cmsJsonResponse([
            'allowed' => true,
            'authenticated' => true,
            'sessionId' => session_id(),
            'auth' => cmsBuildEditorAuthView($rootDir, true),
        ]);
    }

    cmsJsonResponse([
        'allowed' => $editModeAllowed,
        'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
        'error' => $editModeAllowed ? null : cmsEditorAuthError($rootDir, false),
    ]);
}
