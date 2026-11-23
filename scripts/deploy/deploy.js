// Haupt-Deploy per SSH: index.php, vollständiger Dateiabgleich, Release-Paket oder Backup-Rollback.
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const { NodeSSH } = require('node-ssh');
const { isNewerFile, isDeployableContent, releaseFiles, releaseProfileImage } = require('./deploy-files');
const { publishFile, shellEscape } = require('./deploy-atomic');
const {
    resolveDeployTargetProfile,
    resolveTargetEnvValue,
    requireTargetEnvValue,
    getEnvValue,
} = require('./deploy-targets');

const projectRoot = path.resolve(__dirname, '../..');
const ssh = new NodeSSH();

function loadEnv(rootDir) {
    const envPath = path.join(rootDir, '.env');
    if (!fs.existsSync(envPath)) {
        return {};
    }

    const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
    const env = {};
    for (const rawLine of lines) {
        const line = rawLine.trim();
        if (!line || line.startsWith('#')) {
            continue;
        }

        const separatorIndex = line.indexOf('=');
        if (separatorIndex === -1) {
            continue;
        }

        const key = line.slice(0, separatorIndex).trim();
        let value = line.slice(separatorIndex + 1).trim();
        value = value.replace(/^['"]|['"]$/g, '');
        if (key) {
            env[key] = value;
        }
    }

    return env;
}

function resolveDeployMode(argv, env) {
    const cliArgs = new Set(argv);
    if (cliArgs.has('--release')) {
        return 'release';
    }
    if (cliArgs.has('--full')) {
        return 'full';
    }
    if (cliArgs.has('--index-only')) {
        return 'index';
    }

    const configuredMode = getEnvValue(env, ['DEPLOY_MODE', 'deployMode'], 'index').toLowerCase();
    return configuredMode === 'full' ? 'full' : 'index';
}

function resolveDeployTarget(argv) {
    const positionalArgs = argv.filter((arg) => !arg.startsWith('-'));
    const targetArg = positionalArgs.length > 0 ? positionalArgs.join('_') : 'default';
    return resolveDeployTargetProfile(targetArg);
}

function resolveIndexFilePath(sourcePath) {
    const stat = fs.statSync(sourcePath);
    if (stat.isDirectory()) {
        return path.join(sourcePath, 'index.php');
    }
    return sourcePath;
}

function resolveRemoteIndexPath(remotePath, localIndexPath) {
    const normalizedRemotePath = remotePath.replace(/\\/g, '/');
    if (normalizedRemotePath.endsWith('.php')) {
        return normalizedRemotePath;
    }
    return path.posix.join(normalizedRemotePath, path.basename(localIndexPath));
}

async function getRemoteFileTimestamp(remoteFilePath) {
    const cmd = `stat -c %Y ${shellEscape(remoteFilePath)} 2>/dev/null || stat -f %m ${shellEscape(remoteFilePath)} 2>/dev/null`;
    const result = await ssh.execCommand(cmd);
    const value = (result.stdout || '').trim();
    if (!value) {
        return null;
    }

    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
}

async function uploadIndexFile(localFilePath, remoteFilePath) {
    if (!fs.existsSync(localFilePath) || !fs.statSync(localFilePath).isFile()) {
        throw new Error(`Deploy index file not found: ${localFilePath}`);
    }

    const localTime = (fs.statSync(localFilePath).mtimeMs || 0) / 1000;
    const remoteTime = await getRemoteFileTimestamp(remoteFilePath);

    if (!isNewerFile(localTime, remoteTime)) {
        console.log('Skipped (remote is newer or same): index.php');
        return;
    }

    const backup = await publishFile(ssh, { localFile: localFilePath, remoteFile: remoteFilePath });
    console.log('Uploaded:', remoteFilePath);
    if (backup) console.log('Backup:', backup);
}

async function uploadDir(localDir, remoteDir, concurrency) {
    const failed = [];
    const successful = [];
    const skipped = [];
    const remoteFiles = new Map();

    console.log('Checking remote files...');
    const target = shellEscape(remoteDir);
    const findCmd = `if test -e ${target}; then test -d ${target} && find ${target} -type f -printf '%T@ %P\\0'; fi`;
    const result = await ssh.execCommand(findCmd);
    if (result.code !== 0) {
        throw new Error('Could not list remote files before deploy.');
    }
    for (const entry of result.stdout.split('\0')) {
        const separator = entry.indexOf(' ');
        if (separator > 0) {
            remoteFiles.set(entry.slice(separator + 1), Number(entry.slice(0, separator)));
        }
    }

    await ssh.putDirectory(localDir, remoteDir, {
        recursive: true,
        concurrency,
        validate(itemPath) {
            const relativePath = path.relative(localDir, itemPath).split(path.sep).join('/');
            if (!isDeployableContent(relativePath)) {
                return false;
            }

            const stat = fs.lstatSync(itemPath);
            if (stat.isSymbolicLink()) {
                console.log('Skipping symlink:', itemPath);
                return false;
            }
            if (stat.isFile()) {
                if (!isNewerFile(stat.mtimeMs / 1000, remoteFiles.get(relativePath))) {
                    skipped.push(relativePath);
                    return false;
                }
            }

            return true;
        },
        tick(localPath, remotePath, error) {
            if (error) {
                console.log('Failed:', localPath);
                failed.push(localPath);
                return;
            }

            try {
                const stat = fs.statSync(localPath);
                if (!stat.isFile()) {
                    return;
                }

                const relativePath = path.relative(localDir, localPath);
                console.log('Uploaded:', relativePath);
                successful.push(relativePath);
            } catch (error) {
                console.log('Failed:', localPath, error);
                failed.push(localPath);
            }
        },
    });

    console.log('\nSync Summary:');
    console.log('------------');
    if (successful.length > 0) {
        console.log('Updated files:', successful.length);
    }
    if (skipped.length > 0) {
        console.log('Unchanged files:', skipped.length);
    }
    if (failed.length > 0) {
        console.log('Failed files:', failed.length);
        throw new Error(`Deploy failed for ${failed.length} files.`);
    }
}

function resolveDeployPlan(env, deployTarget, deployMode) {
    const host = requireTargetEnvValue(
        env,
        ['DEPLOY_HOST', 'SSH_HOST', 'host'],
        deployTarget.suffix,
        'DEPLOY_HOST / SSH_HOST / host'
    );
    const username = requireTargetEnvValue(
        env,
        ['DEPLOY_USERNAME', 'SSH_USERNAME', 'SSH_USER', 'user'],
        deployTarget.suffix,
        'DEPLOY_USERNAME / SSH_USERNAME / SSH_USER / user'
    );
    const remotePath = requireTargetEnvValue(
        env,
        ['DEPLOY_REMOTE_PATH', 'SSH_REMOTE_PATH', 'DESTINATION_PATH', 'destinationPath'],
        deployTarget.suffix,
        'DEPLOY_REMOTE_PATH / SSH_REMOTE_PATH / DESTINATION_PATH / destinationPath'
    );
    if (!path.posix.isAbsolute(remotePath) || path.posix.resolve(remotePath) === '/') {
        throw new Error('Deploy destination must be an absolute path below the server root.');
    }
    if (deployMode === 'rollback') {
        return { target: deployTarget.targetName, mode: deployMode, host, username, remote: remotePath };
    }
    const sourcePathValue = deployMode === 'release' ? 'dist' : requireTargetEnvValue(
        env,
        ['DEPLOY_SOURCE_PATH', 'SOURCE_PATH', 'sourcePath'],
        deployTarget.suffix,
        'DEPLOY_SOURCE_PATH / SOURCE_PATH / sourcePath'
    );
    const sourcePath = path.isAbsolute(sourcePathValue)
        ? sourcePathValue
        : path.resolve(projectRoot, sourcePathValue);
    if (!fs.existsSync(sourcePath) || !fs.statSync(sourcePath).isDirectory()) {
        throw new Error(`Deploy source directory not found: ${sourcePath}`);
    }
    return { target: deployTarget.targetName, mode: deployMode, host, username, source: sourcePath, remote: remotePath };
}

function resolveDeployAuth(env, suffix) {
    const password = resolveTargetEnvValue(
        env,
        ['DEPLOY_PASSWORD', 'SSH_PASSWORD', 'password'],
        suffix
    );
    const privateKeyValue = resolveTargetEnvValue(
        env,
        ['DEPLOY_PRIVATE_KEY', 'SSH_PRIVATE_KEY', 'privateKey'],
        suffix
    );
    const passphrase = resolveTargetEnvValue(
        env,
        ['DEPLOY_PASSPHRASE', 'SSH_PASSPHRASE', 'passphrase'],
        suffix
    );

    if (privateKeyValue) {
        const privateKeyPath = path.isAbsolute(privateKeyValue)
            ? privateKeyValue
            : path.resolve(projectRoot, privateKeyValue);
        return passphrase ? { privateKeyPath, passphrase } : { privateKeyPath };
    }
    return password ? { password } : null;
}

async function main() {
    const env = loadEnv(projectRoot);
    const argv = process.argv.slice(2);
    const rollbackOption = argv.find(arg => arg.startsWith('--rollback='));
    const flags = argv.filter(arg => ['--release', '--full', '--index-only'].includes(arg));
    if (flags.length > 1 || (rollbackOption && flags.length) || argv.includes('--rollback')) {
        throw new Error('Select one mode: --release, --full, --index-only or --rollback=/absolute/backup.bak.');
    }
    const restoreFrom = rollbackOption?.slice('--rollback='.length);
    const deployTarget = resolveDeployTarget(argv);
    const deployMode = rollbackOption ? 'rollback' : resolveDeployMode(argv, env);
    if (rollbackOption && !path.posix.isAbsolute(restoreFrom)) throw new Error('Use an absolute backup path.');
    const { username, ...plan } = resolveDeployPlan(env, deployTarget, deployMode);
    const { host, source: sourcePath, remote: remotePath } = plan;
    if ((deployMode === 'release' || deployMode === 'full') && remotePath.endsWith('.php')) {
        throw new Error('Release and full mode need a remote directory.');
    }
    const sidecars = deployMode === 'release' ? releaseFiles(sourcePath) : [];
    const localIndexPath = restoreFrom ? null : resolveIndexFilePath(sourcePath);
    if (localIndexPath && !fs.lstatSync(localIndexPath, { throwIfNoEntry: false })?.isFile()) {
        throw new Error(`Deploy index file not found or is a symlink: ${localIndexPath}`);
    }
    if (argv.includes('--dry-run')) {
        console.log(JSON.stringify({ ...plan, ...(deployMode === 'release' ? { files: [...sidecars, 'index.php'] } : {}), ...(restoreFrom ? { restoreFrom } : {}) }, null, 2));
        return;
    }
    const concurrency = Number.parseInt(
        getEnvValue(env, ['DEPLOY_CONCURRENCY', 'SSH_CONCURRENCY', 'concurrency'], '8'),
        10
    ) || 8;
    const auth = resolveDeployAuth(env, deployTarget.suffix);
    if (!auth) {
        throw new Error(`Set DEPLOY_PASSWORD or DEPLOY_PRIVATE_KEY for deploy profile ${plan.target}.`);
    }
    if (localIndexPath) {
        for (const file of [localIndexPath, ...sidecars.filter(file => file.endsWith('.php')).map(file => path.join(sourcePath, file))]) {
            execFileSync('php', ['-l', file], { stdio: 'pipe' });
        }
    }

    console.log('Connecting to server...');
    console.log(`Source: ${sourcePath}`);
    console.log(`Remote: ${remotePath}`);
    console.log(`Target: ${deployTarget.targetName}`);
    console.log(`Mode: ${deployMode}`);

    try {
        await ssh.connect({ host, username, ...auth });
        const remoteIndexPath = resolveRemoteIndexPath(remotePath, 'index.php');
        if (restoreFrom) {
            const backup = await publishFile(ssh, { remoteFile: remoteIndexPath, restoreFrom });
            console.log('Restored:', remoteIndexPath);
            if (backup) console.log('Backup:', backup);
            return;
        }
        if (deployMode === 'full') {
            console.log('Starting sync of newer files...');
            await uploadDir(sourcePath, remotePath, concurrency);
        }

        for (const relative of sidecars) {
            const backup = await publishFile(ssh, {
                localFile: path.join(sourcePath, relative),
                remoteFile: path.posix.join(remotePath, relative),
                releaseRoot: path.posix.normalize(remotePath).replace(/\/$/, ''),
                onlyIfMissing: relative === releaseProfileImage,
            });
            if (backup) console.log('Backup:', backup);
        }
        if (deployMode === 'release') {
            const backup = await publishFile(ssh, { localFile: localIndexPath, remoteFile: remoteIndexPath });
            console.log('Published release:', remoteIndexPath);
            if (backup) console.log('Backup:', backup);
            return;
        }
        await uploadIndexFile(localIndexPath, remoteIndexPath);
    } finally {
        ssh.dispose();
    }
}

if (require.main === module) {
    main().catch((error) => {
        console.error('Error:', error.message);
        process.exit(1);
    });
}

module.exports = { uploadDir, loadEnv, resolveDeployPlan, resolveDeployAuth };
