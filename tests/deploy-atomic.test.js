const fs = require('fs');
const os = require('os');
const path = require('path');
const crypto = require('crypto');
const { execFileSync } = require('child_process');
const { publishFile } = require('../scripts/deploy/deploy-atomic');

test('failed remote validation leaves the live index out of the upload and cleans the candidate', async () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-deploy-validation-'));
    const localFile = path.join(root, 'index.php');
    fs.writeFileSync(localFile, '<?php /* fixture */');
    const ssh = {
        execCommand: jest.fn()
            .mockResolvedValueOnce({ code: 0, stdout: '' })
            .mockResolvedValueOnce({ code: 1, stdout: '' })
            .mockResolvedValueOnce({ code: 0, stdout: '' }),
        putFile: jest.fn().mockResolvedValue(undefined),
    };
    try {
        const remoteFile = '/home/account/public/index.php';
        await expect(publishFile(ssh, { localFile, remoteFile, backupDir: '/home/account/private' })).rejects.toThrow('Remote validation');
        expect(ssh.putFile.mock.calls[0][1]).toMatch(/^\/home\/account\/private\/.*\.candidate$/);
        const activation = ssh.execCommand.mock.calls[1][0];
        expect(activation).toMatch(/php -l.*sha256sum.*cp -p.*mv -T/);
        expect(ssh.execCommand.mock.calls[2][0]).toMatch(/^rm -f .*\.candidate/);
        for (const [command] of ssh.execCommand.mock.calls) {
            expect(() => execFileSync('sh', ['-n', '-c', command])).not.toThrow();
        }
    } finally {
        fs.rmSync(root, { recursive: true, force: true });
    }
});

test('rollback accepts a private backup tied to the selected index and creates a new backup', async () => {
    const remoteFile = '/home/account/public/index.php';
    const backupDir = '/home/account/private';
    const key = crypto.createHash('sha256').update(remoteFile).digest('hex').slice(0, 20);
    const restoreFrom = path.posix.join(backupDir, key + '-1-0000000000000000.bak');
    const ssh = { execCommand: jest.fn().mockResolvedValue({ code: 0, stdout: '' }) };
    await expect(publishFile(ssh, { remoteFile, backupDir, restoreFrom: backupDir + '/other.bak' })).rejects.toThrow('does not belong');
    expect(ssh.execCommand).not.toHaveBeenCalled();
    await publishFile(ssh, { remoteFile, backupDir, restoreFrom });
    expect(ssh.execCommand.mock.calls[1][0]).toContain(restoreFrom);
    expect(ssh.execCommand.mock.calls[2][0]).toMatch(/php -l.*cp -p.*mv -T/);
});
