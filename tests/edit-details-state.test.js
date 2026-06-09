const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadSharedHelpers() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/panel/shared.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const storage = {
    data: new Map(),
    getItem(key) {
      return this.data.has(key) ? this.data.get(key) : null;
    },
    setItem(key, value) {
      this.data.set(key, String(value));
    },
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    localStorage: storage,
    HTMLInputElement: function HTMLInputElement() {},
    HTMLTextAreaElement: function HTMLTextAreaElement() {},
    HTMLSelectElement: function HTMLSelectElement() {},
    getLayoutState: () => ({}),
  });

  vm.runInContext(`${source}
module.exports = {
  readStoredDetailsState,
  writeStoredDetailsState,
  bindStoredDetailsState,
};
`, context);

  return { ...module.exports, storage };
}

function createDetailsMock() {
  const listeners = new Map();
  return {
    open: false,
    addEventListener(type, handler) {
      listeners.set(type, handler);
    },
    removeEventListener(type, handler) {
      if (listeners.get(type) === handler) {
        listeners.delete(type);
      }
    },
    dispatch(type) {
      const handler = listeners.get(type);
      if (handler) {
        handler();
      }
    },
  };
}

describe('persistent edit details state', () => {
  test('reads, writes, and binds the details open state', () => {
    const { readStoredDetailsState, writeStoredDetailsState, bindStoredDetailsState, storage } = loadSharedHelpers();
    const details = createDetailsMock();

    expect(readStoredDetailsState('edit.panel.password-details')).toBeNull();

    writeStoredDetailsState('password-details', true, storage);
    expect(JSON.parse(storage.getItem('poff.edit.details:password-details'))).toEqual({ open: true });
    expect(readStoredDetailsState('password-details', storage)).toBe(true);

    const cleanup = bindStoredDetailsState(details, 'password-details', storage);
    details.open = false;
    details.dispatch('toggle');
    expect(JSON.parse(storage.getItem('poff.edit.details:password-details'))).toEqual({ open: false });

    cleanup();
  });
});
