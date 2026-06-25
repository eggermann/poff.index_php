const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadRenderEditPanel(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'inline.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import[\s\S]*?from\s+['"][^'"]+['"];\n/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const reservedKeys = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind', 'converter']);
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    window: overrides.window || {
      location: { href: 'http://example.test/?edit=true' },
      prompt() { return null; },
      confirm() { return false; },
    },
    HTMLButtonElement: function HTMLButtonElement() {},
    HTMLInputElement: function HTMLInputElement() {},
    HTMLTextAreaElement: function HTMLTextAreaElement() {},
    HTMLSelectElement: function HTMLSelectElement() {},
    escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },
    loadPromptSettings() {
      return {};
    },
    renderPromptWindow() {
      return '';
    },
    PASSWORD_DETAILS_STORAGE_KEY: 'password-details',
    RESERVED_WORK_CONFIG_KEYS: reservedKeys,
    applyWorkFieldTypeDefaults(value) {
      return value;
    },
    createDefaultWorkField() {
      return {};
    },
    extractWorkFields() {
      return [];
    },
    getWorkFieldSchemaProfile() {
      return { visibleControls: new Set() };
    },
    materializeWorkFields(value) {
      return value;
    },
    normalizeWorkField(value) {
      return value;
    },
    schemaFieldTypeOptions() {
      return [];
    },
    bindWorkCategoryControls() {},
    getWorkCategoryOptions() {
      return [];
    },
    normalizeWorkCategories(value = []) {
      return value;
    },
    renderWorkCategorySection() {
      return '';
    },
    bindStoredDetailsState() {},
    createWorkFieldEditor() {
      return {
        renderRows() { return ''; },
        syncWorkFieldsFromDom() {},
        addWorkField() {},
      };
    },
    layoutOverlayState() {
      return {
        wrapperSourceLabel: 'PHP built-in poff-layout',
        inheritedLayoutLabel: '.layout',
        displayMode: 'poff-layout',
      };
    },
    renderPersistentDetailsSection(options = {}) {
      return `${options.titleHtml || ''}${options.noteHtml || ''}${options.bodyHtml || ''}`;
    },
    readMediaConfigFromForm(form, currentWork = {}) {
      return currentWork;
    },
    renderWorkConfigFieldsSection() {
      return '';
    },
    renderConverterSection(config = {}) {
      const available = Array.isArray(config?.converterCatalog?.available) ? config.converterCatalog.available : [];
      const selected = String(config?.work?.converter?.id || '').trim();
      const sourceUrl = String(config?.work?.converter?.sourceUrl || config?.srcUrl || '').trim();
      const disabled = selected === '';
      const options = ['<option value="">none</option>']
        .concat(available.map((item) => `<option value="${item.id || ''}"${item.id === selected ? ' selected' : ''}>${item.label || item.id || ''}</option>`))
        .join('');
      return `
        <div class="edit-work-fields edit-work-converter">
          <select id="edit-converter-id" name="converter_id" form="inlineEditForm">${options}</select>
          <input id="edit-converter-source-url" name="converter_source_url" form="inlineEditForm" type="url" value="${sourceUrl}">
          <select id="edit-converter-quality" name="converter_quality" form="inlineEditForm"></select>
          <select id="edit-converter-format" name="converter_format" form="inlineEditForm"></select>
          <select id="edit-converter-save-mode" name="converter_save_mode" form="inlineEditForm"></select>
          <button id="editConvertWork"${disabled ? ' disabled' : ''}>Convert</button>
          <button id="editCreateConverter">New converter</button>
        </div>
      `;
    },
    renderDirectFileLink(config = {}, status = {}) {
      if (status?.target !== 'file' || !config?.srcUrl) {
        return '';
      }
      return `<div class="small-note"><a href="${config.srcUrl}" target="_blank" rel="noopener noreferrer">Open original file</a></div>`;
    },
    renderPasswordChangeSection(status = {}) {
      if (!status?.auth?.canEdit || status?.target !== 'folder') {
        return '';
      }
      const relativePath = String(status?.relativePath || '').trim();
      if (relativePath !== '') {
        return '';
      }
      const pathname = typeof context.window?.location?.pathname === 'string' ? context.window.location.pathname.trim() : '';
      const normalizedPathname = pathname.replace(/\/+$/, '') || '/';
      if (normalizedPathname === '/') {
        return '';
      }
      return '<div>Change password</div><form id="editPasswordForm" class="edit-inline"><label>Current password</label><input id="edit-current-password" type="password"></form>';
    },
    syncPromptDock() {},
    bindUploadDialog() {},
    renderUploadSectionHtml() {
      return '';
    },
    renderEditLayoutPanel() {
      return { statusEl: null, promptRoot: null };
    },
  });

  vm.runInContext(`${source}
module.exports = { renderEditPanel };
`, context);

  return module.exports.renderEditPanel;
}

