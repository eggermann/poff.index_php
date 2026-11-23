// Enthält Deploy-Regeln: Zeitstempel vergleichen, geschützte Inhalte filtern und Release-Dateien sammeln.
const fs = require('fs');
const path = require('path');

function isNewerFile(localModifiedSeconds, remoteModifiedSeconds) {
    return !Number.isFinite(remoteModifiedSeconds) || localModifiedSeconds > remoteModifiedSeconds;
}

const converterDirectory = 'worktypes/templates/converter';
const releaseProfileImage = '.layout/poff.profile.jpg';
const protectedNames = new Set(['index.php', 'poff.node.json', 'poff.config.json', 'poff.config.toon', 'node_modules']);

function isDeployableContent(relativePath) {
    if (relativePath === converterDirectory || relativePath.startsWith(converterDirectory + '/')) {
        return false;
    }
    return relativePath.split('/').every(part =>
        (!part.startsWith('.') || part === '.layout') && !protectedNames.has(part)
    );
}

function releaseFiles(root) {
    const files = [];
    const source = path.join(root, converterDirectory);
    if (fs.realpathSync(source) !== path.join(fs.realpathSync(root), converterDirectory)) {
        throw new Error('Converter release directory must not traverse a symlink.');
    }

    for (const grain of fs.readdirSync(source, { withFileTypes: true }).sort((a, b) => a.name.localeCompare(b.name))) {
        if (!grain.isDirectory() || !/^[a-z0-9-]+$/.test(grain.name)) {
            throw new Error('Invalid converter release directory: ' + grain.name);
        }
        const relative = path.posix.join(converterDirectory, grain.name);
        const entries = fs.readdirSync(path.join(root, relative), { withFileTypes: true });
        if (!entries.some(entry => entry.isFile() && entry.name === 'converter.json')) {
            throw new Error('Converter release definition missing: ' + grain.name);
        }
        for (const entry of entries) {
            if (!entry.isFile() || !/^(?:converter\.(?:json|php)|[a-z0-9-]+\.(?:hbs|css|js))$/.test(entry.name)) {
                throw new Error('Unexpected converter release entry: ' + path.posix.join(relative, entry.name));
            }
            files.push(path.posix.join(relative, entry.name));
        }
    }
    if (files.length === 0) throw new Error('Release has no converter sidecars.');
    const profile = path.join(root, releaseProfileImage);
    if (fs.lstatSync(profile, { throwIfNoEntry: false })?.isFile()
        && fs.realpathSync(profile) === path.join(fs.realpathSync(root), releaseProfileImage)) {
        files.push(releaseProfileImage);
    }
    return files.sort();
}

module.exports = { isNewerFile, isDeployableContent, releaseFiles, releaseProfileImage };
