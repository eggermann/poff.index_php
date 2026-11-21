<?php

function cmsDefaultAuthPassword(): string
{
    return 'poff';
}

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

function cmsAuthCookieName(): string
{
    return 'poff_editor_auth';
}

function cmsAuthCookiePath(): string
{
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $directory = trim((string) dirname($scriptName), '/');
    if ($directory === '' || $directory === '.') {
        return '/';
    }

    return '/' . $directory . '/';
}

function cmsAuthCookieEncode(array $entries): string
{
    $json = json_encode($entries, JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return '';
    }

    return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
}

function cmsAuthCookieDecode(string $value): array
{
    $raw = trim($value);
    if ($raw === '') {
        return [];
    }

    $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
    if (!is_string($decoded) || $decoded === '') {
        return [];
    }

    $parsed = json_decode($decoded, true);
    return is_array($parsed) ? $parsed : [];
}

function cmsEditorAuthToken(string $rootDir, string $passwordHash): string
{
    $rootKey = cmsAuthSessionKey($rootDir);
    return hash_hmac('sha256', $rootKey . '|' . $passwordHash, $passwordHash);
}

function cmsAuthCookieToken(string $rootDir, string $passwordHash): string
{
    return cmsEditorAuthToken($rootDir, $passwordHash);
}

function cmsEditorAuthHeaderToken(): string
{
    return trim((string) ($_GET['authToken'] ?? $_SERVER['HTTP_X_POFF_EDITOR_AUTH'] ?? ''));
}

function cmsRestoreEditorAuthFromToken(string $rootDir, string $token): bool
{
    if ($token === '' || cmsAuthBypassEnabled()) {
        return false;
    }

    $config = cmsLoadAuthConfig($rootDir);
    $hash = (string) ($config['passwordHash'] ?? '');
    if ($hash === '') {
        return false;
    }

    $expected = cmsEditorAuthToken($rootDir, $hash);
    if (!hash_equals($expected, $token)) {
        return false;
    }

    cmsAuthStartSession();
    if (session_status() === PHP_SESSION_ACTIVE) {
        $rootKey = cmsAuthSessionKey($rootDir);
        $grants = $_SESSION['poff_can_edit_roots'] ?? [];
        if (!is_array($grants)) {
            $grants = [];
        }
        $grants[$rootKey] = true;
        $_SESSION['poff_can_edit_roots'] = $grants;
    }

    return true;
}

function cmsAuthCookieEntries(): array
{
    return cmsAuthCookieDecode((string) ($_COOKIE[cmsAuthCookieName()] ?? ''));
}

function cmsPersistEditorAuthCookie(string $rootDir, bool $allowed): void
{
    if (cmsAuthBypassEnabled() || headers_sent()) {
        return;
    }

    $cookieName = cmsAuthCookieName();
    $cookiePath = cmsAuthCookiePath();
    $entries = cmsAuthCookieEntries();
    $rootKey = cmsAuthSessionKey($rootDir);

    if ($allowed) {
        $config = cmsLoadAuthConfig($rootDir);
        $hash = (string) ($config['passwordHash'] ?? '');
        if ($hash === '') {
            return;
        }

        $entries[$rootKey] = cmsAuthCookieToken($rootDir, $hash);
    } else {
        unset($entries[$rootKey]);
    }

    if ($entries === []) {
        header(sprintf(
            'Set-Cookie: %s=; Expires=%s; Max-Age=0; Path=%s; %sHttpOnly; SameSite=Strict',
            $cookieName,
            gmdate('D, d M Y H:i:s T', time() - 3600),
            $cookiePath,
            !empty($_SERVER['HTTPS']) ? 'Secure; ' : ''
        ), false);
        return;
    }

    $encoded = cmsAuthCookieEncode($entries);
    if ($encoded === '') {
        return;
    }

    header(sprintf(
        'Set-Cookie: %s=%s; Path=%s; %sHttpOnly; SameSite=Strict',
        $cookieName,
        $encoded,
        $cookiePath,
        !empty($_SERVER['HTTPS']) ? 'Secure; ' : ''
    ), false);
}

function cmsRestoreEditorAuthFromCookie(string $rootDir): bool
{
    if (cmsAuthBypassEnabled()) {
        return true;
    }

    $entries = cmsAuthCookieEntries();
    if ($entries === []) {
        return false;
    }

    $config = cmsLoadAuthConfig($rootDir);
    $hash = (string) ($config['passwordHash'] ?? '');
    if ($hash === '') {
        return false;
    }

    $rootKey = cmsAuthSessionKey($rootDir);
    $cookieValue = (string) ($entries[$rootKey] ?? '');
    if ($cookieValue === '') {
        return false;
    }

    return cmsRestoreEditorAuthFromToken($rootDir, $cookieValue);
}

function cmsRestoreEditorAuthFromHeader(string $rootDir): bool
{
    return cmsRestoreEditorAuthFromToken($rootDir, cmsEditorAuthHeaderToken());
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

    $defaultPath = cmsAuthConfigCandidates($rootDir)[0] ?? '';
    if ($defaultPath !== '') {
        $hash = password_hash(cmsDefaultAuthPassword(), PASSWORD_DEFAULT);
        if (is_string($hash) && $hash !== '') {
            @file_put_contents($defaultPath, cmsAuthConfigContents($hash));
            if (is_file($defaultPath)) {
                return [
                    'configured' => true,
                    'passwordHash' => $hash,
                    'path' => $defaultPath,
                ];
            }
        }
    }

    return [
        'configured' => false,
        'passwordHash' => '',
        'path' => null,
    ];
}

function cmsWriteAuthConfig(string $path, string $hash): bool
{
    if ($path === '' || $hash === '') {
        return false;
    }

    return @file_put_contents($path, cmsAuthConfigContents($hash)) !== false;
}

function cmsAuthConfigContents(string $hash): string
{
    return '<' . "?php\n\nreturn [\n    'passwordHash' => '" . addslashes($hash) . "',\n];\n";
}

function cmsIsEditorAuthenticated(string $rootDir): bool
{
    if (cmsAuthBypassEnabled()) {
        return true;
    }

    cmsAuthStartSession();
    $rootKey = cmsAuthSessionKey($rootDir);
    $grants = $_SESSION['poff_can_edit_roots'] ?? null;

    if (is_array($grants) && !empty($grants[$rootKey])) {
        return true;
    }

    if (cmsRestoreEditorAuthFromHeader($rootDir)) {
        return true;
    }

    return cmsRestoreEditorAuthFromCookie($rootDir);
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
    cmsPersistEditorAuthCookie($rootDir, $allowed);
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

function cmsChangeEditorPassword(string $rootDir, string $currentPassword, string $newPassword): array
{
    $config = cmsLoadAuthConfig($rootDir);
    $hash = (string) ($config['passwordHash'] ?? '');
    $path = (string) ($config['path'] ?? '');

    if ($hash === '' || $path === '') {
        return ['ok' => false, 'error' => 'CMS auth is not configured.'];
    }
    if ($currentPassword === '' || !password_verify($currentPassword, $hash)) {
        return ['ok' => false, 'error' => 'Current password is incorrect.'];
    }
    if (trim($newPassword) === '') {
        return ['ok' => false, 'error' => 'New password must not be empty.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($newHash) || $newHash === '') {
        return ['ok' => false, 'error' => 'Failed to hash the new password.'];
    }
    if (!cmsWriteAuthConfig($path, $newHash)) {
        return ['ok' => false, 'error' => 'Failed to write .poff-auth.php.'];
    }

    return ['ok' => true, 'path' => $path];
}
