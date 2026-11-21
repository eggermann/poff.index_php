const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');
const os = require('os');
const path = require('path');
const { pathToFileURL } = require('url');
const vm = require('vm');

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
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) {
        try {
          resolve(JSON.parse(stdout.trim()));
        } catch (error) {
          reject(new Error(`create helper returned invalid JSON: ${error.message}`));
        }
        return;
      }
      reject(new Error(`create helper failed: ${code} ${stderr}`));
    });
  });
}

function runFileCopierCleanup(rootDir) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_file_copier_cleanup.php'), rootDir], {
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
      reject(new Error(`file copier cleanup failed: ${code} ${stderr}`));
    });
  });
}

function runIsolatedPhpBuild(outputDir) {
  return new Promise((resolve, reject) => {
    const relativeOutputDir = path.relative(ROOT, outputDir);
    const proc = spawn('php', [path.join(ROOT, 'build/build.php')], {
      cwd: ROOT,
      env: withTestEnv({
        POFF_BUILD_MODE: 'development',
        POFF_OUTPUT_DIR: relativeOutputDir,
        POFF_SKIP_DISTRIBUTION_COPIES: '1',
      }),
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    proc.stdout.on('data', (d) => (stdout += d.toString()));
    proc.stderr.on('data', (d) => (stderr += d.toString()));
    proc.on('exit', (code) => {
      if (code === 0) return resolve(stdout.trim());
      reject(new Error(`isolated PHP build failed: ${code} ${stderr}`));
    });
  });
}

function runPhpLint(filePath) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', ['-l', filePath], {
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
      reject(new Error(`PHP lint failed: ${code} ${stderr}`));
    });
  });
}

function runConverterGrainMirror(targetDir) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_converter_grain_mirror.php'), targetDir], {
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
      reject(new Error(`converter grain mirror failed: ${code} ${stderr}`));
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

function runViewerWithQuery(relativePath, baseDir = POFF_DIR, editMode = false, query = {}) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_render_viewer.php'), baseDir, relativePath];
    if (editMode) {
      args.push('edit');
    }
    args.push(JSON.stringify({ query }));
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

function runLayoutWithQuery(query = {}) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_render_layout.php'), JSON.stringify({ query })], {
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

function runResolveRemoteRenderedHtmlWithMap(item, mockResponsesByUrl) {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [path.join(ROOT, 'tests/php_resolve_remote_rendered_html.php'), JSON.stringify({
      item,
      mockResponsesByUrl,
    })], {
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

function runCreateLink(targetDir, label, linkUrl, options = {}) {
  return new Promise((resolve, reject) => {
    const args = [path.join(ROOT, 'tests/php_create_link.php'), targetDir, label, linkUrl, JSON.stringify(options)];
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
      if (code === 0) return resolve(JSON.parse(stdout.trim()));
      reject(new Error(`create link helper failed: ${code} ${stderr}`));
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
  const storage = new Map();
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
    URLSearchParams: global.URLSearchParams,
    window: {
      location: {
        pathname: '/dominikeggermann.com/',
        origin: 'http://localhost:8888',
      },
      localStorage: {
        getItem(key) {
          return storage.has(key) ? storage.get(key) : null;
        },
        setItem(key, value) {
          storage.set(String(key), String(value));
        },
        removeItem(key) {
          storage.delete(String(key));
        },
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
  clearEditorAuthToken,
  getEditorAuthToken,
  requestEditAuth,
  requestPromptModels,
  requestLocalPromptModels,
  requestPromptTemplateStream,
  setEditorAuthToken,
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

function runEditRequestBypass(cwd, action, relativePath = '', payload = {}, sessionId = '') {
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
      env: withTestEnv(),
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

function runEditAuthCookieProbe(cwd, password = 'secret-pass') {
  return new Promise((resolve, reject) => {
    const proc = spawn('php', [
      path.join(ROOT, 'tests/php_edit_auth_cookie.php'),
      cwd,
      password,
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
        reject(new Error(`edit auth cookie probe failed: ${code} ${stderr}`));
        return;
      }
      try {
        resolve(JSON.parse(stdout));
      } catch (err) {
        reject(new Error(`invalid json from edit auth cookie probe: ${stdout}`));
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

  test('editor auth survives reload through the explicit editor token', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-auth-cookie-'));
    const result = await runEditAuthCookieProbe(tempDir, 'secret-pass');
    expect(result.passwordOk).toBe(true);
    expect(result.token).toBeTruthy();
    expect(result.restored).toBe(true);
    expect(result.authenticated).toBe(true);
    fs.rmSync(tempDir, { recursive: true, force: true });
  });

  test('edit auth requests attach the stored editor token header', async () => {
    const api = loadPromptApiHelpers();
    api.setEditorAuthToken('token-123');
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
          auth: {
            configured: true,
            authenticated: true,
            editModeAllowed: true,
            canEdit: true,
          },
        }),
      };
    });

    await api.requestEditAuth({ method: 'GET', path: '' });

    expect(requestUrl.toString()).toContain('authToken=token-123');
    expect(requestOptions.headers['X-Poff-Editor-Auth']).toBe('token-123');
  });

  test('guest poff links stay pending until an authenticated editor approves them', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-pending-link-'));
    const sessionId = 'poff-pending-link-session';
    const hash = await makePasswordHash('secret-pass');
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, '.poff-auth.php'), `<?php return ['passwordHash' => '${hash}'];`);
    fs.writeFileSync(path.join(tempDir, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-pending-link',
      slug: 'poff-pending-link',
      type: 'folder',
      tree: [],
      externalSubmissions: {
        enabled: true,
        whitelistEnabled: true,
        whitelist: ['remote.example'],
        maxPending: 3,
      },
    }, null, 2));

    const submitted = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'remote-link',
      linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
      categories: ['image', 'collection'],
      poffNode: {
        protocol: 'poff-node/1',
        baseUrl: 'https://remote.example/index.php',
        workCatalogUrl: 'https://remote.example/index.php?mcp=1&route=export-content&path=portfolio',
        converterCatalogUrl: 'https://remote.example/index.php?mcp=1&route=converter-offers',
      },
    }, sessionId);
    expect(submitted.allowed).toBe(true);
    expect(submitted.pendingApproval).toBe(true);
    expect(submitted.uploaded[0].pendingApproval).toBe(true);

    const guestConfig = JSON.parse(fs.readFileSync(path.join(tempDir, 'poff.config.json'), 'utf8'));
    expect(guestConfig.tree[0].visible).toBe(false);
    expect(guestConfig.tree[0].approvalStatus).toBe('pending');
    expect(guestConfig.tree[0].categories).toEqual(['image', 'collection']);
    expect(guestConfig.tree[0].work.categories).toEqual(['image', 'collection']);
    expect(guestConfig.tree[0].poffNode).toEqual(expect.objectContaining({
      protocol: 'poff-node/1',
      baseUrl: 'https://remote.example/index.php',
    }));

    const updated = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'remote-link-updated',
      linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
      categories: ['converter', 'collection'],
      poffNode: {
        protocol: 'poff-node/1',
        baseUrl: 'https://remote.example/index.php',
        workCatalogUrl: 'https://remote.example/index.php?mcp=1&route=export-content&path=portfolio',
        converterCatalogUrl: 'https://remote.example/index.php?mcp=1&route=converter-offers',
      },
    }, sessionId);
    expect(updated.uploaded[0].updatedRegistration).toBe(true);
    const updatedConfig = JSON.parse(fs.readFileSync(path.join(tempDir, 'poff.config.json'), 'utf8'));
    expect(updatedConfig.tree).toHaveLength(1);
    expect(updatedConfig.tree[0].title).toBe('remote-link-updated');
    expect(updatedConfig.tree[0].categories).toEqual(['converter', 'collection']);

    const login = await runEditRequest(tempDir, 'auth', '', {
      intent: 'login',
      password: 'secret-pass',
    }, sessionId);
    expect(login.auth.authenticated).toBe(true);

    const approved = await runEditRequest(tempDir, 'save', '', {
      treeVisible: ['remote-link'],
    }, login.sessionId || sessionId);
    expect(approved.allowed).toBe(true);
    expect(approved.config.tree[0].visible).toBe(true);
    expect(approved.config.tree[0].approvalStatus).toBe('approved');
    expect(typeof approved.config.tree[0].approvedAt).toBe('string');

    fs.rmSync(tempDir, { recursive: true, force: true });
  });

  test('guest external submissions require host opt-in, honor whitelist, and enforce the pending limit', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-pending-limit-'));
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');

    const disabled = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'disabled-node',
      linkUrl: 'https://remote.example/index.php?view=1&path=portfolio',
    });
    expect(disabled.allowed).toBe(false);

    const direct = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      submissionIntent: 'external-link',
      linkName: 'direct-link',
      linkUrl: 'https://remote.example/index.php?view=1&path=direct-work',
    });
    expect(direct.allowed).toBe(true);
    expect(direct.pendingApproval).toBe(true);

    const tree = [];
    for (let index = 0; index < 2; index += 1) {
      tree.push({
        name: `pending-${index}`,
        slug: `pending-${index}`,
        type: 'link',
        kind: 'link',
        path: `pending-${index}`,
        linkUrl: `https://remote.example/index.php?view=1&path=item-${index}`,
        baseUrl: 'https://remote.example/index.php',
        visible: false,
        externalSubmission: true,
        approvalStatus: 'pending',
      });
    }
    fs.writeFileSync(path.join(tempDir, 'poff.config.json'), JSON.stringify({
      folderName: 'pending-limit',
      slug: 'pending-limit',
      type: 'folder',
      tree,
      externalSubmissions: {
        enabled: true,
        whitelistEnabled: true,
        whitelist: ['remote.example'],
        maxPending: 2,
      },
    }, null, 2));

    const invalid = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'not-poff',
      linkUrl: 'https://example.com/plain-page',
    });
    expect(invalid.allowed).toBe(true);
    expect(invalid.error).toContain('Only links from another Poff system are allowed');

    const notWhitelisted = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'not-whitelisted',
      linkUrl: 'https://other.example/index.php?view=1&path=portfolio',
    });
    expect(notWhitelisted.allowed).toBe(true);
    expect(notWhitelisted.error).toContain('whitelist');

    const overLimit = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'remote-overflow',
      linkUrl: 'https://remote.example/index.php?view=1&path=overflow',
    });
    expect(overLimit.allowed).toBe(true);
    expect(overLimit.error).toContain('Too many external links are waiting for approval');

    fs.rmSync(tempDir, { recursive: true, force: true });
  });

  test('guest external submissions accept poff hash routes', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-pending-hash-link-'));
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, 'poff.config.json'), JSON.stringify({
      folderName: 'poff-pending-hash-link',
      slug: 'poff-pending-hash-link',
      type: 'folder',
      tree: [],
      externalSubmissions: {
        enabled: true,
        whitelistEnabled: true,
        whitelist: ['dominikeggermann.com'],
        maxPending: 5,
      },
    }, null, 2));

    const submitted = await runEditRequest(tempDir, 'upload', '', {
      source: 'url',
      linkName: 'joined-test-1762176567798-mp4',
      linkUrl: 'https://dominikeggermann.com/#/joined-test-1762176567798-mp4',
    });
    expect(submitted.allowed).toBe(true);
    expect(submitted.pendingApproval).toBe(true);
    expect(submitted.uploaded[0].linkUrl).toBe('https://dominikeggermann.com/#/joined-test-1762176567798-mp4');

    const config = JSON.parse(fs.readFileSync(path.join(tempDir, 'poff.config.json'), 'utf8'));
    expect(config.tree[0].visible).toBe(false);
    expect(config.tree[0].approvalStatus).toBe('pending');
    expect(config.tree[0].linkUrl).toBe('https://dominikeggermann.com/#/joined-test-1762176567798-mp4');

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

  test('build cleanup removes all subfolder index files', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-build-cleanup-'));
    try {
      await runFileCopierCleanup(tempRoot);

      expect(fs.existsSync(path.join(tempRoot, 'index.php'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'generated-child', 'index.php'))).toBe(false);
      expect(fs.existsSync(path.join(tempRoot, 'custom-child', 'index.php'))).toBe(false);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
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

  test('folder copy assigns new Work IDs to copied folders and file metadata', async () => {
    const tempRoot = path.join(POFF_DIR, `identity-copy-${Date.now()}`);
    const sourceDir = path.join(tempRoot, 'poff', 'source');
    const worksDir = path.join(sourceDir, '.works');
    const nestedDir = path.join(sourceDir, 'nested');
    const nestedWorksDir = path.join(nestedDir, '.works');
    fs.mkdirSync(worksDir, { recursive: true });
    fs.mkdirSync(nestedWorksDir, { recursive: true });
    fs.writeFileSync(path.join(sourceDir, 'note.txt'), 'copy identity fixture');
    fs.writeFileSync(path.join(nestedDir, 'nested.txt'), 'nested copy identity fixture');
    fs.writeFileSync(path.join(sourceDir, 'poff.config.json'), JSON.stringify({
      id: 'poff_source_folder',
      title: 'Source folder',
      tree: [{ name: 'note.txt', type: 'file' }],
    }));
    fs.writeFileSync(path.join(worksDir, 'note.txt.config.json'), JSON.stringify({
      id: 'poff_source_file',
      name: 'note.txt',
      path: 'note.txt',
      type: 'file',
      work: { type: 'text' },
    }));
    fs.writeFileSync(path.join(nestedDir, 'poff.config.json'), JSON.stringify({
      id: 'poff_source_nested_folder',
      title: 'Nested source folder',
      tree: [{ name: 'nested.txt', type: 'file' }],
    }));
    fs.writeFileSync(path.join(nestedWorksDir, 'nested.txt.config.json'), JSON.stringify({
      id: 'poff_source_nested_file',
      name: 'nested.txt',
      path: 'nested.txt',
      type: 'file',
      work: { type: 'text' },
    }));

    try {
      const sourceRelativePath = path.relative(POFF_DIR, sourceDir);
      const destinationRelativePath = path.relative(POFF_DIR, path.join(tempRoot, 'poff', 'copy'));
      const result = await runCreate([
        `--dest=${destinationRelativePath}`,
        `--path=${sourceRelativePath}`,
      ]);
      const copiedDir = path.join(POFF_DIR, destinationRelativePath);
      const copiedFolderConfig = JSON.parse(fs.readFileSync(path.join(copiedDir, 'poff.config.json'), 'utf8'));
      const copiedFileConfig = JSON.parse(fs.readFileSync(path.join(copiedDir, '.works', 'note.txt.config.json'), 'utf8'));
      const copiedNestedFolderConfig = JSON.parse(fs.readFileSync(path.join(copiedDir, 'nested', 'poff.config.json'), 'utf8'));
      const copiedNestedFileConfig = JSON.parse(fs.readFileSync(path.join(copiedDir, 'nested', '.works', 'nested.txt.config.json'), 'utf8'));

      expect(result.copied).toBe(true);
      expect(result.details.reidentifiedWorks).toBe(4);
      expect(copiedFolderConfig.id).not.toBe('poff_source_folder');
      expect(copiedFileConfig.id).not.toBe('poff_source_file');
      expect(copiedNestedFolderConfig.id).not.toBe('poff_source_nested_folder');
      expect(copiedNestedFileConfig.id).not.toBe('poff_source_nested_file');
      expect(fs.readFileSync(path.join(sourceDir, 'poff.config.json'), 'utf8')).toContain('poff_source_folder');
      expect(fs.readFileSync(path.join(worksDir, 'note.txt.config.json'), 'utf8')).toContain('poff_source_file');
      expect(fs.readFileSync(path.join(nestedDir, 'poff.config.json'), 'utf8')).toContain('poff_source_nested_folder');
      expect(fs.readFileSync(path.join(nestedWorksDir, 'nested.txt.config.json'), 'utf8')).toContain('poff_source_nested_file');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('folder and file Work IDs survive repeated config ensure', async () => {
    const result = await runPhpJson('php_work_id_stability.php');

    expect(result.folder[0]).toMatch(/^poff_[a-f0-9]{16}$/);
    expect(result.file[0]).toMatch(/^poff_[a-f0-9]{16}$/);
    expect(result.folder[1]).toBe(result.folder[0]);
    expect(result.file[1]).toBe(result.file[0]);
    expect(result.file[0]).not.toBe(result.folder[0]);
    expect(result.newIds[0]).toMatch(/^poff_[a-f0-9]{16}$/);
    expect(result.newIds[1]).not.toBe(result.newIds[0]);
    expect(result.nodeIds[0]).toMatch(/^poff_node_[a-f0-9]{32}$/);
    expect(result.nodeIds[1]).toBe(result.nodeIds[0]);
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

  test('rejects local prompt-template requests without an explicit model', async () => {
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const result = await runPromptTemplateLocal(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: '',
      endpoint,
      prompt: 'Create a compact image card.',
    });

    expect(result.allowed).toBe(true);
    expect(result.error).toBe('Select a local model before sending prompts.');
    expect(result.model).toBeUndefined();
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

  test('rejects viewer local prompts without an explicit model', async () => {
    const endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
    const viewerResult = await runViewerPrompt(POFF_DIR, path.relative(POFF_DIR, VIEWER_FOLDER_DIR), {
      provider: 'local',
      model: '',
      endpoint,
      prompt: 'Make it smaller.',
    });

    expect(viewerResult.allowed).toBe(true);
    expect(viewerResult.error).toBe('Select a local model before sending prompts.');
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

function runConverterRoute(mode, rootDir, payload = {}, extraEnv = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_converter_routes.php'),
      mode,
      rootDir,
      JSON.stringify(payload),
    ];
    const proc = spawn('php', args, {
      cwd: ROOT,
      env: withTestEnv(extraEnv),
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
      reject(new Error(`converter helper failed: ${code} ${stderr}`));
    });
  });
}

function runPageoffRemoteConverter(payload = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_pageoff_remote_converter.php'),
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
      reject(new Error(`pageoff remote converter helper failed: ${code} ${stderr}`));
    });
  });
}

function runPageoffRemoteImageConverter(payload = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_pageoff_remote_image_converter.php'),
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
      reject(new Error(`pageoff remote image converter helper failed: ${code} ${stderr}`));
    });
  });
}

