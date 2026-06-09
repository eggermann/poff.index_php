const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadRenderEditDrawerMarkup() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/drawer/render.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    escapeHtml: (value) => String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;'),
    renderPersistentDetailsSection: ({ bodyHtml = '' }) => bodyHtml,
  });

  vm.runInContext(`${source}
module.exports = { renderEditDrawerMarkup };
`, context);

  return module.exports.renderEditDrawerMarkup;
}

function createElementMock() {
  const listeners = new Map();
  return {
    checked: false,
    indeterminate: false,
    value: '',
    addEventListener(type, handler) {
      listeners.set(type, handler);
    },
    querySelector() {
      return null;
    },
    focus() {},
    getListener(type) {
      return listeners.get(type);
    },
  };
}

function loadBindEditDrawerInteractions(windowMock) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/drawer/bind.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    bindStoredDetailsState() {},
    window: windowMock,
  });

  vm.runInContext(`${source}
module.exports = { bindEditDrawerInteractions };
`, context);

  return module.exports.bindEditDrawerInteractions;
}

describe('edit drawer delete action', () => {
  test('renders a delete button when enabled', () => {
    const renderEditDrawerMarkup = loadRenderEditDrawerMarkup();
    const html = renderEditDrawerMarkup({
      config: { work: {} },
      status: { target: 'file' },
      treeHtml: '',
      treeItems: [],
      showDeleteAction: true,
    });

    expect(html).toContain('id="editDrawerDeleteTarget"');
    expect(html).toContain('Delete');
  });

  test('binds drawer delete button to the shared delete callback', async () => {
    const confirmMock = jest.fn(() => true);
    const setTimeoutMock = jest.fn();
    const windowMock = {
      confirm: confirmMock,
      setTimeout: setTimeoutMock,
    };
    const bindEditDrawerInteractions = loadBindEditDrawerInteractions(windowMock);
    const drawerStatus = createElementMock();
    const deleteTargetButton = createElementMock();
    const editDrawer = {
      querySelector(selector) {
        if (selector === '#editDrawerStatus') {
          return drawerStatus;
        }
        if (selector === '#editDrawerDeleteTarget') {
          return deleteTargetButton;
        }
        return null;
      },
      querySelectorAll() {
        return [];
      },
    };
    const onDeleteTarget = jest.fn().mockResolvedValue(undefined);

    bindEditDrawerInteractions({
      editDrawer,
      status: { target: 'file' },
      onDeleteTarget,
    });

    await deleteTargetButton.getListener('click')();

    expect(confirmMock).toHaveBeenCalledWith('Delete this item? This cannot be undone.');
    expect(onDeleteTarget).toHaveBeenCalledWith({ statusEl: drawerStatus });
  });
});
