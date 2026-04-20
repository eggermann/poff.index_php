const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadAppHelpers() {
  const constantsPath = path.join(__dirname, '..', 'src/assets/js/app/constants.js');
  const helpersPath = path.join(__dirname, '..', 'src/assets/js/app/helpers.js');
  const constantsSource = fs.readFileSync(constantsPath, 'utf8')
    .replace(/export const /g, 'const ');
  const helpersSource = fs.readFileSync(helpersPath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const listeners = new Map();
  const documentElements = new Map();

  const createClassList = () => {
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
  };

  const createElement = (id) => {
    const attributes = new Map();
    return {
      id,
      hidden: false,
      classList: createClassList(),
      setAttribute(name, value) {
        attributes.set(name, String(value));
      },
      getAttribute(name) {
        return attributes.get(name) || null;
      },
      addEventListener(type, handler) {
        listeners.set(`${id}:${type}`, handler);
      },
      removeEventListener(type) {
        listeners.delete(`${id}:${type}`);
      },
    };
  };

  const document = {
    getElementById(id) {
      if (!documentElements.has(id)) {
        documentElements.set(id, createElement(id));
      }
      return documentElements.get(id);
    },
  };

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    document,
    __dirname: path.dirname(helpersPath),
    __filename: helpersPath,
  });

  vm.runInContext(`${constantsSource}
${helpersSource}
module.exports = {
  APP_ELEMENT_IDS,
  APP_LABELS,
  createAppElements,
  bindSidebarToggle,
};
`, context);

  return {
    ...module.exports,
    documentElements,
    listeners,
  };
}

describe('app sidebar toggle wiring', () => {
  const {
    APP_ELEMENT_IDS,
    APP_LABELS,
    bindSidebarToggle,
    createAppElements,
    documentElements,
    listeners,
  } = loadAppHelpers();

  test('createAppElements exposes the appShell element required by the sidebar toggle helper', () => {
    const elements = createAppElements();

    expect(Object.keys(APP_ELEMENT_IDS)).toContain('appShell');
    expect(elements.appShell).toBe(documentElements.get('appShell'));
    expect(elements.appSidebar).toBe(documentElements.get('appSidebar'));
    expect(elements.sidebarToggle).toBe(documentElements.get('sidebarToggle'));
  });

  test('bindSidebarToggle wires the toggle click and updates open/closed state', () => {
    const elements = createAppElements();
    const controller = bindSidebarToggle(elements);

    expect(controller).not.toBeNull();
    expect(listeners.has('sidebarToggle:click')).toBe(true);

    const onClick = listeners.get('sidebarToggle:click');
    const { appShell, appSidebar, sidebarToggle } = elements;

    expect(appShell.classList.contains('sidebar-collapsed')).toBe(false);
    expect(appSidebar.hidden).toBe(false);
    expect(appSidebar.getAttribute('aria-hidden')).toBe('false');
    expect(sidebarToggle.getAttribute('aria-expanded')).toBe('true');
    expect(sidebarToggle.getAttribute('aria-label')).toBe(APP_LABELS.closeNavigation);

    onClick();

    expect(appShell.classList.contains('sidebar-collapsed')).toBe(true);
    expect(appSidebar.hidden).toBe(true);
    expect(appSidebar.getAttribute('aria-hidden')).toBe('true');
    expect(sidebarToggle.getAttribute('aria-expanded')).toBe('false');
    expect(sidebarToggle.getAttribute('aria-label')).toBe(APP_LABELS.openNavigation);
  });
});
