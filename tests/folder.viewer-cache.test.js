const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ROOT = path.join(__dirname, '..');

function sleep(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

test('folder viewer cache invalidates when nested folder contents change', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-folder-cache-'));

  try {
    fs.mkdirSync(path.join(tempRoot, 'nested-child'));
    fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'nested-child', 'nested-video.mp4'), 'video', 'utf8');

    const firstResult = spawnSync('php', [path.join(ROOT, 'tests/php_folder_viewer_data.php'), tempRoot, ''], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(firstResult.status).toBe(0);
    expect(firstResult.stderr).toBe('');
    expect(firstResult.stdout).toContain('nested-video.mp4');
    expect(firstResult.stdout).not.toContain('second.txt');

    sleep(1100);
    fs.writeFileSync(path.join(tempRoot, 'nested-child', 'second.txt'), 'later', 'utf8');

    const secondResult = spawnSync('php', [path.join(ROOT, 'tests/php_folder_viewer_data.php'), tempRoot, ''], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(secondResult.status).toBe(0);
    expect(secondResult.stderr).toBe('');
    expect(secondResult.stdout).toContain('nested-video.mp4');
    expect(secondResult.stdout).toContain('second.txt');
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});
