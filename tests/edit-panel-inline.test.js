const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadRenderEditPanel(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'inline.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import[\s\S]*?from\s+['"][^'"]+['"];\n/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const reservedKeys = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind', 'converter', 'presentationSource']);
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
    normalizeWorkCategories(value) {
      return Array.isArray(value) ? value : [];
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
    renderRemoteWorkPresentationSection() {
      return '<div class="edit-remote-presentation">Reuse complete work presentation</div>';
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
  const reservedKeys = new Set(['type', 'template', 'templateMap', 'layout', 'fields', 'categories', 'category', 'kind', 'converter', 'presentationSource']);
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
    normalizeWorkCategories(value) {
      return Array.isArray(value) ? value : [];
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
    renderRemoteWorkPresentationSection() {
      return '<div class="edit-remote-presentation">Reuse complete work presentation</div>';
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

test('guest host with external submissions enabled renders URL-only submission flow', () => {
  const renderEditPanel = loadRenderEditPanelWithHooks({
    renderUploadSectionHtml(options) {
      return options.externalOnly ? '<div id="publicExternalUpload">URL submission</div>' : '';
    },
  });
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: { tree: [], externalSubmissions: { enabled: true } },
    status: { target: 'folder', auth: { canEdit: false, editModeAllowed: true } },
    onUploadFiles() {}, onCreateBlankFile() {}, onCreateFolder() {}, onCreateLink() {}, onImportRemote() {},
  });

  expect(editPanel.innerHTML).toContain('Submit external work');
  expect(editPanel.innerHTML).toContain('publicExternalUpload');
});

test('guest host without opt-in keeps editor login flow', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: { tree: [] },
    status: { target: 'folder', auth: { canEdit: false, editModeAllowed: true } },
  });

  expect(editPanel.innerHTML).toContain('Enter the editor password to unlock editing.');
  expect(editPanel.innerHTML).not.toContain('Submit external work');
});

test('guest add=url intent cannot bypass host opt-in', () => {
  let bindUploadDialogArgs = null;
  const renderEditPanel = loadRenderEditPanelWithHooks({
    window: {
      location: { href: 'http://example.test/?edit=true&add=url' },
      prompt() { return null; },
      confirm() { return false; },
    },
    renderUploadSectionHtml(options) {
      return options.externalOnly ? '<div id="directExternalUpload">URL submission</div>' : '';
    },
    bindUploadDialog(args) {
      bindUploadDialogArgs = args;
    },
  });
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: { tree: [] },
    status: { target: 'folder', auth: { canEdit: false, editModeAllowed: true } },
    onUploadFiles() {}, onCreateBlankFile() {}, onCreateFolder() {}, onCreateLink() {}, onImportRemote() {},
  });

  expect(editPanel.innerHTML).toContain('Enter the editor password to unlock editing.');
  expect(editPanel.innerHTML).not.toContain('Submit external work');
  expect(editPanel.innerHTML).not.toContain('directExternalUpload');
  expect(bindUploadDialogArgs).toBeNull();
});

test('editor configures external work opt-in, whitelist, and pending limit', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Host node',
      tree: [],
      externalSubmissions: {
        enabled: true,
        whitelistEnabled: true,
        whitelist: ['artist.example'],
        maxPending: 4,
      },
    },
    status: { allowed: true, target: 'folder', auth: { canEdit: true, editModeAllowed: true } },
    onUploadFiles() {}, onCreateBlankFile() {}, onCreateFolder() {}, onCreateLink() {}, onImportRemote() {},
  });

  expect(editPanel.innerHTML).toContain('Allow external work');
  expect(editPanel.innerHTML).toContain('Use host whitelist');
  expect(editPanel.innerHTML).toContain('artist.example');
  expect(editPanel.innerHTML).toContain('value="4"');
});

function loadConverterSectionHelpers() {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'inline-sections.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import[\s\S]*?from\s+['"][^'"]+['"];\n/g, '')
    .replace(/export const /g, 'const ')
    .replace(/export function /g, 'function ');
  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    URL,
    window: {
      location: {
        href: 'https://consumer.example/index.php?edit=true',
      },
    },
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
    extractWorkFields() {
      return [];
    },
    normalizeWorkField(value) {
      return value;
    },
    getWorkCategoryOptions() {
      return [];
    },
    normalizeWorkCategories(value = []) {
      return Array.isArray(value) ? value : [];
    },
    renderWorkCategorySection() {
      return '';
    },
    renderPersistentDetailsSection() {
      return '';
    },
  });

  vm.runInContext(`${source}
module.exports = { readMediaConfigFromForm, renderConverterSection, renderRemoteWorkPresentationSection };
`, context);
  return module.exports;
}

test('converter section loads offers from an external Poff node', () => {
  const { renderConverterSection } = loadConverterSectionHelpers();
  const html = renderConverterSection({
    srcUrl: '/photo.heic',
    work: {
      converter: {
        id: 'converter/remote-provider-convert-image',
        node: {
          baseUrl: 'https://provider.example/index.php',
        },
      },
    },
    converterCatalog: {
      available: [
        {
          id: 'converter/remote-provider-convert-image',
          name: 'remote-provider-convert-image',
          label: 'Convert Image · provider',
          engine: 'remote-node',
          accepts: ['image/*'],
          outputs: ['image/png'],
          formats: ['png'],
          defaults: { format: 'png' },
          ui: { format: { options: ['png'] } },
          node: {
            id: 'provider',
            baseUrl: 'https://provider.example/index.php',
            endpoint: 'https://provider.example/index.php?mcp=1&route=convert-offered',
            converterId: 'converter/convert-image',
          },
        },
      ],
    },
  });

  expect(html).toContain('id="edit-converter-node-url"');
  expect(html).toContain('value="https://provider.example/index.php"');
  expect(html).not.toContain('id="editLoadConverterNode"');
  expect(html).toContain('Offers load automatically when URL changes.');
  expect(html).toContain('data-converter-engine="remote-node"');
  expect(html).toContain('data-converter-node="{&quot;id&quot;:&quot;provider&quot;');
});

