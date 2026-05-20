const { spawnSync } = require('child_process');
const path = require('path');

const ROOT = path.join(__dirname, '..');

test('nav ajax path returns nav markup without the full viewer shell', () => {
  const baseDir = path.join(ROOT, 'tests', 'poff-tests');
  const result = spawnSync('php', [path.join(ROOT, 'tests/php_render_nav_ajax.php'), baseDir, 'viewer-folder'], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  expect(result.stderr).toBe('');
  expect(result.stdout).toContain('<ul id="navList" class="nav-list">');
  expect(result.stdout).toContain('Folder Preview');
  expect(result.stdout).not.toContain('poff-default-layout');
  expect(result.stdout).not.toContain('content-frame');
});
