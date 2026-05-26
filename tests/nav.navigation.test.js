const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadNavigation(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/nav/navigation.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\/preview-controller\.js';\n?/g, '')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\/route-resolver\.js';\n?/g, '')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\/sidebar-controller\.js';\n?/g, '')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\.\/core\/selection\.js';\n?/g, '')
    .replace(/export function /g, 'function ');

  const previewCalls = [];
  const sidebarCalls = [];
  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    URL,
    URLSearchParams,
    window: {
      location: {
        href: 'http://example.test/?path=docs/example.txt',
        pathname: '/',
        search: '?path=docs/example.txt',
        hash: '',
      },
      history: {
        replaceState() {},
      },
    },
    getSelectionFromPath(pathValue) {
      return {
        path: pathValue,
        previewPath: pathValue,
        previewIsFile: true,
        isLayout: false,
      };
    },
    createPreviewController: overrides.createPreviewController || (() => ({
      renderPreview(url) {
        previewCalls.push(url);
      },
      buildViewerUrl(pathValue, isFile, forceRefresh) {
        return `viewer:${pathValue}:${isFile ? 'file' : 'folder'}:${forceRefresh ? 'refresh' : 'plain'}`;
      },
      syncPreviewDisabledState() {},
      bindPreviewNavigation() {},
    })),
    createSidebarController: overrides.createSidebarController || (() => ({
      bindNavClick() {},
      loadNav(pathValue) {
        sidebarCalls.push(pathValue);
        return Promise.resolve();
      },
      syncSidebarSelection() {},
    })),
    createRouteResolver: overrides.createRouteResolver || (() => ({
      readHashPath() {
        return '';
      },
      displayHashPath(pathValue) {
        return pathValue;
      },
      resolveHashPath(pathValue) {
        return {
          path: pathValue,
          isFile: true,
        };
      },
      resolveHashPathAsync(pathValue) {
        return Promise.resolve({
          path: pathValue,
          isFile: true,
        });
      },
      rememberSlugPathAlias() {},
    })),
  });

  vm.runInContext(`${source}
module.exports = {
  initNavigation,
};
`, context);

  return {
    initNavigation: module.exports.initNavigation,
    previewCalls,
    sidebarCalls,
  };
}

describe('navigation', () => {
  test('refreshCurrentPreviewLocation refreshes preview without reinitializing edit mode', async () => {
    const initEditMode = jest.fn();
    const { initNavigation, previewCalls, sidebarCalls } = loadNavigation();
    const navigation = initNavigation({
      elements: {
        navList: {},
        contentFrame: {
          classList: {
            toggle() {},
          },
        },
        iframeLoading: {
          classList: {
            toggle() {},
          },
        },
        sidebarLoading: {
          classList: {
            toggle() {},
          },
        },
      },
      editQuery: '',
      currentPathForIframe: 'docs/example.txt',
      renderFolderMeta() {},
      initEditMode,
      getPreviewParams() {
        return { converter_preview: '1' };
      },
    });

    navigation.refreshCurrentPreviewLocation();

    expect(initEditMode).not.toHaveBeenCalled();
    expect(previewCalls).toHaveLength(1);
    expect(previewCalls[0]).toContain('viewer:docs/example.txt:file:refresh');
    expect(sidebarCalls).toHaveLength(0);
  });

  test('refreshCurrentPreviewLocation resolves slug hashes before refreshing preview', async () => {
    const initEditMode = jest.fn();
    const { initNavigation, previewCalls, sidebarCalls } = loadNavigation({
      createRouteResolver: () => ({
        readHashPath() {
          return 'notes-txt';
        },
        displayHashPath(pathValue) {
          return pathValue;
        },
        resolveHashPath(pathValue) {
          if (pathValue === 'notes-txt') {
            return {
              path: 'notes.txt',
              isFile: true,
            };
          }
          return {
            path: pathValue,
            isFile: false,
          };
        },
        resolveHashPathAsync(pathValue) {
          return Promise.resolve(this.resolveHashPath(pathValue));
        },
        rememberSlugPathAlias() {},
      }),
    });
    const navigation = initNavigation({
      elements: {
        navList: {},
        contentFrame: {
          classList: {
            toggle() {},
          },
        },
        iframeLoading: {
          classList: {
            toggle() {},
          },
        },
        sidebarLoading: {
          classList: {
            toggle() {},
          },
        },
      },
      editQuery: '',
      currentPathForIframe: '',
      renderFolderMeta() {},
      initEditMode,
      getPreviewParams() {
        return { converter_preview: '1' };
      },
    });

    navigation.refreshCurrentPreviewLocation();

    expect(initEditMode).not.toHaveBeenCalled();
    expect(previewCalls).toHaveLength(1);
    expect(previewCalls[0]).toContain('viewer:notes.txt:file:refresh');
    expect(previewCalls[0]).not.toContain('viewer:notes-txt:folder');
    expect(sidebarCalls).toHaveLength(0);
  });
});
