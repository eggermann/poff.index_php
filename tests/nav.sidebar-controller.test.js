const fs = require('fs');
const path = require('path');
const vm = require('vm');

function createClassList() {
  const classes = new Set();
  return {
    add(name) {
      classes.add(name);
    },
    remove(name) {
      classes.delete(name);
    },
    contains(name) {
      return classes.has(name);
    },
  };
}

function createNavLink(attributes) {
  const dataset = {};
  Object.entries(attributes).forEach(([name, value]) => {
    if (!name.startsWith('data-')) {
      return;
    }
    const datasetKey = name
      .slice(5)
      .replace(/-([a-z])/g, (_, char) => char.toUpperCase());
    dataset[datasetKey] = value;
  });
  return {
    attributes: { ...attributes },
    dataset,
    tagName: 'A',
    parentElement: null,
    classList: createClassList(),
    scrollIntoView: jest.fn(),
    getAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
    },
    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name);
    },
    closest() {
      return null;
    },
  };
}

function loadSidebarController(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/nav/sidebar-controller.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const requestEditDeleteMock = jest.fn();
  const requestEditConfigMock = jest.fn();
  const windowMock = {
    dispatchEvent: jest.fn(),
    confirm: jest.fn(() => true),
    alert: jest.fn(),
    prompt: jest.fn(),
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    window: windowMock,
    AbortController,
    fetch: overrides.fetch || jest.fn(),
    extractNavHtml: overrides.extractNavHtml || ((html) => html),
    requestEditDelete: requestEditDeleteMock,
    requestEditConfig: requestEditConfigMock,
    CustomEvent: class CustomEvent {
      constructor(type, init = {}) {
        this.type = type;
        this.detail = init.detail;
      }
    },
  });

  vm.runInContext(`${source}
module.exports = {
  createSidebarController,
};
`, context);

  module.exports.createSidebarController.__windowMock = windowMock;
  module.exports.createSidebarController.__requestEditDeleteMock = requestEditDeleteMock;
  module.exports.createSidebarController.__requestEditConfigMock = requestEditConfigMock;
  return module.exports.createSidebarController;
}

