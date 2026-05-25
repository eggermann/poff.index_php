const fs = require('fs');
const path = require('path');
const vm = require('vm');
const HTMLButtonElement = function HTMLButtonElement() {};

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

function loadEditController(documentMock = null, overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/controller.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const window = overrides.window || {
    location: {
      href: 'http://example.test/MAUSMAUS/?edit=true',
      search: '?edit=true',
      hash: '',
      pathname: '/MAUSMAUS/',
    },
    dispatchEvent() {},
    addEventListener() {},
    prompt() { return null; },
    history: {
      replaceState() {},
    },
  };
  const document = documentMock || overrides.document || {
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
    HTMLButtonElement,
    HTMLInputElement: function HTMLInputElement() {},
    HTMLTextAreaElement: function HTMLTextAreaElement() {},
    HTMLSelectElement: function HTMLSelectElement() {},
    requestEditAuth: overrides.requestEditAuth || function requestEditAuth() {
      throw new Error('requestEditAuth should not be called in this test');
    },
    requestEditConfig: overrides.requestEditConfig || function requestEditConfig() {
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
    requestMcpRoute: overrides.requestMcpRoute || function requestMcpRoute() {
      throw new Error('requestMcpRoute should not be called in this test');
    },
    buildVirtualLayoutPath: (value) => value,
    getActiveSelection: overrides.getActiveSelection || (() => ({ path: '', previewPath: '', previewIsFile: false, isFile: false, isLayout: false })),
    bindPromptWindow() {
      return {};
    },
    renderEditDrawer() {
      return { drawerForm: null };
    },
    renderEditPanel: overrides.renderEditPanel || function renderEditPanel() {
      return { statusEl: null, promptRoot: null };
    },
    materializeWorkFields: (value) => value,
    buildLayoutPayload() {
      return { layoutPayload: {} };
    },
    createLayoutNameForPreset: () => () => 'layout',
    getContentTargetPath: overrides.getContentTargetPath || (() => ''),
    getEditTargetPath: overrides.getEditTargetPath || (() => ''),
    setStatusMessage: overrides.setStatusMessage || function setStatusMessage() {},
  });

  vm.runInContext(`${source}
module.exports = {
  createEditController,
};
`, context);

  return {
    createEditController: module.exports.createEditController,
    HTMLButtonElement,
  };
}

describe('edit auth disclosure', () => {
  test('can be closed after opening when edit mode is unavailable', () => {
    const { createEditController } = loadEditController();
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

  test('edit add work button opens the upload dialog trigger', () => {
    const openUploadDialogButton = Object.assign(Object.create(HTMLButtonElement.prototype), {
      clickCalled: false,
      addEventListener() {},
      click() {
        this.clickCalled = true;
      },
    });
    const documentMock = {
      getElementById(id) {
        if (id === 'editOpenUploadDialog') {
          return openUploadDialogButton;
        }
        return null;
      },
      querySelector() {
        return null;
      },
    };
    const { createEditController } = loadEditController(documentMock);
    const editAddWork = Object.assign(Object.create(HTMLButtonElement.prototype), {
      addEventListener(type, handler) {
        this.listener = handler;
      },
      clickCalled: false,
      click() {
        this.clickCalled = true;
      },
    });

    const controller = createEditController({
      elements: {
        editPanel: null,
        editDrawer: null,
        editAuthDetails: null,
        editToggle: null,
        editAddWork,
        editAuthForm: null,
        editAuthPassword: null,
        editAuthSubmit: null,
        editAuthStatus: null,
      },
      context: {
        currentPoffConfig: null,
        cmsAuth: {
          configured: true,
          authenticated: true,
          editModeAllowed: true,
          canEdit: true,
        },
      },
      editRequested: true,
    });

    controller.bindAddWorkButton();
    expect(typeof editAddWork.listener).toBe('function');
    editAddWork.listener();
    expect(openUploadDialogButton.clickCalled).toBe(true);
  });
});

test('creating a converter navigates to the new converter folder path', async () => {
  let capturedPanelOptions = null;
  const editPanel = {
    querySelector() {
      return null;
    },
  };
  const auth = {
    configured: true,
    authenticated: true,
    editModeAllowed: true,
    canEdit: true,
  };
  const windowMock = {
    location: {
      href: 'http://example.test/MAUSMAUS/?edit=true',
      search: '?edit=true',
      hash: '#/bild2-eggermann-tif',
      pathname: '/MAUSMAUS/',
    },
    dispatchEvent() {},
    prompt() {
      return 'convert-image';
    },
    history: {
      replaceState() {},
    },
    addEventListener() {},
  };

  const { createEditController } = loadEditController(null, {
    window: windowMock,
    requestEditAuth: async () => ({ auth }),
    requestEditConfig: async () => ({
      auth,
      allowed: true,
      target: 'file',
      subjectTarget: 'file',
      config: {
        kind: 'image',
        work: {
          type: 'image',
        },
      },
    }),
    requestMcpRoute: async (route) => {
      expect(route).toBe('create-converter');
      return {
        ok: true,
        folder: 'converters/convert-image',
        definition: {
          id: 'converter/convert-image',
          path: 'converters/convert-image',
          name: 'convert-image',
          label: 'Convert Image',
        },
      };
    },
    getActiveSelection: () => ({
      path: 'bild2-eggermann.tif',
      previewPath: 'bild2-eggermann.tif',
      previewIsFile: true,
      isFile: true,
      isLayout: false,
    }),
    getEditTargetPath: () => 'bild2-eggermann.tif',
    renderEditPanel(options) {
      capturedPanelOptions = options;
      return { statusEl: null, promptRoot: null };
    },
  });

  const controller = createEditController({
    elements: {
      editPanel,
      editDrawer: null,
      editAuthDetails: null,
      editToggle: null,
      editAddWork: null,
      editAuthForm: null,
      editAuthPassword: null,
      editAuthSubmit: null,
      editAuthStatus: null,
    },
    context: {
      currentPoffConfig: null,
      cmsAuth: auth,
    },
    editRequested: true,
  });

  await controller.initEditMode();
  expect(capturedPanelOptions).toBeTruthy();
  await capturedPanelOptions.onCreateConverter({
    statusEl: { textContent: '' },
    form: null,
  });

  expect(windowMock.location.hash).toBe('#/converters/convert-image');
});
