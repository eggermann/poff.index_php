const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadPaths(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/controller/paths.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\.\/\.\.\/core\/selection\.js';\n?/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    document: overrides.document || {
      querySelector() {
        return null;
      },
    },
    window: overrides.window || {},
    getActiveSelection: overrides.getActiveSelection || (() => ({
      path: '',
      previewPath: '',
      previewIsFile: false,
      isLayout: false,
    })),
  });

  vm.runInContext(`${source}
module.exports = {
  getContentTargetPath,
  getEditTargetPath,
};
`, context);

  return module.exports;
}

describe('edit target paths', () => {
  test('uses the active nav file path when the current hash is a slug', () => {
    const activeLink = {
      getAttribute(name) {
        if (name === 'data-path') {
          return 'notes.txt';
        }
        if (name === 'data-file') {
          return 'notes.txt';
        }
        return '';
      },
      hasAttribute(name) {
        return name === 'data-file';
      },
    };
    const { getEditTargetPath } = loadPaths({
      document: {
        querySelector(selector) {
          return selector === '#navList a.nav-link-active[data-path]' ? activeLink : null;
        },
      },
    });

    expect(getEditTargetPath({
      path: 'notes-txt',
      previewPath: 'notes-txt',
      previewIsFile: false,
      isLayout: false,
    })).toBe('notes.txt');
  });
});
