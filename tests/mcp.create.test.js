const { spawn } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');
const vm = require('vm');

const ROOT = path.resolve(__dirname, '..');
const BUILD_SHARED_CONFIG = JSON.parse(fs.readFileSync(path.join(ROOT, 'build', 'BuildConfig.shared.json'), 'utf8'));
const RUNTIME_ROOT = path.join(ROOT, BUILD_SHARED_CONFIG.pagesDir, BUILD_SHARED_CONFIG.siteHost);
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
const DELETE_FILE_DIR = path.join(POFF_DIR, 'delete-file-target');
const DELETE_FOLDER_DIR = path.join(POFF_DIR, 'delete-folder-target');
const UPLOAD_TARGET_DIR = path.join(POFF_DIR, 'upload-target');
const VIRTUAL_LINK_DIR = path.join(POFF_DIR, 'virtual-links');
const REMOTE_IMPORT_DIR = path.join(POFF_DIR, 'remote-import-links');

function withTestEnv(extra = {}) {
  return {
    ...process.env,
    POFF_TEST_AUTH_BYPASS: '1',
    ...extra,
  };
}

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
      env: withTestEnv({ POFF_BASE: POFF_DIR }),
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
      env: withTestEnv(),
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

function runWorktypeDetailed(action, kind, payload = null) {
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
      if (code === 0) {
        resolve({
          stdout: stdout.trim(),
          stderr: stderr.trim(),
        });
        return;
      }
      reject(new Error(`worktype helper failed: ${code} ${stderr}`));
    });
  });
}

function runViewer(relativePath, baseDir = POFF_DIR, editMode = false) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_render_viewer.php'), baseDir, relativePath];
    if (editMode) {
      args.push('edit');
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
      reject(new Error(`viewer helper failed: ${code} ${stderr}`));
    });
  });
}

function runViewerWithMock(relativePath, baseDir = POFF_DIR, editMode = false, mockResponse = null) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_render_viewer.php'), baseDir, relativePath];
    if (editMode) {
      args.push('edit');
    }
    if (mockResponse !== null) {
      args.push(JSON.stringify({ mockResponse }));
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
      reject(new Error(`viewer helper failed: ${code} ${stderr}`));
    });
  });
}

function runResolveRemoteRenderedHtml(item, mockResponse = null) {
  return new Promise((resolve, reject) => {
    const payload = { item };
    if (mockResponse !== null) {
      payload.mockResponse = mockResponse;
    }
    const proc = spawn('php', [path.join(ROOT, 'tests/php_resolve_remote_rendered_html.php'), JSON.stringify(payload)], {
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
      reject(new Error(`remote html helper failed: ${code} ${stderr}`));
    });
  });
}

function runNav(relativePath, baseDir = POFF_DIR, editMode = false) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_render_nav.php'), baseDir, relativePath];
    if (editMode) {
      args.push('edit');
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
      reject(new Error(`nav helper failed: ${code} ${stderr}`));
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

function runLayoutView(payload = {}) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_layout_view.php'), JSON.stringify(payload)], {
      cwd: ROOT,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) return resolve(JSON.parse(stdout.trim()));
      reject(new Error(`layout view helper failed: ${code} ${stderr}`));
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

function runRemoteContent(mode, baseDir, relativePath = '', payload = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_remote_content_routes.php'),
      mode,
      baseDir,
      relativePath,
      JSON.stringify(payload),
    ];
    const proc = spawn('php', args, {
      cwd: ROOT,
      env: withTestEnv(),
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) {
        resolve(JSON.parse(stdout.trim()));
        return;
      }
      reject(new Error(`remote content helper failed: ${code} ${stderr}`));
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

function runDeleteTarget(targetDir, relativePath) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_delete_item.php'), targetDir, relativePath];
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
      reject(new Error(`delete helper failed: ${code} ${stderr}`));
    });
  });
}

function runResetFolder(targetDir, relativePath) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_reset_folder.php'), targetDir, relativePath];
    const proc = spawn('php', args, {
      cwd: ROOT,
      env: { ...process.env, POFF_BASE: POFF_DIR },
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) return resolve(stdout.trim());
      reject(new Error(`reset helper failed: ${code} ${stderr}`));
    });
  });
}

function runCreateFolder(targetDir, folderName) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_create_folder.php'), targetDir, folderName];
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
      reject(new Error(`create folder helper failed: ${code} ${stderr}`));
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

function loadPromptDraftHelpers() {
  const filePath = path.join(ROOT, 'src/assets/js/edit/prompt/draft.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
    document: null,
  });

  vm.runInContext(`${source}
module.exports = {
  readPromptEditorDraft,
};
`, context);

  return module.exports;
}

function loadPromptLayoutPayloadHelpers() {
  const filePath = path.join(ROOT, 'src/assets/js/edit/prompt/layout-payload.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*;\n/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
    getLayoutState(config) {
      return config && config.work && config.work.layout && typeof config.work.layout === 'object'
        ? config.work.layout
        : {};
    },
    getLayoutPresetValue() {
      return 'actual';
    },
  });

  vm.runInContext(`${source}
module.exports = {
  buildPromptLayoutPayload,
};
`, context);

  return module.exports;
}

function loadPromptApiHelpers() {
  const filePath = path.join(ROOT, 'src/assets/js/api/edit.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export async function /g, 'async function ')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const fetchState = {
    impl: async () => {
      throw new Error('fetch should be mocked in test');
    },
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
    AbortController: global.AbortController,
    TextDecoder: global.TextDecoder,
    URL: global.URL,
    window: {
      location: {
        pathname: '/dominikeggermann.com/',
        origin: 'http://localhost:8888',
      },
    },
    setTimeout,
    clearTimeout,
    fetch: (...args) => fetchState.impl(...args),
  });

  vm.runInContext(`${source}
module.exports = {
  buildCmsUrl,
  buildLocalModelsUrl,
  requestPromptModels,
  requestLocalPromptModels,
  requestPromptTemplateStream,
};
`, context);

  return {
    ...module.exports,
    setFetch(impl) {
      fetchState.impl = impl;
    },
  };
}

function loadWorkFieldHelpers() {
  const filePath = path.join(ROOT, 'src/assets/js/edit/work-fields.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
    document: null,
  });

  vm.runInContext(`${source}
module.exports = {
  createDefaultWorkField,
  extractWorkFields,
  materializeWorkFields,
  normalizeWorkField,
  summarizeWorkFields,
};
`, context);

  return module.exports;
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
      env: withTestEnv(),
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
      env: withTestEnv(),
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

function runViewerPromptRaw(rootDir, relativePath, payload = {}, mockResponse = null, capturePath = '', mockStreamResponse = null) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_viewer_prompt.php'),
      rootDir,
      relativePath,
      JSON.stringify(payload),
      mockResponse ? JSON.stringify(mockResponse) : '',
      capturePath,
      mockStreamResponse ? JSON.stringify(mockStreamResponse) : '',
    ], {
      cwd: ROOT,
      env: withTestEnv(),
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`viewer prompt raw helper failed: ${code} ${stderr}`));
        return;
      }
      resolve(stdout);
    });
  });
}

function runViewerSave(cwd, relativePath, payload = {}) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_viewer_save.php'),
      cwd,
      relativePath,
      JSON.stringify(payload),
    ], {
      cwd: ROOT,
      env: withTestEnv(),
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code !== 0) {
        reject(new Error(`viewer save helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from viewer save helper: ${stdout}`));
      }
    });
  });
}

