const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');


const POFF_DIR = path.join(ROOT, 'tests/poff-tests');


const TEST_NAME = 'jest-create-route';
const TEST_DEST = path.join(POFF_DIR, TEST_NAME);
const TEST_COPY_NAME = 'jest-copy-route';
const TEST_COPY_DEST = path.join(POFF_DIR, TEST_COPY_NAME);
const TEST_DATA_SRC = path.join(ROOT, 'tests', 'datas');

describe('MCP create route helper (CLI)', () => {
  beforeAll(() => {
    if (fs.existsSync(POFF_DIR)) {
      fs.rmSync(POFF_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(POFF_DIR, { recursive: true });
    if (fs.existsSync(TEST_DEST)) {
      fs.rmSync(TEST_DEST, { recursive: true, force: true });
    }
  });

  afterAll(() => {
    if (fs.existsSync(POFF_DIR)) {
   //  fs.rmSync(POFF_DIR, { recursive: true, force: true });
    }
  });

  test('creates destination folder via create helper', async () => {
    await new Promise((resolve, reject) => {
      const proc = spawn('php', [path.join(ROOT, 'tests/php_call_create.php'), `--dest=${TEST_NAME}`], {
        cwd: ROOT,
        env: { ...process.env, POFF_BASE: POFF_DIR },
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

  test('copies from path into destination', async () => {
    await new Promise((resolve, reject) => {
      const proc = spawn(
        'php',
        [path.join(ROOT, 'tests/php_call_create.php'), `--dest=${TEST_COPY_NAME}`, `--path=${path.relative(ROOT, TEST_DATA_SRC)}`],
        {
          cwd: ROOT,
          env: { ...process.env, POFF_BASE: POFF_DIR },
          stdio: ['ignore', 'pipe', 'pipe'],
        }
      );
      let stderr = '';
      proc.stderr.on('data', (d) => (stderr += d.toString()));
      proc.on('exit', (code) => {
        if (code === 0) return resolve();
        reject(new Error(`create helper failed: ${code} ${stderr}`));
      });
    });

    const rootFile = path.join(TEST_COPY_DEST, 'xmas.md');
    const nestedFile = path.join(TEST_COPY_DEST, 'f1', 'xmas.md');
    const nestedDeep = path.join(TEST_COPY_DEST, 'f1', 'f2', 'xmas.md');
    const nestedDeepCopy = path.join(TEST_COPY_DEST, 'f1', 'f2', 'xmas copy.md');

    [rootFile, nestedFile, nestedDeep, nestedDeepCopy].forEach((file) => {
      expect(fs.existsSync(file)).toBe(true);
    });
  });
});
