const fs = require('fs');
const os = require('os');
const path = require('path');

const mockSSH = { execCommand: jest.fn(), putDirectory: jest.fn() };
jest.mock('node-ssh', () => ({ NodeSSH: jest.fn(() => mockSSH) }));
const { uploadDir } = require('../scripts/deploy/deploy');

test('full upload filters unchanged files before transfer and rejects symlinks', async () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-deploy-filter-'));
  const transferred = [];
  for (const file of ['old.txt', 'new.txt', 'missing.txt']) {
    fs.writeFileSync(path.join(root, file), 'fixture');
    fs.utimesSync(path.join(root, file), 100, 100);
  }
  fs.symlinkSync(path.join(root, 'new.txt'), path.join(root, 'linked.txt'));
  mockSSH.execCommand.mockResolvedValue({ code: 0, stdout: '101 old.txt\0' + '99 new.txt\0', stderr: '' });
  mockSSH.putDirectory.mockImplementation(async (local, remote, options) => {
    for (const file of ['old.txt', 'new.txt', 'missing.txt', 'linked.txt', 'index.php', 'poff.node.json', 'poff.config.json', '.works/item.json', 'worktypes/templates/converter']) {
      const full = path.join(local, file);
      if (options.validate(full)) {
        transferred.push(file);
        options.tick(full, `${remote}/${file}`, null);
      }
    }
    return true;
  });
  try {
    await uploadDir(root, '/test-only', 1);
    expect(transferred).toEqual(['new.txt', 'missing.txt']);
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
});

test('full upload reports transfer failures as a failed deploy', async () => {
  mockSSH.execCommand.mockResolvedValue({ code: 0, stdout: '', stderr: '' });
  mockSSH.putDirectory.mockImplementation(async (local, remote, options) => {
    options.tick(`${local}/failed.txt`, `${remote}/failed.txt`, new Error('transfer failed'));
    return false;
  });
  await expect(uploadDir('/test-only', '/test-only', 1)).rejects.toThrow('Deploy failed for 1 files.');
});
