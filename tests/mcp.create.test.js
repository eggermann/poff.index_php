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
const VIEWER_FILE_NAME = 'viewer-file.txt';
const VIEWER_FILE_PATH = path.join(POFF_DIR, VIEWER_FILE_NAME);
const PERSIST_LAYOUT_DIR = path.join(POFF_DIR, 'persist-layout');
const INHERITED_DEFAULT_DIR = path.join(POFF_DIR, 'inherits-default');
const UPLOAD_TARGET_DIR = path.join(POFF_DIR, 'upload-target');

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

function runLayoutFilesystem(action, dir, fileName = '', payload = null) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_layout_filesystem.php'), action, dir, fileName];
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
      reject(new Error(`layout helper failed: ${code} ${stderr}`));
    });
  });
}

function runUpload(targetDir, sourcePath, uploadName) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_upload_files.php'), targetDir, sourcePath, uploadName];
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
      reject(new Error(`upload helper failed: ${code} ${stderr}`));
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
    fs.mkdirSync(path.join(VIEWER_FOLDER_DIR, '.layout'), { recursive: true });
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, '.layout', 'template.hbs'), '<div class="folder-custom" data-layout="{{layout.directory}}">{{title}}|{{#each tree}}{{#if isFolder}}<span class="branch">{{name}}</span>{{#each children}}{{#if (contains name ".mp4")}}<span class="child">{{path}}</span><span class="child-view">{{pageLink}}</span>{{/if}}{{/each}}{{/if}}{{#if (eq type "file")}}<span class="entry">{{name}}:{{type}}</span><span class="entry-view">{{pageLink}}</span>{{/if}}{{/each}}{{#each allVideos}}<span class="video">{{path}}</span>{{/each}}{{#each layout.assets}}<span class="asset">{{href}}</span>{{/each}}</div>');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, '.layout', 'style.css'), '.folder-custom{color:#8ec5ff;}');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, '.layout', 'script.js'), 'window.__folderLayout = true;');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, '.layout', 'background.txt'), 'folder background');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'poff.config.json'), JSON.stringify({
      title: 'Folder Preview',
      description: 'Folder layout from prompt',
      work: {
        type: 'folder',
        layout: {
          name: 'filesystem-folder-layout',
          engine: 'lightncandy',
          section: 'works',
        },
      },
    }, null, 2));
    fs.writeFileSync(VIEWER_FILE_PATH, 'viewer file');
    fs.mkdirSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`), { recursive: true });
    fs.writeFileSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`, 'template.hbs'), '<div class="file-custom">{{title}}|{{#each layout.assets}}<span class="asset">{{href}}</span>{{/each}}</div>');
    fs.writeFileSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`, 'style.css'), '.file-custom{border:1px solid #8ec5ff;}');
    fs.writeFileSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`, 'script.js'), 'window.__fileLayout = true;');
    fs.writeFileSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`, 'thumbnail.txt'), 'file thumb');
    fs.writeFileSync(path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.config.json`), JSON.stringify({
      title: 'Viewer File',
      description: 'File layout from filesystem',
      work: {
        type: 'text',
        layout: {
          name: 'filesystem-file-layout',
          engine: 'lightncandy',
          section: 'work',
        },
      },
    }, null, 2));
    fs.mkdirSync(path.join(POFF_DIR, '.default', '.layout'), { recursive: true });
    fs.writeFileSync(path.join(POFF_DIR, '.default', '.layout', 'template.hbs'), '<div class="default-fs-layout"><header>{{title}}</header><main>{{#if isFolder}}{{#each items}}<span class="item">{{name}}</span>{{/each}}{{else}}{{name}}{{/if}}</main><footer><img src="{{layout.baseHref}}/eggman_profile-image.jpg" alt="profile"></footer></div>');
    fs.writeFileSync(path.join(POFF_DIR, '.default', '.layout', 'style.css'), '.default-fs-layout{display:block;}');
    fs.writeFileSync(path.join(POFF_DIR, '.default', '.layout', 'script.js'), 'window.__defaultFsLayout = true;');
    fs.writeFileSync(path.join(POFF_DIR, '.default', '.layout', 'eggman_profile-image.jpg'), 'fake image bytes');
    fs.mkdirSync(INHERITED_DEFAULT_DIR, { recursive: true });
    fs.writeFileSync(path.join(INHERITED_DEFAULT_DIR, 'child.txt'), 'child');
    fs.mkdirSync(PERSIST_LAYOUT_DIR, { recursive: true });
    fs.mkdirSync(UPLOAD_TARGET_DIR, { recursive: true });
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

  test('hydrates folder layout metadata from the .layout filesystem', async () => {
    const output = await runLayoutFilesystem('ensure-folder', VIEWER_FOLDER_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout).toMatchObject({
      name: 'filesystem-folder-layout',
      section: 'works',
      storage: 'filesystem',
      directory: '.layout',
    });
    expect(config.work.layout.template).toContain('folder-custom');
    expect(config.work.layout.css).toContain('.folder-custom');
    expect(config.work.layout.js).toContain('__folderLayout');
    expect(config.work.layout.assets).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ path: 'background.txt' }),
      ]),
    );
    expect(config.tree.map((item) => item.name)).not.toContain('.layout');
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

    expect(output).toContain('<div class="poff-default-layout poff-default-layout--image">');
    expect(output).toContain('<img src="assets/photo.png" alt="Project Photo"');
    expect(output).toContain('Inline description');
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
            template: '<div class="custom-shell"><a class="self-link" href="{{pageLink}}">{{title}}</a>{{#each items}}{{#if (eq type "file")}}<span class="entry">{{name}}</span>{{/if}}{{/each}}{{> default-layout}}</div>',
          },
        },
      },
    });

    if (!lightnCandyInstalled) {
      expect(output).toBe('<iframe src="projects" title="projects"></iframe>');
      return;
    }

    expect(output).toContain('<div class="custom-shell">');
    expect(output).toContain('href="?view&#x3D;1&amp;path&#x3D;projects"');
    expect(output).toContain('<div class="poff-default-layout poff-default-layout--folder">');
    expect(output).toContain('<span class="entry">notes.txt</span>');
    expect(output).toContain('projects');
    expect(output).toContain('alpha');
    expect(output).toContain('notes.txt');
  });

  test('inherits a filesystem default layout from .default/.layout', async () => {
    const output = await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout).toMatchObject({
      storage: 'filesystem',
      directory: 'tests/poff-tests/.default/.layout',
    });
    expect(config.work.layout.template).toContain('default-fs-layout');
    expect(config.work.layout.assets).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ path: 'eggman_profile-image.jpg' }),
      ]),
    );

    const rendered = await runViewer('inherits-default');
    expect(rendered).toContain('<div class="default-fs-layout">');
    expect(rendered).toContain('.default/.layout/style.css');
    expect(rendered).toContain('.default/.layout/script.js');
    expect(rendered).toContain('.default/.layout/eggman_profile-image.jpg');
    expect(rendered).toContain('<span class="item">child.txt</span>');
  });

  test('renders folder previews through the typed viewer route', async () => {
    const output = await runViewer('viewer-folder');

    expect(output).toContain('<title>Viewer - viewer-folder</title>');
    expect(output).toContain('.layout/style.css');
    expect(output).toContain('.layout/script.js');
    expect(output).toContain('<div class="folder-custom"');
    expect(output).toContain('Folder Preview');
    expect(output).toContain('nested-child');
    expect(output).toContain('nested-child/nested-video.mp4');
    expect(output).toContain('?view&#x3D;1&amp;file&#x3D;viewer-folder%2Fnested-child%2Fnested-video.mp4');
    expect(output).toContain('?view&#x3D;1&amp;file&#x3D;viewer-folder%2Fchild.txt');
    expect(output).toContain('child.txt:file');
    expect(output).toContain('.layout/background.txt');
  });

  test('renders file previews from .works/<file>.layout', async () => {
    const output = await runViewer(VIEWER_FILE_NAME);

    expect(output).toContain('<title>Viewer - viewer-file.txt</title>');
    expect(output).toContain('.works/viewer-file.txt.layout/style.css');
    expect(output).toContain('.works/viewer-file.txt.layout/script.js');
    expect(output).toContain('<div class="file-custom">');
    expect(output).toContain('Viewer File');
    expect(output).toContain('.works/viewer-file.txt.layout/thumbnail.txt');
  });

  test('persists edited folder layout files into .layout', async () => {
    const payload = {
      name: 'persisted-layout',
      engine: 'lightncandy',
      section: 'works',
      template: '<div class="persisted-layout">Persisted</div>',
      css: '.persisted-layout{color:#fff;}',
      js: 'window.__persistedLayout = true;',
    };

    const output = await runLayoutFilesystem('persist-folder', PERSIST_LAYOUT_DIR, '', payload);
    const serializedLayout = JSON.parse(output);

    expect(serializedLayout).toMatchObject({
      name: 'persisted-layout',
      section: 'works',
      engine: 'lightncandy',
    });
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('Persisted');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'style.css'), 'utf8')).toContain('.persisted-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain('__persistedLayout');

    const ensuredOutput = await runLayoutFilesystem('ensure-folder', PERSIST_LAYOUT_DIR);
    const ensuredConfig = JSON.parse(ensuredOutput);
    expect(ensuredConfig.work.layout.template).toContain('Persisted');
    expect(ensuredConfig.work.layout.storage).toBe('filesystem');
  });

  test('stores uploaded files into a folder target', async () => {
    const sourcePath = path.join(POFF_SOURCE_DIR, 'note.txt');
    const output = await runUpload(UPLOAD_TARGET_DIR, sourcePath, 'slides.pdf');
    const result = JSON.parse(output);

    expect(result.errors).toEqual([]);
    expect(result.stored).toEqual([
      expect.objectContaining({ name: 'slides.pdf', path: 'slides.pdf' }),
    ]);
    expect(fs.existsSync(path.join(UPLOAD_TARGET_DIR, 'slides.pdf'))).toBe(true);
    expect(result.config.tree).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ name: 'slides.pdf', type: 'file' }),
      ]),
    );
  });
});
