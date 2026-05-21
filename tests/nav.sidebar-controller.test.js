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
  return {
    attributes: { ...attributes },
    classList: createClassList(),
    scrollIntoView: jest.fn(),
    getAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
    },
    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name);
    },
  };
}

function loadSidebarController() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/nav/sidebar-controller.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const windowMock = {
    dispatchEvent: jest.fn(),
  };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    window: windowMock,
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
  return module.exports.createSidebarController;
}

describe('sidebar selection scroll', () => {
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

});