function loadRenderEditPanelWithHooks(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'inline.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import[\s\S]*?from\s+['"][^'"]+['"];\n/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const reservedKeys = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind', 'converter']);
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    window: overrides.window || {
      location: { href: 'http://example.test/?edit=true' },
      prompt() { return null; },
      confirm() { return false; },
    },
    HTMLButtonElement: function HTMLButtonElement() {},
    HTMLInputElement: function HTMLInputElement() {},
    HTMLTextAreaElement: function HTMLTextAreaElement() {},
    HTMLSelectElement: function HTMLSelectElement() {},
    escapeHtml(value) {
      return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },
    loadPromptSettings() {
      return {};
    },
    renderPromptWindow() {
      return '';
    },
    PASSWORD_DETAILS_STORAGE_KEY: 'password-details',
    RESERVED_WORK_CONFIG_KEYS: reservedKeys,
    applyWorkFieldTypeDefaults(value) {
      return value;
    },
    createDefaultWorkField() {
      return {};
    },
    extractWorkFields() {
      return [];
    },
    getWorkFieldSchemaProfile() {
      return { visibleControls: new Set() };
    },
    materializeWorkFields(value) {
      return value;
    },
    normalizeWorkField(value) {
      return value;
    },
    schemaFieldTypeOptions() {
      return [];
    },
    bindWorkCategoryControls() {},
    getWorkCategoryOptions() {
      return [];
    },
    normalizeWorkCategories(value = []) {
      return value;
    },
    renderWorkCategorySection() {
      return '';
    },
    bindStoredDetailsState() {},
    createWorkFieldEditor() {
      return {
        renderRows() { return ''; },
        syncWorkFieldsFromDom() {},
        addWorkField() {},
      };
    },
    layoutOverlayState() {
      return {
        wrapperSourceLabel: 'PHP built-in poff-layout',
        inheritedLayoutLabel: '.layout',
        displayMode: 'poff-layout',
      };
    },
    renderPersistentDetailsSection(options = {}) {
      return `${options.titleHtml || ''}${options.noteHtml || ''}${options.bodyHtml || ''}`;
    },
    readMediaConfigFromForm(form, currentWork = {}) {
      return currentWork;
    },
    renderWorkConfigFieldsSection() {
      return '';
    },
    renderConverterSection(config = {}) {
      const available = Array.isArray(config?.converterCatalog?.available) ? config.converterCatalog.available : [];
      const selected = String(config?.work?.converter?.id || '').trim();
      const sourceUrl = String(config?.work?.converter?.sourceUrl || config?.srcUrl || '').trim();
      const disabled = selected === '';
      const options = ['<option value="">none</option>']
        .concat(available.map((item) => `<option value="${item.id || ''}"${item.id === selected ? ' selected' : ''}>${item.label || item.id || ''}</option>`))
        .join('');
      return `
        <div class="edit-work-fields edit-work-converter">
          <select id="edit-converter-id" name="converter_id" form="inlineEditForm">${options}</select>
          <input id="edit-converter-source-url" name="converter_source_url" form="inlineEditForm" type="url" value="${sourceUrl}">
          <select id="edit-converter-quality" name="converter_quality" form="inlineEditForm"></select>
          <select id="edit-converter-format" name="converter_format" form="inlineEditForm"></select>
          <select id="edit-converter-save-mode" name="converter_save_mode" form="inlineEditForm"></select>
          <button id="editConvertWork"${disabled ? ' disabled' : ''}>Convert</button>
          <button id="editCreateConverter">New converter</button>
        </div>
      `;
    },
    renderDirectFileLink(config = {}, status = {}) {
      if (status?.target !== 'file' || !config?.srcUrl) {
        return '';
      }
      return `<div class="small-note"><a href="${config.srcUrl}" target="_blank" rel="noopener noreferrer">Open original file</a></div>`;
    },
    renderPasswordChangeSection(status = {}) {
      if (!status?.auth?.canEdit || status?.target !== 'folder') {
        return '';
      }
      const relativePath = String(status?.relativePath || '').trim();
      if (relativePath !== '') {
        return '';
      }
      const pathname = typeof context.window?.location?.pathname === 'string' ? context.window.location.pathname.trim() : '';
      const normalizedPathname = pathname.replace(/\/+$/, '') || '/';
      if (normalizedPathname === '/') {
        return '';
      }
      return '<div>Change password</div><form id="editPasswordForm" class="edit-inline"><label>Current password</label><input id="edit-current-password" type="password"></form>';
    },
    syncPromptDock() {},
    bindUploadDialog: overrides.bindUploadDialog || (() => {}),
    renderUploadSectionHtml: overrides.renderUploadSectionHtml || (() => ''),
    renderEditLayoutPanel() {
      return { statusEl: null, promptRoot: null };
    },
  });

  vm.runInContext(`${source}
module.exports = { renderEditPanel };
`, context);

  return module.exports.renderEditPanel;
}

