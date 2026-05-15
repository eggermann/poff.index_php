const { spawnSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ROOT = path.join(__dirname, '..');

test('nav rendering uses folderPoffConfig instead of an undefined config variable', () => {
  const baseDir = path.join(ROOT, 'tests', 'poff-tests');
  const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), baseDir, 'viewer-folder'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  expect(result.stderr).toBe('');
  expect(result.stdout).toContain('Folder Preview');
  expect(result.stdout).not.toContain('> ./ viewer-folder<');
  expect(result.stdout).toContain('nav-link-up');
});

test('nav edit rendering includes fast hide actions for tree items', () => {
  const baseDir = path.join(ROOT, 'tests', 'poff-tests');
  const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), baseDir, 'viewer-folder', 'edit'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  expect(result.stderr).toBe('');
  expect(result.stdout).toContain('data-nav-action="toggle-visibility"');
  expect(result.stdout).toContain('data-tree-item="1"');
  expect(result.stdout).toContain('nav-row-action__icon');
  expect(result.stdout).toContain('Hide');
});

test('hidden tree items render an unhide toggle', () => {
  const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-nav-'));
  try {
    fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
    fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-nav',
      slug: 'poff-nav',
      title: 'Poff Nav',
      description: '',
      type: 'folder',
      tree: [
        {
          name: 'visible.txt',
          slug: 'visible-txt',
          type: 'file',
          path: 'visible.txt',
          visible: false,
        },
      ],
    }, null, 2), 'utf8');

    const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav.php'), tempRoot, '', 'edit'], {
      cwd: ROOT,
      encoding: 'utf8',
    });

    expect(result.status).toBe(0);
    expect(result.stderr).toBe('');
    expect(result.stdout).toContain('Unhide');
    expect(result.stdout).toContain('data-nav-hidden="1"');
  } finally {
    fs.rmSync(tempRoot, { recursive: true, force: true });
  }
});
