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
const POFF_DATA_SRC = path.join(POFF_DIR, 'datas');
const POFF_SOURCE_DIR = path.join(POFF_DIR, 'source-files');
const EXISTING_PARENT_DIR = path.join(POFF_DIR, 'existing-parent');
const EXISTING_NESTED_SRC = path.join(EXISTING_PARENT_DIR, 'nested-src');

function copyDirSync(src, dest) {
  if (!fs.existsSync(dest)) {
    fs.mkdirSync(dest, { recursive: true });
  }
  fs.readdirSync(src, { withFileTypes: true }).forEach((entry) => {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyDirSync(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  });
}

function runCreate(args) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_call_create.php'), ...args], {
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
}

describe('MCP create route helper (CLI)', () => {
  beforeAll(() => {
    if (fs.existsSync(POFF_DIR)) {
      fs.rmSync(POFF_DIR, { recursive: true, force: true });
    }
    fs.mkdirSync(POFF_DIR, { recursive: true });
    if (fs.existsSync(TEST_DEST)) {
      fs.rmSync(TEST_DEST, { recursive: true, force: true });
    }
    copyDirSync(TEST_DATA_SRC, POFF_DATA_SRC);
    fs.mkdirSync(POFF_SOURCE_DIR, { recursive: true });
    fs.writeFileSync(path.join(POFF_SOURCE_DIR, 'note.txt'), 'hello poff');
    fs.mkdirSync(EXISTING_NESTED_SRC, { recursive: true });
    fs.writeFileSync(path.join(EXISTING_NESTED_SRC, 'data.md'), 'nested data');
  });

  afterAll(() => {
    if (fs.existsSync(POFF_DIR)) {
      //  fs.rmSync(POFF_DIR, { recursive: true, force: true });
    }
  });

  test('creates destination folder via create helper', async () => {
    await runCreate([`--dest=${TEST_NAME}`]);

    expect(fs.existsSync(TEST_DEST)).toBe(true);
  });

  test('copies from path into destination', async () => {
    await runCreate([`--dest=${TEST_COPY_NAME}`, `--path=${path.relative(POFF_DIR, POFF_DATA_SRC)}`]);

    const rootFile = path.join(TEST_COPY_DEST, 'xmas.md');
    const nestedFile = path.join(TEST_COPY_DEST, 'f1', 'xmas.md');
    const nestedDeep = path.join(TEST_COPY_DEST, 'f1', 'f2', 'xmas.md');
    const nestedDeepCopy = path.join(TEST_COPY_DEST, 'f1', 'f2', 'xmas copy.md');

    [rootFile, nestedFile, nestedDeep, nestedDeepCopy].forEach((file) => {
      expect(fs.existsSync(file)).toBe(true);
    });
  });

  test('copies file into new nested destination when dest has extension', async () => {
    const relPath = path.relative(POFF_DIR, path.join(POFF_SOURCE_DIR, 'note.txt'));
    const nestedDest = 'deep/new-folder/copied-note.txt';

    await runCreate([`--dest=${nestedDest}`, `--path=${relPath}`]);

    const target = path.join(POFF_DIR, nestedDest);
    expect(fs.existsSync(target)).toBe(true);
    expect(fs.readFileSync(target, 'utf8')).toBe('hello poff');
  });

  test('copies folder into existing parent under poff', async () => {
    const relPath = path.relative(POFF_DIR, EXISTING_NESTED_SRC);
    const destFolder = 'existing-parent/new-child';

    await runCreate([`--dest=${destFolder}`, `--path=${relPath}`]);

    const copiedFile = path.join(POFF_DIR, destFolder, 'data.md');
    expect(fs.existsSync(copiedFile)).toBe(true);
    expect(fs.readFileSync(copiedFile, 'utf8')).toBe('nested data');
  });
});
