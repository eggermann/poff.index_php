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
const INVALID_TEMPLATE_DIR = path.join(POFF_DIR, 'invalid-json-template');
const INHERITED_DEFAULT_DIR = path.join(POFF_DIR, 'inherits-default');
const INHERITED_SECTION_DIR = path.join(POFF_DIR, 'inherits-section-default');
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

function runBlankFile(targetDir, fileName, contents = '') {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_blank_file.php'), targetDir, fileName, contents];
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
      reject(new Error(`blank file helper failed: ${code} ${stderr}`));
    });
  });
}

function runPhpJson(scriptName) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests', scriptName)], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`php helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from ${scriptName}: ${stdout}`));
      }
    });
  });
}

function runPromptModelParse(mode, raw) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_prompt_model_parse.php'), mode, raw], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`prompt parse helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from prompt parse helper: ${stdout}`));
      }
    });
  });
}

function runPromptTemplateLocal(rootDir, relativePath, payload = {}, mockResponse = null, capturePath = '') {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_prompt_template_local.php'),
      rootDir,
      relativePath,
      __filename,
      JSON.stringify(payload),
      mockResponse ? JSON.stringify(mockResponse) : '',
      capturePath,
    ], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`prompt template helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from prompt template helper: ${stdout}`));
      }
    });
  });
}

