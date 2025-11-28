const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const POFF_DIR = path.join(ROOT, 'poff');
const TEST_NAME = 'jest-create-route';
const TEST_DEST = path.join(POFF_DIR, TEST_NAME);

describe('MCP create route helper (CLI)', () => {
  beforeAll(() => {
    if (fs.existsSync(TEST_DEST)) {
      fs.rmSync(TEST_DEST, { recursive: true, force: true });
    }
  });

  afterAll(() => {
    if (fs.existsSync(TEST_DEST)) {
      fs.rmSync(TEST_DEST, { recursive: true, force: true });
    }
  });

  test('creates destination folder via create helper', async () => {
    await new Promise((resolve, reject) => {
      const proc = spawn('php', [path.join(ROOT, 'tests/php_call_create.php'), `--name=${TEST_NAME}`], {
        cwd: ROOT,
        env: process.env,
        stdio: ['ignore', 'pipe', 'pipe'],
      });
      let stderr = '';
      proc.stderr.on('data', (d) => (stderr += d.toString()));
      proc.on('exit', (code) => {
        if (code === 0) return resolve();
        reject(new Error(`create helper failed: ${code} ${stderr}`));
      });
    });

    expect(fs.existsSync(TEST_DEST)).toBe(true);
  });
});
