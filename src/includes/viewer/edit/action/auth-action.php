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

    if ($intent === 'change-password') {
        if (!$editModeAllowed || !cmsIsEditorAuthenticated($rootDir)) {
            cmsJsonResponse([
                'allowed' => false,
                'error' => cmsEditorAuthError($rootDir, $editModeAllowed),
                'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
            ], 403);
        }

        $currentPassword = (string) ($ctx['data']['currentPassword'] ?? $ctx['data']['current_password'] ?? '');
        $newPassword = (string) ($ctx['data']['newPassword'] ?? $ctx['data']['new_password'] ?? '');
        $confirmPassword = (string) ($ctx['data']['confirmPassword'] ?? $ctx['data']['confirm_password'] ?? '');
        if ($newPassword !== $confirmPassword) {
            cmsJsonResponse([
                'allowed' => false,
                'error' => 'New password confirmation does not match.',
                'auth' => cmsBuildEditorAuthView($rootDir, true),
            ], 400);
        }

        $result = cmsChangeEditorPassword($rootDir, $currentPassword, $newPassword);
        if (empty($result['ok'])) {
            cmsJsonResponse([
                'allowed' => false,
                'error' => (string) ($result['error'] ?? 'Failed to change password.'),
                'auth' => cmsBuildEditorAuthView($rootDir, true),
            ], 400);
        }

        cmsJsonResponse([
            'allowed' => true,
            'changed' => true,
            'auth' => cmsBuildEditorAuthView($rootDir, true),
        ]);
    }

    cmsJsonResponse([
        'allowed' => $editModeAllowed,
        'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
        'error' => $editModeAllowed ? null : cmsEditorAuthError($rootDir, false),
    ]);
}
