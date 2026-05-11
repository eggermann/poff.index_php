const { spawnSync } = require('child_process');
const path = require('path');

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
