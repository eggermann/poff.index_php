const fs = require('fs');
const path = require('path');
const { NodeSSH } = require('node-ssh');

const projectRoot = path.resolve(__dirname, '..');
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

function getEnvValue(env, key, fallback = '') {
    const processValue = process.env[key];
    if (typeof processValue === 'string' && processValue !== '') {
        return processValue;
    }

    const envValue = env[key];
    if (typeof envValue === 'string' && envValue !== '') {
        return envValue;
    }

    return fallback;
}

function requireEnvValue(env, key) {
    const value = getEnvValue(env, key);
    if (!value) {
        throw new Error(`Missing required deploy setting: ${key}`);
    }
    return value;
}

function shellEscape(value) {
    return `'${String(value).replace(/'/g, `'\\''`)}'`;
}

function resolveSourcePath(env) {
    const configuredPath = getEnvValue(env, 'DEPLOY_SOURCE_PATH', 'pages/dominikeggermann.com');
    return path.isAbsolute(configuredPath)
        ? configuredPath
        : path.resolve(projectRoot, configuredPath);
}

async function uploadDir(localDir, remoteDir, concurrency) {
    const failed = [];
    const successful = [];
    const skipped = [];
    const remoteFiles = {};

    console.log('Checking remote files...');
    const findCmd = `find ${shellEscape(remoteDir)} -type f -printf '%P %T@\\n'`;
    const result = await ssh.execCommand(findCmd);
    if (result.stderr && !/No such file or directory/i.test(result.stderr)) {
        console.warn(result.stderr.trim());
    }
    if (result.stdout) {
        result.stdout.split('\n').forEach((line) => {
            const trimmed = line.trim();
            if (!trimmed) {
                return;
            }
            const parts = trimmed.split(' ');
            const timestamp = parts.pop();
            const filepath = parts.join(' ');
            if (filepath && timestamp) {
                remoteFiles[filepath] = parseFloat(timestamp);
            }
        });
    }

    await ssh.putDirectory(localDir, remoteDir, {
        recursive: true,
        concurrency,
        validate(itemPath) {
            const baseName = path.basename(itemPath);
            if (baseName.startsWith('.') || baseName === 'node_modules') {
                return false;
            }

            if (fs.lstatSync(itemPath).isSymbolicLink()) {
                try {
                    fs.readlinkSync(itemPath);
                    return true;
                } catch (error) {
                    console.log('Skipping broken symlink:', itemPath);
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
                const localTime = stat.mtimeMs / 1000;
                const remoteTime = remoteFiles[relativePath];

                if (!remoteTime || localTime > remoteTime) {
                    console.log('Uploading newer file:', relativePath);
                    successful.push(relativePath);
                } else {
                    console.log('Skipped (remote is newer or same):', relativePath);
                    skipped.push(relativePath);
                }
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
    }
}

async function main() {
    const env = loadEnv(projectRoot);
    const host = requireEnvValue(env, 'DEPLOY_HOST');
    const username = requireEnvValue(env, 'DEPLOY_USERNAME');
    const remotePath = requireEnvValue(env, 'DEPLOY_REMOTE_PATH');
    const sourcePath = resolveSourcePath(env);
    const concurrency = Number.parseInt(getEnvValue(env, 'DEPLOY_CONCURRENCY', '8'), 10) || 8;
    const password = getEnvValue(env, 'DEPLOY_PASSWORD');
    const privateKeyValue = getEnvValue(env, 'DEPLOY_PRIVATE_KEY');
    const passphrase = getEnvValue(env, 'DEPLOY_PASSPHRASE');

    if (!fs.existsSync(sourcePath) || !fs.statSync(sourcePath).isDirectory()) {
        throw new Error(`Deploy source directory not found: ${sourcePath}`);
    }

    const connection = {
        host,
        username,
    };

    if (privateKeyValue) {
        connection.privateKey = path.isAbsolute(privateKeyValue)
            ? privateKeyValue
            : path.resolve(projectRoot, privateKeyValue);
        if (passphrase) {
            connection.passphrase = passphrase;
        }
    } else if (password) {
        connection.password = password;
    } else {
        throw new Error('Set DEPLOY_PASSWORD or DEPLOY_PRIVATE_KEY for deploy auth.');
    }

    console.log('Connecting to server...');
    console.log(`Source: ${sourcePath}`);
    console.log(`Remote: ${remotePath}`);

    try {
        await ssh.connect(connection);
        console.log('Starting sync of newer files...');
        await uploadDir(sourcePath, remotePath, concurrency);
    } finally {
        ssh.dispose();
    }
}

main().catch((error) => {
    console.error('Error:', error.message);
    process.exit(1);
});
