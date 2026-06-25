<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/includes/auth.php';

$cwdArg = $argv[1] ?? '';
$password = $argv[2] ?? 'secret-pass';

$cwd = realpath($cwdArg);
if ($cwd === false || !is_dir($cwd)) {
    fwrite(STDERR, "Invalid cwd: {$cwdArg}\n");
    exit(1);
}

$_SERVER['SCRIPT_NAME'] = '/dodi/index.php';
$_SERVER['HTTPS'] = 'on';
$_COOKIE = [];
$_SESSION = [];

$hash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($hash) || $hash === '') {
    fwrite(STDERR, "Failed to hash password.\n");
    exit(1);
}

file_put_contents($cwd . DIRECTORY_SEPARATOR . '.poff-auth.php', "<?php return ['passwordHash' => '" . addslashes($hash) . "'];\n");
file_put_contents($cwd . DIRECTORY_SEPARATOR . '.edit.allow', '');

$config = cmsLoadAuthConfig($cwd);
$passwordOk = password_verify($password, (string) ($config['passwordHash'] ?? ''));
$token = cmsEditorAuthToken($cwd, (string) ($config['passwordHash'] ?? ''));

$_GET['authToken'] = $token;
$_COOKIE = [];
$_SESSION = [];

$restored = cmsIsEditorAuthenticated($cwd);
$authView = cmsBuildEditorAuthView($cwd, true);

echo json_encode([
    'passwordOk' => $passwordOk,
    'token' => $token,
    'restored' => $restored,
    'authenticated' => $authView['authenticated'] ?? false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