function runEditRequest(cwd, action, relativePath = '', payload = {}, sessionId = '') {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_edit_request.php'),
      cwd,
      action,
      relativePath,
      JSON.stringify(payload),
      sessionId,
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
        reject(new Error(`edit request helper failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from edit request helper: ${stdout}`));
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

async function makePasswordHash(password) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', ['-r', `echo password_hash(${JSON.stringify(password)}, PASSWORD_DEFAULT);`], {
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
        reject(new Error(`password hash helper failed: ${code} ${stderr}`));
        return;
      }
      resolve(stdout.trim());
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
    fs.mkdirSync(path.join(VIEWER_FOLDER_DIR, 'nested-child'), { recursive: true });
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'child.txt'), 'viewer child');
    fs.writeFileSync(path.join(VIEWER_FOLDER_DIR, 'nested-child', 'nested-video.mp4'), 'fake video');
    fs.writeFileSync(path.join(POFF_DIR, '.poff-auth.php'), "<?php\nreturn ['passwordHash' => 'fake'];\n");
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
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'template.hbs'), '<div class="default-fs-layout"><header>{{title}}</header><main>{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main><footer><img src="{{layout.baseHref}}/poff.profile.jpg" alt="profile"></footer></div>');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'works.hbs'), '{{#each items}}<span class="item">{{name}}</span>{{/each}}');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'work.hbs'), '<span class="file-name">{{name}}</span>');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'style.css'), '.default-fs-layout{display:block;}');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'script.js'), 'window.__defaultFsLayout = true;');
    fs.writeFileSync(path.join(POFF_DIR, '.layout', 'poff.profile.jpg'), 'fake image bytes');
    fs.mkdirSync(INHERITED_DEFAULT_DIR, { recursive: true });
    fs.writeFileSync(path.join(INHERITED_DEFAULT_DIR, 'child.txt'), 'child');
    fs.mkdirSync(INHERITED_SECTION_DIR, { recursive: true });
    fs.writeFileSync(path.join(INHERITED_SECTION_DIR, 'hero.txt'), 'hero');
    fs.mkdirSync(DELETE_FILE_DIR, { recursive: true });
    fs.writeFileSync(path.join(DELETE_FILE_DIR, 'remove-me.txt'), 'remove me');
    fs.mkdirSync(path.join(DELETE_FILE_DIR, '.works', 'remove-me.txt.layout'), { recursive: true });
    fs.writeFileSync(path.join(DELETE_FILE_DIR, '.works', 'remove-me.txt.config.json'), JSON.stringify({
      title: 'Remove Me',
      description: 'Delete target file',
      work: {
        type: 'text',
        layout: {
          name: 'filesystem-file-layout',
          engine: 'lightncandy',
          section: 'work',
        },
      },
    }, null, 2));
    fs.writeFileSync(path.join(DELETE_FILE_DIR, '.works', 'remove-me.txt.layout', 'template.hbs'), '<div class="delete-target-file">{{title}}</div>');
    fs.mkdirSync(path.join(DELETE_FOLDER_DIR, 'nested', 'child'), { recursive: true });
    fs.writeFileSync(path.join(DELETE_FOLDER_DIR, 'nested', 'child', 'deep.txt'), 'deep');
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
    fs.mkdirSync(VIRTUAL_LINK_DIR, { recursive: true });
    fs.mkdirSync(path.join(VIRTUAL_LINK_DIR, '.layout'), { recursive: true });
    fs.writeFileSync(path.join(VIRTUAL_LINK_DIR, 'existing.txt'), 'existing');
    fs.writeFileSync(path.join(VIRTUAL_LINK_DIR, '.layout', 'works.hbs'), '{{#each items}}<a class="virtual-link" href="{{pageLink}}">{{name}}</a>{{/each}}');
    fs.writeFileSync(path.join(VIRTUAL_LINK_DIR, 'poff.config.json'), JSON.stringify({
      folderName: 'virtual-links',
      slug: 'virtual-links',
      title: 'Virtual Links',
      description: 'Folder with configured links',
      tree: [
        {
          name: 'Portfolio',
          type: 'folder',
          path: '?view=1&path=linkone',
          visible: true,
        },
        {
          name: 'Contact',
          type: 'link',
          url: 'https://example.com/contact',
          visible: true,
        },
        {
          name: 'existing.txt',
          type: 'file',
          path: 'existing.txt',
          visible: true,
        },
      ],
      work: {
        type: 'folder',
        layout: {
          name: 'filesystem-folder-layout',
          engine: 'lightncandy',
          section: 'works',
        },
      },
    }, null, 2));
    fs.mkdirSync(REMOTE_IMPORT_DIR, { recursive: true });
    fs.mkdirSync(path.join(REMOTE_IMPORT_DIR, '.layout'), { recursive: true });
    fs.writeFileSync(path.join(REMOTE_IMPORT_DIR, '.layout', 'works.hbs'), '{{#each items}}<a class="remote-link" href="{{pageLink}}">{{name}}</a>{{/each}}');
    fs.writeFileSync(path.join(REMOTE_IMPORT_DIR, 'poff.config.json'), JSON.stringify({
      folderName: 'remote-import-links',
      slug: 'remote-import-links',
      title: 'Remote Import Links',
      description: 'Folder with imported remote entries',
      tree: [],
      work: {
        type: 'folder',
        layout: {
          name: 'filesystem-folder-layout',
          engine: 'lightncandy',
          section: 'works',
        },
      },
    }, null, 2));
  });

  test('viewer save stays locked until auth login succeeds', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-auth-'));
    const sessionId = 'poff-auth-session';
    const hash = await makePasswordHash('secret-pass');
    fs.writeFileSync(path.join(tempDir, '.poff-auth.php'), `<?php return ['passwordHash' => '${hash}'];`);

    const denied = await runEditRequest(tempDir, 'save', '', { title: 'Blocked title' });
    expect(denied.allowed).toBe(false);
    expect(denied.error).toContain('Edit login required');

    const loggedIn = await runEditRequest(tempDir, 'auth', '', { intent: 'login', password: 'secret-pass' }, sessionId);
    expect(loggedIn.allowed).toBe(true);
    expect(loggedIn.auth.authenticated).toBe(true);

    const saved = await runEditRequest(tempDir, 'save', '', { title: 'Unlocked title' }, loggedIn.sessionId || sessionId);
    expect(saved.saved).toBe(true);
    expect(saved.config.title).toBe('Unlocked title');

    const changed = await runEditRequest(tempDir, 'auth', '', {
      intent: 'change-password',
      currentPassword: 'secret-pass',
      newPassword: 'new-secret-pass',
      confirmPassword: 'new-secret-pass',
    }, loggedIn.sessionId || sessionId);
    expect(changed.allowed).toBe(true);
    expect(changed.changed).toBe(true);

    await runEditRequest(tempDir, 'auth', '', { intent: 'logout' }, loggedIn.sessionId || sessionId);

    const oldLoginDenied = await runEditRequest(tempDir, 'auth', '', {
      intent: 'login',
      password: 'secret-pass',
    }, sessionId);
    expect(oldLoginDenied.allowed).toBe(false);

    const newLogin = await runEditRequest(tempDir, 'auth', '', {
      intent: 'login',
      password: 'new-secret-pass',
    }, sessionId);
    expect(newLogin.allowed).toBe(true);
    expect(newLogin.auth.authenticated).toBe(true);

    fs.rmSync(tempDir, { recursive: true, force: true });
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
              content: JSON.stringify({
                template: '<section class="lm-studio-card">{{title}}</section>',
                css: '.lm-studio-card{--card-surface:#fff;display:grid;gap:clamp(0.75rem,2vw,1.25rem);}',
                js: 'document.querySelectorAll(".lm-studio-card").forEach((card) => card.dataset.ready = "true");',
              }),
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
    expect(result.css).toContain('.lm-studio-card');
    expect(result.js).toContain('dataset.ready');
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
    expect(captured.payload.messages[3].content).toContain('"outerWrapper"');
    expect(captured.payload.messages[3].content).toContain('"source": "resolved active wrapper"');
    expect(captured.payload.messages[3].content).toContain('USER: Create a compact image card.');
    expect(captured.payload.messages[3].content).not.toContain('Previous draft.');
    expect(captured.payload.messages[3].content).not.toContain('Make it smaller.');
  });

  test('rejects prompt-template CSS with unsafe global styles', async () => {
    const result = await runPromptTemplateLocal(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'gemma4',
      endpoint: 'http://127.0.0.1:1234/v1/chat/completions',
      prompt: 'Create a compact image card.',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: JSON.stringify({
        choices: [
          {
            message: {
              content: JSON.stringify({
                template: '<section class="unsafe-card">{{title}}</section>',
                css: '<style>body{margin:0;} @import url("https://example.com/base.css");</style>',
              }),
            },
          },
        ],
      }),
    });

    expect(result.allowed).toBe(true);
    expect(result.error).toContain('Generated CSS was rejected');
    expect(result.error).toContain('CSS must not include <style> tags.');
    expect(result.error).toContain('CSS must not import external stylesheets.');
    expect(result.error).toContain('CSS must not include global html/body/:root/universal selectors.');
    expect(result.template).toBeUndefined();
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

  test('includes unsaved editor draft content in layout prompt context', async () => {
    const capturePath = path.join(POFF_DIR, 'viewer-layout-draft-request.json');
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const result = await runViewerPrompt(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: 'gemma4',
      endpoint,
      prompt: 'Push this draft further.',
      layoutPreset: 'actual',
      draft: {
        template: '<div class="draft-layout">{{title}}</div>',
        sectionTemplate: '<article class="draft-section">{{description}}</article>',
        css: '.draft-layout{background:red;}',
        js: 'document.body.dataset.draft = "on";',
      },
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
              }),
            },
          },
        ],
      }),
    }, capturePath);

    const captured = JSON.parse(fs.readFileSync(capturePath, 'utf8'));

    expect(result.allowed).toBe(true);
    expect(captured.payload.messages[0].content).toContain('current.editorDraft');
    expect(captured.payload.messages[1].content).toContain('"editorDraft"');
    expect(captured.payload.messages[1].content).toContain('<div class=\\"draft-layout\\">{{title}}</div>');
    expect(captured.payload.messages[1].content).toContain('.draft-layout{background:red;}');
  });

  test('strips malformed fenced JSON-ish layout wrappers from local prompt responses', async () => {
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
              content: `\`\`\`json
{
  "template": "<div class="poff-default-layout">
    <header>{{title}}</header>
    <footer>done</footer>
  </div>",
  "css": ".poff-default-layout{min-height:100vh;}",
  "js": "document.documentElement.dataset.layout = 'cinematic';"
}
\`\`\``,
            },
          },
        ],
      }),
    });

    expect(result.allowed).toBe(true);
    expect(result.template).toContain('<main class="poff-default-layout__main">');
    expect(result.template).not.toContain('```json');
    expect(result.template).not.toContain('"template"');
    expect(result.css).toContain('min-height:100vh');
    expect(result.js).toContain("layout = 'cinematic'");
  });

  test('omits folder-only prompt context fields for file targets', async () => {
    const result = await runPhpJson('php_prompt_compact_context.php');

    expect(result.file.current).toEqual(expect.objectContaining({
      subjectType: 'file',
      sectionTemplateTarget: '.works/viewer-file.txt.layout/work.hbs',
      root: expect.objectContaining({
        title: 'Viewer File',
      }),
      work: expect.objectContaining({
        title: 'viewer-file.txt',
      }),
      outerWrapper: expect.objectContaining({
        sectionPartial: 'work',
      }),
    }));
    expect(result.file.current.outerWrapper.template).toContain('poff-default-layout__main');
    expect(result.file.counts).toBeUndefined();
    expect(result.file.items).toBeUndefined();
    expect(result.file.current.parentWork).toEqual(expect.objectContaining({
      title: 'poff-tests',
      path: '',
    }));
    expect(result.file.siblingWorks).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'viewer-folder',
        kind: 'folder',
      }),
    ]));
    expect(result.file.siblingWorks.map((item) => item.name)).not.toContain('viewer-file.txt');
    expect(result.folder.current).toEqual(expect.objectContaining({
      subjectType: 'folder',
      sectionTemplateTarget: 'viewer-folder/.layout/works.hbs',
      root: expect.objectContaining({
        title: 'Folder Preview',
      }),
      work: expect.objectContaining({
        title: 'viewer-folder',
      }),
      outerWrapper: expect.objectContaining({
        sectionPartial: 'works',
      }),
    }));
    expect(result.folder.current.outerWrapper.template).toContain('poff-default-layout__main');
    expect(result.folder.current.parentWork).toEqual(expect.objectContaining({
      title: 'poff-tests',
      path: '',
    }));
    expect(result.folder.siblingWorks.map((item) => item.name)).not.toContain('viewer-folder');
    expect(result.folder.counts).toEqual(expect.objectContaining({
      items: expect.any(Number),
      files: expect.any(Number),
    }));
    expect(Array.isArray(result.folder.items)).toBe(true);
    expect(Array.isArray(result.folder.current.tree)).toBe(true);
    expect(result.folder.current.tree).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'nested-child',
        type: 'folder',
        children: expect.arrayContaining([
          expect.objectContaining({
            name: 'nested-video.mp4',
            type: 'file',
          }),
        ]),
      }),
    ]));
    expect(result.folder.current.workTree).toEqual(expect.objectContaining({
      children: expect.arrayContaining([
        expect.objectContaining({
          name: 'nested-child',
          type: 'folder',
        }),
      ]),
    }));
  });

  test('includes custom work fields in prompt compact output', async () => {
    const result = await runPhpJson('php_prompt_compact_context.php');

    expect(result.fileConfig).toEqual(expect.objectContaining({
      work: expect.objectContaining({
        fields: expect.arrayContaining([
          expect.objectContaining({
            type: 'text',
            name: 'text1',
            label: 'Text 1',
            value: 'Prominent section copy',
          }),
        ]),
        text1: 'Prominent section copy',
        templateMap: expect.objectContaining({
          count: 2,
          entries: expect.arrayContaining([
            expect.objectContaining({
              mime: 'image/jpeg',
              template: 'image',
            }),
          ]),
        }),
      }),
    }));
    expect(result.folderConfig).toEqual(expect.objectContaining({
      work: expect.objectContaining({
        fields: expect.arrayContaining([
          expect.objectContaining({
            type: 'text',
            name: 'text1',
            label: 'Text 1',
            value: 'Folder prominent copy',
          }),
        ]),
        text1: 'Folder prominent copy',
        templateMap: expect.objectContaining({
          count: 1,
          entries: expect.arrayContaining([
            expect.objectContaining({
              mime: 'video/quicktime',
              template: 'video',
            }),
          ]),
        }),
      }),
    }));
    expect(result.file.current).toEqual(expect.objectContaining({
      workFields: expect.arrayContaining([
        expect.objectContaining({
          name: 'text1',
          type: 'text',
          value: 'Prominent section copy',
        }),
      ]),
      work: expect.objectContaining({
        templateMap: expect.objectContaining({
          count: 2,
          entries: expect.arrayContaining([
            expect.objectContaining({
              mime: 'video/quicktime',
              template: 'video',
            }),
          ]),
        }),
      }),
    }));
  });

  test('keeps explicit internal and external configured tree links intact in prompt context', async () => {
    const result = await runPhpJson('php_virtual_link_context.php');

    expect(result.prompt.items).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'Portfolio',
        path: 'linkone',
        pageLink: '?view=1&path=linkone',
        isFolder: true,
      }),
      expect.objectContaining({
        name: 'Contact',
        kind: 'link',
        pageLink: 'https://example.com/contact',
        linkUrl: 'https://example.com/contact',
        isFile: true,
      }),
    ]));
    expect(JSON.stringify(result.prompt)).not.toContain('?view=1&path=%3Fview%3D1%26path%3Dlinkone');
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

  test('strips outer layout shell blocks from non-layout prompt responses', async () => {
    const result = await runPromptModelParse('work', JSON.stringify({
      id: 'chatcmpl-shell-artifacts',
      choices: [
        {
          message: {
            content: '<header class="poff-default-layout__header"><div class="poff-default-layout__header-copy"><h2 class="poff-default-layout__video-title">{{title}}</h2></div></header><div class="poff-default-layout__video"><video class="poff-default-layout__video-player" src="{{srcUrl}}" autoplay controls></video></div>',
          },
        },
      ],
    }));

    expect(result.template).toContain('poff-default-layout__video');
    expect(result.template).toContain('autoplay');
    expect(result.template).not.toContain('poff-default-layout__header');
    expect(result.template).not.toContain('poff-default-layout__header-copy');
  });

  test('extracts main-inner content when a non-layout prompt returns a full wrapper', async () => {
    const result = await runPromptModelParse('work', JSON.stringify({
      id: 'chatcmpl-full-wrapper',
      choices: [
        {
          message: {
            content: '<div class="poff-default-layout"><header class="poff-default-layout__header"><h1>{{title}}</h1></header><main class="poff-default-layout__main"><article class="autoplay-card"><video src="{{srcUrl}}" autoplay muted loop controls></video></article></main><footer class="poff-default-layout__footer">done</footer></div>',
          },
        },
      ],
    }));

    expect(result.template).toBe('<article class="autoplay-card"><video src="{{srcUrl}}" autoplay muted loop controls></video></article>');
  });

  test('strips generic shell tags from non-layout prompt responses', async () => {
    const result = await runPromptModelParse('work', JSON.stringify({
      id: 'chatcmpl-generic-shell-artifacts',
      choices: [
        {
          message: {
            content: '<header><h2>{{title}}</h2></header><div class="video-card"><video src="{{srcUrl}}" autoplay controls></video></div><footer><a href="{{pageLink}}">Open</a></footer>',
          },
        },
      ],
    }));

    expect(result.template).toBe('<div class="video-card"><video src="{{srcUrl}}" autoplay controls></video></div>');
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

  test('salvages malformed fenced JSON-ish layout content without leaking wrapper artefacts', async () => {
    const result = await runPromptModelParse('layout', JSON.stringify({
      id: 'chatcmpl-layout-malformed',
      choices: [
        {
          message: {
            content: `\`\`\`json
{
  "template": "<div class="poff-default-layout">
    <header>{{title}}</header>
    <footer>done</footer>
  </div>",
  "css": ".poff-default-layout{outline:0;}",
  "js": "document.body.dataset.layout='on';"
}
\`\`\``,
          },
        },
      ],
    }));

    expect(result.template).toContain('<main class="poff-default-layout__main">');
    expect(result.template).not.toContain('```json');
    expect(result.template).not.toContain('"template"');
    expect(result.css).toContain('.poff-default-layout{outline:0;}');
    expect(result.js).toContain("dataset.layout='on'");
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
    expect(viewerResult.error).toBe('Model returned reasoning only and no template text. Disable reasoning/thinking in LM Studio or ask the model to return final template text.');
    expect(viewerResult.template).toBeUndefined();
  });

  test('recovers streamed JSON when the final SSE event is dropped', async () => {
    const api = loadPromptApiHelpers();
    const chunks = [
      'data: {"choices":[{"delta":{"content":"{\\"template\\":\\""}}]}\n\n',
      'data: {"choices":[{"delta":{"content":"<div class=card></div>\\"}"}}]}\n\n',
    ];
    api.setFetch(async () => ({
      ok: true,
      headers: {
        get(name) {
          return name && name.toLowerCase() === 'content-type'
            ? 'text/event-stream; charset=utf-8'
            : null;
        },
      },
      body: {
        getReader() {
          let index = 0;
          return {
            async read() {
              if (index >= chunks.length) {
                return { done: true, value: undefined };
              }
              const value = new TextEncoder().encode(chunks[index++]);
              return { done: false, value };
            },
          };
        },
      },
      text: async () => '',
    }));

    const response = await api.requestPromptTemplateStream({
      path: '',
      provider: 'openai',
      model: 'gpt-4o-mini',
    });

    expect(response.allowed).toBe(true);
    expect(response.template).toBe('<div class=card></div>');
    expect(response.error).toBeUndefined();
  });

  test('loads LM Studio models from the companion models endpoint and filters embedding models', async () => {
    const api = loadPromptApiHelpers();
    let requestUrl = null;
    let requestOptions = null;
    api.setFetch(async (url, options) => {
      requestUrl = url;
      requestOptions = options;
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({
          allowed: true,
          models: ['google/gemma-4-e4b', 'qwen/qwen3-vl-4b'],
        }),
      };
    });

    const response = await api.requestLocalPromptModels('http://127.0.0.1:1234/v1/chat/completions');

    expect(requestUrl.toString()).toBe('http://localhost:8888/dominikeggermann.com/?edit=models');
    expect(requestOptions.method).toBe('POST');
    expect(JSON.parse(requestOptions.body)).toEqual({
      provider: 'local',
      endpoint: 'http://127.0.0.1:1234/v1/chat/completions',
      apiKey: '',
    });
    expect(api.buildLocalModelsUrl('http://127.0.0.1:1234/v1/chat/completions')).toBe('http://127.0.0.1:1234/v1/models');
    expect(response.error).toBeUndefined();
    expect(response.models).toEqual(['google/gemma-4-e4b', 'qwen/qwen3-vl-4b']);
  });

  test('local models proxy response tolerates legacy LM Studio shape after proxying', async () => {
    const api = loadPromptApiHelpers();
    api.setFetch(async () => ({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({
        allowed: true,
        models: ['google/gemma-4-e4b', 'qwen/qwen3-vl-4b'],
      }),
    }));

    const response = await api.requestLocalPromptModels('http://127.0.0.1:1234/v1/chat/completions');

    expect(response.error).toBeUndefined();
    expect(response.models).toEqual(['google/gemma-4-e4b', 'qwen/qwen3-vl-4b']);
  });

  test('loads OpenAI models from companion models endpoint with api key payload', async () => {
    const api = loadPromptApiHelpers();
    let requestUrl = null;
    let requestOptions = null;
    api.setFetch(async (url, options) => {
      requestUrl = url;
      requestOptions = options;
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({
          allowed: true,
          models: ['gpt-4o-mini', 'gpt-4.1-mini'],
        }),
      };
    });

    const response = await api.requestPromptModels({
      provider: 'openai',
      apiKey: 'sk-test',
    });

    expect(requestUrl.toString()).toBe('http://localhost:8888/dominikeggermann.com/?edit=models');
    expect(requestOptions.method).toBe('POST');
    expect(JSON.parse(requestOptions.body)).toEqual({
      provider: 'openai',
      endpoint: '',
      apiKey: 'sk-test',
    });
    expect(response.error).toBeUndefined();
    expect(response.models).toEqual(['gpt-4o-mini', 'gpt-4.1-mini']);
  });

  test('loads Gemini models from companion models endpoint with api key payload', async () => {
    const api = loadPromptApiHelpers();
    let requestUrl = null;
    let requestOptions = null;
    api.setFetch(async (url, options) => {
      requestUrl = url;
      requestOptions = options;
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({
          allowed: true,
          models: ['gemini-2.5-flash', 'gemini-1.5-flash'],
        }),
      };
    });

    const response = await api.requestPromptModels({
      provider: 'gemini',
      apiKey: 'gemini-test',
    });

    expect(requestUrl.toString()).toBe('http://localhost:8888/dominikeggermann.com/?edit=models');
    expect(requestOptions.method).toBe('POST');
    expect(JSON.parse(requestOptions.body)).toEqual({
      provider: 'gemini',
      endpoint: '',
      apiKey: 'gemini-test',
    });
    expect(response.error).toBeUndefined();
    expect(response.models).toEqual(['gemini-2.5-flash', 'gemini-1.5-flash']);
  });

  test('viewer prompt stream emits a final SSE payload on success', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'viewer-prompt-stream-final-'));
    try {
      const output = await runViewerPromptRaw(tempRoot, '', {
        provider: 'openai',
        model: 'gpt-4o-mini',
        apiKey: 'test-key',
        prompt: 'make it red',
        stream: true,
      }, null, '', {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        chunks: [
          'data: {"choices":[{"delta":{"content":"{\\"template\\":\\"<div class=card></div>\\"}"}}]}\n\n',
          'data: [DONE]\n\n',
        ],
      });

      expect(output).toContain('event: final');
      expect(output).toContain('"allowed":true');
      expect(output).toContain('"template":"<div class=card></div>"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });
});

