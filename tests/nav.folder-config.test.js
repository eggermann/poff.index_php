const { spawnSync } = require('child_process');
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
  expect(result.stdout).toContain('viewer-folder');
  expect(result.stdout).toContain('nav-link-up');
});
