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
const VIEWER_FOLDER_DIR = path.join(POFF_DIR, 'viewer-folder');

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

function runWorktype(action, kind, payload = null) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_render_worktype.php'), action, kind];
    if (payload !== null) {
      args.push(JSON.stringify(payload));
    }
    const proc = spawn('php', args, {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) return resolve(stdout.trim());
      reject(new Error(`worktype helper failed: ${code} ${stderr}`));
    });
  });
}

function runViewer(relativePath, baseDir = POFF_DIR) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_render_viewer.php'), baseDir, relativePath], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) return resolve(stdout.trim());
      reject(new Error(`viewer helper failed: ${code} ${stderr}`));
    });
  });
}

async function hasLightnCandy() {
  return new Promise((resolve) => {
    const proc = spawn('php', ['-r', `require ${JSON.stringify(path.join(ROOT, 'vendor/autoload.php'))}; echo class_exists('LightnCandy\\\\LightnCandy') ? 'yes' : 'no';`], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.on('exit', () => resolve(stdout.trim() === 'yes'));
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
    fs.mkdirSync(path.join(VIEWER_FOLDER_DIR, 'nested-child'), { recursive: true });
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'child.txt'), 'viewer child');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'nested-child', 'nested-video.mp4'), 'fake video');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'poff.config.json'), JSON.stringify({
      title: 'Folder Preview',
      description: 'Folder layout from prompt',
      work: {
        type: 'folder',
        layout: {
          name: 'prompted-folder-layout',
          engine: 'lightncandy',
          section: 'works',
          template: '<div class="folder-custom">{{title}}|{{#each tree}}{{#if isFolder}}<span class="branch">{{name}}</span>{{#each children}}{{#if (contains name ".mp4")}}<span class="child">{{path}}</span>{{/if}}{{/each}}{{/if}}{{#if (eq type "file")}}<span class="entry">{{name}}:{{type}}</span>{{/if}}{{/each}}{{#each allVideos}}<span class="video">{{path}}</span>{{/each}}</div>',
        },
      },
    }, null, 2));
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

describe('Worktype HBS renderer', () => {
  test('normalizes default layout metadata for files', async () => {
    const output = await runWorktype('definition', 'image');
    const definition = JSON.parse(output);

    expect(definition.layout).toMatchObject({
      mode: 'default',
      name: 'default-layout',
      engine: 'lightncandy',
      section: 'work',
    });
  });

  test('renders the default layout with the file work partial', async () => {
    const lightnCandyInstalled = await hasLightnCandy();
    const output = await runWorktype('render', 'image', {
      ctx: {
        path: 'assets/photo.png',
        name: 'photo.png',
        title: 'Project Photo',
        description: 'Inline description',
        descriptionHtml: '<div class="work-description">Inline description</div>',
        linkUrl: '',
        slug: 'project-photo',
        work: {
          type: 'image',
          fit: 'contain',
          background: '#111111',
          caption: '',
          layout: {
            name: 'default-layout',
            engine: 'lightncandy',
            section: 'work',
          },
        },
      },
    });

    if (!lightnCandyInstalled) {
      expect(output).toBe('<iframe src="assets/photo.png" title="photo.png"></iframe>');
      return;
    }

    expect(output).toContain('<section class="viewer-template viewer-template--image">');
    expect(output).toContain('<img src="assets/photo.png" alt="Project Photo"');
    expect(output).toContain('<div class="work-description">Inline description</div>');
  });

  test('allows a custom HBS layout template to include the default layout partial', async () => {
    const lightnCandyInstalled = await hasLightnCandy();
    const output = await runWorktype('render', 'folder', {
      ctx: {
        path: 'projects',
        name: 'projects',
        title: 'Projects',
        description: '',
        descriptionHtml: '',
        linkUrl: '',
        slug: 'projects',
        displayPath: 'projects',
        hasItems: true,
        itemCount: 2,
        items: [
          { name: 'alpha', type: 'folder', path: 'projects/alpha', isFolder: true },
          { name: 'notes.txt', type: 'file', path: 'projects/notes.txt', isFile: true },
        ],
        tree: [
          { name: 'alpha', type: 'folder', path: 'projects/alpha', isFolder: true },
          { name: 'notes.txt', type: 'file', path: 'projects/notes.txt', isFile: true },
        ],
        work: {
          type: 'folder',
          layout: {
            name: 'custom-layout',
            engine: 'lightncandy',
            section: 'works',
            template: '<div class="custom-shell">{{#each items}}{{#if (eq type "file")}}<span class="entry">{{name}}</span>{{/if}}{{/each}}{{> default-layout}}</div>',
          },
        },
      },
    });

    if (!lightnCandyInstalled) {
      expect(output).toBe('<iframe src="projects" title="projects"></iframe>');
      return;
    }

    expect(output).toContain('<div class="custom-shell">');
    expect(output).toContain('<section class="viewer-template viewer-template--folder">');
    expect(output).toContain('<span class="entry">notes.txt</span>');
    expect(output).toContain('projects');
    expect(output).toContain('alpha');
    expect(output).toContain('notes.txt');
  });

  test('renders folder previews through the typed viewer route', async () => {
    const output = await runViewer('viewer-folder');

    expect(output).toContain('<title>Viewer - viewer-folder</title>');
    expect(output).toContain('<div class="folder-custom">');
    expect(output).toContain('Folder Preview');
    expect(output).toContain('nested-child');
    expect(output).toContain('nested-child/nested-video.mp4');
    expect(output).toContain('child.txt:file');
  });
});
