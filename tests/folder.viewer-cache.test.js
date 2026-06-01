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

test('folder viewer can build a shallow root snapshot without descending into nested folders', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-folder-shallow-'));

  try {
    fs.mkdirSync(path.join(tempRoot, 'nested-child', 'deep-child'), { recursive: true });
    fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'nested-child', 'nested-video.mp4'), 'video', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'nested-child', 'deep-child', 'too-deep.txt'), 'deep', 'utf8');

    const result = spawnSync('php', [
      path.join(ROOT, 'tests/php_folder_viewer_data.php'),
      tempRoot,
      '',
      JSON.stringify({ maxDepth: 0 }),
    ], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    expect(result.stderr).toBe('');

    const payload = JSON.parse(result.stdout);
    const nestedFolder = payload.tree.find((item) => item.name === 'nested-child');
    expect(nestedFolder).toEqual(expect.objectContaining({
      isFolder: true,
      childCount: 0,
      children: [],
    }));
    expect(JSON.stringify(payload)).not.toContain('too-deep.txt');
    expect(payload.allItems.some((item) => item.name === 'nested-video.mp4')).toBe(false);
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});
