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
  let sidebarNavigate = null;
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
    createSidebarController: overrides.createSidebarController || ((params = {}) => {
      sidebarNavigate = params.navigateToPath;
      return {
        bindNavClick() {},
        loadNav(pathValue) {
          sidebarCalls.push(pathValue);
          return Promise.resolve();
        },
        syncSidebarSelection() {},
      };
    }),
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
    getSidebarNavigate() {
      return sidebarNavigate;
    },
  };
}

describe('navigation', () => {
  test('ignores stale sidebar completion after a newer navigation starts', async () => {
    const pending = [];
    const syncSidebarSelection = jest.fn();
    const iframeLabel = { textContent: '' };
    const sidebarLabel = { textContent: '' };
    const createLoadingElement = (labelElement) => ({
      classList: {
        toggle() {},
      },
      setAttribute() {},
      querySelector(selector) {
        return selector === '.loader-label' ? labelElement : null;
      },
    });
    const { initNavigation } = loadNavigation({
      createSidebarController: (params = {}) => {
        return {
          bindNavClick() {},
          loadNav(pathValue, options = {}) {
            params.setLoadingVisible(params.sidebarLoading, true, options.loadingLabel);
            return new Promise((resolve) => pending.push(resolve));
          },
          syncSidebarSelection,
        };
      },
    });
    const navigation = initNavigation({
      elements: {
        navList: {},
        contentFrame: {
          classList: {
            toggle() {},
          },
        },
        iframeLoading: createLoadingElement(iframeLabel),
        sidebarLoading: createLoadingElement(sidebarLabel),
      },
      editQuery: '',
      currentPathForIframe: '',
      renderFolderMeta() {},
      initEditMode() {},
    });

    const firstNavigation = navigation.syncFromLocation();
    const secondNavigation = navigation.syncFromLocation();
    await Promise.resolve();
    await Promise.resolve();
    expect(iframeLabel.textContent).toBe('Opening the studio archive…');
    expect(sidebarLabel.textContent).toBe('Loading work menu…');
    pending[0]();
    await Promise.resolve();
    expect(syncSidebarSelection).not.toHaveBeenCalled();

    pending[1]();
    await Promise.all([firstNavigation, secondNavigation]);
    await Promise.resolve();
    expect(syncSidebarSelection).toHaveBeenCalledTimes(1);
  });

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

  test('does not fetch sidebar navigation while the public explorer is collapsed', async () => {
    const { initNavigation, sidebarCalls } = loadNavigation();
    const navigation = initNavigation({
      elements: {
        navList: {},
        contentFrame: { classList: { toggle() {} } },
        iframeLoading: { classList: { toggle() {} } },
        sidebarLoading: { classList: { toggle() {} } },
      },
      editQuery: '',
      currentPathForIframe: 'works/first.jpg',
      renderFolderMeta() {},
      initEditMode() {},
      isSidebarOpen() {
        return false;
      },
    });

    await navigation.syncFromLocation();

    expect(sidebarCalls).toEqual([]);
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

  test('sidebar direct preview urls render remote target without replacing local selection path', () => {
    const { initNavigation, previewCalls, getSidebarNavigate } = loadNavigation();
    initNavigation({
      elements: {
        navList: {
          querySelectorAll() {
            return [];
          },
        },
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
      initEditMode() {},
    });

    const navigateToPath = getSidebarNavigate();
    navigateToPath('mcmmcmc', {
      isFile: true,
      previewUrl: 'https://dominikeggermann.com/?view=1&path=portfolio',
    });

    expect(previewCalls).toEqual([
      'https://dominikeggermann.com/?view=1&path=portfolio',
    ]);
  });
});