function createEditPanel() {
  return {
    innerHTML: '',
    querySelector() {
      return null;
    },
    querySelectorAll() {
      return [];
    },
  };
}

test('file edit mode shows a direct link to the original file in a new tab', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Example',
      description: '',
      srcUrl: '/MAUSMAUS/files/example.json',
      work: {
        type: 'text',
      },
    },
    status: {
      allowed: true,
      target: 'file',
      auth: {
        canEdit: true,
      },
    },
  });

  expect(editPanel.innerHTML).toContain('Edit mode (file)');
  expect(editPanel.innerHTML).toContain('Open original file');
  expect(editPanel.innerHTML).toContain('href="/MAUSMAUS/files/example.json"');
  expect(editPanel.innerHTML).toContain('target="_blank"');
  expect(editPanel.innerHTML).toContain('rel="noopener noreferrer"');
});

test('convert button renders disabled when no converter is selected', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Example',
      description: '',
      work: {
        type: 'text',
      },
      converterCatalog: {
        available: [],
      },
    },
    status: {
      allowed: true,
      target: 'file',
      auth: {
        canEdit: true,
      },
    },
  });

  expect(editPanel.innerHTML).toContain('id="editConvertWork" disabled');
});

test('converter controls stay attached to inline edit form after being moved below it', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Example',
      description: '',
      srcUrl: '/MAUSMAUS/files/example.tiff',
      work: {
        type: 'text',
      },
      converterCatalog: {
        available: [
          {
            id: 'converter/convert-text-editor',
            label: 'Convert Text Editor',
          },
        ],
      },
    },
    status: {
      allowed: true,
      target: 'file',
      auth: {
        canEdit: true,
      },
    },
  });

  expect(editPanel.innerHTML).toContain('id="edit-converter-id" name="converter_id" form="inlineEditForm"');
  expect(editPanel.innerHTML).toContain('id="edit-converter-source-url" name="converter_source_url" form="inlineEditForm"');
  expect(editPanel.innerHTML).toContain('value="/MAUSMAUS/files/example.tiff"');
  expect(editPanel.innerHTML).toContain('id="edit-converter-quality" name="converter_quality" form="inlineEditForm"');
  expect(editPanel.innerHTML).toContain('id="edit-converter-format" name="converter_format" form="inlineEditForm"');
  expect(editPanel.innerHTML).toContain('id="edit-converter-save-mode" name="converter_save_mode" form="inlineEditForm"');
  expect(editPanel.innerHTML).toContain('class="edit-panel-stack"');
});