function runTextEditorRemoteConverter(payload = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_text_editor_remote_converter.php'),
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
      reject(new Error(`text editor remote converter helper failed: ${code} ${stderr}`));
    });
  });
}

function runViewerMcpRoute(route, rootDir, payload = {}) {
  return new Promise((resolve, reject) => {
    const args = [
      path.join(ROOT, 'tests/php_viewer_mcp_route.php'),
      rootDir,
      route,
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
      reject(new Error(`viewer mcp helper failed: ${code} ${stderr}`));
    });
  });
}

function createConverterApp(rootDir, name, overrides = {}) {
  const folder = path.join(rootDir, 'poff', 'converters', name);
  const layout = path.join(folder, '.layout');
  fs.mkdirSync(layout, { recursive: true });
  const definition = {
    type: 'converter',
    name,
    label: name.split('-').map((part) => part[0].toUpperCase() + part.slice(1)).join(' '),
    accepts: ['image/tiff', 'image/*'],
    outputs: ['image/webp', 'image/jpeg', 'image/png'],
    engine: name.includes('remote') ? 'remote-node' : 'imagemagick',
    defaults: {
      format: 'webp',
      quality: 'default',
      saveMode: 'new-hidden-work',
      hiddenByDefault: true,
    },
    ui: {
      format: { type: 'select', label: 'Output format', options: ['webp', 'jpeg', 'png'] },
      quality: { type: 'select', label: 'Quality', options: ['preview', 'default', 'archival', 'small-web'] },
    },
    ...overrides,
  };
  fs.writeFileSync(path.join(folder, 'converter.json'), JSON.stringify(definition, null, 2));
  for (const file of ['template.hbs', 'work.hbs', 'external.hbs', 'style.css', 'script.js']) {
    fs.writeFileSync(path.join(layout, file), file.endsWith('.hbs') ? `<section>${name}</section>` : '');
  }
  return folder;
}