function runViewerPrompt(rootDir, relativePath, payload = {}, mockResponse = null, capturePath = '') {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_viewer_prompt.php'),
      rootDir,
      relativePath,
      JSON.stringify(payload),
      mockResponse ? JSON.stringify(mockResponse) : '',
      capturePath,
    ], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`viewer prompt helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from viewer prompt helper: ${stdout}`));
      }
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
    fs.mkdirSync(path.join(POFF_DIR, '.layout'), { recursive: true });
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'template.hbs'), '<div class="default-fs-layout"><header>{{title}}</header><main>{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main><footer><img src="{{layout.baseHref}}/eggman_profile-image.jpg" alt="profile"></footer></div>');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'works.hbs'), '{{#each items}}<span class="item">{{name}}</span>{{/each}}');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'work.hbs'), '<span class="file-name">{{name}}</span>');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'style.css'), '.default-fs-layout{display:block;}');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'script.js'), 'window.__defaultFsLayout = true;');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'eggman_profile-image.jpg'), 'fake image bytes');
    fs.mkdirSync(INHERITED_DEFAULT_DIR, { recursive: true });
    fs.writeFileSync(path.join(INHERITED_DEFAULT_DIR, 'child.txt'), 'child');
    fs.mkdirSync(INHERITED_SECTION_DIR, { recursive: true });
    fs.writeFileSync(path.join(INHERITED_SECTION_DIR, 'hero.txt'), 'hero');
    fs.mkdirSync(PERSIST_LAYOUT_DIR, { recursive: true });
    fs.mkdirSync(path.join(INVALID_TEMPLATE_DIR, '.layout'), { recursive: true });
    fs.writeFileSync(path.join(INVALID_TEMPLATE_DIR, 'poster.txt'), 'poster');
    fs.writeFileSync(path.join(INVALID_TEMPLATE_DIR, '.layout', 'template.hbs'), '<div class="invalid-json-layout"><main>{{> works}}</main></div>');
    fs.writeFileSync(path.join(INVALID_TEMPLATE_DIR, '.layout', 'works.hbs'), JSON.stringify({
      id: 'chatcmpl-md9dsfuuobyyebhpfelhd',
      object: 'chat.completion',
      created: 1776458336,
      model: 'google/gemma-4-e4b',
      choices: [
        {
          index: 0,
          message: {
            role: 'assistant',
            content: '',
            reasoning_content: 'The model reasoned but did not produce final template code.',
            tool_calls: [],
          },
          logprobs: null,
          finish_reason: 'length',
        },
      ],
      usage: {
        prompt_tokens: 3870,
        completion_tokens: 226,
        total_tokens: 4096,
      },
    }, null, 2));
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

  test('formats prompt provider failures with status and redacted detail', async () => {
    const result = await runPhpJson('php_prompt_error_helpers.php');

    expect(result.cmsOpenAi).toBe('OpenAI request failed (HTTP 401): Incorrect API key provided: sk-***.');
    expect(result.cmsLocalHtml).toBe('Local endpoint request failed (HTTP 502): Bad Gateway.');
    expect(result.mcpGemini).toBe('Gemini request failed (HTTP 429): Quota exceeded for model gemini-1.5-flash.');
  });

  test('posts LM Studio local prompt payload and parses the gemma template response', async () => {
    const capturePath = path.join(POFF_DIR, 'lm-studio-request.json');
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const result = await runPromptTemplateLocal(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'gemma4',
      endpoint,
      prompt: 'Create a compact image card.',
      history: [
        { role: 'assistant', content: 'Previous draft.' },
        { role: 'user', content: 'Make it smaller.' },
      ],
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: JSON.stringify({
        choices: [
          {
            message: {
              content: '<section class="lm-studio-card">{{title}}</section>',
            },
          },
        ],
      }),
    }, capturePath);

    const captured = JSON.parse(fs.readFileSync(capturePath, 'utf8'));

    expect(result.allowed).toBe(true);
    expect(result.provider).toBe('local');
    expect(result.model).toBe('gemma4');
    expect(result.template).toBe('<section class="lm-studio-card">{{title}}</section>');
    expect(captured.url).toBe(endpoint);
    expect(captured.headers).toEqual([]);
    expect(captured.payload.model).toBe('gemma4');
    expect(captured.payload.temperature).toBe(0.4);
    expect(captured.payload.messages[0]).toEqual(expect.objectContaining({
      role: 'system',
    }));
    expect(captured.payload.messages[0].content).toContain('Handlebars (HBS) template generator');
    expect(captured.payload.messages[1]).toEqual({
      role: 'assistant',
      content: 'Previous draft.',
    });
    expect(captured.payload.messages[2]).toEqual({
      role: 'user',
      content: 'Make it smaller.',
    });
    expect(captured.payload.messages[3]).toEqual(expect.objectContaining({
      role: 'user',
    }));
    expect(captured.payload.messages[3].content).toContain('Config JSON:');
    expect(captured.payload.messages[3].content).toContain('"title": "Folder Preview"');
    expect(captured.payload.messages[3].content).toContain('USER: Create a compact image card.');
  });

  test('routes subject-path layout prompts through the layout wrapper handler', async () => {
    const capturePath = path.join(POFF_DIR, 'viewer-layout-request.json');
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const result = await runViewerPrompt(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'gemma4',
      endpoint,
      prompt: 'Make the wrapper more cinematic.',
      layoutPreset: 'actual',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: JSON.stringify({
        choices: [
          {
            message: {
              content: JSON.stringify({
                template: '<div class="poff-default-layout"><header></header><footer></footer></div>',
                css: '.poff-default-layout{min-height:100vh;}',
              }),
            },
          },
        ],
      }),
    }, capturePath);

    const captured = JSON.parse(fs.readFileSync(capturePath, 'utf8'));

    expect(result.allowed).toBe(true);
    expect(result.target).toBe('layout');
    expect(result.subjectTarget).toBe('folder');
    expect(result.provider).toBe('local');
    expect(result.model).toBe('gemma4');
    expect(result.css).toContain('min-height:100vh');
    expect(result.template).toContain('<main class="poff-default-layout__main">');
    expect(captured.url).toBe(endpoint);
    expect(captured.payload.messages[0].content).toContain('layout generator');
    expect(captured.payload.messages[1].content).toContain('"templateTarget": ".layout/template.hbs"');
  });

  test('omits folder-only prompt context fields for file targets', async () => {
    const result = await runPhpJson('php_prompt_compact_context.php');

    expect(result.file.current).toEqual(expect.objectContaining({
      subjectType: 'file',
      sectionTemplateTarget: '.works/viewer-file.txt.layout/work.hbs',
    }));
    expect(result.file.counts).toBeUndefined();
    expect(result.file.items).toBeUndefined();
    expect(result.folder.current).toEqual(expect.objectContaining({
      subjectType: 'folder',
      sectionTemplateTarget: 'viewer-folder/.layout/works.hbs',
    }));
    expect(result.folder.counts).toEqual(expect.objectContaining({
      items: expect.any(Number),
      files: expect.any(Number),
    }));
    expect(Array.isArray(result.folder.items)).toBe(true);
  });

  test('parses layout prompt JSON with css/js and restores the required default main block', async () => {
    const result = await runPromptModelParse('layout', JSON.stringify({
      template: '<div class="poff-default-layout poff-default-layout--file"><header class="poff-default-layout__header"></header><footer class="poff-default-layout__footer"></footer></div>',
      css: '.poff-default-layout{outline:0;}',
      js: 'document.documentElement.dataset.promptLayout = "on";',
    }));

    expect(result.css).toContain('.poff-default-layout{outline:0;}');
    expect(result.js).toContain('dataset.promptLayout');
    expect(result.template).toContain('<main class="poff-default-layout__main">');
    expect(result.template).toContain('{{#if isFolder}}');
    expect(result.template).toContain('{{> works}}');
    expect(result.template).toContain('{{> work}}');
  });

  test('parses OpenAI-compatible chat completion envelopes into template text', async () => {
    const result = await runPromptModelParse('work', JSON.stringify({
      id: 'chatcmpl-test',
      choices: [
        {
          message: {
            content: '<div class="card">{{title}}</div>',
          },
        },
      ],
    }));

    expect(result.template).toBe('<div class="card">{{title}}</div>');
  });

  test('parses fenced JSON chat completion envelopes into structured layout fields', async () => {
    const result = await runPromptModelParse('layout', JSON.stringify({
      id: 'chatcmpl-layout',
      choices: [
        {
          message: {
            content: "```json\n{\n  \"template\": \"<div class=\\\"poff-default-layout\\\"><header>{{title}}</header><footer>done</footer></div>\",\n  \"css\": \".poff-default-layout{outline:0;}\",\n  \"js\": \"document.body.dataset.layout='on';\",\n  \"work\": {\"works.hbs\": \"<article>{{description}}</article>\"}\n}\n```",
          },
        },
      ],
    }));

    expect(result.template).toContain('<main class="poff-default-layout__main">');
    expect(result.css).toContain('.poff-default-layout{outline:0;}');
    expect(result.js).toContain("dataset.layout='on'");
    expect(result.work).toEqual({ 'works.hbs': '<article>{{description}}</article>' });
  });

  test('treats empty chat completion content as empty instead of raw JSON', async () => {
    const result = await runPromptModelParse('work', JSON.stringify({
      id: 'chatcmpl-empty',
      choices: [
        {
          message: {
            role: 'assistant',
            content: '',
            reasoning_content: 'The model only reasoned and never produced a final answer.',
          },
          finish_reason: 'length',
        },
      ],
    }));

    expect(result.template).toBe('');
  });

  test('treats usage-only JSON model responses as empty instead of literal template text', async () => {
    const parseResult = await runPromptModelParse('work', JSON.stringify({
      usage: {
        prompt_tokens: 3870,
        completion_tokens: 226,
        total_tokens: 4096,
      },
      model: 'gemma4',
    }));

    expect(parseResult.template).toBe('');

    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const viewerResult = await runViewerPrompt(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'gemma4',
      endpoint,
      prompt: 'Make it smaller.',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: JSON.stringify({
        usage: {
          prompt_tokens: 3870,
          completion_tokens: 226,
          total_tokens: 4096,
        },
        model: 'gemma4',
      }),
    });

    expect(viewerResult.allowed).toBe(true);
    expect(viewerResult.error).toBe('Template was empty.');
    expect(viewerResult.template).toBeUndefined();
  });

  test('treats reasoning-only local chat responses as empty instead of storing the raw JSON envelope', async () => {
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const viewerResult = await runViewerPrompt(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'google/gemma-4-e4b',
      endpoint,
      prompt: 'Make the layout red again.',
      layoutPreset: 'actual',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: JSON.stringify({
        id: 'chatcmpl-md9dsfuuobyyebhpfelhd',
        object: 'chat.completion',
        created: 1776458336,
        model: 'google/gemma-4-e4b',
        choices: [
          {
            index: 0,
            message: {
              role: 'assistant',
              content: '',
              reasoning_content: 'The model reasoned but did not produce final template code.',
              tool_calls: [],
            },
            logprobs: null,
            finish_reason: 'length',
          },
        ],
        usage: {
          prompt_tokens: 3870,
          completion_tokens: 226,
          total_tokens: 4096,
          completion_tokens_details: {
            reasoning_tokens: 223,
          },
        },
        stats: {},
        system_fingerprint: 'google/gemma-4-e4b',
      }),
    });

    expect(viewerResult.allowed).toBe(true);
    expect(viewerResult.error).toBe('Template was empty.');
    expect(viewerResult.template).toBeUndefined();
  });
});

