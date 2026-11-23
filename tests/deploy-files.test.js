const path = require('path');
const fs = require('fs');
const os = require('os');
const { execFileSync, spawnSync } = require('child_process');
const { isNewerFile, releaseFiles } = require('../scripts/deploy/deploy-files');

test.each([
  [10, undefined, true], [10, null, true], [10, NaN, true],
  [10, 9, true], [10, 10, false], [10, 11, false], [0, 0, false],
])('compares local %p with remote %p before upload', (local, remote, expected) => {
  expect(isNewerFile(local, remote)).toBe(expected);
});

test('dry-run resolves a complete target without credentials or connection', () => {
  const source = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-deploy-plan-'));
  try {
    fs.writeFileSync(path.join(source, 'index.php'), '<?php /* fixture */');
    const output = execFileSync(process.execPath, [path.resolve(__dirname, '../scripts/deploy/deploy.js'), 'testplan', '--index-only', '--dry-run'], {
    encoding: 'utf8',
    env: {
      ...process.env,
      DEPLOY_HOST_testplan: 'invalid.example',
      DEPLOY_USERNAME_testplan: 'test-user',
      DEPLOY_REMOTE_PATH_testplan: '/test-only',
      DEPLOY_SOURCE_PATH_testplan: source,
    },
  });
    expect(JSON.parse(output)).toEqual({ target: 'testplan', mode: 'index', host: 'invalid.example', source, remote: '/test-only' });
  } finally {
    fs.rmSync(source, { recursive: true, force: true });
  }
});

test('release lists converter assets and excludes unrelated content and identities', () => {
  const source = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-release-files-'));
  const grain = path.join(source, 'worktypes/templates/converter/default');
  try {
    fs.mkdirSync(grain, { recursive: true });
    fs.writeFileSync(path.join(grain, 'converter.json'), '{}');
    fs.writeFileSync(path.join(grain, 'template.hbs'), 'template');
    fs.writeFileSync(path.join(source, 'poff.node.json'), '{}');
    fs.writeFileSync(path.join(source, 'picture.jpg'), 'fixture');
    fs.mkdirSync(path.join(source, '.layout'));
    fs.writeFileSync(path.join(source, '.layout/poff.profile.jpg'), 'fixture');
    fs.writeFileSync(path.join(source, '.layout/script.js'), 'site-specific');
    expect(releaseFiles(source)).toEqual([
      '.layout/poff.profile.jpg',
      'worktypes/templates/converter/default/converter.json',
      'worktypes/templates/converter/default/template.hbs',
    ]);
    fs.symlinkSync(path.join(source, 'poff.node.json'), path.join(grain, 'extra.js'));
    expect(() => releaseFiles(source)).toThrow('Unexpected converter release entry');
  } finally {
    fs.rmSync(source, { recursive: true, force: true });
  }
});

test('an unconfigured target fails before a connection despite base .env values', () => {
  const result = spawnSync(process.execPath, [path.resolve(__dirname, '../scripts/deploy/deploy.js'), 'missingprofiletest', '--dry-run'], { encoding: 'utf8' });
  expect(result.status).toBe(1);
  expect(result.stderr).toContain('Missing required deploy setting');
  expect(result.stdout).not.toContain('Connecting');
});
