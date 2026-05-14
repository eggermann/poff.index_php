<?php

function cmsAuthConfigCandidates(string $rootDir): array
{
    $root = rtrim($rootDir, DIRECTORY_SEPARATOR);
    return [
        $root . DIRECTORY_SEPARATOR . '.poff-auth.php',
        $root . DIRECTORY_SEPARATOR . 'auth.config.php',
    ];
}

function cmsAuthSessionKey(string $rootDir): string
{
    $resolved = realpath($rootDir);
    if (!is_string($resolved) || $resolved === '') {
        $resolved = $rootDir;
    }

    return sha1($resolved);
}

function cmsAuthBypassEnabled(): bool
{
    $flag = getenv('POFF_TEST_AUTH_BYPASS');
    return is_string($flag) && in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'on'], true);
}

function cmsAuthStartSession(): void
{
    if (cmsAuthBypassEnabled() || session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
        return;
    }

    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'cookie_secure' => !empty($_SERVER['HTTPS']),
    ]);
}

function cmsLoadAuthConfig(string $rootDir): array
{
    foreach (cmsAuthConfigCandidates($rootDir) as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }

        $loaded = require $candidate;
        $hash = '';

        if (is_string($loaded)) {
            $hash = trim($loaded);
        } elseif (is_array($loaded)) {
            $hash = trim((string) ($loaded['passwordHash'] ?? $loaded['password_hash'] ?? $loaded['hash'] ?? ''));
        }

        return [
            'configured' => $hash !== '',
            'passwordHash' => $hash,
            'path' => $candidate,
        ];
    }

    return [
        'configured' => false,
        'passwordHash' => '',
        'path' => null,
    ];
}

function cmsIsEditorAuthenticated(string $rootDir): bool
{
    if (cmsAuthBypassEnabled()) {
        return true;
    }

    cmsAuthStartSession();
    $rootKey = cmsAuthSessionKey($rootDir);
    $grants = $_SESSION['poff_can_edit_roots'] ?? null;

    return is_array($grants) && !empty($grants[$rootKey]);
}

function cmsSetEditorAuthenticated(string $rootDir, bool $allowed): void
{
    if (cmsAuthBypassEnabled()) {
        return;
    }

    cmsAuthStartSession();
    $rootKey = cmsAuthSessionKey($rootDir);
    $grants = $_SESSION['poff_can_edit_roots'] ?? [];
    if (!is_array($grants)) {
        $grants = [];
    }

    if ($allowed) {
        $grants[$rootKey] = true;
    } else {
        unset($grants[$rootKey]);
    }

    $_SESSION['poff_can_edit_roots'] = $grants;
}

function cmsBuildEditorAuthView(string $rootDir, bool $editModeAllowed = true): array
{
    $config = cmsLoadAuthConfig($rootDir);
    $authenticated = cmsIsEditorAuthenticated($rootDir);
    $configured = (bool) ($config['configured'] ?? false);

    return [
        'configured' => $configured,
        'authenticated' => $authenticated,
        'editModeAllowed' => $editModeAllowed,
        'canEdit' => $editModeAllowed && ($authenticated || cmsAuthBypassEnabled()),
        'configPath' => $config['path'],
    ];
}

function cmsEditorAuthError(string $rootDir, bool $editModeAllowed = true): string
{
    if (!$editModeAllowed) {
        return 'Edit mode not enabled.';
    }

    $config = cmsLoadAuthConfig($rootDir);
    if (empty($config['configured'])) {
        return 'CMS auth is not configured. Create .poff-auth.php with a password hash.';
    }

    return 'Edit login required.';
}

function cmsRequireEditorAccess(string $rootDir, bool $editModeAllowed = true): void
{
    if (cmsAuthBypassEnabled()) {
        return;
    }

    if (!$editModeAllowed || !cmsIsEditorAuthenticated($rootDir)) {
        cmsJsonResponse([
            'allowed' => false,
            'error' => cmsEditorAuthError($rootDir, $editModeAllowed),
            'auth' => cmsBuildEditorAuthView($rootDir, $editModeAllowed),
        ], 403);
    }
}

function cmsAttemptEditorLogin(string $rootDir, string $password): bool
{
    $config = cmsLoadAuthConfig($rootDir);
    $hash = (string) ($config['passwordHash'] ?? '');
    if ($hash === '' || $password === '') {
        return false;
    }

    if (!password_verify($password, $hash)) {
        return false;
    }

    cmsAuthStartSession();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    cmsSetEditorAuthenticated($rootDir, true);
    return true;
}
