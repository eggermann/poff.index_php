const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadRenderEditPanel() {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'inline.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import[\s\S]*?from\s+['"][^'"]+['"];\n/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    window: {
      prompt() { return null; },
      confirm() { return false; },
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
    loadPromptSettings() {
      return {};
    },
    renderPromptWindow() {
      return '';
    },
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
    layoutOverlayState() {
      return {
        wrapperSourceLabel: 'PHP built-in poff-layout',
        inheritedLayoutLabel: '.layout',
        displayMode: 'poff-layout',
      };
    },
    renderPersistentDetailsSection() {
      return '';
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