describe('Worktype HBS renderer', () => {
  test('normalizes default layout metadata for files', async () => {
    const output = await runWorktype('definition', 'image');
    const definition = JSON.parse(output);

    expect(definition.layout).toMatchObject({
      mode: 'poff-layout',
      name: 'poff-layout',
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

  test('sanitizes persisted raw chat JSON in section templates on read', async () => {
    const output = await runLayoutFilesystem('ensure-folder', INVALID_TEMPLATE_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout.sectionTemplate).toBe('');
    expect(config.work.layout.defaultSectionTemplate).toContain('folder-view');
    expect(config.work.layout.template).toContain('invalid-json-layout');
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
            name: 'poff-layout',
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
    expect(output).toContain('poff-default-layout__sidebar');
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
            template: '<div class="custom-shell"><a class="self-link" href="{{pageLink}}">{{title}}</a>{{#each items}}{{#if (eq type "file")}}<span class="entry">{{name}}</span>{{/if}}{{/each}}{{> poff-layout}}</div>',
          },
        },
      },
    });

    if (!lightnCandyInstalled) {
      expect(output).toContain('<div class="poff-folder-fallback">');
      expect(output).toContain('projects');
      expect(output).toContain('notes.txt');
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

  test('falls back to a non-iframe folder view when a layout template is invalid', async () => {
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
        parentPageLink: '?view=1&path=',
        directoryPageLink: '?view=1&path=projects',
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
            name: 'broken-layout',
            engine: 'lightncandy',
            section: 'works',
            template: '{{#if hasItems}}{{/unless}}',
          },
        },
      },
    });

    expect(output).not.toContain('<iframe ');
    if (lightnCandyInstalled) {
      expect(output).toContain('<div class="poff-default-layout poff-default-layout--folder">');
    } else {
      expect(output).toContain('<div class="poff-folder-fallback">');
    }
    expect(output).toContain('projects');
    expect(output).toContain('alpha');
    expect(output).toContain('notes.txt');
  });

  test('inherits a parent folder layout from the nearest ancestor .layout', async () => {
    const output = await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout).toMatchObject({
      name: 'filesystem-layout',
      storage: 'filesystem',
      directory: 'tests/poff-tests/.layout',
    });
    expect(config.work.layout.template).toContain('default-fs-layout');
    expect(config.work.layout.assets).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ path: 'eggman_profile-image.jpg' }),
      ]),
    );

    const rendered = await runViewer('inherits-default');
    expect(rendered).toContain('<div class="default-fs-layout">');
    expect(rendered).toContain('tests/poff-tests/.layout/style.css');
    expect(rendered).toContain('tests/poff-tests/.layout/script.js');
    expect(rendered).toContain('tests/poff-tests/.layout/eggman_profile-image.jpg');
    expect(rendered).toContain('<span class="item">child.txt</span>');
  });

  test('can persist edits back into the inherited original filesystem layout source', async () => {
    const originalTarget = path.relative(ROOT, path.join(POFF_DIR, '.layout'));
    await runLayoutFilesystem('persist-original', originalTarget, '', {
      template: '<div class="default-fs-layout default-fs-layout--edited"><header>{{title}}</header><main>{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main><footer><img src="{{layout.baseHref}}/eggman_profile-image.jpg" alt="profile"></footer></div>',
      css: '.default-fs-layout--edited{color:#ff5f5f;}',
      js: 'window.__editedDefaultFsLayout = true;',
    });

    expect(fs.readFileSync(path.join(POFF_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('default-fs-layout--edited');
    expect(fs.readFileSync(path.join(POFF_DIR, '.layout', 'style.css'), 'utf8')).toContain('#ff5f5f');
    expect(fs.readFileSync(path.join(POFF_DIR, '.layout', 'script.js'), 'utf8')).toContain('__editedDefaultFsLayout');

    const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR));
    expect(ensured.work.layout.directory).toBe('tests/poff-tests/.layout');
    expect(ensured.work.layout.template).toContain('default-fs-layout--edited');
    expect(ensured.work.layout.css).toContain('#ff5f5f');
    expect(ensured.work.layout.js).toContain('__editedDefaultFsLayout');
  });

  test('resolves virtual .layout targets separately from real file and folder targets', async () => {
    const folderTarget = JSON.parse(await runLayoutFilesystem('resolve-target', POFF_DIR, 'inherits-default/.layout'));
    expect(folderTarget).toMatchObject({
      type: 'layout',
      subjectType: 'folder',
      subjectRelativePath: 'inherits-default',
      virtualPath: 'inherits-default/.layout',
    });

    const fileTarget = JSON.parse(await runLayoutFilesystem('resolve-target', POFF_DIR, 'viewer-file.txt/.layout'));
    expect(fileTarget).toMatchObject({
      type: 'layout',
      subjectType: 'file',
      subjectRelativePath: 'viewer-file.txt',
      virtualPath: 'viewer-file.txt/.layout',
      file: 'viewer-file.txt',
    });
  });

  test('keeps the inherited wrapper when a local wrapped partial override exists', async () => {
    const payload = {
      name: 'poff-layout',
      engine: 'lightncandy',
      section: 'works',
      sectionTemplate: '<div class="local-works">{{#each items}}<span class="local-item">{{name}}</span>{{/each}}</div>',
    };

    await runLayoutFilesystem('persist-folder', INHERITED_SECTION_DIR, '', payload);

    expect(fs.readFileSync(path.join(INHERITED_SECTION_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('local-works');

    const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_SECTION_DIR));
    expect(ensured.work.layout.directory).toBe('tests/poff-tests/.layout');
    expect(ensured.work.layout.template).toContain('default-fs-layout');
    expect(ensured.work.layout.sectionDirectory).toBe('.layout');
    expect(ensured.work.layout.sectionTemplate).toContain('local-works');

    const rendered = await runViewer('inherits-section-default');
    expect(rendered).toContain('class="default-fs-layout');
    expect(rendered).toContain('<div class="local-works">');
    expect(rendered).toContain('<span class="local-item">hero.txt</span>');
    expect(rendered).toContain('tests/poff-tests/.layout/eggman_profile-image.jpg');
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

  test('injects the wrapped work partial when a filesystem file wrapper forgets to include it', async () => {
    await runLayoutFilesystem('persist-file', POFF_DIR, VIEWER_FILE_NAME, {
      name: 'filesystem-layout',
      engine: 'lightncandy',
      section: 'work',
      template: '<div class="broken-file-wrapper"><header>{{title}}</header><main><p>Wrapper only</p></main></div>',
      sectionTemplate: '<video class="wrapped-video" src="{{srcUrl}}" controls></video>',
    });

    const output = await runViewer(VIEWER_FILE_NAME);

    expect(output).toContain('<div class="broken-file-wrapper">');
    expect(output).toContain('<p>Wrapper only</p>');
    expect(output).toContain('<video class="wrapped-video" src="viewer-file.txt" controls></video>');
  });

  test('persists edited folder layout files into .layout', async () => {
    const payload = {
      name: 'persisted-layout',
      engine: 'lightncandy',
      section: 'works',
      preset: 'actual',
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
      preset: 'actual',
    });
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('Persisted');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'style.css'), 'utf8')).toContain('.persisted-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain('__persistedLayout');
    await runLayoutFilesystem('ensure-folder', PERSIST_LAYOUT_DIR);
    const persistConfigPath = path.join(PERSIST_LAYOUT_DIR, 'poff.config.json');
    const persistConfig = JSON.parse(fs.readFileSync(persistConfigPath, 'utf8'));
    persistConfig.work = {
      ...(persistConfig.work || {}),
      layout: serializedLayout,
    };
    fs.writeFileSync(persistConfigPath, JSON.stringify(persistConfig, null, 2));

    const ensuredOutput = await runLayoutFilesystem('ensure-folder', PERSIST_LAYOUT_DIR);
    const ensuredConfig = JSON.parse(ensuredOutput);
    expect(ensuredConfig.work.layout.template).toContain('Persisted');
    expect(ensuredConfig.work.layout.storage).toBe('filesystem');
    expect(ensuredConfig.work.layout.preset).toBe('actual');
  });

  test('persists wrapped content partials into works.hbs without replacing the wrapper', async () => {
    const payload = {
      name: 'poff-layout',
      engine: 'lightncandy',
      section: 'works',
      sectionTemplate: '<section class="persisted-section">{{title}}</section>',
    };

    const output = await runLayoutFilesystem('persist-folder', PERSIST_LAYOUT_DIR, '', payload);
    const serializedLayout = JSON.parse(output);

    expect(serializedLayout).toMatchObject({
      name: 'poff-layout',
      section: 'works',
      engine: 'lightncandy',
    });
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('persisted-section');

    const ensuredOutput = await runLayoutFilesystem('ensure-folder', PERSIST_LAYOUT_DIR);
    const ensuredConfig = JSON.parse(ensuredOutput);
    expect(ensuredConfig.work.layout.sectionTemplate).toContain('persisted-section');
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

  test('creates a blank file in a folder target', async () => {
    const output = await runBlankFile(UPLOAD_TARGET_DIR, 'draft.txt');
    const result = JSON.parse(output);

    expect(result.errors).toEqual([]);
    expect(result.stored).toEqual([
      expect.objectContaining({ name: 'draft.txt', path: 'draft.txt' }),
    ]);
    expect(fs.existsSync(path.join(UPLOAD_TARGET_DIR, 'draft.txt'))).toBe(true);
    expect(fs.readFileSync(path.join(UPLOAD_TARGET_DIR, 'draft.txt'), 'utf8')).toBe('');
    expect(result.config.tree).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ name: 'draft.txt', type: 'file' }),
      ]),
    );
  });
});