test('password section renders for a homefolder mounted under a subpath', () => {
  const renderEditPanel = loadRenderEditPanel({
    window: {
      location: {
        pathname: '/MAUSMAUS/',
      },
      prompt() { return null; },
      confirm() { return false; },
    },
  });
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      __poffRelativePath: '',
      title: 'Root folder',
      description: '',
    },
    status: {
      allowed: true,
      target: 'folder',
      auth: {
        canEdit: true,
      },
    },
  });

  expect(editPanel.innerHTML).toContain('Edit mode (folder)');
  expect(editPanel.innerHTML).toContain('Change password');
  expect(editPanel.innerHTML).toContain('Current password');
});

test('password section stays hidden on the main site root', () => {
  const renderEditPanel = loadRenderEditPanel({
    window: {
      location: {
        pathname: '/',
      },
      prompt() { return null; },
      confirm() { return false; },
    },
  });
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      __poffRelativePath: '',
      title: 'Root folder',
      description: '',
    },
    status: {
      allowed: true,
      target: 'folder',
      auth: {
        canEdit: true,
      },
    },
  });

  expect(editPanel.innerHTML).toContain('Edit mode (folder)');
  expect(editPanel.innerHTML).not.toContain('Change password');
  expect(editPanel.innerHTML).not.toContain('Current password');
});

test('guest edit mode no longer renders the external link submission panel', () => {
  let bindUploadDialogArgs = null;
  let renderUploadSectionArgs = null;
  const renderEditPanel = loadRenderEditPanelWithHooks({
    renderUploadSectionHtml(args) {
      renderUploadSectionArgs = args;
      return '<button id="editOpenUploadDialog" hidden type="button">Submit link</button><dialog id="editUploadDialog"></dialog>';
    },
    bindUploadDialog(args) {
      bindUploadDialogArgs = args;
    },
  });
  const statusEl = { id: 'editInlineStatus' };
  const editPanel = {
    hidden: false,
    innerHTML: '',
    querySelector(selector) {
      if (selector === '#editInlineStatus') {
        return statusEl;
      }
      return null;
    },
    querySelectorAll() {
      return [];
    },
  };

  const result = renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      tree: [],
    },
    status: {
      allowed: true,
      target: 'folder',
      auth: {
        canEdit: false,
        editModeAllowed: true,
      },
    },
    onUploadFiles() {},
    onCreateBlankFile() {},
    onCreateFolder() {},
    onCreateLink() {},
  });

  expect(editPanel.hidden).toBe(false);
  expect(editPanel.innerHTML).toContain('Edit mode');
  expect(editPanel.innerHTML).not.toContain('Submit external link');
  expect(editPanel.innerHTML).not.toContain('Submit link');
  expect(renderUploadSectionArgs).toBeNull();
  expect(bindUploadDialogArgs).toBeNull();
  expect(result.statusEl).toBe(statusEl);
});

test('file edit mode still renders and binds the add work upload dialog', () => {
  let bindUploadDialogArgs = null;
  let renderUploadSectionArgs = null;
  const renderEditPanel = loadRenderEditPanelWithHooks({
    renderUploadSectionHtml(args) {
      renderUploadSectionArgs = args;
      return '<button id="editOpenUploadDialog" hidden type="button">Add work</button><dialog id="editUploadDialog"></dialog>';
    },
    bindUploadDialog(args) {
      bindUploadDialogArgs = args;
    },
  });
  const statusEl = { id: 'editInlineStatus' };
  const editPanel = {
    hidden: false,
    innerHTML: '',
    querySelector(selector) {
      if (selector === '#editInlineStatus') {
        return statusEl;
      }
      return null;
    },
    querySelectorAll() {
      return [];
    },
  };

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Example',
      description: '',
      work: {
        type: 'text',
      },
    },
    status: {
      allowed: true,
      target: 'file',
      auth: {
        canEdit: true,
      },
    },
    onUploadFiles() {},
    onCreateBlankFile() {},
    onCreateFolder() {},
    onCreateLink() {},
  });

  expect(renderUploadSectionArgs).toEqual(expect.objectContaining({
    isFileTarget: true,
    showLauncher: false,
  }));
  expect(bindUploadDialogArgs).toEqual(expect.objectContaining({
    editPanel,
    statusEl,
    externalOnly: false,
  }));
});
