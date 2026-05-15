const fs = require('fs');
const path = require('path');
const vm = require('vm');

function createClassList() {
  const classes = new Set();
  return {
    toggle(name, force) {
      if (force === undefined) {
        if (classes.has(name)) {
          classes.delete(name);
          return false;
        }
        classes.add(name);
        return true;
      }
      if (force) {
        classes.add(name);
        return true;
      }
      classes.delete(name);
      return false;
    },
    contains(name) {
      return classes.has(name);
    },
  };
}

function createElementMock() {
  const listeners = new Map();
  return {
    hidden: false,
    open: false,
    textContent: '',
    classList: createClassList(),
    addEventListener(type, handler) {
      listeners.set(type, handler);
    },
    removeEventListener(type) {
      listeners.delete(type);
    },
    setAttribute(name, value) {
      this[name] = String(value);
    },
    getListener(type) {
      return listeners.get(type);
    },
    focus() {},
  };
}

function loadEditController() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/controller.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const window = {
    location: {
      href: 'http://example.test/MAUSMAUS/?edit=true',
      search: '?edit=true',
      hash: '',
      pathname: '/MAUSMAUS/',
    },
    dispatchEvent() {},
    history: {
      replaceState() {},
    },
  };
  const document = {
    getElementById() {
      return null;
    },
    querySelector() {
      return null;
    },
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    window,
    document,
    URL,
    CustomEvent,
    HTMLButtonElement: function HTMLButtonElement() {},
    HTMLInputElement: function HTMLInputElement() {},
    HTMLTextAreaElement: function HTMLTextAreaElement() {},
    HTMLSelectElement: function HTMLSelectElement() {},
    requestEditAuth() {
      throw new Error('requestEditAuth should not be called in this test');
    },
    requestEditConfig() {
      throw new Error('requestEditConfig should not be called in this test');
    },
    requestEditDelete() {
      throw new Error('requestEditDelete should not be called in this test');
    },
    requestEditReset() {
      throw new Error('requestEditReset should not be called in this test');
    },
    requestEditUpload() {
      throw new Error('requestEditUpload should not be called in this test');
    },
    requestPromptTemplate() {
      throw new Error('requestPromptTemplate should not be called in this test');
    },
    buildVirtualLayoutPath: (value) => value,
    getActiveSelection: () => ({ path: '', previewPath: '', previewIsFile: false, isFile: false, isLayout: false }),
    bindPromptWindow() {
      return {};
    },
    renderEditDrawer() {
      return { drawerForm: null };
    },
    renderEditPanel() {
      return { statusEl: null, promptRoot: null };
    },
    materializeWorkFields: (value) => value,
    buildLayoutPayload() {
      return { layoutPayload: {} };
    },
    createLayoutNameForPreset: () => () => 'layout',
    getContentTargetPath: () => '',
    getEditTargetPath: () => '',
    setStatusMessage() {},
  });

  vm.runInContext(`${source}
module.exports = {
  createEditController,
};
`, context);

  return module.exports.createEditController;
}

describe('edit auth disclosure', () => {
  test('can be closed after opening when edit mode is unavailable', () => {
    const createEditController = loadEditController();
    const editAuthDetails = createElementMock();
    const editToggle = createElementMock();
    const editAuthForm = createElementMock();
    const editAuthPassword = createElementMock();
    const editAuthSubmit = createElementMock();
    const editAuthStatus = createElementMock();

    const controller = createEditController({
      elements: {
        editPanel: null,
        editDrawer: null,
        editAuthDetails,
        editToggle,
        editAddWork: null,
        editAuthForm,
        editAuthPassword,
        editAuthSubmit,
        editAuthStatus,
      },
      context: {
        currentPoffConfig: null,
        cmsAuth: {
          configured: true,
          authenticated: false,
          editModeAllowed: true,
          canEdit: false,
        },
      },
      editRequested: true,
    });

    controller.bindEditToggle();

    const onToggle = editAuthDetails.getListener('toggle');
    expect(typeof onToggle).toBe('function');

    editAuthDetails.open = true;
    onToggle();

    expect(editAuthDetails.open).toBe(true);
    expect(editAuthForm.hidden).toBe(false);
    expect(editAuthPassword.hidden).toBe(false);

    editAuthDetails.open = false;
    onToggle();

    expect(editAuthDetails.open).toBe(false);
    expect(editAuthForm.hidden).toBe(true);
    expect(editAuthStatus.textContent).toBe('');
  });
});
