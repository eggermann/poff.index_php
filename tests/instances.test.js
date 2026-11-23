const fs = require('fs');
const path = require('path');
const os = require('os');
const { deploy, localStatus, inspectDeployProfiles } = require('../scripts/runtime/instances');
const { NodeSSH } = require('node-ssh');

let root;
beforeEach(() => { root = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-instance-test-')); });
afterEach(() => { jest.restoreAllMocks(); fs.rmSync(root, { recursive: true, force: true }); });
function fixture() {
  const sourceDir = path.join(root, 'source');
  const targetDir = path.join(root, 'target');
  fs.mkdirSync(sourceDir); fs.mkdirSync(targetDir);
  const source = path.join(sourceDir, 'index.php');
  const target = path.join(targetDir, 'index.php');
  fs.writeFileSync(source, '<?php /* new test fixture */');
  fs.writeFileSync(target, '<?php /* old test fixture */');
  const inventory = path.join(root, 'inventory.json');
  fs.writeFileSync(inventory, JSON.stringify({ instances: [{ id: 'local-test', kind: 'local', folder: targetDir, eligible: true, ...localStatus(targetDir) }] }));
  return { source, target, inventory };
}
test('plan leaves a target unchanged', async () => {
  const f = fixture(); const original = fs.readFileSync(f.target, 'utf8');
  await deploy(f.inventory, f.source, 'all');
  expect(fs.readFileSync(f.target, 'utf8')).toBe(original);
});
test('all plans verified instances and reports an unavailable target', async () => {
  const f = fixture();
  const data = JSON.parse(fs.readFileSync(f.inventory));
  data.instances.push({ id: 'web-unavailable', kind: 'web', server: 'eggman4', eligible: true, indexExists: null });
  fs.writeFileSync(f.inventory, JSON.stringify(data));
  const log = jest.spyOn(console, 'log').mockImplementation(() => {});
  await deploy(f.inventory, f.source, 'all');
  expect(log).toHaveBeenCalledWith(expect.stringContaining('SKIP web-unavailable'));
  expect(log).toHaveBeenCalledWith(expect.stringContaining('PLAN local-test'));
  await expect(deploy(f.inventory, f.source, 'web-unavailable')).rejects.toThrow('Unverified/missing target');
});

test('inventory reports a profile with destination settings but missing authentication', () => {
  const env = {
    DEPLOY_HOST_pageoff: 'example.test', DEPLOY_USERNAME_pageoff: 'test-user',
    DEPLOY_REMOTE_PATH_pageoff: '/public/site/', DEPLOY_SOURCE_PATH_pageoff: root,
    DEPLOY_PRIVATE_KEY_pageoff: path.join(root, 'missing-key'),
  };
  const profiles = inspectDeployProfiles(env, [{ id: 'web-test', host: 'example.test', folder: '/public/site' }]);
  expect(profiles.find(profile => profile.name === 'pageoff')).toEqual(expect.objectContaining({
    status: 'missing-credentials', instance: 'web-test',
  }));
  expect(JSON.stringify(profiles)).not.toContain('missing-key');
});
test('apply replaces only index.php and retains a private backup', async () => {
  const f = fixture(); const original = fs.readFileSync(f.target, 'utf8');
  jest.spyOn(os, 'homedir').mockReturnValue(root);
  const content = path.join(path.dirname(f.target), 'content.txt'); fs.writeFileSync(content, 'keep');
  await deploy(f.inventory, f.source, 'local-test', true);
  expect(fs.readFileSync(f.target, 'utf8')).toBe(fs.readFileSync(f.source, 'utf8'));
  expect(fs.readFileSync(content, 'utf8')).toBe('keep');
  const backups = path.join(root, '.local/state/pageoff/backups');
  expect(fs.readFileSync(path.join(backups, fs.readdirSync(backups)[0]), 'utf8')).toBe(original);
  expect(fs.readdirSync(path.dirname(f.target)).sort()).toEqual(['content.txt', 'index.php']);
});
test('a target modified after inventory is never overwritten', async () => {
  const f = fixture(); fs.writeFileSync(f.target, '<?php /* changed since scan */');
  await expect(deploy(f.inventory, f.source, 'local-test', true)).rejects.toThrow('Target changed since scan');
  expect(fs.readFileSync(f.target, 'utf8')).toContain('changed since scan');
});
test('unknown selection fails instead of silently deploying something else', async () => {
  const f = fixture(); await expect(deploy(f.inventory, f.source, 'missing')).rejects.toThrow();
});
test('an index alias of the source is not replaced', async () => {
  const f = fixture(); fs.unlinkSync(f.target); fs.symlinkSync(f.source, f.target);
  await deploy(f.inventory, f.source, 'all', true);
  expect(fs.lstatSync(f.target).isSymbolicLink()).toBe(true);
});

test('remote updates stage privately and validate before the final rename', async () => {
  const f = fixture();
  const originalUser = process.env.user;
  process.env.user = 'eggman4';
  const commands = [];
  const status = { folderExists: true, indexExists: true, sha256: 'old-fingerprint' };
  fs.writeFileSync(f.inventory, JSON.stringify({ instances: [{ id: 'web-test', kind: 'web', server: 'eggman4', folder: '/public/test', eligible: true, ...status }] }));
  jest.spyOn(NodeSSH.prototype, 'connect').mockResolvedValue(undefined);
  jest.spyOn(NodeSSH.prototype, 'isConnected').mockReturnValue(true);
  jest.spyOn(NodeSSH.prototype, 'dispose').mockImplementation(() => {});
  jest.spyOn(NodeSSH.prototype, 'execCommand').mockImplementation(async (command) => {
    commands.push(command);
    return { code: 0, stdout: command.startsWith('python3') ? JSON.stringify(status) : '' };
  });
  const upload = jest.spyOn(NodeSSH.prototype, 'putFile').mockResolvedValue(undefined);
  try {
    await deploy(f.inventory, f.source, 'web-test', true);
    expect(upload.mock.calls[0][1]).toMatch(/^\/var\/www\/virtual\/eggman4\/\.poff-deploy-backups\//);
    const activation = commands.find(command => command.includes('mv '));
    expect(activation).toContain('php -l');
    expect(activation).toContain('sha256sum');
    expect(activation).toContain('cp -p');
    expect(activation).toContain("'/public/test/index.php'");
  } finally {
    if (originalUser === undefined) delete process.env.user;
    else process.env.user = originalUser;
  }
});
