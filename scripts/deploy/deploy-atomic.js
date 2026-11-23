// Gemeinsamer Datei-Deploy für deploy.js und instances.js.
// Nutzt privaten Staging-Ordner, prüft PHP-Syntax/Hash, sichert die alte Datei außerhalb des Webroots
// und aktiviert die neue Version atomar. Kann ein Backup wiederherstellen und parallele Deploys sperren.
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

function shellEscape(value) {
    return "'" + String(value).replace(/'/g, "'\\''") + "'";
}

async function checkedCommand(ssh, command, message) {
    const result = await ssh.execCommand(command);
    if (result.code !== 0) {
        throw new Error(message);
    }
    return result.stdout.trim();
}

function fileDigest(file) {
    return crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
}

async function publishFile(ssh, { localFile, remoteFile, backupDir, expectedHash, restoreFrom, releaseRoot, onlyIfMissing = false }) {
    if (!path.posix.isAbsolute(remoteFile)) {
        throw new Error('Deploy destination must be absolute.');
    }
    if (localFile && remoteFile.endsWith('.php')) {
        execFileSync('php', ['-l', localFile], { stdio: 'pipe' });
    }
    const hash = localFile ? fileDigest(localFile) : null;
    if (!backupDir) {
        const home = await checkedCommand(ssh, 'printf "%s" "$HOME"', 'Could not resolve deploy account home.');
        if (!path.posix.isAbsolute(home)) throw new Error('Invalid deploy account home.');
        backupDir = path.posix.join(home, '.poff-deploy-backups');
    }
    const folder = path.posix.dirname(remoteFile);
    const fileKey = crypto.createHash('sha256').update(remoteFile).digest('hex').slice(0, 20);
    const backupName = new RegExp('^' + fileKey + '-[0-9]+-[a-f0-9]{16}\\.bak$');
    if (restoreFrom && (path.posix.dirname(restoreFrom) !== backupDir || !backupName.test(path.posix.basename(restoreFrom)))) {
        throw new Error('Backup does not belong to this deploy destination.');
    }
    const stamp = fileKey + '-' + Date.now() + '-' + crypto.randomBytes(8).toString('hex');
    const candidate = path.posix.join(backupDir, stamp + '.candidate');
    const backup = path.posix.join(backupDir, stamp + '.bak');
    const q = shellEscape;
    const releasePathCheck = releaseRoot
        ? ['test "$(realpath -m ' + q(folder) + ')" = "$(realpath -m ' + q(releaseRoot) + ')"' + q(folder.slice(releaseRoot.length))]
        : [];
    await checkedCommand(ssh, [
        'umask 077',
        'test ! -L ' + q(backupDir),
        'mkdir -p ' + q(backupDir),
        'chmod 700 ' + q(backupDir),
        ...releasePathCheck,
        'mkdir -p ' + q(folder),
        'test "$(stat -c %d ' + q(backupDir) + ')" = "$(stat -c %d ' + q(folder) + ')"',
        'case "$(realpath ' + q(backupDir) + ')/" in "$(realpath ' + q(releaseRoot || folder) + ')/"*) exit 1 ;; esac',
    ].join(' && '), 'Private staging requires a directory outside the destination on the same filesystem.');
    try {
        if (localFile) {
            await ssh.putFile(localFile, candidate);
        } else {
            await checkedCommand(ssh, 'test -f ' + q(restoreFrom) + ' && test ! -L ' + q(restoreFrom) + ' && cp ' + q(restoreFrom) + ' ' + q(candidate), 'Could not read deploy backup.');
        }
        const checks = [
            'umask 077',
            'exec 9>>' + q(path.posix.join(backupDir, fileKey + '.lock')),
            'flock -x 9',
            ...releasePathCheck,
            'test ! -L ' + q(remoteFile),
        ];
        if (remoteFile.endsWith('.php')) checks.push('php -l ' + q(candidate) + ' >/dev/null');
        if (hash) checks.push('test "$(sha256sum ' + q(candidate) + ' | cut -d " " -f 1)" = ' + q(hash));
        if (expectedHash) {
            checks.push('test -f ' + q(remoteFile) + ' && test "$(sha256sum ' + q(remoteFile) + ' | cut -d " " -f 1)" = ' + q(expectedHash));
        }
        if (onlyIfMissing) checks.push('if test -e ' + q(remoteFile) + '; then exit 0; fi');
        checks.push(
            'if test -e ' + q(remoteFile) + '; then test -f ' + q(remoteFile)
                + ' && chmod --reference=' + q(remoteFile) + ' ' + q(candidate)
                + ' && cp -p ' + q(remoteFile) + ' ' + q(backup)
                + ' && printf "%s" ' + q(backup)
                + '; else chmod 644 ' + q(candidate) + '; fi',
            'mv -T ' + q(candidate) + ' ' + q(remoteFile)
        );
        return await checkedCommand(ssh, checks.join(' && '), 'Remote validation or atomic replacement failed for ' + remoteFile);
    } finally {
        // Cleanup must not obscure the result of activation.
        await ssh.execCommand('rm -f ' + q(candidate)).catch(() => {});
    }
}

module.exports = { publishFile, shellEscape };
