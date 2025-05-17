const fs = require('fs')
const path = require('path')
const {NodeSSH} = require('node-ssh')

const ssh = new NodeSSH()

const config = require(`${process.env.HOME}/config-data/eggman1`);
let destinationPath = '/var/www/virtual/eggman1/dominikeggermann.com/';
const sourcePath = `${__dirname}/pages/dominikeggermann.com/`;

const uploadDir = async (localDir, remoteDir) => {
    const failed = []
    const successful = []
    const skipped = []
    
    // Get remote file timestamps
    const findCmd = `find "${remoteDir}" -type f -printf '%P %T@\\n'`;
    const remoteFiles = {};
    
    console.log('Checking remote files...');
    const result = await ssh.execCommand(findCmd);
    if (result.stdout) {
        result.stdout.split('\n').forEach(line => {
            const [filepath, timestamp] = line.trim().split(' ');
            if (filepath && timestamp) {
                remoteFiles[filepath] = parseFloat(timestamp);
            }
        });
    }

    return ssh.putDirectory(localDir, remoteDir, {
        recursive: true,
        concurrency: 8,
        validate: function (itemPath) {
            const baseName = path.basename(itemPath)
            return baseName[0] !== '.' && // do not allow dot files
                baseName !== 'node_modules' // do not allow node_modules
        },
        tick: function (localPath, remotePath, error) {
            if (error) {
                console.log('Failed:', localPath)
                failed.push(localPath)
                return;
            }

            try {
                const stat = fs.statSync(localPath);
                if (!stat.isFile()) {
                    return; // Skip directories
                }

                const relativePath = path.relative(localDir, localPath);
                const localTime = stat.mtimeMs / 1000;
                const remoteTime = remoteFiles[relativePath];
                
                // Only upload if local file is newer or remote doesn't exist
                if (!remoteTime || localTime > remoteTime) {
                    console.log('Uploading newer file:', relativePath)
                    successful.push(relativePath)
                } else {
                    console.log('Skipped (remote is newer or same):', relativePath)
                    skipped.push(relativePath)
                }
            } catch (err) {
                console.log('Failed:', localPath, err)
                failed.push(localPath)
            }
        }
    }).then(function (status) {
        console.log('\nSync Summary:')
        console.log('------------')
        if (successful.length > 0) {
            console.log('Updated files:', successful.length)
        }
        if (skipped.length > 0) {
            console.log('Unchanged files:', skipped.length)
        }
        if (failed.length > 0) {
            console.log('Failed files:', failed.length)
        }
        ssh.dispose();
    })
}

// Connect and start synchronization
console.log('Connecting to server...');
ssh.connect({
    host: config.host,
    username: config.user,
    password: config.password,
}).then(() => {
    console.log('Starting sync of newer files...');
    return uploadDir(sourcePath, destinationPath);
}).catch(error => {
    console.error('Error:', error);
    process.exit(1);
});