describe('Worktype HBS renderer', () => {
  test('reads the current unsaved editor draft for layout and work prompt targets', () => {
    const { readPromptEditorDraft } = loadPromptDraftHelpers();
    const fields = {
      '#edit-layout-primary-template': { value: '<div class="draft-layout"></div>' },
      '#edit-layout-primary-css': { value: '.draft-layout{color:red;}' },
      '#edit-layout-primary-js': { value: 'console.log("draft");' },
      '#edit-content-template': { value: '<article class="draft-section"></article>' },
    };
    const root = {
      querySelector(selector) {
        return fields[selector] || null;
      },
    };

    expect(readPromptEditorDraft({ isLayout: true }, root)).toEqual({
      template: '<div class="draft-layout"></div>',
      sectionTemplate: '<article class="draft-section"></article>',
      css: '.draft-layout{color:red;}',
      js: 'console.log("draft");',
    });

    expect(readPromptEditorDraft({ isLayout: false }, root)).toEqual({
      template: '<article class="draft-section"></article>',
    });
  });

  test('builds section-only save payloads for file prompts even when the model returns css/js', () => {
    const { buildPromptLayoutPayload } = loadPromptLayoutPayloadHelpers();

    const { layoutPayload } = buildPromptLayoutPayload({
      selection: {
        isLayout: false,
        previewIsFile: true,
        previewPath: '1er/12test2 (Konvertiert).mp4',
      },
      currentConfig: {
        work: {
          layout: {
            name: 'poff-layout',
            directory: '1er/.layout',
            inheritedDirectory: '.layout',
          },
        },
      },
      drawerForm: {
        elements: {
          work_layout: { value: 'poff-layout' },
        },
      },
      templateText: '<article class="file-prompt-result">{{title}}</article>',
      nextCss: '.file-prompt-result{color:red;}',
      nextJs: 'window.__filePrompt = true;',
      responseModel: 'gpt-4o-mini',
    });

    expect(layoutPayload).toEqual({
      sectionTemplate: '<article class="file-prompt-result">{{title}}</article>',
    });
    expect(layoutPayload).not.toHaveProperty('name');
    expect(layoutPayload).not.toHaveProperty('engine');
    expect(layoutPayload).not.toHaveProperty('css');
    expect(layoutPayload).not.toHaveProperty('js');
    expect(layoutPayload).not.toHaveProperty('model');
  });

  test('keeps layout prompts focused on the outer wrapper only', () => {
    const { buildPromptLayoutPayload } = loadPromptLayoutPayloadHelpers();

    const { layoutPayload } = buildPromptLayoutPayload({
      selection: {
        isLayout: true,
        previewIsFile: false,
        layoutIsFile: false,
        previewPath: '1er/.layout',
      },
      currentConfig: {
        work: {
          layout: {
            name: 'filesystem-layout',
            directory: '1er/.layout',
            storage: 'filesystem',
          },
        },
      },
      drawerForm: {
        elements: {
          work_layout: { value: 'filesystem-layout' },
        },
      },
      templateText: '<div class="layout-shell"></div>',
      responseSectionTemplate: '<article class="should-not-be-saved"></article>',
      responseWorkTemplate: '<span class="should-not-be-saved"></span>',
      responseWorksTemplate: '<div class="should-not-be-saved"></div>',
      nextCss: '.layout-shell{color:rebeccapurple;}',
      nextJs: 'console.log("layout");',
      responseModel: 'gpt-4o-mini',
      layoutPreset: 'actual',
    });

    expect(layoutPayload).toMatchObject({
      originalTemplate: '<div class="layout-shell"></div>',
      originalCss: '.layout-shell{color:rebeccapurple;}',
      originalJs: 'console.log("layout");',
      originalTarget: '1er/.layout',
    });
    expect(layoutPayload).not.toHaveProperty('sectionTemplate');
    expect(layoutPayload).not.toHaveProperty('workTemplate');
    expect(layoutPayload).not.toHaveProperty('worksTemplate');
  });

  test('persists prompt history in the shared config for other browsers', async () => {
    const tempDir = path.join(POFF_DIR, `shared-prompt-history-${Date.now()}`);
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(tempDir, { recursive: true });

    try {
      const history = [
        { role: 'user', content: 'set MAUAMAUAMAUAM' },
        {
          role: 'assistant',
          content: '<div>ok</div>',
          templateSnapshot: {
            targetType: 'partial',
            template: '<div>ok</div>',
          },
        },
      ];

      const saved = await runViewerSave(tempDir, '', {
        promptHistory: history,
      });

      const storedConfig = JSON.parse(fs.readFileSync(path.join(tempDir, 'poff.config.json'), 'utf8'));
      expect(storedConfig.promptHistory).toHaveLength(2);
      expect(storedConfig.promptHistory[0].content).toContain('MAUAMAUAMAUAM');
      expect(storedConfig.promptHistory[1].templateSnapshot.template).toContain('<div>ok</div>');
      expect(saved.config.promptHistory).toHaveLength(2);
      expect(saved.config.promptHistory[0].content).toContain('MAUAMAUAMAUAM');
      expect(saved.config.promptHistory[1].templateSnapshot.template).toContain('<div>ok</div>');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('normalizes and materializes custom work fields', () => {
    const {
      createDefaultWorkField,
      extractWorkFields,
      materializeWorkFields,
      summarizeWorkFields,
    } = loadWorkFieldHelpers();

    const work = {
      type: 'text',
      fields: [
        { type: 'text', name: 'text1', label: 'Text 1', value: 'Primary copy', const: 'Primary copy', uniqueItems: true },
      ],
      text1: 'Primary copy',
    };

    expect(extractWorkFields(work)).toEqual([
      expect.objectContaining({
        type: 'text',
        name: 'text1',
        label: 'Text 1',
        value: 'Primary copy',
        const: 'Primary copy',
        uniqueItems: true,
      }),
    ]);
    expect(summarizeWorkFields(work.fields)).toContain('Text 1: Primary copy');
    expect(createDefaultWorkField([])).toEqual(expect.objectContaining({
      type: 'text',
      name: 'text1',
      label: 'Text 1',
      title: 'Text 1',
      value: '',
      description: '',
      placeholder: '',
      const: '',
      uniqueItems: false,
    }));

    const rematerialized = materializeWorkFields({
      ...work,
      text1: 'Updated copy',
    });
    expect(rematerialized.fields).toEqual([
      expect.objectContaining({
        name: 'text1',
        value: 'Updated copy',
      }),
    ]);
    expect(rematerialized.text1).toBe('Updated copy');
  });

  test('normalizes default layout metadata for files', async () => {
    const output = await runWorktype('definition', 'image');
    const definition = JSON.parse(output);

    expect(definition.categories).toEqual(expect.arrayContaining(['image', 'media']));
    expect(definition.layout).toMatchObject({
      mode: 'poff-layout',
      name: 'poff-layout',
      engine: 'lightncandy',
      section: 'work',
    });
  });

  test('suggests the autoplay video template for quicktime files', async () => {
    const output = await runWorktype('catalog', 'video', {
      mime: 'video/quicktime',
      fileName: 'sample.mov',
      subjectType: 'file',
    });
    const catalog = JSON.parse(output);

    expect(catalog.selected).toBe('video');
    expect(catalog.choices.map((choice) => choice.value)).toContain('video');
    expect(catalog.choices.map((choice) => choice.value)).not.toContain('video-autoplay');
    expect(catalog.choices.map((choice) => choice.kind)).toEqual(expect.arrayContaining(['video']));
    expect(catalog.choices.every((choice) => choice.kind === 'video')).toBe(true);
  });

  test('resolves inherited template maps and keeps autoplay on quicktime files', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-template-map-'));
    try {
      const parentDir = path.join(tempRoot, 'parent');
      fs.mkdirSync(parentDir, { recursive: true });
      fs.writeFileSync(path.join(parentDir, 'poff.config.json'), JSON.stringify({
        folderName: 'parent',
        title: 'Parent',
        slug: 'parent',
        description: '',
        work: {
          type: 'folder',
          templateMap: {
            'video/quicktime': 'image',
          },
        },
        tree: [],
      }, null, 2));
      fs.writeFileSync(path.join(parentDir, 'sample.mov'), 'fake video');

      const resolvedInherited = JSON.parse(await runLayoutFilesystem('resolve-work-template', parentDir, '', {
        kind: 'video',
        mime: 'video/quicktime',
        fileName: 'sample.mov',
        work: {
          type: 'video',
        },
      }));
      expect(resolvedInherited.template).toBe('image');
      expect(resolvedInherited.autoplay).toBe(false);

      const resolvedOverride = JSON.parse(await runLayoutFilesystem('resolve-work-template', parentDir, '', {
        kind: 'video',
        mime: 'video/quicktime',
        fileName: 'sample.mov',
        work: {
          type: 'video',
          template: 'video',
        },
      }));
      expect(resolvedOverride.template).toBe('video');
      expect(resolvedOverride.autoplay).toBe(true);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('accepts wildcard MIME template map keys for video files', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-template-map-wildcard-'));
    try {
      const parentDir = path.join(tempRoot, 'parent');
      fs.mkdirSync(parentDir, { recursive: true });
      fs.writeFileSync(path.join(parentDir, 'poff.config.json'), JSON.stringify({
        folderName: 'parent',
        title: 'Parent',
        slug: 'parent',
        description: '',
        work: {
          type: 'folder',
          templateMap: {
            'video/.*': 'image',
          },
        },
        tree: [],
      }, null, 2));
      fs.writeFileSync(path.join(parentDir, 'sample.mov'), 'fake video');

      const resolvedWildcard = JSON.parse(await runLayoutFilesystem('resolve-work-template', parentDir, '', {
        kind: 'video',
        mime: 'video/quicktime',
        fileName: 'sample.mov',
        work: {
          type: 'video',
        },
      }));
      expect(resolvedWildcard.template).toBe('image');
      expect(resolvedWildcard.autoplay).toBe(false);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('limits folder templates to folder worktypes', async () => {
    const output = await runWorktype('catalog', 'folder', {
      subjectType: 'folder',
    });
    const catalog = JSON.parse(output);

    expect(catalog.selected).toBe('folder');
    expect(catalog.choices).toEqual(expect.arrayContaining([
      expect.objectContaining({ value: 'folder', kind: 'folder' }),
    ]));
    expect(catalog.choices.map((choice) => choice.kind).every((kind) => kind === 'folder')).toBe(true);
  });

  test('exposes the shared work category pool in the catalog', async () => {
    const output = await runWorktype('catalog', 'image', {
      subjectType: 'file',
    });
    const catalog = JSON.parse(output);

    expect(catalog.categories).toEqual(expect.arrayContaining([
      'image',
      'media',
      'visual',
      'video',
      'motion',
      'audio',
      'sound',
      'pdf',
      'document',
      'text',
      'link',
      'reference',
      'folder',
      'collection',
      'other',
    ]));
  });

  test('renders a selected work template variant as the active section partial', async () => {
    const lightnCandyInstalled = await hasLightnCandy();
    const output = await runWorktype('render', 'video', {
      ctx: {
        path: 'movies/sample.mov',
        name: 'sample.mov',
        title: 'Sample Movie',
        description: '',
        descriptionHtml: '',
        linkUrl: '',
        slug: 'sample-movie',
        mimeType: 'video/quicktime',
        work: {
          type: 'video',
          template: 'video',
          autoplay: true,
          muted: true,
          layout: {
            name: 'poff-layout',
            engine: 'lightncandy',
            section: 'work',
          },
        },
      },
    });

    if (!lightnCandyInstalled) {
      expect(output).toBe('<iframe src="movies/sample.mov" title="sample.mov"></iframe>');
      return;
    }

    expect(output).toContain('<video class="mx-auto block max-h-screen max-w-full"');
    expect(output).toContain('autoplay');
    expect(output).toContain('muted');
    expect(output).toContain('src="movies/sample.mov"');
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
    expect(config.work.categories).toEqual(expect.arrayContaining(['folder', 'collection']));
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

  test('.htaccess stays hidden in the default tree but is shown in edit mode', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-visibility-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, '.htaccess'), 'RewriteEngine On');
    fs.writeFileSync(path.join(tempDir, 'visible.txt'), 'visible');

    try {
      const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', tempDir));
      expect(ensured.tree.map((item) => item.name)).not.toContain('.htaccess');
      expect(ensured.tree.map((item) => item.name)).toContain('visible.txt');

      const normalNav = await runNav('', tempDir);
      const editNav = await runNav('', tempDir, true);

      expect(normalNav).not.toContain('data-file=".htaccess"');
      expect(normalNav).not.toContain('.htaccess</a>');
      expect(editNav).toContain('data-file=".htaccess"');
      expect(editNav).toContain('.htaccess</a>');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('.poff-auth.php stays hidden in navigation and direct viewer access', async () => {
    const normalNav = await runNav('', POFF_DIR);
    const editNav = await runNav('', POFF_DIR, true);
    const viewerOutput = await runViewer('.poff-auth.php', POFF_DIR);

    expect(normalNav).not.toContain('.poff-auth.php');
    expect(editNav).not.toContain('.poff-auth.php');
    expect(viewerOutput).toContain('Path not found.');
  });

  test('sanitizes persisted raw chat JSON in section templates on read', async () => {
    const output = await runLayoutFilesystem('ensure-folder', INVALID_TEMPLATE_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout.sectionTemplate).toBe('');
    expect(config.work.layout.defaultSectionTemplate).toContain('folder-view');
    expect(config.work.layout.template).toContain('invalid-json-layout');
  });

  test('sanitizes persisted malformed fenced JSON-ish templates on read', async () => {
    fs.writeFileSync(path.join(INVALID_TEMPLATE_DIR, '.layout', 'works.hbs'), `\`\`\`json
{
  "template": "<article class="artifact-card">
    <h2>{{title}}</h2>
    <p>{{description}}</p>
  </article>"
}
\`\`\``);

    const output = await runLayoutFilesystem('ensure-folder', INVALID_TEMPLATE_DIR);
    const config = JSON.parse(output);

    expect(config.work.layout.sectionTemplate).toContain('<article class="artifact-card">');
    expect(config.work.layout.sectionTemplate).toContain('{{description}}');
    expect(config.work.layout.sectionTemplate).not.toContain('```json');
    expect(config.work.layout.sectionTemplate).not.toContain('"template"');
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
    expect(output).toContain('src="assets/photo.png" alt="Project Photo"');
    expect(output).toContain('Inline description');
    expect(output).toContain('class="poff-default-layout__download"');
    expect(output).toContain('href="assets/photo.png"');
    expect(output).toContain('download="photo.png"');
    expect(fs.readFileSync(path.join(ROOT, 'src/includes/worktypes/templates/layout/default/script.js'), 'utf8'))
      .toContain('DOMContentLoaded');
  });

  test('falls back to the default layout script when a filesystem layout has no script.js', async () => {
    const tempDir = path.join(POFF_DIR, 'missing-script-layout');
    const fileName = 'poster.png';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.works', `${fileName}.layout`), { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'fake image');
    fs.writeFileSync(
      path.join(tempDir, '.works', `${fileName}.layout`, 'template.hbs'),
      '<section class="custom-shell">{{> poff-layout}}</section>',
    );
    fs.writeFileSync(
      path.join(tempDir, '.works', `${fileName}.layout`, 'work.hbs'),
      '<article class="custom-work">{{title}}</article>',
    );
    fs.writeFileSync(
      path.join(tempDir, '.works', `${fileName}.config.json`),
      JSON.stringify({
        title: 'Poster',
        description: '',
        work: {
          type: 'image',
          layout: {
            mode: 'filesystem-file-layout',
            name: 'filesystem-file-layout',
            engine: 'lightncandy',
            section: 'work',
          },
        },
      }, null, 2),
    );

    try {
      const output = await runViewer(fileName, tempDir);

      expect(output).toContain('const initDefaultLayout = () => {');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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
    expect(output).not.toContain('poff-default-layout__download');
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

  test('falls back cleanly when the active section partial is invalid', async () => {
    const lightnCandyInstalled = await hasLightnCandy();
    const result = await runWorktypeDetailed('render', 'folder', {
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
            name: 'poff-layout',
            engine: 'lightncandy',
            section: 'works',
            sectionTemplate: '{{#if hasItems}}{{/unless}}',
          },
        },
      },
    });

    expect(result.stderr).toBe('');
    expect(result.stdout).not.toContain('<iframe ');
    if (lightnCandyInstalled) {
      expect(result.stdout).toContain('<div class="poff-default-layout poff-default-layout--folder">');
    } else {
      expect(result.stdout).toContain('<div class="poff-folder-fallback">');
    }
    expect(result.stdout).toContain('projects');
    expect(result.stdout).toContain('alpha');
    expect(result.stdout).toContain('notes.txt');
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
        expect.objectContaining({ path: 'poff.profile.jpg' }),
      ]),
    );

    const rendered = await runViewer('inherits-default');
    expect(rendered).toContain('<div class="default-fs-layout">');
    expect(rendered).toContain('tests/poff-tests/.layout/style.css');
    expect(rendered).toContain('tests/poff-tests/.layout/script.js');
    expect(rendered).toContain('const initDefaultLayout = () => {');
    expect(rendered).toContain('tests/poff-tests/.layout/poff.profile.jpg');
    expect(rendered).toContain('<span class="item">child.txt</span>');
  });

  test('actual layout preset ignores stale shared source and inherits parent .layout', async () => {
    const tempRoot = path.join(POFF_DIR, 'stale-shared-inherit');
    const childDir = path.join(tempRoot, 'child');
    fs.rmSync(tempRoot, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempRoot, '.layout'), { recursive: true });
    fs.mkdirSync(childDir, { recursive: true });
    fs.writeFileSync(path.join(childDir, 'note.txt'), 'note');
    fs.writeFileSync(
      path.join(tempRoot, '.layout', 'template.hbs'),
      '<div class="parent-layout">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</div>',
    );
    fs.writeFileSync(path.join(tempRoot, '.layout', 'works.hbs'), '<section class="parent-works">{{title}}</section>');
    fs.writeFileSync(path.join(childDir, 'poff.config.json'), JSON.stringify({
      folderName: 'child',
      slug: 'child',
      title: 'child',
      description: '',
      type: 'folder',
      id: 'poff_stale_shared',
      tree: [],
      treeHash: 'stale',
      updatedAt: new Date().toISOString(),
      work: {
        type: 'folder',
        layout: {
          name: 'filesystem-layout',
          engine: 'lightncandy',
          section: 'works',
          preset: 'actual',
          source: 'shared',
          sharedName: '1er/.layout',
          storage: 'shared',
          directory: '1er/.layout',
          sectionDirectory: '1er/.layout',
        },
      },
    }, null, 2));

    try {
      const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', childDir));
      expect(ensured.work.layout.storage).toBe('filesystem');
      expect(ensured.work.layout.directory).toBe('tests/poff-tests/stale-shared-inherit/.layout');
      expect(ensured.work.layout.template).toContain('parent-layout');
      expect(ensured.work.layout.sectionTemplate).toContain('parent-works');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('can persist edits back into the inherited original filesystem layout source', async () => {
    const originalTarget = path.relative(ROOT, path.join(POFF_DIR, '.layout'));
    await runLayoutFilesystem('persist-original', originalTarget, '', {
      template: '<div class="default-fs-layout default-fs-layout--edited"><header>{{title}}</header><main>{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main><footer><img src="{{layout.baseHref}}/poff.profile.jpg" alt="profile"></footer></div>',
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

  test('ignores inherited .layout files when layout mode is none', async () => {
    await runViewerSave(POFF_DIR, 'inherits-default/.layout', {
      layout: {
        name: 'none',
        preset: 'none',
      },
    });

    const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR));
    expect(ensured.work.layout).toMatchObject({
      name: 'none',
      mode: 'none',
      storage: 'none',
    });
    expect(ensured.work.layout.sectionTemplate || '').toBe('');

    const output = await runViewer('inherits-default');
    expect(output).not.toContain('<div class="default-fs-layout">');
    expect(output).not.toContain('tests/poff-tests/.layout/style.css');
    expect(output).toContain('<div class="folder-view ');
    expect(output).toContain('child.txt');

    await runViewerSave(POFF_DIR, 'inherits-default/.layout', {
      layout: {
        name: 'filesystem-layout',
        preset: 'inherit',
      },
    });

    const inherited = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR));
    expect(inherited.work.layout).toMatchObject({
      name: 'filesystem-layout',
      storage: 'filesystem',
      directory: 'tests/poff-tests/.layout',
      preset: 'actual',
    });
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
    expect(rendered).toContain('tests/poff-tests/.layout/poff.profile.jpg');
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

  test('renders configured virtual links without nesting viewer urls', async () => {
    const output = await runViewer('virtual-links');

    expect(output).toMatch(/href="\?view(?:=|&#x3D;)1&amp;path(?:=|&#x3D;)linkone"/);
    expect(output).toContain('href="https://example.com/contact"');
    expect(output).not.toContain('%3Fview%3D1%26path%3Dlinkone');
  });

  test('renders link files with an embedded preview for same-origin targets', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-link-preview-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'sample.url'), '[InternetShortcut]\nURL=http://localhost:8888/dominikeggermann.com/#/1749825166559-flux-jpeg\n');

      const renderedHtml = await runResolveRemoteRenderedHtml({
        linkUrl: 'http://localhost:8888/dominikeggermann.com/#/1749825166559-flux-jpeg',
        pageLink: 'http://localhost:8888/dominikeggermann.com/#/1749825166559-flux-jpeg',
        baseUrl: 'http://localhost:8888/dominikeggermann.com/#/1749825166559-flux-jpeg',
      }, {
        status: 200,
        body: '<html><body><article class="remote-snapshot">Remote Snapshot</article></body></html>',
      });

      expect(renderedHtml).toContain('remote-snapshot');
      expect(renderedHtml).toContain('Remote Snapshot');
      expect(renderedHtml).not.toContain('appShell');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('routes add-work poff links through the upload action again', async () => {
    const result = await runPhpJson('php_upload_link_action.php');

    expect(result.allowed).toBe(true);
    expect(result.error).toBeUndefined();
    expect(result.uploaded).toEqual([
      expect.objectContaining({
        name: 'my-link',
        linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
      }),
    ]);
    expect(result.config.tree).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'my-link',
        type: 'link',
        linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
      }),
    ]));
  });

  test('renders remote links through external.hbs even when only the fallback target is available', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-link-fallback-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'sample.url'), '[InternetShortcut]\nURL=https://remote.example/portfolio\n');

      const rendered = await runViewerWithMock('sample.url', tempRoot, false, {
        ok: false,
        status: 502,
        statusLine: 'HTTP/1.1 502 Bad Gateway',
        body: '',
      });

      expect(rendered).toContain('poff-default-layout');
      expect(rendered).toContain('<iframe');
      expect(rendered).toContain('src="https://remote.example/portfolio"');
      expect(rendered).not.toContain('<div class="message">');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('keeps local viewer routes for link files inside folder previews', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-link-folder-preview-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'sample.url'), '[InternetShortcut]\nURL=https://remote.example/portfolio\n');

      const rendered = await runViewer('', tempRoot, false);

      expect(rendered).toContain('?view&#x3D;1&amp;file&#x3D;sample.url');
      expect(rendered).not.toContain('href="https://remote.example/portfolio"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('renders virtual remote link items through the local host viewer wrapper', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-virtual-link-viewer-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'virtual-link-viewer',
        slug: 'virtual-link-viewer',
        title: 'Virtual Link Viewer',
        description: '',
        type: 'folder',
        tree: [
          {
            name: 'dominikeggermann.com',
            title: 'dominikeggermann.com',
            type: 'link',
            kind: 'link',
            path: 'dominikeggermann.com',
            linkUrl: 'https://dominikeggermann.com/',
            visible: true,
          },
        ],
      }, null, 2));

      const folderRendered = await runViewer('', tempRoot, false);
      expect(folderRendered).toContain('?view&#x3D;1&amp;file&#x3D;dominikeggermann.com');

      const fileRendered = await runViewerWithMock('dominikeggermann.com', tempRoot, false, {
        ok: false,
        status: 502,
        statusLine: 'HTTP/1.1 502 Bad Gateway',
        body: '',
      });

      expect(fileRendered).toContain('poff-default-layout');
      expect(fileRendered).toContain('<iframe');
      expect(fileRendered).toContain('src="https://dominikeggermann.com/"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('renders virtual configured items with external snapshots on the local host', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-virtual-external-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'virtual-external',
        slug: 'virtual-external',
        title: 'Virtual External',
        description: '',
        type: 'folder',
        tree: [
          {
            name: 'dominikeggermann.com',
            title: 'dominikeggermann.com',
            slug: 'dominikeggermann-com',
            type: 'file',
            path: 'dominikeggermann.com',
            linkUrl: 'https://dominikeggermann.com/',
            visible: true,
          },
        ],
      }, null, 2));

      const renderedHtml = await runResolveRemoteRenderedHtml({
        path: 'dominikeggermann.com',
        title: 'dominikeggermann.com',
        linkUrl: 'https://dominikeggermann.com/',
        pageLink: 'https://dominikeggermann.com/',
      }, {
        status: 200,
        body: '<html><body><article class="remote-snapshot">Remote Snapshot</article></body></html>',
      });

      expect(renderedHtml).toContain('remote-snapshot');
      expect(renderedHtml).toContain('Remote Snapshot');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('exports normalized remote content with absolute viewer and asset links', async () => {
    const result = await runRemoteContent('export', POFF_DIR, 'viewer-folder', {
      baseUrl: 'https://origin.example/index.php',
      sourceId: 'origin-example',
    });

    expect(result.route).toBe('export-content');
    expect(result.source).toEqual(expect.objectContaining({
      id: 'origin-example',
      baseUrl: 'https://origin.example/index.php',
      path: 'viewer-folder',
    }));
    expect(result.root.pageLink).toBe('https://origin.example/index.php?view=1&path=viewer-folder');
    expect(result.items).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'child.txt',
        pageLink: 'https://origin.example/index.php?view=1&file=viewer-folder%2Fchild.txt',
        srcUrl: 'https://origin.example/viewer-folder/child.txt',
        routeSlug: 'child-txt',
        routePath: 'viewer-folder/child.txt',
      }),
      expect.objectContaining({
        name: 'nested-child',
        isFolder: true,
        pageLink: 'https://origin.example/index.php?view=1&path=viewer-folder%2Fnested-child',
        routeSlug: 'nested-child',
        routePath: 'viewer-folder/nested-child',
      }),
    ]));
  });

  test('imports remote exports into config tree as virtual entries and renders their links', async () => {
    const remoteFeed = {
      route: 'export-content',
      source: {
        id: 'origin-example',
        path: 'viewer-folder',
      },
      items: [
      {
        name: 'Portfolio',
        title: 'Portfolio',
        type: 'folder',
        kind: 'folder',
        path: 'viewer-folder/nested-child',
        relativePath: 'viewer-folder/nested-child',
        pageLink: 'https://origin.example/index.php?view=1&path=viewer-folder%2Fnested-child',
        srcUrl: 'https://origin.example/index.php?path=viewer-folder%2Fnested-child',
        routeSlug: 'nested-child',
        routePath: 'viewer-folder/nested-child',
        renderedHtml: '<article class="remote-snapshot remote-snapshot--folder">Portfolio snapshot</article>',
        visible: true,
        isFolder: true,
        isFile: false,
      },
      {
          name: 'Leaf Note',
          title: 'Leaf Note',
          type: 'file',
        kind: 'text',
        path: 'viewer-folder/child.txt',
        relativePath: 'viewer-folder/child.txt',
        pageLink: 'https://origin.example/index.php?view=1&file=viewer-folder%2Fchild.txt',
        srcUrl: 'https://origin.example/viewer-folder/child.txt',
        routeSlug: 'child-txt',
        routePath: 'viewer-folder/child.txt',
        renderedHtml: '<article class="remote-snapshot remote-snapshot--file">Leaf Note snapshot</article>',
        visible: true,
        isFolder: false,
        isFile: true,
      },
    ],
    };

    const result = await runRemoteContent('import', POFF_DIR, 'remote-import-links', {
      url: 'https://origin.example/index.php?mcp=1&route=export-content&path=viewer-folder',
      sourceId: 'origin-example',
      mockResponse: {
        status: 200,
        body: remoteFeed,
      },
    });

    expect(result.route).toBe('import-remote');
    expect(result.saved).toBe(true);
    expect(result.importedCount).toBe(2);

    const storedConfig = JSON.parse(fs.readFileSync(path.join(REMOTE_IMPORT_DIR, 'poff.config.json'), 'utf8'));
    expect(storedConfig.remoteSources).toEqual(expect.arrayContaining([
      expect.objectContaining({
        id: 'origin-example',
        url: 'https://origin.example/index.php?mcp=1&route=export-content&path=viewer-folder',
      }),
    ]));
    expect(storedConfig.tree).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'Portfolio',
        type: 'folder',
        pageLink: 'https://origin.example/index.php?view=1&path=viewer-folder%2Fnested-child',
        remoteSource: 'origin-example',
        routeSlug: 'nested-child',
        routePath: 'viewer-folder/nested-child',
      }),
      expect.objectContaining({
        name: 'Leaf Note',
        type: 'file',
        pageLink: 'https://origin.example/index.php?view=1&file=viewer-folder%2Fchild.txt',
        remoteSource: 'origin-example',
        routeSlug: 'child-txt',
        routePath: 'viewer-folder/child.txt',
      }),
    ]));

    const leafEntry = storedConfig.tree.find((item) => item.name === 'Leaf Note');
    expect(leafEntry).toBeTruthy();
    expect(leafEntry.renderedHtml).toBeUndefined();
    expect(leafEntry.template).toBeUndefined();
  });

  test('keeps the active wrapper and strips only the remote shell chrome', async () => {
    const remoteRenderDir = path.join(POFF_DIR, 'remote-rendered-layout');
    fs.rmSync(remoteRenderDir, { recursive: true, force: true });
    fs.mkdirSync(remoteRenderDir, { recursive: true });
    fs.writeFileSync(path.join(remoteRenderDir, 'flux-preview'), 'remote snapshot stub');
    fs.writeFileSync(path.join(remoteRenderDir, 'poff.config.json'), JSON.stringify({
      folderName: 'remote-rendered-layout',
      slug: 'remote-rendered-layout',
      title: 'Remote Rendered Layout',
      description: 'Folder with a remote rendered layout snapshot',
      tree: [
        {
          name: 'Flux Preview',
          title: 'Flux Preview',
          type: 'file',
          kind: 'link',
          path: 'flux-preview',
          pageLink: 'https://origin.example/index.php?view=1&file=flux-preview',
          renderedHtml: '<div id="appShell" class="container"><button id="sidebarToggle">menu</button><div class="poff-default-layout poff-default-layout--file"><header class="poff-default-layout__header"><h1>Flux Preview</h1></header><main class="poff-default-layout__main"><article class="remote-snapshot remote-snapshot--file">Flux snapshot</article></main><footer class="poff-default-layout__footer">done</footer></div></div>',
          template: 'external',
          visible: true,
        },
      ],
      work: {
        type: 'folder',
        layout: {
          name: 'filesystem-folder-layout',
          engine: 'lightncandy',
          section: 'works',
        },
      },
    }, null, 2));

    try {
      const rendered = await runViewer('flux-preview', remoteRenderDir, true);

      expect(rendered).toContain('poff.profile.jpg');
      expect(rendered).toContain('remote-snapshot--file');
      expect(rendered).toContain('Flux snapshot');
      expect(rendered).not.toContain('id="appShell"');
      expect(rendered).not.toContain('id="sidebarToggle"');
      expect(rendered).not.toContain('<footer class="poff-default-layout__footer">done</footer>');
    } finally {
      fs.rmSync(remoteRenderDir, { recursive: true, force: true });
    }
  });

  test('strips remote app shell wrappers and keeps only the remote viewer body', async () => {
    const remoteRenderDir = path.join(POFF_DIR, 'remote-rendered-viewer');
    fs.rmSync(remoteRenderDir, { recursive: true, force: true });
    fs.mkdirSync(remoteRenderDir, { recursive: true });
    fs.writeFileSync(path.join(remoteRenderDir, 'flux-preview'), 'remote viewer stub');
    fs.writeFileSync(path.join(remoteRenderDir, 'poff.config.json'), JSON.stringify({
      folderName: 'remote-rendered-viewer',
      slug: 'remote-rendered-viewer',
      title: 'Remote Rendered Viewer',
      description: 'Folder with a remote viewer snapshot',
      tree: [
        {
          name: 'Flux Preview',
          title: 'Flux Preview',
          type: 'file',
          kind: 'link',
          path: 'flux-preview',
          pageLink: 'https://origin.example/index.php?view=1&file=flux-preview',
          renderedHtml: '<div id="appShell" class="container"><div id="contentFrame"><div class="viewer"><article class="remote-snapshot remote-snapshot--file">Flux snapshot</article></div></div></div>',
          template: 'external',
          visible: true,
        },
      ],
    }, null, 2));

    try {
      const rendered = await runViewer('flux-preview', remoteRenderDir, true);

      expect(rendered).toContain('remote-snapshot--file');
      expect(rendered).toContain('Flux snapshot');
      expect(rendered).not.toContain('id=\"appShell\"');
      expect(rendered).not.toContain('id=\"contentFrame\"');
      expect(rendered).not.toContain('class=\"viewer\"');
    } finally {
      fs.rmSync(remoteRenderDir, { recursive: true, force: true });
    }
  });

  test('normalizes duplicated viewer urls in custom layout links', async () => {
    const tempDir = path.join(POFF_DIR, 'viewer-link-normalize');
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, 'note.txt'), 'note');

    try {
      await runLayoutFilesystem('persist-folder', tempDir, '', {
        name: 'custom-layout',
        engine: 'lightncandy',
        section: 'works',
        template: '<header class="poff-default-layout__header"><div class="poff-default-layout__content"><a href="{{pageLink}}?view=1&path=linkone">Portfolio</a></div></header><main class="poff-default-layout__main">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main>',
        worksTemplate: '<div>{{#each items}}<a href="{{pageLink}}">{{name}}</a>{{/each}}</div>',
        workTemplate: '<span>{{name}}</span>',
      });

      const template = fs.readFileSync(path.join(tempDir, '.layout', 'template.hbs'), 'utf8');
      const output = await runViewer('', tempDir);

      expect(template).toContain('href="?view=1&path=linkone"');
      expect(template).not.toContain('{{pageLink}}?view=1&path=linkone');
      expect(output).toContain('href="?view=1&path=linkone"');
      expect(output).not.toContain('?view=1&amp;path=viewer-link-normalize?view=1&amp;path=linkone');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('file views inherit the containing folder work partial over stale file-local sections', async () => {
    const tempDir = path.join(POFF_DIR, 'file-layout-inheritance');
    const fileName = 'clip.mp4';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.layout'), { recursive: true });
    fs.mkdirSync(path.join(tempDir, '.works', `${fileName}.layout`), { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'video');
    fs.writeFileSync(
      path.join(tempDir, '.layout', 'template.hbs'),
      '<div class="folder-shell">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</div>',
    );
    fs.writeFileSync(path.join(tempDir, '.layout', 'work.hbs'), '<article class="folder-work">{{name}}</article>');
    fs.writeFileSync(path.join(tempDir, '.works', `${fileName}.layout`, 'work.hbs'), '<article class="stale-file-work">{{name}}</article>');

    try {
      const ensured = JSON.parse(await runLayoutFilesystem('ensure-file', tempDir, fileName));
      expect(ensured.work.layout.directory).toBe('tests/poff-tests/file-layout-inheritance/.layout');
      expect(ensured.work.layout.sectionDirectory).toBe('tests/poff-tests/file-layout-inheritance/.layout');
      expect(ensured.work.layout.sectionTemplate).toContain('folder-work');
      expect(ensured.work.layout.sectionTemplate).not.toContain('stale-file-work');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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

  test('persists a prompt-generated file layout into the file page with template, CSS, and JS', async () => {
    const tempDir = path.join(POFF_DIR, `prompt-file-layout-${Date.now()}`);
    const fileName = 'clip.mp4';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.works', `${fileName}.layout`), { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'video');

    try {
      await runLayoutFilesystem('persist-file', tempDir, fileName, {
        name: 'filesystem-layout',
        engine: 'lightncandy',
        section: 'work',
        template: '<section class="prompt-file-layout"><h2>{{title}}</h2><div class="prompt-file-layout__body">{{> work}}</div></section>',
        css: '.prompt-file-layout{border:1px solid #5f5;padding:1rem;}',
        js: 'console.log(\'ok\');',
        sectionTemplate: '<article class="prompt-file-work">{{name}}</article>',
      });

      const ensured = JSON.parse(await runLayoutFilesystem('ensure-file', tempDir, fileName));
      expect(ensured.work.layout.directory).toBe(`.works/${fileName}.layout`);
      expect(ensured.work.layout.template).toContain('prompt-file-layout');
      expect(ensured.work.layout.css).toContain('.prompt-file-layout');
      expect(ensured.work.layout.js).toContain("console.log('ok')");
      expect(ensured.work.layout.sectionTemplate).toContain('prompt-file-work');

      const output = await runViewer(fileName, tempDir);
      expect(output).toContain('<section class="prompt-file-layout">');
      expect(output).toContain('.works/clip.mp4.layout/style.css');
      expect(output).toContain('.works/clip.mp4.layout/script.js');
      expect(output).toContain('<article class="prompt-file-work">');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('renders built-in file layout assets from the containing folder .layout', async () => {
    const tempDir = path.join(POFF_DIR, 'default-file-layout-assets');
    const fileName = 'poster.png';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.layout'), { recursive: true });
    fs.mkdirSync(path.join(tempDir, '.works'), { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'fake image');
    fs.writeFileSync(path.join(tempDir, '.layout', 'poff.profile.jpg'), 'fake profile image');
    fs.writeFileSync(path.join(tempDir, '.works', `${fileName}.config.json`), JSON.stringify({
      title: 'Poster',
      description: '',
      work: {
        type: 'image',
        layout: {
          mode: 'poff-layout',
          name: 'poff-layout',
          engine: 'lightncandy',
          section: 'work',
        },
      },
    }, null, 2));

    try {
      const output = await runViewer(`default-file-layout-assets/${fileName}`);

      expect(output).toContain('default-file-layout-assets/.layout/poff.profile.jpg');
      expect(output).not.toContain(`default-file-layout-assets/.works/${fileName}.layout/poff.profile.jpg`);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('renders built-in file layout assets from inherited parent .layout', async () => {
    const tempDir = path.join(POFF_DIR, 'default-file-layout-inherited-assets');
    const childDir = path.join(tempDir, 'child');
    const fileName = 'poster.png';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.layout'), { recursive: true });
    fs.mkdirSync(path.join(childDir, '.works'), { recursive: true });
    fs.writeFileSync(path.join(childDir, fileName), 'fake image');
    fs.writeFileSync(path.join(tempDir, '.layout', 'poff.profile.jpg'), 'fake profile image');
    fs.writeFileSync(path.join(childDir, '.works', `${fileName}.config.json`), JSON.stringify({
      title: 'Poster',
      description: '',
      work: {
        type: 'image',
        layout: {
          mode: 'poff-layout',
          name: 'poff-layout',
          engine: 'lightncandy',
          section: 'work',
        },
      },
    }, null, 2));

    try {
      const output = await runViewer(`default-file-layout-inherited-assets/child/${fileName}`);

      expect(output).toContain('default-file-layout-inherited-assets/.layout/poff.profile.jpg');
      expect(output).not.toContain(`default-file-layout-inherited-assets/child/.layout/poff.profile.jpg`);
      expect(output).not.toContain(`default-file-layout-inherited-assets/child/.works/${fileName}.layout/poff.profile.jpg`);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('inherits explicit none layout from a parent folder into child files and nested folders', async () => {
    const tempDir = path.join(POFF_DIR, `inherits-none-layout-${Date.now()}`);
    const nestedDir = path.join(tempDir, 'nested');
    const fileName = 'poster.png';
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(tempDir, '.layout'), { recursive: true });
    fs.mkdirSync(nestedDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'fake image');
    fs.writeFileSync(path.join(nestedDir, 'child.txt'), 'child');
    fs.writeFileSync(path.join(tempDir, '.layout', 'works.hbs'), '<div class="local-works">{{title}}</div>');
    fs.writeFileSync(path.join(tempDir, 'poff.config.json'), JSON.stringify({
      title: 'Parent none layout',
      work: {
        type: 'folder',
        layout: {
          mode: 'none',
          name: 'none',
          engine: 'lightncandy',
          section: 'works',
          preset: 'none',
        },
      },
    }, null, 2));

    try {
      const ensuredFile = JSON.parse(await runLayoutFilesystem('ensure-file', tempDir, fileName));
      expect(ensuredFile.work.layout).toMatchObject({
        name: 'none',
        mode: 'none',
        storage: 'none',
      });

      const ensuredFolder = JSON.parse(await runLayoutFilesystem('ensure-folder', nestedDir));
      expect(ensuredFolder.work.layout).toMatchObject({
        name: 'none',
        mode: 'none',
        storage: 'none',
      });

      const relativeFilePath = path.relative(POFF_DIR, path.join(tempDir, fileName)).replace(/\\/g, '/');
      const rendered = await runViewer(relativeFilePath);
      expect(rendered).not.toContain('poff.profile.jpg');
      expect(rendered).toContain(`src="${relativeFilePath}"`);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('uses a child folder .layout over an inherited parent .layout and persists the full layout set', async () => {
    const tempDir = path.join(POFF_DIR, `nested-folder-layout-${Date.now()}`);
    const parentDir = path.join(tempDir, 'parent');
    const childDir = path.join(parentDir, 'child');
    const childRelativePath = path.relative(POFF_DIR, childDir).replace(/\\/g, '/');
    const parentLayoutRelativePath = path.relative(ROOT, path.join(parentDir, '.layout')).replace(/\\/g, '/');
    fs.rmSync(tempDir, { recursive: true, force: true });
    fs.mkdirSync(path.join(parentDir, '.layout'), { recursive: true });
    fs.mkdirSync(childDir, { recursive: true });
    fs.writeFileSync(path.join(parentDir, 'parent.txt'), 'parent');
    fs.writeFileSync(path.join(childDir, 'child.txt'), 'child');
    fs.writeFileSync(
      path.join(parentDir, '.layout', 'template.hbs'),
      '<div class="parent-layout">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</div>',
    );
    fs.writeFileSync(path.join(parentDir, '.layout', 'style.css'), '.parent-layout{color:#f55;}');
    fs.writeFileSync(path.join(parentDir, '.layout', 'script.js'), 'window.__parentLayout = true;');
    fs.writeFileSync(
      path.join(parentDir, '.layout', 'works.hbs'),
      '<section class="parent-works">{{#each items}}<span class="parent-item">{{name}}</span>{{/each}}</section>',
    );

    try {
      const payload = {
        name: 'child-layout',
        engine: 'lightncandy',
        section: 'works',
        preset: 'actual',
        template: '<div class="child-layout">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</div>',
        css: '.child-layout{color:#5f5;}',
        js: 'window.__childLayout = true;',
        worksTemplate: '<section class="child-works">{{#each items}}<span class="child-item">{{name}}</span>{{/each}}</section>',
        workTemplate: '<article class="child-work">{{name}}</article>',
      };

      const output = await runLayoutFilesystem('persist-folder', childDir, '', payload);
      const persisted = JSON.parse(output);
      expect(persisted).toMatchObject({
        name: 'child-layout',
        section: 'works',
        engine: 'lightncandy',
        preset: 'actual',
      });

      expect(fs.readFileSync(path.join(childDir, '.layout', 'template.hbs'), 'utf8')).toContain('child-layout');
      expect(fs.readFileSync(path.join(childDir, '.layout', 'style.css'), 'utf8')).toContain('.child-layout');
      expect(fs.readFileSync(path.join(childDir, '.layout', 'script.js'), 'utf8')).toContain('__childLayout');
      expect(fs.readFileSync(path.join(childDir, '.layout', 'works.hbs'), 'utf8')).toContain('child-works');
      expect(fs.readFileSync(path.join(childDir, '.layout', 'work.hbs'), 'utf8')).toContain('child-work');
      expect(fs.readFileSync(path.join(parentDir, '.layout', 'template.hbs'), 'utf8')).toContain('parent-layout');

      const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', childDir));
      expect(ensured.work.layout.directory).toBe('.layout');
      expect(ensured.work.layout.inheritedDirectory).toBe(parentLayoutRelativePath);
      expect(ensured.work.layout.template).toContain('child-layout');
      expect(ensured.work.layout.css).toContain('.child-layout');
      expect(ensured.work.layout.js).toContain('__childLayout');
      expect(ensured.work.layout.sectionTemplate).toContain('child-works');

      const rendered = await runViewer(childRelativePath);
      expect(rendered).toContain('<div class="child-layout">');
      expect(rendered).toContain('<section class="child-works">');
      expect(rendered).toContain('<span class="child-item">child.txt</span>');
      expect(rendered).not.toContain('parent-layout');
      expect(rendered).not.toContain('parent-works');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('renders the viewer shell stylesheet inline in the generated page', async () => {
    const output = await runViewer(VIEWER_FILE_NAME);

    expect(output).toContain('<style data-app-style>');
    expect(output).not.toContain('href="/build/assets/app.css"');
  });

  test('sanitizes persisted file work partials that accidentally include outer wrapper shell blocks', async () => {
    await runLayoutFilesystem('persist-file', POFF_DIR, VIEWER_FILE_NAME, {
      name: 'filesystem-layout',
      engine: 'lightncandy',
      section: 'work',
      template: '<div class="file-custom">{{title}}</div>',
      sectionTemplate: '<header class="poff-default-layout__header"><div class="poff-default-layout__header-copy"><h2 class="poff-default-layout__video-title">{{title}}</h2></div></header><div class="poff-default-layout__video"><video class="poff-default-layout__video-player" src="{{srcUrl}}" autoplay playsinline controls></video></div>',
    });

    const ensured = JSON.parse(await runLayoutFilesystem('ensure-file', POFF_DIR, VIEWER_FILE_NAME));
    expect(ensured.work.layout.sectionTemplate).toContain('poff-default-layout__video');
    expect(ensured.work.layout.sectionTemplate).toContain('autoplay');
    expect(ensured.work.layout.sectionTemplate).not.toContain('poff-default-layout__header');
    expect(ensured.work.layout.sectionTemplate).not.toContain('poff-default-layout__header-copy');

    const output = await runViewer(VIEWER_FILE_NAME);
    expect(output).toContain('poff-default-layout__video-player');
    expect(output).toContain('autoplay');
    expect(output).not.toContain('poff-default-layout__header-copy');
  });

  test('saves section-only file edits into the runtime pages tree without rewriting the wrapper template', async () => {
    const sourceTemplatePath = path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`, 'template.hbs');
    const sourceTemplateBefore = fs.readFileSync(sourceTemplatePath, 'utf8');
    const runtimeLayoutDir = path.join(POFF_DIR, '.works', `${VIEWER_FILE_NAME}.layout`);
    const runtimeWorkPath = path.join(runtimeLayoutDir, 'work.hbs');
    const runtimeWorkBefore = fs.readFileSync(runtimeWorkPath, 'utf8');
    if (fs.existsSync(runtimeLayoutDir)) {
      const leakedTemplatePath = path.join(runtimeLayoutDir, 'template.hbs');
      if (fs.existsSync(leakedTemplatePath)) {
        fs.writeFileSync(leakedTemplatePath, sourceTemplateBefore);
      }
    }

    const result = await runViewerSave(POFF_DIR, VIEWER_FILE_NAME, {
      layout: {
        sectionTemplate: '<article class="source-only-update">{{title}}</article>',
      },
    });

    expect(result.saved).toBe(true);
    expect(fs.readFileSync(sourceTemplatePath, 'utf8')).toBe(sourceTemplateBefore);
    expect(runtimeWorkBefore).not.toContain('source-only-update');
    expect(fs.existsSync(path.join(runtimeLayoutDir, 'template.hbs'))).toBe(true);
    expect(fs.readFileSync(runtimeWorkPath, 'utf8')).toContain('source-only-update');
  });

  test('inherits edit mode from an ancestor allow marker', async () => {
    const runtimeRoot = RUNTIME_ROOT;
    const nestedRuntimeDir = path.join(runtimeRoot, 'tests', 'poff-tests', 'viewer-folder');
    fs.mkdirSync(nestedRuntimeDir, { recursive: true });
    fs.writeFileSync(path.join(runtimeRoot, '.edit.allow'), 'allow');
    const denyPath = path.join(nestedRuntimeDir, 'edit.not-allow');
    if (fs.existsSync(denyPath)) {
      fs.unlinkSync(denyPath);
    }

    const result = await runViewerSave(runtimeRoot, 'tests/poff-tests/viewer-folder', {
      description: 'Inherited edit mode works',
    });

    expect(result.allowed).toBe(true);
    expect(result.saved).toBe(true);
    expect(result.config.description).toBe('Inherited edit mode works');
  });

  test('stops inherited edit mode when edit.not-allow exists locally', async () => {
    const runtimeRoot = RUNTIME_ROOT;
    const nestedRuntimeDir = path.join(runtimeRoot, 'tests', 'poff-tests', 'viewer-folder');
    fs.mkdirSync(nestedRuntimeDir, { recursive: true });
    fs.writeFileSync(path.join(runtimeRoot, '.edit.allow'), 'allow');
    fs.writeFileSync(path.join(nestedRuntimeDir, 'edit.not-allow'), 'deny');

    const result = await runViewerSave(runtimeRoot, 'tests/poff-tests/viewer-folder', {
      description: 'This should be blocked',
    });

    expect(result.allowed).toBe(false);
    expect(result.error).toBe('Edit mode not enabled.');

    fs.unlinkSync(path.join(nestedRuntimeDir, 'edit.not-allow'));
  });

  test('exposes worktype-specific config fields in the default work definitions', async () => {
    const videoDef = JSON.parse(await runWorktype('definition', 'video'));
    const imageDef = JSON.parse(await runWorktype('definition', 'image'));

    expect(videoDef).toMatchObject({
      type: 'video',
      autoplay: false,
      loop: false,
      muted: false,
      poster: null,
    });
    expect(imageDef).toMatchObject({
      type: 'image',
      fit: 'contain',
      background: '#000',
      caption: '',
    });
  });

  test('persists worktype-specific config fields from edit save', async () => {
    const tempDir = path.join(POFF_DIR, `work-config-save-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, 'clip.mp4'), 'video');

    try {
      const result = await runViewerSave(tempDir, 'clip.mp4', {
        work: {
          type: 'video',
          autoplay: true,
          loop: true,
          muted: false,
          poster: 'poster.png',
        },
      });

      expect(result.saved).toBe(true);
      expect(result.config.work.type).toBe('video');
      expect(result.config.work.autoplay).toBe(true);
      expect(result.config.work.loop).toBe(true);
      expect(result.config.work.muted).toBe(false);
      expect(result.config.work.poster).toBe('poster.png');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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
      js: 'console.log(\'ok\');',
      worksTemplate: '<div class="persisted-folder-inner">{{#each items}}<span>{{name}}</span>{{/each}}</div>',
      workTemplate: '<div class="persisted-file-inner">{{name}}</div>',
    };

    fs.writeFileSync(path.join(PERSIST_LAYOUT_DIR, 'shared-file.txt'), 'shared');
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
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain("console.log('ok')");
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('persisted-folder-inner');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'work.hbs'), 'utf8')).toContain('persisted-file-inner');
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
    expect(ensuredConfig.work.layout.js).toContain("console.log('ok')");

    const ensuredFileOutput = await runLayoutFilesystem('ensure-file', PERSIST_LAYOUT_DIR, 'shared-file.txt');
    const ensuredFileConfig = JSON.parse(ensuredFileOutput);
    expect(ensuredFileConfig.work.layout.directory).toBe('tests/poff-tests/persist-layout/.layout');
    expect(ensuredFileConfig.work.layout.sectionTemplate).toContain('persisted-file-inner');
    expect(await runViewer('persist-layout')).toContain('.layout/script.js');
  });

  test('keeps custom layout files when switching presets away from custom', async () => {
    const payload = {
      name: 'custom-layout',
      engine: 'lightncandy',
      section: 'works',
      preset: 'custom',
      template: '<div class="saved-custom-layout">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</div>',
      sectionTemplate: '<section class="saved-custom-works">{{title}}</section>',
      css: '.saved-custom-layout{color:#123;}',
      js: 'window.__savedCustomLayout = true;',
    };

    await runLayoutFilesystem('persist-folder', PERSIST_LAYOUT_DIR, '', payload);

    await runViewerSave(POFF_DIR, 'persist-layout/.layout', {
      layout: {
        name: 'none',
        preset: 'none',
        template: '',
        sectionTemplate: '',
        css: '',
        js: '',
      },
    });

    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('saved-custom-works');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'style.css'), 'utf8')).toContain('.saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain('__savedCustomLayout');

    await runViewerSave(POFF_DIR, 'persist-layout/.layout', {
      layout: {
        name: 'custom-layout',
        preset: 'custom',
        template: '',
        sectionTemplate: '',
        css: '',
        js: '',
      },
    });

    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('saved-custom-works');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'style.css'), 'utf8')).toContain('.saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain('__savedCustomLayout');

    await runViewerSave(POFF_DIR, 'persist-layout/.layout', {
      layout: {
        name: 'filesystem-layout',
        preset: 'inherit',
        template: '',
        sectionTemplate: '',
        css: '',
        js: '',
      },
    });

    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'template.hbs'), 'utf8')).toContain('saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'works.hbs'), 'utf8')).toContain('saved-custom-works');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'style.css'), 'utf8')).toContain('.saved-custom-layout');
    expect(fs.readFileSync(path.join(PERSIST_LAYOUT_DIR, '.layout', 'script.js'), 'utf8')).toContain('__savedCustomLayout');
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

  test('creates a folder in a folder target', async () => {
    const output = await runCreateFolder(UPLOAD_TARGET_DIR, 'assets');
    const result = JSON.parse(output);

    expect(result.errors).toEqual([]);
    expect(result.stored).toEqual([
      expect.objectContaining({ name: 'assets', path: 'assets' }),
    ]);
    expect(fs.existsSync(path.join(UPLOAD_TARGET_DIR, 'assets'))).toBe(true);
    expect(fs.lstatSync(path.join(UPLOAD_TARGET_DIR, 'assets')).isDirectory()).toBe(true);
    expect(result.config.tree).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ name: 'assets', type: 'folder' }),
      ]),
    );
  });

  test('deletes a file target and its metadata', async () => {
    const output = await runDeleteTarget(DELETE_FILE_DIR, 'remove-me.txt');
    const result = JSON.parse(output);

    expect(result.errors).toEqual([]);
    expect(result.deleted).toEqual([
      expect.objectContaining({ name: 'remove-me.txt', path: 'remove-me.txt', type: 'file' }),
    ]);
    expect(fs.existsSync(path.join(DELETE_FILE_DIR, 'remove-me.txt'))).toBe(false);
    expect(fs.existsSync(path.join(DELETE_FILE_DIR, '.works', 'remove-me.txt.config.json'))).toBe(false);
    expect(fs.existsSync(path.join(DELETE_FILE_DIR, '.works', 'remove-me.txt.layout'))).toBe(false);
    expect(result.config.tree).not.toEqual(
      expect.arrayContaining([
        expect.objectContaining({ name: 'remove-me.txt' }),
      ]),
    );
  });

  test('deletes a folder target recursively', async () => {
    const output = await runDeleteTarget(DELETE_FOLDER_DIR, 'nested');
    const result = JSON.parse(output);

    expect(result.errors).toEqual([]);
    expect(result.deleted).toEqual([
      expect.objectContaining({ name: 'nested', path: 'nested', type: 'folder' }),
    ]);
    expect(fs.existsSync(path.join(DELETE_FOLDER_DIR, 'nested'))).toBe(false);
    expect(result.config.tree).not.toEqual(
      expect.arrayContaining([
        expect.objectContaining({ name: 'nested' }),
      ]),
    );
  });

  test('resets a folder work override back to the inherited default layout', async () => {
    const tempDir = path.join(POFF_DIR, `reset-layout-${Date.now()}`);
    copyDirSync(PERSIST_LAYOUT_DIR, tempDir);

    try {
      expect(fs.existsSync(path.join(tempDir, '.layout'))).toBe(true);

      const output = await runResetFolder(tempDir, '');
      const result = JSON.parse(output);

      expect(result.errors).toEqual([]);
      expect(result.reset).toEqual([
        expect.objectContaining({ name: '.layout', path: '.layout', type: 'layout' }),
      ]);
      expect(fs.existsSync(path.join(tempDir, '.layout'))).toBe(false);
      expect(result.config.work.layout).toEqual(
        expect.objectContaining({
          name: 'filesystem-layout',
          mode: 'filesystem-layout',
          section: 'works',
        }),
      );
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('hydrates a shared marketplace layout without creating local wrapper files', async () => {
    const tempDir = path.join(os.tmpdir(), `shared-layout-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, 'story.txt'), 'shared');

    try {
      const output = await runLayoutFilesystem('persist-folder', tempDir, '', {
        name: 'filesystem-layout',
        engine: 'lightncandy',
        section: 'works',
        preset: 'shared',
        source: 'shared',
        sharedName: 'filesystem-layout',
      });
      const serializedLayout = JSON.parse(output);

      expect(serializedLayout).toMatchObject({
        name: 'filesystem-layout',
        preset: 'shared',
        source: 'shared',
        sharedName: 'filesystem-layout',
      });
      expect(fs.existsSync(path.join(tempDir, '.layout'))).toBe(false);

      const configPath = path.join(tempDir, 'poff.config.json');
      fs.writeFileSync(configPath, JSON.stringify({
        folderName: 'shared-layout',
        slug: 'shared-layout',
        title: 'shared-layout',
        description: '',
        type: 'folder',
        id: 'poff_shared_layout',
        tree: [
          {
            name: 'story.txt',
            slug: 'story-txt',
            type: 'file',
            path: 'story.txt',
            modifiedAt: new Date().toISOString(),
            visible: true,
          },
        ],
        treeHash: 'shared',
        updatedAt: new Date().toISOString(),
        work: {
          type: 'folder',
          layout: serializedLayout,
        },
      }, null, 2));

      const ensuredOutput = await runLayoutFilesystem('ensure-folder', tempDir);
      const ensuredConfig = JSON.parse(ensuredOutput);
      expect(ensuredConfig.work.layout).toEqual(
        expect.objectContaining({
          preset: 'shared',
          source: 'shared',
          sharedName: 'filesystem-layout',
        }),
      );
      expect(Array.isArray(ensuredConfig.work.layout.sharedLayouts)).toBe(true);
      expect(ensuredConfig.work.layout.sharedLayouts.length).toBeGreaterThan(0);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('hydrates a recursive folder collection layout by relative layout path', async () => {
    const tempDir = path.join(POFF_DIR, `recursive-layout-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, 'story.txt'), 'recursive shared');

    try {
      const output = await runLayoutFilesystem('persist-folder', tempDir, '', {
        name: 'viewer-folder/.layout',
        engine: 'lightncandy',
        section: 'works',
        preset: 'shared',
        source: 'shared',
        sharedName: 'viewer-folder/.layout',
      });
      const serializedLayout = JSON.parse(output);

      const configPath = path.join(tempDir, 'poff.config.json');
      fs.writeFileSync(configPath, JSON.stringify({
        folderName: 'recursive-layout',
        slug: 'recursive-layout',
        title: 'recursive-layout',
        description: '',
        type: 'folder',
        id: 'poff_recursive_layout',
        tree: [
          {
            name: 'story.txt',
            slug: 'story-txt',
            type: 'file',
            path: 'story.txt',
            modifiedAt: new Date().toISOString(),
            visible: true,
          },
        ],
        treeHash: 'recursive',
        updatedAt: new Date().toISOString(),
        work: {
          type: 'folder',
          layout: serializedLayout,
        },
      }, null, 2));

      const ensuredOutput = await runLayoutFilesystem('ensure-folder', tempDir);
      const ensuredConfig = JSON.parse(ensuredOutput);
      expect(ensuredConfig.work.layout).toEqual(
        expect.objectContaining({
          preset: 'shared',
          source: 'shared',
          sharedName: 'viewer-folder/.layout',
          storage: 'shared',
          directory: 'viewer-folder/.layout',
        }),
      );
      expect(ensuredConfig.work.layout.template).toContain('folder-custom');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });
});
