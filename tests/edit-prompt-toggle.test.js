const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadSyncPromptDock() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/panel/shared.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const promptDock = {
    children: [],
    replaceChildren(...nodes) {
      this.children = nodes;
    },
    appendChild(node) {
      this.children.push(node);
      return node;
    },
  };
  const document = {
    querySelector(selector) {
      return selector === '#promptDock' ? promptDock : null;
    },
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    document,
    __dirname: path.dirname(filePath),
    __filename: filePath,
  });

  vm.runInContext(`${source}
module.exports = {
  syncPromptDock,
};
`, context);

  return { syncPromptDock: module.exports.syncPromptDock, promptDock };
}

function loadPromptLayerController(documentMock) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/prompt/layer.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    document: documentMock,
    __dirname: path.dirname(filePath),
    __filename: filePath,
  });

  vm.runInContext(`${source}
module.exports = {
  createPromptLayerController,
};
`, context);

  return module.exports;
}

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

describe('prompt floating toggle regressions', () => {
  const { syncPromptDock, promptDock } = loadSyncPromptDock();

  test('syncPromptDock moves the floating prompt root as one docked unit', () => {
    const floatingRoot = { id: 'promptFloatingRoot' };
    const promptRoot = {
      querySelector(selector) {
        return selector === '#promptFloatingRoot' ? floatingRoot : null;
      },
    };

    syncPromptDock(promptRoot);

    expect(promptDock.children).toEqual([promptRoot]);
  });

  test('prompt layer controller toggles the moved floating root and button visibility', () => {
    const root = {
      classList: createClassList(),
    };
    const promptWindowEl = { hidden: false };
    const promptLayerCloseEl = { hidden: false, addEventListener() {} };
    const promptLayerOpenEl = { hidden: true, addEventListener() {} };
    const { createPromptLayerController } = loadPromptLayerController({
      addEventListener() {},
    });
    const controller = createPromptLayerController({
      root,
      windowEl: promptWindowEl,
      closeEl: promptLayerCloseEl,
      openEl: promptLayerOpenEl,
      storageKey: 'prompt-layer-state',
      storage: {
        getItem() {
          return null;
        },
        setItem() {},
      },
    });

    controller.applyState(true, { skipPersist: true });
    expect(root.classList.contains('prompt-layer-collapsed')).toBe(true);
    expect(promptWindowEl.hidden).toBe(true);
    expect(promptLayerCloseEl.hidden).toBe(true);
    expect(promptLayerOpenEl.hidden).toBe(false);

    controller.applyState(false, { skipPersist: true });

    expect(root.classList.contains('prompt-layer-collapsed')).toBe(false);
    expect(promptWindowEl.hidden).toBe(false);
    expect(promptLayerCloseEl.hidden).toBe(false);
    expect(promptLayerOpenEl.hidden).toBe(true);
  });

  test('Escape closes the prompt layer', () => {
    const listeners = {};
    const documentMock = {
      addEventListener(type, handler) {
        listeners[type] = handler;
      },
    };
    const root = {
      classList: createClassList(),
    };
    const promptWindowEl = { hidden: false };
    const promptLayerCloseEl = { hidden: false, addEventListener() {} };
    const promptLayerOpenEl = { hidden: true, addEventListener() {} };

    const { createPromptLayerController } = loadPromptLayerController(documentMock);
    createPromptLayerController({
      root,
      windowEl: promptWindowEl,
      closeEl: promptLayerCloseEl,
      openEl: promptLayerOpenEl,
      storageKey: 'prompt-layer-state',
      storage: {
        getItem() {
          return null;
        },
        setItem() {},
      },
    });

    expect(typeof listeners.keydown).toBe('function');
    listeners.keydown({ key: 'Escape' });
    expect(root.classList.contains('prompt-layer-collapsed')).toBe(true);
    expect(promptWindowEl.hidden).toBe(true);
    expect(promptLayerCloseEl.hidden).toBe(true);
    expect(promptLayerOpenEl.hidden).toBe(false);
  });
});