test('remote work presentation section explains atomic HBS CSS and JS replacement', () => {
  const { renderRemoteWorkPresentationSection } = loadConverterSectionHelpers();
  const html = renderRemoteWorkPresentationSection({
    name: 'local-image.jpg',
    work: {
      type: 'image',
      layout: {
        sectionDirectory: '.works/local-image.jpg.layout',
      },
      presentationSource: {
        url: 'https://artist.example/?view=1&file=fullscreen.jpg',
        title: 'Fullscreen image',
      },
    },
  });

  expect(html).toContain('Reuse complete work presentation');
  expect(html).toContain('<code>work.hbs</code>, <code>style.css</code>, and <code>script.js</code>');
  expect(html).toContain('Your local media stays unchanged.');
  expect(html).toContain('id="editCheckRemotePresentation"');
  expect(html).toContain('id="editApplyRemotePresentation" disabled>Replace complete presentation</button>');
  expect(html).toContain('Current presentation source:');
  expect(html).toContain('Fullscreen image');
});

test('remote converter selection preserves provider routing metadata', () => {
  const { readMediaConfigFromForm } = loadConverterSectionHelpers();
  const node = {
    id: 'provider',
    baseUrl: 'https://provider.example/index.php',
    endpoint: 'https://provider.example/index.php?mcp=1&route=convert-offered',
    converterId: 'converter/convert-image',
  };
  const selectedOption = {
    dataset: {
      converterName: 'remote-provider-convert-image',
      converterType: 'converter',
      converterLabel: 'Convert Image · provider',
      converterDescription: 'Remote image conversion',
      converterEngine: 'remote-node',
      converterFormats: 'png',
      converterAccepts: JSON.stringify(['image/*']),
      converterOutputs: JSON.stringify(['image/png']),
      converterDefaults: JSON.stringify({ format: 'png' }),
      converterUi: JSON.stringify({ format: { options: ['png'] } }),
      converterNode: JSON.stringify(node),
    },
  };
  const form = {
    elements: {
      converter_id: {
        value: 'converter/remote-provider-convert-image',
        selectedOptions: [selectedOption],
      },
      converter_quality: { value: 'default' },
      converter_format: { value: 'png' },
      converter_save_mode: { value: 'new-hidden-work' },
    },
    querySelectorAll() {
      return [];
    },
  };

  const work = readMediaConfigFromForm(form, {});
  expect(work.converter).toEqual(expect.objectContaining({
    id: 'converter/remote-provider-convert-image',
    label: 'Convert Image · provider',
    accepts: ['image/*'],
    outputs: ['image/png'],
    formats: ['png'],
    node,
  }));
});

test('file converter defaults to pageoff and offers same-type bulk conversion', () => {
  const { renderConverterSection } = loadConverterSectionHelpers();
  const html = renderConverterSection({
    mimeType: 'image/heic',
    conversionScope: {
      mimeType: 'image/heic',
      sameTypeCount: 16,
    },
    converterCatalog: {
      available: [],
      autoLoadRemote: true,
    },
  });

  expect(html).toContain('value="https://pageoff.de/index.php"');
  expect(html).toContain('id="edit-converter-all-of-type" type="checkbox" checked');
  expect(html).toContain('Convert all 16 <strong>image/heic</strong> files in this folder');
  expect(html).toContain('id="edit-converter-hide-originals" type="checkbox" checked');
  expect(html).toContain('id="edit-converter-hide-thumbnails" type="checkbox" checked');
  expect(html).toContain('Hide matching <strong>thumb*</strong> files');
  expect(html).toContain('<summary>Output and visibility</summary>');
  expect(html).toContain('<summary>Converter node</summary>');
  expect(html).toContain('Offer your own converter');
  expect(html).toContain('No converted files yet.');
  expect(html).not.toContain('[show]');
  expect(html).not.toContain('Reload converter');
  expect(html).not.toContain('Convert folder images');
  expect(html).not.toContain('This node');
});

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

test('primary edit actions avoid duplicate destructive controls and hide layout diagnostics', () => {
  const renderEditPanel = loadRenderEditPanel();
  const editPanel = createEditPanel();

  renderEditPanel({
    editPanel,
    editRequested: true,
    config: {
      title: 'Collection',
      description: '',
      work: {
        type: 'folder',
        categories: ['folder', 'collection'],
      },
    },
    status: {
      allowed: true,
      target: 'folder',
      relativePath: 'collection',
      auth: {
        canEdit: true,
      },
    },
    onResetFolderWork() {},
    onDeleteTarget() {},
  });

  expect(editPanel.innerHTML).toContain('id="editMoreToggle">Settings</button>');
  expect(editPanel.innerHTML).toContain('id="editChangeLayout">Layout</button>');
  expect(editPanel.innerHTML).toContain('<summary>Layout source</summary>');
  expect(editPanel.innerHTML).toContain('id="editRegisterPoff"');
  expect(editPanel.innerHTML).toContain('Filters: folder · collection.');
  expect(editPanel.innerHTML).not.toContain('id="editDeleteTarget"');
  expect(editPanel.innerHTML).not.toContain('id="editResetFolderWork"');
  expect(editPanel.innerHTML).not.toContain('More...');
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
  expect(editPanel.innerHTML).not.toContain('id="edit-converter-source-url"');
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