describe('sidebar selection scroll', () => {
  test('aborts stale navigation requests and only renders the latest response', async () => {
    const requests = [];
    const fetchMock = jest.fn((url, options = {}) => new Promise((resolve, reject) => {
      const abortError = new Error('aborted');
      abortError.name = 'AbortError';
      options.signal?.addEventListener('abort', () => reject(abortError), { once: true });
      requests.push({ url, options, resolve });
    }));
    const createSidebarController = loadSidebarController({ fetch: fetchMock });
    const navList = {
      dataset: {},
      innerHTML: '',
    };
    const controller = createSidebarController({
      navList,
      editQuery: '',
      navigateToPath() {},
      getCurrentSelection() {
        return null;
      },
      setLoadingVisible() {},
    });

    const firstRequest = controller.loadNav('slow-folder');
    const secondRequest = controller.loadNav('latest-folder');

    expect(requests[0].options.signal.aborted).toBe(true);
    requests[1].resolve({
      ok: true,
      text: async () => '<li>latest navigation</li>',
    });

    await Promise.all([firstRequest, secondRequest]);

    expect(navList.innerHTML).toBe('<li>latest navigation</li>');
    expect(navList.dataset.loaded).toBe('1');
    expect(navList.dataset.error).toBeUndefined();
  });

  test('scrolls the exact active list item into view even when filenames repeat', () => {
    const createSidebarController = loadSidebarController();
    const first = createNavLink({
      'data-path': 'album/first.mp4',
      'data-file': 'clip.mp4',
    });
    const second = createNavLink({
      'data-path': 'album/second.mp4',
      'data-file': 'clip.mp4',
    });
    const navList = {
      querySelectorAll(selector) {
        if (selector === '.nav-link-active') {
          return [first, second].filter((link) => link.classList.contains('nav-link-active'));
        }
        if (selector === 'a[data-path]') {
          return [first, second];
        }
        if (selector === 'a[data-layout-path]') {
          return [];
        }
        if (selector === 'a[data-file]') {
          return [first, second];
        }
        return [];
      },
    };

    const controller = createSidebarController({
      navList,
      editQuery: '',
      navigateToPath() {},
      getCurrentSelection() {
        return null;
      },
      setLoadingVisible() {},
    });

    controller.syncSidebarSelection('album/second.mp4', true, false);

    expect(first.classList.contains('nav-link-active')).toBe(false);
    expect(second.classList.contains('nav-link-active')).toBe(true);
    expect(first.scrollIntoView).not.toHaveBeenCalled();
    expect(second.scrollIntoView).toHaveBeenCalledWith({
      behavior: 'smooth',
      block: 'center',
      inline: 'nearest',
    });
  });

  test('centers the selected row inside the sidebar viewport', () => {
    const createSidebarController = loadSidebarController();
    const sidebar = {
      scrollTop: 0,
      clientHeight: 800,
      getBoundingClientRect() {
        return { top: 0, height: 800 };
      },
    };
    const selected = createNavLink({
      'data-path': 'album/deep/file.mp4',
    });
    selected.getBoundingClientRect = () => ({ top: 1500, height: 56 });
    const navList = {
      closest(selector) {
        return selector === '#appSidebar' ? sidebar : null;
      },
      querySelectorAll(selector) {
        if (selector === '.nav-link-active') {
          return selected.classList.contains('nav-link-active') ? [selected] : [];
        }
        if (selector === 'a[data-path]') {
          return [selected];
        }
        if (selector === 'a[data-layout-path]') {
          return [];
        }
        if (selector === 'a[data-file]') {
          return [selected];
        }
        return [];
      },
    };

    const controller = createSidebarController({
      navList,
      editQuery: '',
      navigateToPath() {},
      getCurrentSelection() {
        return null;
      },
      setLoadingVisible() {},
    });

    controller.syncSidebarSelection('album/deep/file.mp4', true, false);

    expect(sidebar.scrollTop).toBe(1128);
  });

  test('uses explicit file metadata for extensionless virtual link items', () => {
    const createSidebarController = loadSidebarController();
    const navigateToPath = jest.fn();
    const target = createNavLink({
      href: '#',
      'data-path': 'eggermann',
      'data-is-file': '1',
      'data-view-url': 'https://dominikeggermann.com/?view=1&path=portfolio',
    });
    const navList = {
      addEventListener(type, handler) {
        if (type === 'click') {
          this.handler = handler;
        }
      },
    };

    const controller = createSidebarController({
      navList,
      editQuery: '',
      navigateToPath,
      getCurrentSelection() {
        return null;
      },
      setLoadingVisible() {},
    });

    controller.bindNavClick();

    navList.handler({
      target,
      preventDefault: jest.fn(),
    });

    expect(navigateToPath).toHaveBeenCalledWith('eggermann', {
      isFile: true,
      previewUrl: 'https://dominikeggermann.com/?view=1&path=portfolio',
    });
  });

  test('delete actions call the delete endpoint and refresh to the parent folder', async () => {
    const createSidebarController = loadSidebarController();
    const navigateToPath = jest.fn();
    const requestEditDeleteMock = createSidebarController.__requestEditDeleteMock;
    const windowMock = createSidebarController.__windowMock;
    requestEditDeleteMock.mockResolvedValue({ deleted: [{ path: 'album/second.mp4' }] });

    const targetLink = createNavLink({
      'data-path': 'album/second.mp4',
      'data-is-file': '1',
    });
    const row = {
      querySelector(selector) {
        if (selector === 'a[data-tree-item="1"]') {
          return targetLink;
        }
        return null;
      },
    };
    const deleteButton = {
      closest(selector) {
        if (selector === '[data-nav-action="delete-target"]') {
          return this;
        }
        if (selector === 'li') {
          return row;
        }
        return null;
      },
      getAttribute(name) {
        if (name === 'data-nav-path') {
          return 'album/second.mp4';
        }
        return null;
      },
    };
    const navList = {
      addEventListener(type, handler) {
        if (type === 'click') {
          this.handler = handler;
        }
      },
    };

    const controller = createSidebarController({
      navList,
      editQuery: '',
      navigateToPath,
      getCurrentSelection() {
        return null;
      },
      setLoadingVisible() {},
    });

    controller.bindNavClick();

    await navList.handler({
      target: deleteButton,
      preventDefault: jest.fn(),
      stopPropagation: jest.fn(),
    });

    await Promise.resolve();
    await Promise.resolve();

    expect(windowMock.confirm).toHaveBeenCalledWith('Delete this item? This cannot be undone.');
    expect(requestEditDeleteMock).toHaveBeenCalledWith({
      path: 'album/second.mp4',
    });
    expect(navigateToPath).toHaveBeenCalledWith('album', {
      isFile: false,
      forceRefresh: true,
    });
  });

  test('move actions preserve the source path, send destination and refresh its folder', async () => {
    const createSidebarController = loadSidebarController();
    const navigateToPath = jest.fn();
    const requestEditConfigMock = createSidebarController.__requestEditConfigMock;
    const windowMock = createSidebarController.__windowMock;
    windowMock.prompt
      .mockReturnValueOnce('archive/2026')
      .mockReturnValueOnce('final.jpg');
    requestEditConfigMock.mockResolvedValue({ allowed: true, moved: [{ to: 'archive/2026/final.jpg' }] });

    const targetLink = createNavLink({ 'data-path': 'photos/draft.jpg', 'data-is-file': '1' });
    const row = { querySelector: () => targetLink };
    const moveButton = {
      closest(selector) {
        if (selector === '[data-nav-action="move-target"]') return this;
        if (selector === 'li') return row;
        return null;
      },
      getAttribute(name) {
        return name === 'data-nav-path' ? 'photos/draft.jpg' : null;
      },
    };
    const navList = { addEventListener(type, handler) { if (type === 'click') this.handler = handler; } };
    const controller = createSidebarController({ navList, editQuery: '', navigateToPath, setLoadingVisible() {} });

    controller.bindNavClick();
    await navList.handler({ target: moveButton, preventDefault: jest.fn(), stopPropagation: jest.fn() });
    await Promise.resolve();
    await Promise.resolve();

    expect(requestEditConfigMock).toHaveBeenCalledWith('move', {
      path: 'photos/draft.jpg',
      destination: 'archive/2026',
      name: 'final.jpg',
    });
    expect(navigateToPath).toHaveBeenCalledWith('archive/2026', {
      isFile: false,
      forceRefresh: true,
    });
  });

});
