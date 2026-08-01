const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');

test('build flattens PoffConfig helper traits into a syntax-valid class', () => {
  const script = path.join(__dirname, 'php_build_poff_config.php');
  const result = spawnSync('php', [script], {
    cwd: path.join(__dirname, '..'),
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  const payload = JSON.parse(result.stdout);

  expect(payload.containsTraitDefinition).toBe(false);
  expect(payload.containsHelperUseStatement).toBe(false);
  expect(payload.containsHydrateConfigLayout).toBe(true);
  expect(payload.containsPersistLayoutFiles).toBe(true);
  expect(payload.containsLayoutCollectionPackage).toBe(true);
  expect(payload.lintExitCode).toBe(0);
});

test('build and watcher share one distribution target config', () => {
  const result = spawnSync('php', [path.join(ROOT, 'build', 'export-config.php')], {
    cwd: ROOT,
    encoding: 'utf8',
  });

  expect(result.status).toBe(0);
  const config = JSON.parse(result.stdout);
  expect(config.standaloneCopyTargets).toEqual(expect.arrayContaining([
    'pageoff',
    'pageoff-artist',
    'pageoff-writer',
    'pageoff-curator',
    'pageoff-civic',
  ]));
  expect(config.additionalIndexCopyTargets).toContainEqual({
    name: 'dominikeggermann-project',
    cleanSubdirectories: false,
  });

  const buildSource = fs.readFileSync(path.join(ROOT, 'build', 'build.php'), 'utf8');
  const watcherSource = fs.readFileSync(path.join(ROOT, 'scripts', 'watch-build.js'), 'utf8');
  expect(buildSource).toContain("$config['standaloneCopyTargets']");
  expect(watcherSource).toContain('config.standaloneCopyTargets');
  expect(watcherSource).not.toContain("const standaloneCopyTargets = ['MAUSMAUS'");
  expect(buildSource).not.toMatch(/legacy/i);
  expect(buildSource).not.toContain('is_array($target)');
});
