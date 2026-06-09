const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadPathsModule(windowMock = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/controller/paths.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const window = {
    POFF_CONTEXT: null,
    ...windowMock,
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    window,
    document: {
      querySelector() {
        return null;
      },
    },
    URL,
    getActiveSelection: () => ({ path: '', previewPath: '', previewIsFile: false, isFile: false, isLayout: false }),
  });

  vm.runInContext(`${source}
module.exports = {
  getContentTargetPath,
  getAddWorkTargetPath,
  getEditTargetPath,
};`, context);

  return module.exports;
}

describe('edit controller target path helpers', () => {
  test('keeps the configured link path as the edit target', () => {
    const { getEditTargetPath } = loadPathsModule({
      POFF_CONTEXT: {
        currentPoffConfig: {
          tree: [
            { name: 'plim', path: 'plim', type: 'link' },
          ],
        },
      },
    });

    expect(getEditTargetPath({ path: 'plim', previewPath: 'plim', previewIsFile: true, isLayout: false })).toBe('plim');
  });

  test('keeps folder targets unchanged', () => {
    const { getContentTargetPath } = loadPathsModule({
      POFF_CONTEXT: {
        currentPoffConfig: {
          tree: [
            { name: 'MAUSMAUS', path: '', type: 'folder' },
          ],
        },
      },
    });

    expect(getContentTargetPath({ path: '', previewPath: '', previewIsFile: false, isLayout: false })).toBe('');
    expect(getContentTargetPath({ path: 'trudels', previewPath: 'trudels', previewIsFile: false, isLayout: false })).toBe('trudels');
  });

  test('uses the containing folder for file previews and add-work targets', () => {
    const { getAddWorkTargetPath, getContentTargetPath } = loadPathsModule();

    expect(getContentTargetPath({ path: 'folder/file.mp4', previewPath: 'folder/file.mp4', previewIsFile: true, isLayout: false })).toBe('folder');
    expect(getAddWorkTargetPath({ path: 'folder/file.mp4', previewPath: 'folder/file.mp4', previewIsFile: true, isLayout: false })).toBe('folder');
  });

  test('uses the parent folder for hidden dotfiles', () => {
    const { getAddWorkTargetPath, getContentTargetPath } = loadPathsModule();

    expect(getContentTargetPath({ path: 'folder/.htaccess', previewPath: 'folder/.htaccess', previewIsFile: true, isLayout: false })).toBe('folder');
    expect(getAddWorkTargetPath({ path: 'folder/.htaccess', previewPath: 'folder/.htaccess', previewIsFile: true, isLayout: false })).toBe('folder');
  });
});