describe('Poff converters', () => {
  beforeAll(() => {
    fs.mkdirSync(POFF_DIR, { recursive: true });
  });

  test('discovers converter app folders and derives IDs from folder names', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-discovery-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    createConverterApp(tempRoot, 'convert-image', { label: 'Convert Image' });
    createConverterApp(tempRoot, 'convert-imagemagick', { label: 'Convert ImageMagick' });
    createConverterApp(tempRoot, 'convert-remote-image', {
      label: 'Convert Remote Image',
      engine: 'remote-node',
      remote: { nodeId: 'image-converter-node', endpoint: 'https://converter.example/index.php?mcp=1&route=convert' },
    });

    try {
      const result = await runConverterRoute('available', tempRoot, {
        mimeType: 'image/tiff',
        kind: 'image',
        extension: 'tiff',
      });

      expect(result).toEqual(expect.arrayContaining([
        expect.objectContaining({
          id: 'converter/convert-image',
          name: 'convert-image',
          label: 'Convert Image',
          folder: 'poff/converters/convert-image',
          path: 'poff/converters/convert-image',
          viewerHref: '?view=1&path=poff%2Fconverters%2Fconvert-image',
          templateFolder: 'poff/converters/convert-image/.layout',
          formats: ['webp', 'jpeg', 'png'],
        }),
        expect.objectContaining({
          id: 'converter/convert-imagemagick',
          name: 'convert-imagemagick',
        }),
        expect.objectContaining({
          id: 'converter/convert-remote-image',
          name: 'convert-remote-image',
        }),
      ]));
      expect(result.map((item) => item.id)).not.toEqual(expect.arrayContaining([
        'imagemagick-image-webp',
        'imagemagick-image-jpeg',
        'imagemagick-image-png',
      ]));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('converter matching prefers supplied MIME and falls back through kind then extension', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-matching-${Date.now()}`);
    const definitions = [
      ['mime-wildcard', ['image/*']],
      ['kind-only', ['image']],
      ['extension-only', ['zzx']],
    ];
    for (const [name, accepts] of definitions) {
      const folder = path.join(tempRoot, 'poff', 'converters', name);
      fs.mkdirSync(folder, { recursive: true });
      fs.writeFileSync(path.join(folder, 'converter.json'), JSON.stringify({
        type: 'converter',
        accepts,
        outputs: ['image/png'],
        engine: 'text-copy',
      }));
    }

    try {
      const byMime = await runConverterRoute('available', tempRoot, {
        mimeType: 'image/tiff', kind: 'other', extension: 'txt',
      });
      const testConverters = (items) => items
        .map(({ name }) => name)
        .filter((name) => definitions.some(([definedName]) => definedName === name));
      expect(testConverters(byMime)).toEqual(['mime-wildcard']);

      const byKind = await runConverterRoute('available', tempRoot, {
        mimeType: '', kind: 'image', extension: '',
      });
      expect(testConverters(byKind).sort()).toEqual(['kind-only', 'mime-wildcard']);

      const byExtension = await runConverterRoute('available', tempRoot, {
        mimeType: '', kind: '', extension: 'zzx',
      });
      expect(testConverters(byExtension)).toEqual(['extension-only']);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('converter discovery uses folder precedence and keeps disabled matches out of remote offers', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-precedence-${Date.now()}`);
    const writeDefinition = (folder, definition) => {
      const absoluteFolder = path.join(tempRoot, folder);
      fs.mkdirSync(absoluteFolder, { recursive: true });
      fs.writeFileSync(path.join(absoluteFolder, 'converter.json'), JSON.stringify({
        type: 'converter',
        accepts: ['text/plain'],
        outputs: ['text/plain'],
        engine: 'text-copy',
        ...definition,
      }));
    };
    writeDefinition('poff/converters/collision', { label: 'Poff converter' });
    writeDefinition('converters/collision', { label: 'Visible converter' });
    writeDefinition('.converters/collision', { label: 'Hidden converter' });
    writeDefinition('poff/converters/disabled', { label: 'Disabled converter', enabled: false });

    try {
      const discovered = await runConverterRoute('discover', tempRoot);
      expect(discovered['converter/collision']).toEqual(expect.objectContaining({
        label: 'Hidden converter',
        folder: '.converters/collision',
      }));

      const matched = await runConverterRoute('available', tempRoot, { mimeType: 'text/plain' });
      expect(matched).toEqual(expect.arrayContaining([
        expect.objectContaining({ id: 'converter/disabled', enabled: false }),
      ]));

      const offers = await runConverterRoute('offers', tempRoot, { mimeType: 'text/plain' });
      expect(offers.converters.map(({ id }) => id)).toContain('converter/collision');
      expect(offers.converters.map(({ id }) => id)).not.toContain('converter/disabled');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('legacy fallback returns folder-backed converter IDs for image/tiff', async () => {
    const result = await runConverterRoute('available', POFF_DIR, {
      mimeType: 'image/tiff',
      kind: 'image',
      extension: 'tiff',
    });

    expect(result).toEqual(expect.arrayContaining([
      expect.objectContaining({
        id: 'converter/convert-image',
        name: 'convert-image',
        label: 'Convert Image',
        formats: ['webp', 'jpeg', 'png'],
      }),
      expect.objectContaining({
        id: 'converter/convert-imagemagick',
        name: 'convert-imagemagick',
        label: 'Convert ImageMagick',
      }),
      expect.objectContaining({
        id: 'converter/convert-remote-node',
        name: 'convert-remote-node',
        label: 'Convert Remote Node',
      }),
      expect.objectContaining({
        id: 'converter/convert-pageoff-remote-image',
        name: 'convert-pageoff-remote-image',
        label: 'Convert Pageoff Remote Image',
        engine: 'remote-node',
        url: 'https://pageoff.de/?edit=true#/converter/convert-pageoff-remote-image',
        remote: expect.objectContaining({
          endpoint: 'https://pageoff.de/worktypes/templates/converter/convert-pageoff-remote-image/converter.php',
          publicUrl: 'https://pageoff.de/?edit=true#/converter/convert-pageoff-remote-image',
        }),
      }),
    ]));
  });

  test('publishes locally executable converters as generic Poff node offers', async () => {
    const result = await runConverterRoute('offers', POFF_DIR, {
      mimeType: 'image/tiff',
      kind: 'image',
      extension: 'tiff',
      nodeUrl: 'https://provider.example/index.php',
      nodeId: 'provider-example',
    });

    expect(result).toEqual(expect.objectContaining({
      ok: true,
      protocol: 'poff-converter/1',
      node: expect.objectContaining({
        id: 'provider-example',
        endpoint: 'https://provider.example/index.php?mcp=1&route=convert-offered',
      }),
    }));
    expect(result.converters).toEqual(expect.arrayContaining([
      expect.objectContaining({
        id: 'converter/convert-image',
        accepts: expect.arrayContaining(['image/*']),
        formats: expect.arrayContaining(['png']),
      }),
    ]));
    expect(result.converters.every((converter) => converter.engine === undefined)).toBe(true);
  });

  test('registers the current page with Poff using existing work categories', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-register-node-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'gallery',
        title: 'Public gallery',
        type: 'folder',
        tree: [],
        work: {
          type: 'folder',
          categories: ['image', 'collection'],
        },
      }, null, 2));

      const result = await runConverterRoute('register-node', tempRoot, {
        pageUrl: 'https://node.example/index.php?view=1&path=.',
        registryUrl: 'https://pageoff.de/',
      });

      expect(result).toEqual(expect.objectContaining({
        ok: true,
        route: 'register-node',
        pendingApproval: true,
        categories: ['image', 'collection'],
      }));
      expect(result.node).toEqual(expect.objectContaining({
        protocol: 'poff-node/1',
        baseUrl: 'https://node.example/index.php',
      }));
      expect(result._capturedRegistry.url).toBe('https://pageoff.de/index.php?edit=upload');
      expect(result._capturedRegistry.payload).toEqual(expect.objectContaining({
        source: 'url',
        linkName: 'Public gallery',
        linkUrl: 'https://node.example/index.php?view=1&path=.',
        categories: ['image', 'collection'],
      }));
      expect(result._capturedRegistry.payload.poffNode).toEqual(expect.objectContaining({
        workCatalogUrl: expect.stringContaining('route=export-content'),
        converterCatalogUrl: expect.stringContaining('route=converter-offers'),
      }));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('discovers converter offers from an external Poff node', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-node-catalog-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');

    try {
      const result = await runConverterRoute('remote-catalog', tempRoot, {
        path: 'photo.heic',
        nodeUrl: 'https://example.com/index.php',
        mimeType: 'image/heic',
        kind: 'image',
        extension: 'heic',
        mockCatalog: {
          ok: true,
          protocol: 'poff-converter/1',
          node: {
            id: 'provider-example',
            url: 'https://example.com/index.php',
            endpoint: 'https://example.com/index.php?mcp=1&route=convert-offered',
          },
          converters: [
            {
              id: 'converter/convert-image',
              name: 'convert-image',
              label: 'Convert Image',
              accepts: ['image/*'],
              outputs: ['image/png'],
              formats: ['png'],
              defaults: { format: 'png' },
              ui: {
                format: {
                  type: 'select',
                  options: ['png'],
                },
              },
            },
          ],
        },
      });

      expect(result).toEqual(expect.objectContaining({
        ok: true,
        protocol: 'poff-converter/1',
        node: expect.objectContaining({ id: 'provider-example' }),
      }));
      expect(result.available).toEqual([
        expect.objectContaining({
          id: 'converter/remote-provider-example-convert-image',
          engine: 'remote-node',
          enabled: true,
          source: 'remote-node-catalog',
          node: expect.objectContaining({
            id: 'provider-example',
            converterId: 'converter/convert-image',
            endpoint: 'https://example.com/index.php?mcp=1&route=convert-offered',
          }),
        }),
      ]);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('converts an inline file offered by a foreign Poff node', async () => {
    const body = fs.readFileSync(path.join(ROOT, 'archive/scan-001.tiff'));
    const result = await runConverterRoute('convert-offered', POFF_DIR, {
      source: {
        name: 'scan-001.tiff',
        path: 'scan-001.tiff',
        mimeType: 'image/tiff',
        bodyBase64: body.toString('base64'),
      },
      converter: {
        id: 'converter/convert-image',
        format: 'png',
        quality: 'default',
      },
      target: {
        saveAs: 'scan-001.png',
      },
    });

    expect(result).toEqual(expect.objectContaining({
      ok: true,
      route: 'convert-offered',
      output: expect.objectContaining({
        name: 'scan-001.png',
        mimeType: 'image/png',
      }),
      generatedBy: expect.objectContaining({
        id: 'converter/convert-image',
        node: 'provider',
      }),
    }));
    expect(Buffer.from(result.output.bodyBase64, 'base64').subarray(0, 8).toString('binary')).toBe('\x89PNG\r\n\x1a\n');
  });

  test('keeps a requested jpg filename for a jpeg result', async () => {
    const body = fs.readFileSync(path.join(ROOT, 'archive/scan-001.tiff'));
    const result = await runConverterRoute('convert-offered', POFF_DIR, {
      source: {
        name: 'scan-001.tiff',
        path: 'scan-001.tiff',
        mimeType: 'image/tiff',
        bodyBase64: body.toString('base64'),
      },
      converter: {
        id: 'converter/convert-image',
        format: 'jpeg',
        quality: 'default',
      },
      target: {
        saveAs: 'scan-001.jpg',
      },
    });

    expect(result).toEqual(expect.objectContaining({
      ok: true,
      route: 'convert-offered',
      output: expect.objectContaining({
        name: 'scan-001.jpg',
        mimeType: 'image/jpeg',
      }),
    }));
    expect(Buffer.from(result.output.bodyBase64, 'base64').subarray(0, 3).toString('hex')).toBe('ffd8ff');
  });

  test('sends a local source to a discovered provider converter and receives its result', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-node-consumer-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(tempRoot, 'scan-001.tiff'));

    try {
      const sourcePath = path.join(tempRoot, 'scan-001.tiff');
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          name: 'scan-001.tiff',
          path: 'scan-001.tiff',
          mimeType: 'image/tiff',
          extension: 'tiff',
          size: fs.statSync(sourcePath).size,
        },
        converter: {
          id: 'converter/remote-provider-example-convert-image',
          name: 'remote-provider-example-convert-image',
          label: 'Convert Image · provider-example',
          accepts: ['image/*'],
          outputs: ['image/png'],
          formats: ['png'],
          defaults: { format: 'png' },
          ui: {},
          engine: 'remote-node',
          format: 'png',
          quality: 'default',
          node: {
            id: 'provider-example',
            baseUrl: 'https://provider.example/index.php',
            endpoint: 'https://provider.example/index.php?mcp=1&route=convert-offered',
            converterId: 'converter/convert-image',
          },
        },
        target: {
          folder: '',
          saveAs: 'scan-001.png',
          mode: 'new-hidden-work',
        },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'scan-001.png',
              mimeType: 'image/png',
              kind: 'image',
              size: 3,
              bodyBase64: Buffer.from('png').toString('base64'),
            },
          },
        },
        captureRemotePayload: true,
      });

      expect(result.ok).toBe(true);
      expect(result.output.mimeType).toBe('image/png');
      expect(result._capturedRemotePayload.source.bodyBase64).toBe(
        fs.readFileSync(sourcePath).toString('base64')
      );
      expect(result._capturedRemotePayload.converter.id).toBe('converter/convert-image');
      expect(result._capturedRemotePayload.converter.node).toBe('provider');
      expect(result.generatedBy.node).toBe('provider-example');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('rejects a remote converter result with an unoffered output MIME type', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-node-invalid-output-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(tempRoot, 'scan-001.tiff'));

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          name: 'scan-001.tiff',
          path: 'scan-001.tiff',
          mimeType: 'image/tiff',
          extension: 'tiff',
        },
        converter: {
          id: 'converter/remote-provider-example-convert-image',
          name: 'remote-provider-example-convert-image',
          accepts: ['image/*'],
          outputs: ['image/png'],
          formats: ['png'],
          engine: 'remote-node',
          format: 'png',
          node: {
            id: 'provider-example',
            baseUrl: 'https://provider.example/index.php',
            endpoint: 'https://provider.example/index.php?mcp=1&route=convert-offered',
            converterId: 'converter/convert-image',
          },
        },
        target: { saveAs: 'scan-001.png' },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'scan-001.svg',
              mimeType: 'image/svg+xml',
              bodyBase64: Buffer.from('<svg/>').toString('base64'),
            },
          },
        },
      });

      expect(result).toEqual(expect.objectContaining({
        ok: false,
        error: 'Remote output MIME type is not allowed.',
      }));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('uses the actual source file size instead of the supplied size', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-source-size-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    const sourcePath = path.join(tempRoot, 'oversized.tiff');
    fs.closeSync(fs.openSync(sourcePath, 'w'));
    fs.truncateSync(sourcePath, 104857601);

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          name: 'oversized.tiff',
          path: 'oversized.tiff',
          mimeType: 'image/tiff',
          extension: 'tiff',
          size: 1,
        },
        converter: {
          id: 'converter/convert-image',
          format: 'png',
        },
      });

      expect(result).toEqual(expect.objectContaining({
        ok: false,
        error: 'Source file is too large.',
      }));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('bulk converts eligible folder images and applies converted-only visibility', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-bulk-${Date.now()}`);
    const albumDir = path.join(tempRoot, 'album');
    fs.mkdirSync(albumDir, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(albumDir, 'scan-a.tiff'));
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(albumDir, 'scan-b.tiff'));
    fs.copyFileSync(path.join(POFF_DIR, '.layout', 'poff.profile.jpg'), path.join(albumDir, 'photo.jpg'));
    fs.copyFileSync(path.join(POFF_DIR, '.layout', 'poff.profile.jpg'), path.join(albumDir, 'thumb-photo.jpg'));
    fs.writeFileSync(path.join(albumDir, 'notes.txt'), 'not an image');

    try {
      const result = await runConverterRoute('bulk-convert', tempRoot, {
        path: 'album',
        converter: {
          id: 'converter/convert-image',
          name: 'convert-image',
          format: 'png',
          quality: 'default',
        },
        mimeType: 'image/tiff',
        hideThumbnails: true,
        visibilityMode: 'converted-only',
        enableImageMagickCli: true,
      });

      expect(result).toEqual(expect.objectContaining({
        ok: true,
        convertedCount: 2,
        failedCount: 0,
        hiddenThumbnailCount: 1,
        visibilityMode: 'converted-only',
      }));
      expect(fs.existsSync(path.join(albumDir, 'scan-a.png'))).toBe(true);
      expect(fs.existsSync(path.join(albumDir, 'scan-b.png'))).toBe(true);
      expect(fs.existsSync(path.join(albumDir, 'photo.png'))).toBe(false);
      const config = JSON.parse(fs.readFileSync(path.join(albumDir, 'poff.config.json'), 'utf8'));
      const byName = new Map(config.tree.map((item) => [item.name, item]));
      expect(byName.get('scan-a.tiff').visible).toBe(false);
      expect(byName.get('scan-b.tiff').visible).toBe(false);
      expect(byName.get('scan-a.png')).toEqual(expect.objectContaining({
        visible: true,
        generated: true,
        sourceWork: 'scan-a.tiff',
      }));
      expect(byName.get('scan-b.png')).toEqual(expect.objectContaining({
        visible: true,
        generated: true,
        sourceWork: 'scan-b.tiff',
      }));
      expect(byName.get('notes.txt').visible).toBe(true);
      expect(byName.get('photo.jpg').visible).toBe(true);
      expect(byName.get('thumb-photo.jpg').visible).toBe(false);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('discovers the text converter for text/plain', async () => {
    const result = await runConverterRoute('available', POFF_DIR, {
      mimeType: 'text/plain',
      kind: 'text',
      extension: 'txt',
    });

    expect(result).toEqual(expect.arrayContaining([
      expect.objectContaining({
        id: 'converter/convert-text',
        name: 'convert-text',
        label: 'Convert Text',
        folder: 'poff/converters/convert-text',
        formats: ['txt'],
        outputs: ['text/plain'],
      }),
      expect.objectContaining({
        id: 'converter/convert-pageoff-remote-text',
        name: 'convert-pageoff-remote-text',
        label: 'Convert Pageoff Remote Text',
        engine: 'remote-node',
        url: 'https://pageoff.de/?edit=true#/converter/convert-pageoff-remote-text',
        outputs: ['text/html', 'text/plain'],
        remote: expect.objectContaining({
          endpoint: 'https://pageoff.de/worktypes/templates/converter/convert-pageoff-remote-text/converter.php',
          publicUrl: 'https://pageoff.de/?edit=true#/converter/convert-pageoff-remote-text',
        }),
      }),
      expect.objectContaining({
        id: 'converter/convert-text-editor',
        name: 'convert-text-editor',
        label: 'Convert Text Editor',
        formats: ['json', 'html', 'txt', 'md'],
        outputs: ['application/json', 'text/html', 'text/plain', 'text/markdown'],
      }),
    ]));
    const editorDefinition = result.find((item) => item.id === 'converter/convert-text-editor');
    expect(editorDefinition?.folder || '').toContain('worktypes/templates/converter/convert-text-editor');
    expect(editorDefinition?.templateFolder || '').toContain('worktypes/templates/converter/convert-text-editor');
  });

  test('pageoff remote image converter payload prefixes generated files as external works', async () => {
    const payload = await runConverterRoute('build-payload', POFF_DIR, {
      source: {
        path: 'scan-001.tiff',
        name: 'scan-001.tiff',
        mimeType: 'image/tiff',
      },
      converter: {
        id: 'converter/convert-pageoff-remote-image',
        format: 'png',
        quality: 'default',
      },
    });

    expect(payload.converter.id).toBe('converter/convert-pageoff-remote-image');
    expect(payload.target.saveAs).toBe('external-scan-001.png');
  });

  test('pageoff local converter.php returns a php-generated text conversion response', async () => {
    const result = await runPageoffRemoteConverter({
      source: {
        path: 'draft.txt',
        name: 'draft.txt',
        mimeType: 'text/plain',
      },
      converter: {
        id: 'converter/convert-pageoff-remote-text',
        format: 'html',
        fontSize: '22px',
      },
      target: {
        saveAs: 'draft.html',
      },
      editor: {
        content: 'Hello from pageoff remote PHP',
      },
    });

    const html = Buffer.from(result.output.bodyBase64, 'base64').toString('utf8');
    expect(result.ok).toBe(true);
    expect(result.output.name).toBe('draft.html');
    expect(result.output.mimeType).toBe('text/html');
    expect(html).toContain('Generated by remote PHP converter');
    expect(html).toContain('Font size: 22px');
    expect(result.generatedBy.usedPhp).toBe(true);
  });

  test('pageoff local remote php conversion can be saved locally as a generated work', async () => {
    const tempRoot = path.join(POFF_DIR, `pageoff-remote-save-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempRoot, 'draft.txt'), 'Local source text');

    try {
      const conversion = await runPageoffRemoteConverter({
        source: {
          path: 'draft.txt',
          name: 'draft.txt',
          mimeType: 'text/plain',
        },
        converter: {
          id: 'converter/convert-pageoff-remote-text',
          format: 'html',
          fontSize: '18px',
        },
        target: {
          saveAs: 'draft.html',
        },
        editor: {
          content: 'Saved from remote PHP converter.php',
        },
      });

      const saved = await runConverterRoute('save', tempRoot, {
        sourcePath: 'draft.txt',
        conversion,
      });

      const stored = fs.readFileSync(path.join(tempRoot, 'draft.html'), 'utf8');
      expect(saved.ok).toBe(true);
      expect(saved.path).toBe('draft.html');
      expect(stored).toContain('Saved from remote PHP converter.php');
      expect(stored).toContain('PHP used: yes');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('pageoff local converter.php endpoint is allowed for remote converter requests on localhost', async () => {
    const tempRoot = path.join(POFF_DIR, `pageoff-remote-allow-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    const draftPath = path.join(tempRoot, 'draft.txt');
    fs.writeFileSync(draftPath, 'Allow localhost route');

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          path: 'draft.txt',
          name: 'draft.txt',
          mimeType: 'text/plain',
          extension: 'txt',
          size: fs.statSync(draftPath).size,
        },
        converter: {
          id: 'converter/convert-pageoff-remote-text',
          format: 'html',
          node: {
            id: 'pageoff-local-php',
            endpoint: 'http://localhost/worktypes/templates/converter/convert-pageoff-remote-text/converter.php',
          },
        },
        target: {
          saveAs: 'draft.html',
        },
        editor: {
          content: 'Trusted localhost route',
        },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'draft.html',
              mimeType: 'text/html',
              kind: 'text',
              size: 16,
              bodyBase64: Buffer.from('mock remote body', 'utf8').toString('base64'),
            },
          },
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.name).toBe('draft.html');
      expect(Buffer.from(result.output.bodyBase64, 'base64').toString('utf8')).toBe('mock remote body');
      expect(result.generatedBy.endpoint).toContain('worktypes/templates/converter/convert-pageoff-remote-text/converter.php');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('pageoff local converter.php endpoint stays allowed on localhost when the app is mounted under a path prefix', async () => {
    const tempRoot = path.join(POFF_DIR, `pageoff-remote-prefix-allow-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    const draftPath = path.join(tempRoot, 'draft.txt');
    fs.writeFileSync(draftPath, 'Allow localhost prefixed route');

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          path: 'draft.txt',
          name: 'draft.txt',
          mimeType: 'text/plain',
          extension: 'txt',
          size: fs.statSync(draftPath).size,
        },
        converter: {
          id: 'converter/convert-pageoff-remote-text',
          format: 'html',
          node: {
            id: 'pageoff-local-php',
            endpoint: 'http://localhost:8888/MAUSMAUS/worktypes/templates/converter/convert-pageoff-remote-text/converter.php',
          },
        },
        target: {
          saveAs: 'draft.html',
        },
        editor: {
          content: 'Trusted localhost route with prefix',
        },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'draft.html',
              mimeType: 'text/html',
              kind: 'text',
              size: 23,
              bodyBase64: Buffer.from('mock prefixed remote body', 'utf8').toString('base64'),
            },
          },
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.name).toBe('draft.html');
      expect(Buffer.from(result.output.bodyBase64, 'base64').toString('utf8')).toBe('mock prefixed remote body');
      expect(result.generatedBy.endpoint).toContain('/MAUSMAUS/worktypes/templates/converter/convert-pageoff-remote-text/converter.php');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('pageoff remote image converter turns a tiff source into a png body', async () => {
    const tempRoot = path.join(POFF_DIR, `pageoff-remote-image-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(tempRoot, 'scan-001.tiff'));

    try {
      const sourcePath = path.join(tempRoot, 'scan-001.tiff');
      const result = await runPageoffRemoteImageConverter({
        source: {
          path: 'scan-001.tiff',
          name: 'scan-001.tiff',
          mimeType: 'image/tiff',
          bodyBase64: fs.readFileSync(sourcePath).toString('base64'),
        },
        converter: {
          id: 'converter/convert-pageoff-remote-image',
          format: 'png',
          quality: 'default',
        },
        target: {
          saveAs: 'scan-001.png',
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.mimeType).toBe('image/png');
      expect(Buffer.from(result.output.bodyBase64, 'base64').subarray(0, 8).toString('binary')).toBe('\x89PNG\r\n\x1a\n');

      const saved = await runConverterRoute('save', tempRoot, {
        sourcePath: 'scan-001.tiff',
        conversion: result,
      });

      expect(saved.ok).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'scan-001.png'))).toBe(true);
      expect(fs.readFileSync(path.join(tempRoot, 'scan-001.png')).subarray(0, 8).toString('binary')).toBe('\x89PNG\r\n\x1a\n');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('pageoff remote image converter can fetch the source from a URL when base64 is missing', async () => {
    const tempRoot = path.join(POFF_DIR, `pageoff-remote-image-url-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.copyFileSync(path.join(ROOT, 'archive/scan-001.tiff'), path.join(tempRoot, 'scan-001.tiff'));

    try {
      const sourcePath = path.join(tempRoot, 'scan-001.tiff');
      const result = await runPageoffRemoteImageConverter({
        source: {
          path: 'scan-001.tiff',
          name: 'scan-001.tiff',
          mimeType: 'image/tiff',
          srcUrl: pathToFileURL(sourcePath).href,
        },
        converter: {
          id: 'converter/convert-pageoff-remote-image',
          format: 'png',
          quality: 'default',
        },
        target: {
          saveAs: 'scan-001.png',
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.mimeType).toBe('image/png');
      expect(Buffer.from(result.output.bodyBase64, 'base64').subarray(0, 8).toString('binary')).toBe('\x89PNG\r\n\x1a\n');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('offers the text editor converter for application/rtf as plain text', async () => {
    const result = await runConverterRoute('available', POFF_DIR, {
      mimeType: 'application/rtf',
      kind: 'text',
      extension: 'rtf',
    });

    expect(result).toEqual(expect.arrayContaining([
      expect.objectContaining({
        id: 'converter/convert-text-editor',
        name: 'convert-text-editor',
        label: 'Convert Text Editor',
      }),
    ]));
  });

  test('text editor converter can save edited html output for a text file', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-text-editor-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempRoot, 'draft.txt'), 'Original text');

    try {
      const result = await runTextEditorRemoteConverter({
        source: {
          name: 'draft.txt',
          path: 'draft.txt',
          mimeType: 'text/plain',
        },
        converter: {
          id: 'converter/convert-text-editor',
          format: 'html',
          quality: 'default',
          saveMode: 'new-hidden-work',
        },
        target: {
          folder: '',
          saveAs: 'draft.html',
          mode: 'new-hidden-work',
        },
        editor: {
          content: 'Edited text from converter',
        },
      });

      expect(result).toEqual(expect.objectContaining({
        ok: true,
        type: 'converted-work',
      }));
      expect(result.output).toEqual(expect.objectContaining({
        name: 'draft.html',
        mimeType: 'text/html',
        kind: 'text',
      }));
      const html = Buffer.from(result.output.bodyBase64, 'base64').toString('utf8');
      expect(html).toContain('Edited text from converter');
      expect(html).toContain('Convert Text Editor');
      expect(result.generatedBy.usedPhp).toBe(true);

      const saved = await runConverterRoute('save', tempRoot, {
        sourcePath: 'draft.txt',
        conversion: result,
      });
      const stored = fs.readFileSync(path.join(tempRoot, 'draft.html'), 'utf8');
      expect(saved.ok).toBe(true);
      expect(stored).toContain('Edited text from converter');
      expect(stored).toContain('PHP used: yes');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('text editor converter can save edited source content back into the source file', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-text-editor-source-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempRoot, 'bak.htaccess'), "Header set X-Test original\n");

    try {
      const result = await runTextEditorRemoteConverter({
        source: {
          name: 'bak.htaccess',
          path: 'bak.htaccess',
          mimeType: 'text/plain',
        },
        converter: {
          id: 'converter/convert-text-editor',
          format: 'source',
          quality: 'default',
          saveMode: 'replace-source-file',
        },
        target: {
          folder: '',
          saveAs: 'bak.htaccess',
          mode: 'replace-source-file',
        },
        editor: {
          content: "Header set X-Test updated\n",
        },
      });

      const saved = await runConverterRoute('save', tempRoot, {
        sourcePath: 'bak.htaccess',
        conversion: {
          ...result,
          target: {
            mode: 'replace-source-file',
          },
        },
      });

      expect(saved).toEqual(expect.objectContaining({
        ok: true,
        type: 'saved-source-file',
        path: 'bak.htaccess',
      }));
      expect(fs.readFileSync(path.join(tempRoot, 'bak.htaccess'), 'utf8')).toBe("Header set X-Test updated\n");
      expect(fs.existsSync(path.join(tempRoot, 'poff.config.json'))).toBe(false);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('text editor converter localhost converter.php endpoint is allowed for remote converter requests', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-text-editor-allow-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    const draftPath = path.join(tempRoot, 'draft.txt');
    fs.writeFileSync(draftPath, 'Original text');

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          name: 'draft.txt',
          path: 'draft.txt',
          mimeType: 'text/plain',
          extension: 'txt',
          size: fs.statSync(draftPath).size,
        },
        converter: {
          id: 'converter/convert-text-editor',
          format: 'source',
          quality: 'default',
          node: {
            id: 'text-editor-local-php',
            endpoint: 'http://localhost/worktypes/templates/converter/convert-text-editor/converter.php',
          },
        },
        target: {
          folder: '',
          saveAs: 'draft.txt',
          mode: 'new-hidden-work',
        },
        editor: {
          content: 'Edited plain text',
        },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'draft.txt',
              mimeType: 'text/plain',
              kind: 'text',
              size: 17,
              bodyBase64: Buffer.from('Edited plain text', 'utf8').toString('base64'),
            },
          },
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.mimeType).toBe('text/plain');
      expect(Buffer.from(result.output.bodyBase64, 'base64').toString('utf8')).toBe('Edited plain text');
      expect(result.generatedBy.endpoint).toContain('worktypes/templates/converter/convert-text-editor/converter.php');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('text editor converter localhost converter.php endpoint stays allowed when the app is mounted under a path prefix', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-text-editor-prefix-allow-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    const draftPath = path.join(tempRoot, 'draft.txt');
    fs.writeFileSync(draftPath, 'Original text');

    try {
      const result = await runConverterRoute('convert', tempRoot, {
        source: {
          name: 'draft.txt',
          path: 'draft.txt',
          mimeType: 'text/plain',
          extension: 'txt',
          size: fs.statSync(draftPath).size,
        },
        converter: {
          id: 'converter/convert-text-editor',
          format: 'source',
          quality: 'default',
          node: {
            id: 'text-editor-local-php',
            endpoint: 'http://localhost:8888/MAUSMAUS/worktypes/templates/converter/convert-text-editor/converter.php',
          },
        },
        target: {
          folder: '',
          saveAs: 'draft.txt',
          mode: 'new-hidden-work',
        },
        editor: {
          content: 'Edited plain text with prefix',
        },
        mockHttpPost: {
          ok: true,
          status: 200,
          body: {
            ok: true,
            output: {
              name: 'draft.txt',
              mimeType: 'text/plain',
              kind: 'text',
              size: 29,
              bodyBase64: Buffer.from('Edited plain text with prefix', 'utf8').toString('base64'),
            },
          },
        },
      });

      expect(result.ok).toBe(true);
      expect(result.output.mimeType).toBe('text/plain');
      expect(Buffer.from(result.output.bodyBase64, 'base64').toString('utf8')).toBe('Edited plain text with prefix');
      expect(result.generatedBy.endpoint).toContain('/MAUSMAUS/worktypes/templates/converter/convert-text-editor/converter.php');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('file viewer switches to converter template preview when a converter is selected', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-preview-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, 'hello.txt'), 'Hello world');
    createConverterApp(tempRoot, 'convert-text', {
      label: 'Convert Text',
      accepts: ['text/plain', 'text/*'],
      outputs: ['text/plain'],
      engine: 'text-copy',
      defaults: {
        format: 'txt',
        quality: 'preview',
        saveMode: 'new-hidden-work',
        hiddenByDefault: true,
      },
      ui: {
        format: { type: 'select', label: 'Output format', options: ['txt'] },
        quality: { type: 'select', label: 'Quality', options: ['preview', 'default'] },
      },
    });
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text', '.layout', 'template.hbs'),
      '<section class="converter-preview">{{> work}}</section>'
    );
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text', '.layout', 'work.hbs'),
      '<div class="converter-preview__panel">{{converter.label}}|{{source.name}}|{{target.saveAs}}|{{status.state}}</div>'
    );

    try {
      const rendered = await runViewerWithQuery('hello.txt', tempRoot, false, {
        converter_preview: '1',
        converter_id: 'converter/convert-text',
        converter_format: 'txt',
        converter_quality: 'preview',
        converter_save_mode: 'new-hidden-work',
      });

      expect(rendered).toContain('converter-preview');
      expect(rendered).toContain('Convert Text');
      expect(rendered).toContain('hello.txt');
      expect(rendered).toContain('Convert Text|hello.txt|hello.txt|ready');
      expect(rendered).not.toContain('<pre class=');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('text editor converter preview receives source file content', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-editor-preview-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, 'draft.txt'), 'Hello from source file');
    createConverterApp(tempRoot, 'convert-text-editor', {
      label: 'Convert Text Editor',
      accepts: ['text/plain', 'text/*'],
      outputs: ['text/html', 'text/plain', 'text/markdown'],
      engine: 'text-copy',
      defaults: {
        format: 'html',
        quality: 'default',
        saveMode: 'new-hidden-work',
        hiddenByDefault: true,
      },
      ui: {
        format: { type: 'select', label: 'Output format', options: ['html', 'txt', 'md'] },
        quality: { type: 'select', label: 'Quality', options: ['default'] },
      },
    });
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text-editor', '.layout', 'template.hbs'),
      '<section class="poff-text-editor-converter">{{> work}}</section>'
    );
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text-editor', '.layout', 'work.hbs'),
      '<h2>{{converter.label}}</h2><textarea data-poff-text-editor-fallback>{{textContent}}</textarea>'
    );

    try {
      const rendered = await runViewerWithQuery('draft.txt', tempRoot, false, {
        converter_preview: '1',
        converter_id: 'converter/convert-text-editor',
        converter_format: 'html',
        converter_quality: 'default',
        converter_save_mode: 'new-hidden-work',
      });

      expect(rendered).toContain('poff-text-editor-converter');
      expect(rendered).toContain('Convert Text Editor');
      expect(rendered).toContain('Hello from source file');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('text editor converter preview exposes the selected output format to the template', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-editor-format-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, 'draft.json'), '{"hello":true}');
    createConverterApp(tempRoot, 'convert-text-editor', {
      label: 'Convert Text Editor',
      accepts: ['text/plain', 'text/*', 'application/json'],
      outputs: ['text/html', 'application/json', 'text/plain', 'text/markdown'],
      engine: 'text-copy',
      defaults: {
        format: 'html',
        quality: 'default',
        saveMode: 'new-hidden-work',
        hiddenByDefault: true,
      },
      ui: {
        format: { type: 'select', label: 'Output format', options: ['source', 'json', 'html', 'txt', 'md'] },
        quality: { type: 'select', label: 'Quality', options: ['default'] },
      },
    });
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text-editor', '.layout', 'template.hbs'),
      fs.readFileSync(path.join(ROOT, 'src/includes/worktypes/templates/converter/convert-text-editor/template.hbs'), 'utf8')
    );
    fs.writeFileSync(
      path.join(tempRoot, 'poff', 'converters', 'convert-text-editor', '.layout', 'work.hbs'),
      fs.readFileSync(path.join(ROOT, 'src/includes/worktypes/templates/converter/convert-text-editor/work.hbs'), 'utf8')
    );

    try {
      const rendered = await runViewerWithQuery('draft.json', tempRoot, false, {
        converter_preview: '1',
        converter_id: 'converter/convert-text-editor',
        converter_format: 'html',
        converter_quality: 'default',
        converter_save_mode: 'new-hidden-work',
      });

      expect(rendered).toContain('poff-text-editor-converter');
      expect(rendered).toContain('data-converter-format="html"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('creates a converter app from the default grain', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-grain-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });

    try {
      const result = await runConverterRoute('ensure-default', tempRoot, {
        name: 'convert-image',
      });

      expect(result.ok).toBe(true);
      expect(result.folder).toBe('poff/converters/convert-image');
      expect(result.definition).toEqual(expect.objectContaining({
        id: 'converter/convert-image',
        name: 'convert-image',
        folder: 'poff/converters/convert-image',
      }));
      expect(fs.existsSync(path.join(tempRoot, 'poff/converters/convert-image/converter.json'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'poff/converters/convert-image/.layout/template.hbs'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'poff/converters/convert-image/.layout/work.hbs'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'poff/converters/convert-image/.layout/external.hbs'))).toBe(true);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('create-converter route creates a folder-backed converter app from the starter grain', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-create-route-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');

    try {
      const result = await runConverterRoute('create-converter', tempRoot, {
        path: 'hello.txt',
        name: 'convert-mov-audio',
      });

      expect(result).toEqual(expect.objectContaining({
        route: 'create-converter',
        ok: true,
        folder: 'converters/convert-mov-audio',
        targetType: 'converter',
        promptPath: 'converters/convert-mov-audio',
      }));
      expect(result.definition).toEqual(expect.objectContaining({
        id: 'converter/convert-mov-audio',
        name: 'convert-mov-audio',
        folder: 'converters/convert-mov-audio',
        path: 'converters/convert-mov-audio',
        viewerHref: '?view=1&path=converters%2Fconvert-mov-audio',
      }));
      expect(fs.existsSync(path.join(tempRoot, 'converters/convert-mov-audio/converter.json'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'converters/convert-mov-audio/poff.config.json'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'converters/convert-mov-audio/.layout/template.hbs'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'converters/convert-mov-audio/.layout/work.hbs'))).toBe(true);
      expect(fs.existsSync(path.join(tempRoot, 'converters/convert-mov-audio/.layout/external.hbs'))).toBe(true);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('viewer bootstrap dispatches mcp routes as json instead of html', async () => {
    const tempRoot = path.join(POFF_DIR, `viewer-mcp-route-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');

    try {
      const result = await runViewerMcpRoute('create-converter', tempRoot, {
        path: 'hello.txt',
        name: 'convert-live-entry',
      });

      expect(result).toEqual(expect.objectContaining({
        route: 'create-converter',
        ok: true,
        folder: 'converters/convert-live-entry',
      }));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('directly opening a created converter folder renders it as a converter work', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-viewer-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');

    try {
      const created = await runConverterRoute('create-converter', tempRoot, {
        path: 'hello.txt',
        name: 'convert-mov-audio',
      });

      expect(created.ok).toBe(true);
      const output = await runViewer('converters/convert-mov-audio', tempRoot);
      expect(output).toContain('data-viewer-type="converter"');
      expect(output).toContain('poff-converter-work');
      expect(output).toContain('Convert Mov Audio');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('opening converter.json renders the parent converter work preview', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-definition-viewer-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');

    try {
      const created = await runConverterRoute('create-converter', tempRoot, {
        path: 'hello.txt',
        name: 'convert-text-preview',
      });

      expect(created.ok).toBe(true);
      const output = await runViewer('converters/convert-text-preview/converter.json', tempRoot, true);
      expect(output).toContain('data-viewer-type="converter"');
      expect(output).toContain('poff-converter-work');
      expect(output).toContain('Convert Text Preview');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('converter prompt targets converter folders and references the default grain', async () => {
    const tempRoot = path.join(POFF_DIR, `converter-prompt-${Date.now()}`);
    fs.mkdirSync(tempRoot, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
    createConverterApp(tempRoot, 'convert-image', { label: 'Convert Image' });

    try {
      const result = await runConverterRoute('converter-prompt', tempRoot, {
        path: 'poff/converters/convert-image',
      });

      expect(result.route).toBe('converter-prompt');
      expect(result.targetType).toBe('converter');
      expect(result.grain).toEqual(expect.objectContaining({
        folder: 'src/includes/worktypes/templates/converter/default',
        templateFolder: 'src/includes/worktypes/templates/converter/default',
      }));
      expect(result.definition).toEqual(expect.objectContaining({
        id: 'converter/convert-image',
        folder: 'poff/converters/convert-image',
      }));
      expect(result.instruction).toContain('Do not render the final generated work directly.');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('normalizes legacy converter IDs to folder-backed IDs with format options', async () => {
    const webp = await runConverterRoute('normalize', POFF_DIR, {
      id: 'imagemagick-image-webp',
      quality: 'preview',
    });
    const jpeg = await runConverterRoute('normalize', POFF_DIR, {
      id: 'imagemagick-image-jpeg',
    });
    const remote = await runConverterRoute('normalize', POFF_DIR, {
      id: 'remote-node-converter',
    });

    expect(webp).toEqual(expect.objectContaining({
      id: 'converter/convert-imagemagick',
      name: 'convert-imagemagick',
      format: 'webp',
      quality: 'preview',
    }));
    expect(jpeg).toEqual(expect.objectContaining({
      id: 'converter/convert-imagemagick',
      format: 'jpeg',
    }));
    expect(remote).toEqual(expect.objectContaining({
      id: 'converter/convert-remote-node',
      name: 'convert-remote-node',
    }));
  });

  test('unsupported MIME returns no enabled local image converter', async () => {
    const result = await runConverterRoute('available', POFF_DIR, {
      mimeType: 'application/x-poff-test',
      kind: 'other',
      extension: 'bin',
    });

    expect(result.filter((item) => item.enabled).length).toBe(0);
  });

  test('save-converted-work stores hidden generated metadata beside source', async () => {
    const tempDir = path.join(POFF_DIR, `converter-save-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, 'scan-001.tiff'), 'fake tiff');

    try {
      const result = await runConverterRoute('save', tempDir, {
        sourcePath: 'scan-001.tiff',
        conversion: {
          ok: true,
          type: 'converted-work',
          source: {
            path: 'scan-001.tiff',
            mimeType: 'image/tiff',
          },
          output: {
            name: 'scan-001.webp',
            mimeType: 'image/webp',
            kind: 'image',
            size: 4,
            bodyBase64: Buffer.from('webp').toString('base64'),
          },
          generatedBy: {
            type: 'converter',
            name: 'convert-imagemagick',
            id: 'converter/convert-imagemagick',
            folder: 'poff/converters/convert-imagemagick',
            node: 'local',
            engine: 'imagemagick',
            quality: 'default',
            format: 'webp',
            convertedAt: new Date().toISOString(),
          },
        },
      });

      expect(result.ok).toBe(true);
      expect(fs.readFileSync(path.join(tempDir, 'scan-001.webp'), 'utf8')).toBe('webp');
      const folderConfig = JSON.parse(fs.readFileSync(path.join(tempDir, 'poff.config.json'), 'utf8'));
      expect(folderConfig.tree).toEqual(expect.arrayContaining([
        expect.objectContaining({
          name: 'scan-001.webp',
          visible: false,
          generated: true,
          sourceWork: 'scan-001.tiff',
          generatedBy: expect.objectContaining({
            name: 'convert-imagemagick',
            id: 'converter/convert-imagemagick',
            folder: 'poff/converters/convert-imagemagick',
          }),
          external: expect.objectContaining({
            relation: 'converted-from',
          }),
        }),
      ]));
      const fileConfig = JSON.parse(fs.readFileSync(path.join(tempDir, '.works', 'scan-001.webp.config.json'), 'utf8'));
      expect(fileConfig.work.template).toBe('external');
      expect(fileConfig.external.sourceWork.path).toBe('scan-001.tiff');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test.each(['new-hidden-work', 'replace-generated-work'])(
    'save mode %s overwrites a same-name target',
    async (mode) => {
      const tempDir = path.join(POFF_DIR, `converter-save-mode-${Date.now()}`);
      fs.mkdirSync(tempDir, { recursive: true });
      fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
      fs.writeFileSync(path.join(tempDir, 'source.txt'), 'source');
      fs.writeFileSync(path.join(tempDir, 'converted.png'), 'existing content');

      try {
        const result = await runConverterRoute('save', tempDir, {
          sourcePath: 'source.txt',
          conversion: {
            ok: true,
            source: { path: 'source.txt', mimeType: 'text/plain' },
            output: {
              name: 'converted.png',
              mimeType: 'image/png',
              kind: 'image',
              bodyBase64: Buffer.from('new conversion').toString('base64'),
            },
            target: { mode },
          },
        });

        expect(result.ok).toBe(true);
        expect(fs.readFileSync(path.join(tempDir, 'converted.png'), 'utf8')).toBe('new conversion');
      } finally {
        fs.rmSync(tempDir, { recursive: true, force: true });
      }
    }
  );

  test('downloadUrl rejects HTTP redirects without requesting the destination', async () => {
    const tempDir = path.join(POFF_DIR, `converter-download-redirect-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, 'source.txt'), 'source');
    const requests = [];
    const server = http.createServer((request, response) => {
      requests.push(request.url);
      if (request.url === '/redirect') {
        response.writeHead(302, { Location: '/payload' });
        response.end();
        return;
      }
      response.writeHead(200, { 'Content-Type': 'text/plain' });
      response.end('redirected conversion');
    });

    try {
      await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
      const { port } = server.address();
      const result = await runConverterRoute('save', tempDir, {
        sourcePath: 'source.txt',
        conversion: {
          ok: true,
          source: { path: 'source.txt', mimeType: 'text/plain' },
          output: {
            name: 'redirected.txt',
            mimeType: 'text/plain',
            kind: 'text',
            downloadUrl: `http://127.0.0.1:${port}/redirect`,
          },
          target: { mode: 'new-hidden-work' },
        },
      }, { POFF_ALLOW_PRIVATE_CONVERTER_HOSTS: '1' });

      expect(result).toEqual(expect.objectContaining({
        ok: false,
        error: 'Converted download failed.',
      }));
      expect(requests).toEqual(['/redirect']);
      expect(fs.existsSync(path.join(tempDir, 'redirected.txt'))).toBe(false);
    } finally {
      await new Promise((resolve) => server.close(resolve));
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('remote converter rejects private hosts', async () => {
    const result = await runConverterRoute('remote-security', POFF_DIR, {
      url: 'http://127.0.0.1/index.php?mcp=1&route=convert',
    });

    expect(result.error).toBe('Converter endpoint host is not allowed.');
  });
});

describe('Worktype HBS renderer', () => {
  test('keeps worktype catalog metadata from per-kind definitions', async () => {
    const image = JSON.parse(await runWorktype('definition', 'image'));
    expect(image.mimes).toEqual(['image/*', 'image/.*']);

    const pdf = JSON.parse(await runWorktype('definition', 'pdf'));
    expect(pdf.mimes).toEqual(['application/pdf', 'application/x-pdf']);
    expect(pdf.viewer).toBe('embed');

    const htaccess = JSON.parse(await runWorktype('definition', 'htaccess'));
    expect(htaccess).toEqual(expect.objectContaining({
      suffixes: ['.htaccess'],
      label: '.htaccess',
      preferred: true,
      description: 'Apache access policy and iframe embedding rules.',
    }));

    const bundledMetadata = JSON.parse(await runWorktype('bundle-metadata', ''));
    expect(bundledMetadata.image).toEqual({ mimes: ['image/*', 'image/.*'] });
    expect(bundledMetadata.htaccess).toEqual(expect.objectContaining({
      suffixes: ['.htaccess'],
      label: '.htaccess',
      preferred: true,
    }));

    const imageCatalog = JSON.parse(await runWorktype('catalog', 'image', {
      mime: 'image/jpeg', fileName: 'photo.unknown', subjectType: 'file',
    }));
    const imageChoice = imageCatalog.choices.find(({ value }) => value === 'image');
    expect(imageChoice.mimes).toEqual(['image/*', 'image/.*']);
    expect(imageCatalog.selected).toBe('image');

    const htaccessCatalog = JSON.parse(await runWorktype('catalog', 'htaccess', {
      mime: 'text/plain', fileName: '.htaccess', subjectType: 'file',
    }));
    expect(htaccessCatalog.choices.find(({ value }) => value === 'htaccess').extensions).toEqual(['.htaccess']);
  });

  test('keeps extensibility definitions and assets wired into the single-file builder', () => {
    const builder = fs.readFileSync(path.join(ROOT, 'build/build.php'), 'utf8');

    for (const sourcePart of [
      '/includes/MediaType.php',
      '/includes/Converter.php',
      '/includes/Worktype/Definitions.php',
      '/includes/Worktype/Render.php',
      '/includes/worktypes/worktypes.php',
      '/includes/worktypes/*.worktype.php',
      '/includes/worktypes/templates/*.hbs',
    ]) {
      expect(builder).toContain(sourcePart);
    }
    expect(builder).toContain('Worktype::setEmbedded(');
    expect(builder).toContain('Worktype::setEmbeddedTemplates(');
    expect(builder).toContain('Worktype::setEmbeddedLayoutAssets(');
    expect(builder).toContain("'/includes/worktypes/templates/converter'");
    expect(builder).toContain("'style.css', 'script.js'");
    expect(builder).toContain("$bundle['metadata']");
    expect(builder).toContain("['mimes', 'mimeTypes', 'extensions', 'suffixes', 'label', 'preferred', 'description']");
  });

  test('builds a syntax-valid isolated bundle with Worktype templates and layout assets embedded', async () => {
    const outputDir = path.join(ROOT, 'tests', `.tmp-build-parity-${process.pid}-${Date.now()}`);

    try {
      const buildResult = await runIsolatedPhpBuild(outputDir);
      const bundlePath = path.join(outputDir, 'index.php');
      expect(buildResult).toContain('Build completed successfully (development)');
      expect(fs.existsSync(bundlePath)).toBe(true);
      expect(await runPhpLint(bundlePath)).toContain('No syntax errors detected');

      const bundle = fs.readFileSync(bundlePath, 'utf8');
      for (const embeddedContent of [
        'Worktype::setEmbedded(',
        'Worktype::setEmbeddedTemplates(',
        'Worktype::setEmbeddedLayoutAssets(',
        'newWorkId',
        'reassignWorkIdForCopy',
        'class PoffNodeIdentity',
        'image/*',
        'max-h-screen max-w-full object-contain',
        '--poff-shell-bg',
      ]) {
        if (!bundle.includes(embeddedContent)) {
          throw new Error(`isolated bundle is missing embedded content: ${embeddedContent}`);
        }
      }
    } finally {
      fs.rmSync(outputDir, { recursive: true, force: true });
    }
  });

  test('copies converter definition and template sidecars into an isolated runtime folder', async () => {
    const targetDir = path.join(ROOT, 'tests', `.tmp-converter-grains-${process.pid}-${Date.now()}`);

    try {
      expect(await runConverterGrainMirror(targetDir)).toBe('ok');
      for (const relativePath of [
        'default/converter.json',
        'default/template.hbs',
        'convert-pageoff-remote-image/converter.php',
        'convert-pageoff-remote-image/style.css',
      ]) {
        expect(fs.existsSync(path.join(targetDir, relativePath))).toBe(true);
      }
    } finally {
      fs.rmSync(targetDir, { recursive: true, force: true });
    }
  });

  test('detects resource MIME and kind without selecting renderer or converter', async () => {
    const detected = JSON.parse(await runWorktype('detect', '', { fileName: 'photo.jpg' }));
    expect(detected).toEqual({ kind: 'image', mime: 'image/jpeg' });
  });

  test('selects templateMap entries by exact MIME, wildcard, kind, then extension', async () => {
    const cases = [
      [{ 'image/jpeg': 'video', 'image/*': 'audio', image: 'text', jpg: 'pdf' }, 'video'],
      [{ 'image/*': 'audio', 'image/.*': 'video', image: 'text', jpg: 'pdf' }, 'audio'],
      [{ 'image/.*': 'video', image: 'text', jpg: 'pdf' }, 'video'],
      [{ image: 'text', jpg: 'pdf' }, 'text'],
      [{ jpg: 'pdf' }, 'pdf'],
    ];

    for (const [templateMap, expected] of cases) {
      const result = JSON.parse(await runWorktype('select', 'image', {
        mime: 'image/jpeg', fileName: 'photo.jpg', templateMap,
      }));
      expect(result.template).toBe(expected);
      expect(result.source).toBe('templateMap');
    }
  });

  test('explicit work template overrides inherited and local templateMap selection', async () => {
    const tempRoot = path.join(POFF_DIR, `worktype-inheritance-${Date.now()}`);
    const childDir = path.join(tempRoot, 'section');
    fs.mkdirSync(childDir, { recursive: true });
    fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
      work: { templateMap: { 'image/*': 'audio', jpg: 'pdf' } },
    }));
    fs.writeFileSync(path.join(childDir, 'poff.config.json'), JSON.stringify({
      work: { templateMap: { 'image/jpeg': 'video' } },
    }));

    try {
      const inherited = JSON.parse(await runLayoutFilesystem('resolve-work-template', childDir, '', {
        work: {}, kind: 'image', mime: 'image/jpeg', fileName: 'photo.jpg',
      }));
      expect(inherited.template).toBe('video');
      expect(inherited.templateMap).toEqual({ 'image/*': 'audio', 'image/jpeg': 'video', jpg: 'pdf' });

      const explicit = JSON.parse(await runLayoutFilesystem('resolve-work-template', childDir, '', {
        work: { template: 'text' }, kind: 'image', mime: 'image/jpeg', fileName: 'photo.jpg',
      }));
      expect(explicit).toEqual(expect.objectContaining({ template: 'text', source: 'explicit' }));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

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

  test('.htaccess is included in the default tree as a hidden entry and shown in edit mode', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-visibility-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, '.htaccess'), 'RewriteEngine On');
    fs.writeFileSync(path.join(tempDir, 'visible.txt'), 'visible');

    try {
      const ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', tempDir));
      expect(ensured.tree.map((item) => item.name)).toContain('.htaccess');
      expect(ensured.tree.find((item) => item.name === '.htaccess')).toMatchObject({
        name: '.htaccess',
        visible: false,
      });
      expect(ensured.tree.map((item) => item.name)).toContain('visible.txt');

      const normalNav = await runNav('', tempDir);
      const editNav = await runNav('', tempDir, true);

      expect(normalNav).not.toContain('data-file=".htaccess"');
      expect(normalNav).not.toContain('Create embed policy');
      expect(editNav).toContain('data-file=".htaccess"');
      expect(editNav).toContain('data-hidden="true"');
      expect(editNav).toContain('nav-link-hidden');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('creates a default .htaccess file with iframe policy boilerplate', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-create-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });

    try {
      const output = await runBlankFile(tempDir, '.htaccess');
      const result = JSON.parse(output);
      const filePath = path.join(tempDir, '.htaccess');

      expect(result.errors).toEqual([]);
      expect(result.stored).toEqual([
        expect.objectContaining({ name: '.htaccess', path: '.htaccess' }),
      ]);
      expect(fs.existsSync(filePath)).toBe(true);
      const contents = fs.readFileSync(filePath, 'utf8');
      expect(contents).toContain('Poff iframe policy');
      expect(contents).toContain('Header always unset X-Frame-Options');
      expect(contents).toContain('Content-Security-Policy');
      expect(contents).toContain('frame-ancestors *;');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('saving an external link creates the default permissive folder htaccess policy', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-external-link-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });

    try {
      const result = await runCreateLink(tempDir, 'Client Work', 'https://client.example/path/to/work');

      expect(result.result.errors).toEqual([]);
      expect(result.htaccessExists).toBe(true);
      expect(result.htaccess).toContain('Header always unset X-Frame-Options');
      expect(result.htaccess).toContain('frame-ancestors *;');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('saving multiple external links keeps the default permissive htaccess policy unchanged', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-external-link-dedupe-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });

    try {
      await runCreateLink(tempDir, 'Client Work 1', 'https://client.example/one');
      const result = await runCreateLink(tempDir, 'Client Work 2', 'https://client.example/two');

      expect(result.result.errors).toEqual([]);
      expect(result.htaccess).toContain('frame-ancestors *;');
      expect(result.htaccess).not.toContain('https://client.example');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('saving an external link in a nested folder updates htaccess in the main root folder', async () => {
    const result = await runPhpJson('php_upload_link_nested_root.php');

    expect(result.response.errors).toEqual([]);
    expect(result.response.stored[0]).toEqual(expect.objectContaining({
      name: 'client-link',
      linkUrl: 'https://client.example/path/to/work',
    }));
    expect(result.rootHtaccess).toContain('Header always unset X-Frame-Options');
    expect(result.rootHtaccess).toContain('frame-ancestors *;');
    expect(result.nestedHtaccessExists).toBe(false);
    expect(result.nestedConfig.tree).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'client-link',
        type: 'link',
        linkUrl: 'https://client.example/path/to/work',
      }),
    ]));
  });

  test('.poff-auth.php stays hidden in navigation and direct viewer access', async () => {
    const normalNav = await runNav('', POFF_DIR);
    const editNav = await runNav('', POFF_DIR, true);
    const viewerOutput = await runViewer('.poff-auth.php', POFF_DIR);

    expect(normalNav).not.toContain('.poff-auth.php');
    expect(editNav).not.toContain('.poff-auth.php');
    expect(viewerOutput).toContain('Path not found.');
  });

  test('.htaccess renders through the CMS viewer instead of Apache', async () => {
    const tempDir = path.join(POFF_DIR, `htaccess-viewer-${Date.now()}`);
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.htaccess'), 'RewriteEngine On\n', 'utf8');

    try {
      const viewerOutput = await runViewer('.htaccess', tempDir);
      expect(viewerOutput).toContain('data-viewer-type="htaccess"');
      expect(viewerOutput).not.toContain('Forbidden');
      expect(viewerOutput).not.toContain('Path not found.');
      expect(viewerOutput).not.toContain('Submit external link');
      expect(viewerOutput).not.toContain('data-viewer-type="external"');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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

  test('uses the file name when persisted file title is blank', async () => {
    const tempDir = path.join(POFF_DIR, `blank-viewer-title-${Date.now()}`);
    const fileName = 'public-note.txt';
    fs.mkdirSync(path.join(tempDir, '.works'), { recursive: true });
    fs.writeFileSync(path.join(tempDir, fileName), 'Public note', 'utf8');
    fs.writeFileSync(
      path.join(tempDir, '.works', `${fileName}.config.json`),
      JSON.stringify({ title: '', work: { type: 'text', template: 'text' } }),
      'utf8',
    );

    try {
      const output = await runViewer(fileName, tempDir);
      expect(output).toContain('public-note.txt');
      expect(output).toContain('<title>public-note.txt</title>');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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

  test('switching from collection back to inherit clears stale shared layout state', async () => {
    await runViewerSave(POFF_DIR, 'inherits-default/.layout', {
      layout: {
        name: 'filesystem-layout',
        preset: 'shared',
        source: 'shared',
        sharedName: 'filesystem-layout',
      },
    });

    let ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR));
    expect(ensured.work.layout).toMatchObject({
      preset: 'shared',
      sharedName: 'filesystem-layout',
    });

    await runViewerSave(POFF_DIR, 'inherits-default/.layout', {
      layout: {
        name: 'filesystem-layout',
        preset: 'inherit',
      },
    });

    ensured = JSON.parse(await runLayoutFilesystem('ensure-folder', INHERITED_DEFAULT_DIR));
    expect(ensured.work.layout).toMatchObject({
      name: 'filesystem-layout',
      preset: 'actual',
      storage: 'filesystem',
      directory: 'tests/poff-tests/.layout',
    });
    expect(ensured.work.layout.source || '').toBe('');
    expect(ensured.work.layout.sharedName || '').toBe('');
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

    expect(output).toContain('<title>Folder Preview</title>');
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

  test('default folder cards show thumbnails for browser-readable images', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-folder-image-cards-'));
    const name = `folder-image-cards-${Date.now()}`;
    const folder = path.join(tempRoot, name);
    fs.mkdirSync(folder, { recursive: true });
    fs.copyFileSync(path.join(POFF_DIR, '.layout', 'poff.profile.jpg'), path.join(folder, 'photo.jpg'));

    try {
      const output = await runViewer(name, tempRoot);
      expect(output).toContain('folder-view-card-preview');
      expect(output).toMatch(/<img[^>]+src="[^"]*photo\.jpg"[^>]+loading="lazy"/);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('keeps the single-file index entrypoint out of the content tree', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-single-file-root-'));
    fs.writeFileSync(path.join(tempDir, 'index.php'), '<?php // generated entrypoint');
    fs.writeFileSync(path.join(tempDir, 'public-note.txt'), 'hello');

    try {
      const config = JSON.parse(await runLayoutFilesystem('ensure-folder', tempDir));
      expect(config.tree.map((item) => item.name)).toContain('public-note.txt');
      expect(config.tree.map((item) => item.name)).not.toContain('index.php');
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
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

  test('injects the standalone shell toggle when shell=0 is requested', async () => {
    const rendered = await runLayoutWithQuery({ shell: '0' });

    expect(rendered).toContain('poff-shell-standalone');
    expect(rendered).toContain('id="appShell"');
    expect(rendered).toContain('id="appSidebar"');
    expect(rendered).toContain('id="contentFrame"');
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

  test('extracts direct work content from shell-wrapped remote html instead of returning the outer shell', async () => {
    const rendered = await runResolveRemoteRenderedHtml({
      linkUrl: 'https://remote.example/portfolio',
      baseUrl: 'https://remote.example/portfolio',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: `
        <html>
          <body>
            <script>const currentPoffConfig = {"tree":[]};</script>
            <div id="appShell" class="container">
              <div id="contentFrame">
                <div class="media-shell">
                  <video controls>
                    <source src="/assets/example.mp4">
                    Fallback text
                  </video>
                </div>
              </div>
            </div>
          </body>
        </html>
      `,
    });

    expect(rendered).toContain('<video');
    expect(rendered).toContain('/assets/example.mp4');
    expect(rendered).not.toContain('currentPoffConfig');
    expect(rendered).not.toContain('appShell');
    expect(rendered).not.toContain('contentFrame');
  });

  test('prefers the nested layout main over a generic shell main when extracting remote work html', async () => {
    const rendered = await runResolveRemoteRenderedHtml({
      linkUrl: 'https://remote.example/portfolio',
      baseUrl: 'https://remote.example/portfolio',
    }, {
      ok: true,
      status: 200,
      statusLine: 'HTTP/1.1 200 OK',
      body: `
        <html>
          <body>
            <main class="main-content">
              <script>const currentPoffConfig = {"tree":[]};</script>
              <div id="appShell" class="container">
                <div id="contentFrame">
                  <div class="viewer">
                    <div class="poff-default-layout__main">
                      <article class="remote-snapshot">Remote Snapshot</article>
                    </div>
                  </div>
                </div>
              </div>
            </main>
          </body>
        </html>
      `,
    });

    expect(rendered).toContain('remote-snapshot');
    expect(rendered).toContain('Remote Snapshot');
    expect(rendered).not.toContain('currentPoffConfig');
    expect(rendered).not.toContain('main-content');
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

  test('renders hidden virtual remote link items in edit mode previews', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-hidden-virtual-link-viewer-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'hidden-virtual-link-viewer',
        slug: 'hidden-virtual-link-viewer',
        title: 'Hidden Virtual Link Viewer',
        description: '',
        type: 'folder',
        tree: [
          {
            name: 'rr',
            title: 'rr',
            type: 'link',
            kind: 'link',
            path: 'rr',
            linkUrl: 'https://dominikeggermann.com/',
            visible: false,
            externalSubmission: true,
            approvalStatus: 'pending',
          },
        ],
      }, null, 2));

      const rendered = await runViewerWithMock('rr', tempRoot, true, {
        ok: false,
        status: 502,
        statusLine: 'HTTP/1.1 502 Bad Gateway',
        body: '',
      });

      expect(rendered).toContain('poff-default-layout');
      expect(rendered).toContain('<iframe');
      expect(rendered).toContain('src="https://dominikeggermann.com/"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('virtual remote link items prefer iframe previews over remote inline html when no local file exists', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-virtual-link-preview-priority-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'virtual-link-preview-priority',
        slug: 'virtual-link-preview-priority',
        title: 'Virtual Link Preview Priority',
        description: '',
        type: 'folder',
        tree: [
          {
            name: 'mcmmcmc',
            title: 'mcmmcmc',
            type: 'link',
            kind: 'link',
            path: 'mcmmcmc',
            linkUrl: 'https://dominikeggermann.com/',
            visible: false,
            externalSubmission: true,
            approvalStatus: 'pending',
          },
        ],
      }, null, 2));

      const rendered = await runViewerWithMock('mcmmcmc', tempRoot, true, {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: '<html><body><article class="remote-inline">Inline remote html</article></body></html>',
      });

      expect(rendered).toContain('<iframe');
      expect(rendered).toContain('src="https://dominikeggermann.com/"');
      expect(rendered).not.toContain('remote-inline');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('hidden pending link slugs resolve only for edit-mode lookups', async () => {
    const result = await runPhpJson('php_hidden_pending_resolve.php');

    expect(result.public).toBeNull();
    expect(result.editor).toEqual(expect.objectContaining({
      path: 'XXXXXX',
      type: 'file',
      isFile: true,
      slug: 'xxxxxx',
    }));
  });

  test('resolves root virtual links as editable file targets', async () => {
    const result = await runPhpJson('php_virtual_link_edit_target.php');

    expect(result.resolved).toEqual(expect.objectContaining({
      type: 'file',
      file: 'dominikeggermann.com',
      virtual: true,
    }));
    expect(result.config).toEqual(expect.objectContaining({
      name: 'dominikeggermann.com',
      path: 'dominikeggermann.com',
      type: 'file',
    }));
  });

  test('resolves file slugs as editable file targets', async () => {
    const result = await runPhpJson('php_file_slug_edit_target.php');

    expect(result.resolved).toEqual(expect.objectContaining({
      type: 'file',
      file: 'bak.htaccess',
      virtual: true,
    }));
    expect(result.config).toEqual(expect.objectContaining({
      name: 'bak.htaccess',
      path: 'bak.htaccess',
      type: 'file',
    }));
  });

  test('root edit config loads without descending into nested folders', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-root-edit-config-'));
    try {
      fs.writeFileSync(path.join(tempRoot, '.edit.allow'), '');
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'Root Edit Config',
        title: 'Root Edit Config',
        slug: 'root-edit-config',
        tree: [
          { name: 'nested', type: 'folder' },
          { name: 'visible.txt', type: 'file' },
        ],
      }, null, 2));
      fs.mkdirSync(path.join(tempRoot, 'nested', 'deep'), { recursive: true });
      fs.writeFileSync(path.join(tempRoot, 'visible.txt'), 'hello', 'utf8');
      fs.writeFileSync(path.join(tempRoot, 'nested', 'child.txt'), 'child', 'utf8');
      fs.writeFileSync(path.join(tempRoot, 'nested', 'deep', 'too-deep.txt'), 'deep', 'utf8');

      const result = await runEditRequestBypass(tempRoot, 'config', '');

      expect(result.allowed).toBe(true);
      expect(result.error || null).toBeNull();
      expect(result.config).toEqual(expect.objectContaining({
        title: 'Root Edit Config',
      }));
      expect(result.config.workTemplateMapCatalog?.rows?.[0]?.sampleName).toBe('visible.txt');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('renders hash-route virtual link items through the original external url when iframe fallback is used', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-virtual-hash-link-viewer-'));
    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: 'virtual-hash-link-viewer',
        slug: 'virtual-hash-link-viewer',
        title: 'Virtual Hash Link Viewer',
        description: '',
        type: 'folder',
        tree: [
          {
            name: 'dominikeggermann.com-2',
            title: 'dominikeggermann.com-2',
            type: 'link',
            kind: 'link',
            path: 'dominikeggermann.com-2',
            linkUrl: 'https://dominikeggermann.com/#/joined-test-1762176567798-mp4',
            visible: true,
          },
        ],
      }, null, 2));

      const fileRendered = await runViewerWithMock('dominikeggermann.com-2', tempRoot, false, {
        ok: false,
        status: 502,
        statusLine: 'HTTP/1.1 502 Bad Gateway',
        body: '',
      });

      expect(fileRendered).toContain('poff-default-layout');
      expect(fileRendered).toContain('<iframe');
      expect(fileRendered).toContain('src="https://dominikeggermann.com/#/joined-test-1762176567798-mp4"');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('resolves remote hash routes through ajax and consumes snapshot rendered html', async () => {
    const rendered = await runResolveRemoteRenderedHtmlWithMap({
      linkUrl: 'https://dominikeggermann.com/#/joined-test-1762176567798-mp4',
      baseUrl: 'https://dominikeggermann.com/#/joined-test-1762176567798-mp4',
    }, {
      'https://dominikeggermann.com/?ajax=resolve&slug=joined-test-1762176567798-mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          path: 'ai-movs/joined-test-1762176567798.mp4',
          type: 'file',
          isFile: true,
          slug: 'joined-test-1762176567798-mp4',
        }),
      },
      'https://dominikeggermann.com/?ajax=snapshot&file=ai-movs%2Fjoined-test-1762176567798.mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          kind: 'video',
          renderedHtml: '<video controls><source src="/ai-movs/joined-test-1762176567798.mp4"></video>',
          assetsBaseUrl: 'https://dominikeggermann.com',
          context: {
            baseUrl: 'https://dominikeggermann.com',
            srcUrl: 'https://dominikeggermann.com/ai-movs/joined-test-1762176567798.mp4',
            work: {
              template: 'video',
              type: 'video',
              autoplay: true,
              controls: true,
              loop: false,
              muted: false,
              layout: {
                section: 'work',
                sectionTemplate: '',
              },
            },
          },
        }),
      },
    });

    expect(rendered).toContain('<video');
    expect(rendered).toContain('https://dominikeggermann.com/ai-movs/joined-test-1762176567798.mp4');
    expect(rendered).not.toContain('appShell');
  });

  test('keeps the original hash route when baseUrl has already been normalized', async () => {
    const rendered = await runResolveRemoteRenderedHtmlWithMap({
      linkUrl: 'https://dominikeggermann.com/#/joined-test-1762176567798-mp4',
      baseUrl: 'https://dominikeggermann.com',
    }, {
      'https://dominikeggermann.com/?ajax=resolve&slug=joined-test-1762176567798-mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          path: 'ai-movs/joined-test-1762176567798.mp4',
          type: 'file',
          isFile: true,
          slug: 'joined-test-1762176567798-mp4',
        }),
      },
      'https://dominikeggermann.com/?ajax=snapshot&file=ai-movs%2Fjoined-test-1762176567798.mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          kind: 'video',
          renderedHtml: '<video controls><source src="/ai-movs/joined-test-1762176567798.mp4"></video>',
          assetsBaseUrl: 'https://dominikeggermann.com',
          context: {
            baseUrl: 'https://dominikeggermann.com',
            srcUrl: 'https://dominikeggermann.com/ai-movs/joined-test-1762176567798.mp4',
            work: {
              template: 'video',
              type: 'video',
            },
          },
        }),
      },
    });

    expect(rendered).toContain('<video');
    expect(rendered).toContain('https://dominikeggermann.com/ai-movs/joined-test-1762176567798.mp4');
    expect(rendered).not.toContain('appShell');
  });

  test('does not render remote snapshot context locally when rendered html is missing', async () => {
    const rendered = await runResolveRemoteRenderedHtmlWithMap({
      linkUrl: 'https://dominikeggermann.com/#/context-only-work',
      baseUrl: 'https://dominikeggermann.com/#/context-only-work',
    }, {
      'https://dominikeggermann.com/?ajax=resolve&slug=context-only-work': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          path: 'works/context-only-work.mp4',
          type: 'file',
          isFile: true,
          slug: 'context-only-work',
        }),
      },
      'https://dominikeggermann.com/?ajax=snapshot&file=works%2Fcontext-only-work.mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          kind: 'video',
          context: {
            baseUrl: 'https://dominikeggermann.com',
            srcUrl: 'https://dominikeggermann.com/works/context-only-work.mp4',
            work: {
              template: 'video',
              type: 'video',
            },
          },
        }),
      },
    });

    expect(rendered).toBe('');
  });

  test('prefers remote snapshot rendered html over local type rendering', async () => {
    const rendered = await runResolveRemoteRenderedHtmlWithMap({
      linkUrl: 'https://dominikeggermann.com/#/custom-work',
      baseUrl: 'https://dominikeggermann.com/#/custom-work',
    }, {
      'https://dominikeggermann.com/?ajax=resolve&slug=custom-work': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          path: 'works/custom-work.mp4',
          type: 'file',
          isFile: true,
          slug: 'custom-work',
        }),
      },
      'https://dominikeggermann.com/?ajax=snapshot&file=works%2Fcustom-work.mp4': {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          kind: 'video',
          renderedHtml: '<article class="remote-custom-template"><video onclick="bad()"><source src="/works/custom-work.mp4"></video><script>alert(1)</script></article>',
          assetsBaseUrl: 'https://dominikeggermann.com',
          context: {
            baseUrl: 'https://dominikeggermann.com',
            work: {
              template: 'missing-remote-custom-template',
              type: 'video',
            },
          },
        }),
      },
    });

    expect(rendered).toContain('remote-custom-template');
    expect(rendered).toContain('https://dominikeggermann.com/works/custom-work.mp4');
    expect(rendered).not.toContain('missing-remote-custom-template');
    expect(rendered).not.toContain('<script');
    expect(rendered).not.toContain('onclick=');
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
      expect(renderedHtml).not.toContain('external-work--embedded');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('adds a direct link toolbar and full-width media rules to embedded external renders', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-external-toolbar-'));
    try {
      fs.writeFileSync(
        path.join(tempRoot, 'sample.url'),
        '[InternetShortcut]\nURL=https://dominikeggermann.com/#/joined-test-1762176567798-mp4\n',
      );

      const rendered = await runViewerWithMock('sample.url', tempRoot, false, {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: JSON.stringify({
          resolved: true,
          kind: 'video',
          renderedHtml: '<video controls><source src="/ai-movs/joined-test-1762176567798.mp4"></video>',
          assetsBaseUrl: 'https://dominikeggermann.com',
          context: {
            baseUrl: 'https://dominikeggermann.com',
            srcUrl: 'https://dominikeggermann.com/ai-movs/joined-test-1762176567798.mp4',
            work: {
              template: 'video',
              type: 'video',
            },
          },
        }),
      });

      expect(rendered).toContain('external-work--embedded__toolbar');
      expect(rendered).toContain('External work · live');
      expect(rendered).toContain('Visit source');
      expect(rendered).toContain('href="https://dominikeggermann.com/#/joined-test-1762176567798-mp4"');
      expect(rendered).toContain('width: 100%');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('renders ordinary webloc targets immediately without fetching remote HTML', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-webloc-iframe-'));
    try {
      fs.writeFileSync(
        path.join(tempRoot, 'sample.webloc'),
        '<?xml version="1.0"?><plist><dict><key>URL</key><string>https://example.org/player.php?word=1984&amp;lang=en</string></dict></plist>',
      );

      const rendered = await runViewerWithMock('sample.webloc', tempRoot, false, {
        ok: true,
        status: 200,
        statusLine: 'HTTP/1.1 200 OK',
        body: '<html><body>REMOTE FETCH SHOULD NOT RUN</body></html>',
      });

      expect(rendered).toContain('<iframe');
      expect(rendered).toContain('src="https://example.org/player.php?word&#x3D;1984&amp;lang&#x3D;en"');
      expect(rendered).toContain('allow="autoplay; encrypted-media; picture-in-picture"');
      expect(rendered).toContain('href="https://example.org/player.php?word&#x3D;1984&amp;lang&#x3D;en"');
      expect(rendered).not.toContain('REMOTE FETCH SHOULD NOT RUN');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('exports normalized remote content with absolute viewer and asset links', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-remote-export-'));
    const exportFolder = path.join(tempRoot, 'viewer-folder');
    fs.mkdirSync(path.join(exportFolder, 'nested-child'), { recursive: true });
    fs.writeFileSync(path.join(exportFolder, 'child.txt'), 'export identity fixture');

    try {
      const result = await runRemoteContent('export', tempRoot, 'viewer-folder', {
        baseUrl: 'https://origin.example/index.php',
        sourceId: 'origin-example',
      });
      const repeatedResult = await runRemoteContent('export', tempRoot, 'viewer-folder', {
        baseUrl: 'https://origin.example/index.php',
        sourceId: 'origin-example',
      });

      expect(result.route).toBe('export-content');
      expect(result.source).toEqual(expect.objectContaining({
        id: 'origin-example',
        nodeId: expect.stringMatching(/^poff_node_[a-f0-9]{32}$/),
        baseUrl: 'https://origin.example/index.php',
        path: 'viewer-folder',
      }));
      expect(repeatedResult.source.nodeId).toBe(result.source.nodeId);
      expect(repeatedResult.root.workId).toBe(result.root.workId);
      expect(repeatedResult.items.find((item) => item.name === 'child.txt').workId)
        .toBe(result.items.find((item) => item.name === 'child.txt').workId);
      expect(result.root.workId).toMatch(/^poff_[a-f0-9]{16}$/);
      expect(repeatedResult.items).not.toEqual(expect.arrayContaining([
        expect.objectContaining({ name: 'poff.node.json' }),
      ]));
      expect(result.root.pageLink).toBe('https://origin.example/index.php?view=1&path=viewer-folder');
      expect(result.items).toEqual(expect.arrayContaining([
        expect.objectContaining({
          name: 'child.txt',
          workId: expect.stringMatching(/^poff_[a-f0-9]{16}$/),
          pageLink: 'https://origin.example/index.php?view=1&file=viewer-folder%2Fchild.txt',
          srcUrl: 'https://origin.example/viewer-folder/child.txt',
          routeSlug: 'child-txt',
          routePath: 'viewer-folder/child.txt',
        }),
        expect.objectContaining({
          name: 'nested-child',
          isFolder: true,
          workId: expect.stringMatching(/^poff_[a-f0-9]{16}$/),
          pageLink: 'https://origin.example/index.php?view=1&path=viewer-folder%2Fnested-child',
          routeSlug: 'nested-child',
          routePath: 'viewer-folder/nested-child',
        }),
      ]));
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('inspects and atomically replaces a compatible remote work presentation', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-remote-presentation-'));
    const localName = 'local-image.jpg';
    const workUrl = 'https://origin.example/index.php?view=1&file=works%2Ffullscreen.jpg';
    const snapshotUrl = 'https://origin.example/index.php?ajax=snapshot&file=works%2Ffullscreen.jpg';
    fs.copyFileSync(path.join(POFF_DIR, '.layout', 'poff.profile.jpg'), path.join(tempRoot, localName));

    try {
      const request = {
        url: workUrl,
        mockResponses: {
          [snapshotUrl]: {
            status: 200,
            body: {
              resolved: true,
              kind: 'image',
              meta: {
                title: 'Fullscreen image',
                path: 'works/fullscreen.jpg',
              },
              context: {
                mimeType: 'image/jpeg',
                work: {
                  type: 'image',
                  layout: {
                    sectionTemplate: '<div class="fullscreen"><img src="{{srcUrl}}" alt="{{title}}" onerror="bad()"><script>bad()</script></div>',
                    css: '.fullscreen{position:fixed;inset:0}.fullscreen img{width:100%;height:100%;object-fit:cover}',
                    js: 'document.addEventListener("DOMContentLoaded",()=>document.body.classList.add("fullscreen-ready"));',
                  },
                },
              },
            },
          },
        },
      };
      const result = await runRemoteContent('presentation', tempRoot, localName, request);

      expect(result).toEqual(expect.objectContaining({
        ok: true,
        route: 'remote-work-presentation',
        compatible: true,
        matches: expect.arrayContaining(['same suffix', 'same MIME', 'same work type']),
      }));
      expect(result.remote).toEqual(expect.objectContaining({
        title: 'Fullscreen image',
        path: 'works/fullscreen.jpg',
        url: workUrl,
      }));
      expect(result.presentation.workHbs).toContain('class="fullscreen"');
      expect(result.presentation.workHbs).toContain('{{srcUrl}}');
      expect(result.presentation.workHbs).not.toContain('<script>');
      expect(result.presentation.workHbs).not.toContain('onerror=');
      expect(result.presentation.styleCss).toContain('object-fit:cover');
      expect(result.presentation.scriptJs).toContain('fullscreen-ready');
      expect(result.localPresentation.hasPresentation).toBe(false);
      expect(fs.existsSync(path.join(tempRoot, '.works', `${localName}.layout`, 'work.hbs'))).toBe(false);

      const copied = await runRemoteContent('presentation', tempRoot, localName, {
        ...request,
        action: 'copy',
      });
      const layoutDir = path.join(tempRoot, '.works', `${localName}.layout`);
      expect(copied).toEqual(expect.objectContaining({
        ok: true,
        copied: true,
        replaced: false,
        backupPath: '',
      }));
      expect(fs.readFileSync(path.join(layoutDir, 'work.hbs'), 'utf8')).toContain('class="fullscreen"');
      expect(fs.readFileSync(path.join(layoutDir, 'style.css'), 'utf8')).toContain('object-fit:cover');
      expect(fs.readFileSync(path.join(layoutDir, 'script.js'), 'utf8')).toContain('fullscreen-ready');
      expect(fs.existsSync(path.join(layoutDir, 'template.hbs'))).toBe(true);

      fs.writeFileSync(path.join(layoutDir, 'style.css'), '.local-before-replace{display:block}');
      const blockedReplace = await runRemoteContent('presentation', tempRoot, localName, {
        ...request,
        action: 'copy',
      });
      expect(blockedReplace).toEqual(expect.objectContaining({
        ok: false,
        requiresConfirmation: true,
      }));
      expect(fs.readFileSync(path.join(layoutDir, 'style.css'), 'utf8')).toContain('local-before-replace');

      const replaced = await runRemoteContent('presentation', tempRoot, localName, {
        ...request,
        action: 'copy',
        confirmReplace: true,
      });
      expect(replaced.replaced).toBe(true);
      expect(replaced.backupPath).toContain(`.works/${localName}.layout.history/`);
      expect(fs.readFileSync(path.join(tempRoot, replaced.backupPath, 'style.css'), 'utf8')).toContain('local-before-replace');
      expect(fs.readFileSync(path.join(layoutDir, 'style.css'), 'utf8')).toContain('object-fit:cover');

      const config = JSON.parse(fs.readFileSync(path.join(tempRoot, '.works', `${localName}.config.json`), 'utf8'));
      expect(config.work.presentationSource).toEqual(expect.objectContaining({
        url: workUrl,
        files: ['work.hbs', 'style.css', 'script.js'],
      }));
      expect(config.work.layout).not.toHaveProperty('sectionTemplate');
      expect(config.work.layout).not.toHaveProperty('css');
      expect(config.work.layout).not.toHaveProperty('js');
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
  });

  test('public remote exports reject hidden implementation folders', async () => {
    const result = await runRemoteContent('export', POFF_DIR, '.works', {
      baseUrl: 'https://origin.example/',
    });

    expect(result).toEqual(expect.objectContaining({
      route: 'export-content',
      error: 'Invalid public folder path.',
    }));
  });

  test('imports remote exports into config tree as virtual entries and renders their links', async () => {
    const remoteFeed = {
      route: 'export-content',
      source: {
        id: 'origin-example',
        nodeId: 'poff_node_0123456789abcdef0123456789abcdef',
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
        reference: {
          nodeId: 'poff_node_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
          workId: 'poff_bbbbbbbbbbbbbbbb',
        },
        renderedHtml: '<article class="remote-snapshot remote-snapshot--folder">Portfolio snapshot</article>',
        visible: true,
        isFolder: true,
        isFile: false,
      },
      {
          name: 'Leaf Note',
          title: 'Leaf Note',
          type: 'file',
        workId: 'poff_0123456789abcdef',
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
        nodeId: 'poff_node_0123456789abcdef0123456789abcdef',
        url: 'https://origin.example/index.php?mcp=1&route=export-content&path=viewer-folder',
      }),
    ]));
    expect(storedConfig.tree).toEqual(expect.arrayContaining([
      expect.objectContaining({
        name: 'Portfolio',
        type: 'folder',
        pageLink: 'https://origin.example/index.php?view=1&path=viewer-folder%2Fnested-child',
        remoteSource: 'origin-example',
        remoteNodeId: 'poff_node_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        remoteFeedNodeId: 'poff_node_0123456789abcdef0123456789abcdef',
        externalWork: true,
        routeSlug: 'nested-child',
        routePath: 'viewer-folder/nested-child',
      }),
      expect.objectContaining({
        name: 'Leaf Note',
        type: 'file',
        pageLink: 'https://origin.example/index.php?view=1&file=viewer-folder%2Fchild.txt',
        remoteSource: 'origin-example',
        remoteNodeId: 'poff_node_0123456789abcdef0123456789abcdef',
        remoteWorkId: 'poff_0123456789abcdef',
        remoteFeedNodeId: 'poff_node_0123456789abcdef0123456789abcdef',
        externalWork: true,
        routeSlug: 'child-txt',
        routePath: 'viewer-folder/child.txt',
      }),
    ]));

    const leafEntry = storedConfig.tree.find((item) => item.name === 'Leaf Note');
    expect(leafEntry).toBeTruthy();
    expect(leafEntry.id).toBeUndefined();
    expect(leafEntry.remoteNodeId).toBe('poff_node_0123456789abcdef0123456789abcdef');
    expect(leafEntry.remoteWorkId).toBe('poff_0123456789abcdef');
    expect(leafEntry.remoteFeedNodeId).toBe('poff_node_0123456789abcdef0123456789abcdef');
    expect(leafEntry.remoteSource).toBe('origin-example');
    expect(leafEntry.routePath).toBe('viewer-folder/child.txt');
    expect(leafEntry.renderedHtml).toBeUndefined();
    expect(leafEntry.template).toBeUndefined();

    const relocatedFeed = JSON.parse(JSON.stringify(remoteFeed));
    relocatedFeed.source.id = 'new-origin.example';
    const replaced = await runRemoteContent('import', POFF_DIR, 'remote-import-links', {
      url: 'https://new-origin.example/index.php?mcp=1&route=export-content&path=viewer-folder',
      replace: true,
      mockResponse: {
        status: 200,
        body: relocatedFeed,
      },
    });
    expect(replaced.saved).toBe(true);
    expect(replaced.importedCount).toBe(2);

    const replacedConfig = JSON.parse(fs.readFileSync(path.join(REMOTE_IMPORT_DIR, 'poff.config.json'), 'utf8'));
    expect(replacedConfig.tree.map((item) => item.name).sort()).toEqual(['Leaf Note', 'Portfolio']);
    expect(replacedConfig.remoteSources.filter((source) => source.nodeId === remoteFeed.source.nodeId)).toHaveLength(1);

    const relayRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-remote-relay-'));
    try {
      copyDirSync(REMOTE_IMPORT_DIR, path.join(relayRoot, 'remote-import-links'));
      const relayFeed = await runRemoteContent('export', relayRoot, 'remote-import-links', {
        baseUrl: 'https://relay.example/index.php',
      });
      expect(relayFeed.items.find((item) => item.name === 'Portfolio').reference).toEqual({
        nodeId: 'poff_node_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        workId: 'poff_bbbbbbbbbbbbbbbb',
      });
      expect(relayFeed.items.find((item) => item.name === 'Leaf Note').reference).toEqual({
        nodeId: 'poff_node_0123456789abcdef0123456789abcdef',
        workId: 'poff_0123456789abcdef',
      });
    } finally {
      fs.rmSync(relayRoot, { recursive: true, force: true });
    }
  });

  test('copies remote export files into a new local folder and creates a looping player', async () => {
    const tempRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-remote-copy-'));
    const exportUrl = 'https://origin.example/?mcp=1&route=export-content&path=films';
    const assetUrl = 'https://origin.example/films/clip.mp4';
    const remoteFeed = {
      route: 'export-content',
      root: {
        name: 'films',
        title: 'Remote Films',
      },
      items: [
        {
          name: 'clip.mp4',
          title: 'Clip',
          type: 'file',
          isFile: true,
          srcUrl: assetUrl,
        },
      ],
    };

    try {
      fs.writeFileSync(path.join(tempRoot, 'poff.config.json'), JSON.stringify({
        folderName: path.basename(tempRoot),
        title: 'Copy target',
        type: 'folder',
        tree: [],
        work: {
          type: 'folder',
        },
      }, null, 2));

      const result = await runRemoteContent('import', tempRoot, '', {
        url: exportUrl,
        mode: 'copy',
        destinationName: 'local-films',
        createPlayer: true,
        mockResponses: {
          [exportUrl]: {
            status: 200,
            body: remoteFeed,
          },
          [assetUrl]: {
            status: 200,
            bodyRaw: 'fake-mp4-data',
          },
        },
      });

      expect(result).toEqual(expect.objectContaining({
        route: 'import-remote',
        saved: true,
        mode: 'copy',
        copiedCount: 1,
        destinationPath: 'local-films',
        openPath: 'local-films',
        playerCreated: true,
      }));
      expect(fs.readFileSync(path.join(tempRoot, 'local-films', 'clip.mp4'), 'utf8')).toBe('fake-mp4-data');
      const playerTemplate = fs.readFileSync(path.join(tempRoot, 'local-films', '.layout', 'works.hbs'), 'utf8');
      expect(playerTemplate).toContain('data-remote-playlist');
      expect(playerTemplate).toContain('<source src="{{srcUrl}}"');
      expect(playerTemplate).toContain('data-remote-loop');
      const playerScript = fs.readFileSync(path.join(tempRoot, 'local-films', '.layout', 'script.js'), 'utf8');
      expect(playerScript).toContain("loopToggle?.addEventListener('click'");
      expect(playerScript).toContain("tracks.forEach((track, trackIndex) => track.addEventListener('click'");
      const copiedConfig = JSON.parse(fs.readFileSync(path.join(tempRoot, 'local-films', 'poff.config.json'), 'utf8'));
      expect(copiedConfig.id).toMatch(/^poff_[a-f0-9]{16}$/);
      expect(copiedConfig.tree).toEqual([
        expect.objectContaining({
          name: 'clip.mp4',
          visible: true,
        }),
      ]);
      expect(copiedConfig.copiedFrom.url).toBe(exportUrl);
    } finally {
      fs.rmSync(tempRoot, { recursive: true, force: true });
    }
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

    expect(output).toContain('<title>Viewer File</title>');
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

  test('allows edit mode without an allow marker', async () => {
    const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-markerless-edit-'));

    try {
      expect(fs.existsSync(path.join(tempDir, '.edit.allow'))).toBe(false);
      expect(fs.existsSync(path.join(tempDir, 'edit.allow'))).toBe(false);

      const result = await runViewerSave(tempDir, '', {
        description: 'Markerless edit mode works',
      });

      expect(result.allowed).toBe(true);
      expect(result.saved).toBe(true);
      expect(result.config.description).toBe('Markerless edit mode works');
      expect(fs.existsSync(path.join(tempDir, '.edit.allow'))).toBe(false);
      expect(fs.existsSync(path.join(tempDir, 'edit.allow'))).toBe(false);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('stops edit mode when edit.not-allow exists locally', async () => {
    const runtimeRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'poff-denied-edit-'));
    const nestedRuntimeDir = path.join(runtimeRoot, 'nested');
    fs.mkdirSync(nestedRuntimeDir, { recursive: true });
    fs.writeFileSync(path.join(nestedRuntimeDir, 'edit.not-allow'), 'deny');

    try {
      const result = await runViewerSave(runtimeRoot, 'nested', {
        description: 'This should be blocked',
      });

      expect(result.allowed).toBe(false);
      expect(result.error).toBe('Edit mode not enabled.');
    } finally {
      fs.rmSync(runtimeRoot, { recursive: true, force: true });
    }
  });

  test('exposes worktype-specific config fields in the default work definitions', async () => {
    const videoDef = JSON.parse(await runWorktype('definition', 'video'));
    const imageDef = JSON.parse(await runWorktype('definition', 'image'));
    const htaccessDef = JSON.parse(await runWorktype('definition', 'htaccess'));

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
    expect(htaccessDef).toMatchObject({
      type: 'htaccess',
      syntax: 'apacheconf',
      headerName: 'Content-Security-Policy',
      frameAncestors: ["'self'"],
      allowAll: false,
    });
  });

  test('selects the htaccess worktype for a dotfile filename', async () => {
    const catalog = JSON.parse(await runWorktype('catalog', 'other', {
      fileName: '.htaccess',
      subjectType: 'file',
    }));
    expect(catalog.selected).toBe('htaccess');
    expect(catalog.choices).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          value: 'htaccess',
        }),
      ]),
    );
  });

  test('renders htaccess work content as a text preview', async () => {
    const rendered = await runWorktype('render', 'htaccess', {
      ctx: {
        textContent: '# Poff iframe policy',
        work: {
          template: 'htaccess',
          syntax: 'apacheconf',
        },
      },
    });

    expect(rendered).toContain('# Poff iframe policy');
    expect(rendered).toContain('Apache .htaccess');
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

  test('defaults a blank file title to the file name during edit save', async () => {
    const tempDir = path.join(POFF_DIR, `file-title-save-${Date.now()}`);
    const fileName = 'untitled.mp4';
    fs.mkdirSync(tempDir, { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, fileName), 'video');

    try {
      const result = await runViewerSave(tempDir, fileName, {
        title: '',
      });
      const savedConfigPath = path.join(tempDir, '.works', `${fileName}.config.json`);
      const savedConfig = JSON.parse(fs.readFileSync(savedConfigPath, 'utf8'));

      expect(result.saved).toBe(true);
      expect(result.config.title).toBe(fileName);
      expect(savedConfig.title).toBe(fileName);
    } finally {
      fs.rmSync(tempDir, { recursive: true, force: true });
    }
  });

  test('saves nested item description into the parent tree metadata', async () => {
    const tempDir = path.join(POFF_DIR, `nested-description-save-${Date.now()}`);
    fs.mkdirSync(path.join(tempDir, 'nested'), { recursive: true });
    fs.writeFileSync(path.join(tempDir, '.edit.allow'), '');
    fs.writeFileSync(path.join(tempDir, 'nested', 'item.txt'), 'hello');
    fs.writeFileSync(path.join(tempDir, 'nested', 'poff.config.json'), JSON.stringify({
      folderName: 'nested',
      slug: 'nested',
      title: 'nested',
      description: '',
      tree: [
        {
          name: 'item.txt',
          title: 'item.txt',
          description: '',
          type: 'file',
          path: 'item.txt',
        },
      ],
      work: {
        type: 'folder',
        layout: {
          mode: 'actual',
          name: 'actual',
          preset: 'actual',
        },
      },
    }, null, 2));

    try {
      const result = await runViewerSave(tempDir, 'nested/item.txt', {
        title: 'Nested Title',
        description: 'Nested Description',
      });
      const parentConfigPath = path.join(tempDir, 'nested', 'poff.config.json');
      const parentConfig = JSON.parse(fs.readFileSync(parentConfigPath, 'utf8'));
      const treeItem = parentConfig.tree.find((item) => item.name === 'item.txt');

      expect(result.saved).toBe(true);
      expect(treeItem).toEqual(expect.objectContaining({
        title: 'Nested Title',
        description: 'Nested Description',
      }));
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

  test('resets a folder work override when called through the virtual layout path', async () => {
    const tempDir = path.join(POFF_DIR, `reset-layout-virtual-${Date.now()}`);
    copyDirSync(PERSIST_LAYOUT_DIR, tempDir);

    try {
      expect(fs.existsSync(path.join(tempDir, '.layout'))).toBe(true);

      const output = await runResetFolder(tempDir, '.layout');
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
        sharedName: 'filesystem-layout',
      });
      expect(serializedLayout.source).toBeUndefined();
      expect(serializedLayout.storage).toBeUndefined();
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
