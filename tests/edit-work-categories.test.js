const fs = require('fs');
const path = require('path');
const vm = require('vm');

class MockHTMLInputElement {}
class MockHTMLTextAreaElement {}
class MockHTMLSelectElement {}
class MockEvent {
  constructor(type, init = {}) {
    this.type = type;
    this.bubbles = !!init.bubbles;
  }
}

function loadCategoryHelpers() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/panel/categories.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
    HTMLInputElement: MockHTMLInputElement,
    HTMLTextAreaElement: MockHTMLTextAreaElement,
    HTMLSelectElement: MockHTMLSelectElement,
    Event: MockEvent,
    escapeHtml: (value) => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;'),
    bindStoredDetailsState() {
      return () => {};
    },
    renderPersistentDetailsSection({
      defaultOpen = true,
      id = '',
      className = '',
      summaryClassName = '',
      bodyClassName = '',
      titleHtml = '',
      noteHtml = '',
      bodyHtml = '',
    } = {}) {
      const detailsClasses = ['edit-collapsible-details', className].filter(Boolean).join(' ');
      const summaryClasses = ['edit-work-fields-header', 'edit-collapsible-summary', summaryClassName].filter(Boolean).join(' ');
      const bodyClasses = ['edit-collapsible-details-body', bodyClassName].filter(Boolean).join(' ');

      return `
        <details class="${detailsClasses}"${id ? ` id="${id}"` : ''}${defaultOpen ? ' open' : ''}>
            <summary class="${summaryClasses}">
                <div>
                    ${titleHtml ? `<div class="edit-work-fields-title">${titleHtml}</div>` : ''}
                    ${noteHtml ? `<div class="small-note">${noteHtml}</div>` : ''}
                </div>
            </summary>
            <div class="${bodyClasses}">
                ${bodyHtml}
            </div>
        </details>
    `;
    },
  });

  vm.runInContext(`${source}
module.exports = {
  normalizeWorkCategories,
  getWorkCategoryOptions,
  renderWorkCategorySection,
  bindWorkCategoryControls,
};
`, context);

  return module.exports;
}

function createCategoryPanel(initialCategories = ['image']) {
  const listeners = {};
  const hidden = Object.assign(new MockHTMLInputElement(), {
    value: JSON.stringify(initialCategories),
    dispatchEvent() {},
  });
  const select = Object.assign(new MockHTMLSelectElement(), {
    value: 'video',
    addEventListener(type, handler) {
      listeners[`select:${type}`] = handler;
    },
    removeEventListener(type) {
      delete listeners[`select:${type}`];
    },
  });
  const addButton = {
    addEventListener(type, handler) {
      listeners[`add:${type}`] = handler;
    },
    removeEventListener(type) {
      delete listeners[`add:${type}`];
    },
  };
  const customInput = Object.assign(new MockHTMLInputElement(), {
    value: '',
    addEventListener(type, handler) {
      listeners[`custom:${type}`] = handler;
    },
    removeEventListener(type) {
      delete listeners[`custom:${type}`];
    },
  });
  const customAddButton = {
    addEventListener(type, handler) {
      listeners[`customAdd:${type}`] = handler;
    },
    removeEventListener(type) {
      delete listeners[`customAdd:${type}`];
    },
  };
  const pills = { innerHTML: '' };
  const section = {
    addEventListener(type, handler) {
      listeners[`section:${type}`] = handler;
    },
    removeEventListener(type) {
      delete listeners[`section:${type}`];
    },
  };
  const panel = {
    querySelector(selector) {
      if (selector === '#edit-work-categories') {
        return hidden;
      }
      if (selector === '#edit-work-category-select') {
        return select;
      }
      if (selector === '#editWorkCategoryAdd') {
        return addButton;
      }
      if (selector === '#edit-work-category-custom') {
        return customInput;
      }
      if (selector === '#editWorkCategoryCustomAdd') {
        return customAddButton;
      }
      if (selector === '#editWorkCategoryPills') {
        return pills;
      }
      if (selector === '[data-work-category-section]') {
        return section;
      }
      return null;
    },
  };

  return {
    panel,
    hidden,
    select,
    addButton,
    customInput,
    customAddButton,
    pills,
    section,
    listeners,
  };
}

describe('work category edit helpers', () => {
  const {
    normalizeWorkCategories,
    getWorkCategoryOptions,
    renderWorkCategorySection,
    bindWorkCategoryControls,
  } = loadCategoryHelpers();

  test('normalizes category lists from arrays and JSON strings', () => {
    expect(normalizeWorkCategories(['Image', 'media', 'media', ''])).toEqual(['image', 'media']);
    expect(normalizeWorkCategories('["video","motion"]')).toEqual(['video', 'motion']);
  });

  test('renders a category picker and category pills from the work catalog', () => {
    const html = renderWorkCategorySection({
      work: {
        categories: ['image', 'media'],
      },
      workTemplateCatalog: {
        categories: ['image', 'media', 'visual', 'video', 'motion'],
      },
    });

    expect(getWorkCategoryOptions({ categories: ['image', 'media', 'visual'] })).toEqual(['image', 'media', 'visual']);
    expect(html).toContain('class="edit-collapsible-details edit-work-fields edit-work-category-section"');
    expect(html).toContain('<summary class="edit-work-fields-header edit-collapsible-summary edit-work-category-summary">');
    expect(html).not.toContain('<details class="edit-collapsible-details edit-work-fields edit-work-category-section" open>');
    expect(html).toContain('id="edit-work-category-select"');
    expect(html).toContain('id="editWorkCategoryAdd"');
    expect(html).toContain('data-work-config-key="categories"');
    expect(html).toContain('data-work-category-remove="image"');
    expect(html).toContain('data-work-category-remove="media"');
    expect(html).toContain('<option value="video"');
  });

  test('adds and removes categories through the pill controls', () => {
    const panelState = createCategoryPanel(['image']);
    const cleanup = bindWorkCategoryControls({ editPanel: panelState.panel });

    expect(panelState.pills.innerHTML).toContain('image');

    panelState.listeners['add:click']();
    expect(JSON.parse(panelState.hidden.value)).toEqual(['image', 'video']);
    expect(panelState.pills.innerHTML).toContain('video');

    panelState.listeners['section:click']({
      target: {
        closest(selector) {
          if (selector !== '[data-work-category-remove]') {
            return null;
          }
          return {
            dataset: { workCategoryRemove: 'image' },
            getAttribute() {
              return 'image';
            },
          };
        },
      },
    });

    expect(JSON.parse(panelState.hidden.value)).toEqual(['video']);
    expect(panelState.pills.innerHTML).toContain('video');
    expect(panelState.pills.innerHTML).not.toContain('image');

    cleanup();
  });

  test('adds custom categories with length limits', () => {
    const panelState = createCategoryPanel(['image']);
    bindWorkCategoryControls({ editPanel: panelState.panel });

    panelState.customInput.value = 'VeryLongCustomCategoryNameThatGetsTrimmed';
    panelState.listeners['customAdd:click']();

    expect(JSON.parse(panelState.hidden.value)).toEqual(['image', 'verylongcustomcategoryna']);
    expect(panelState.customInput.value).toBe('');
    expect(panelState.pills.innerHTML).toContain('verylongcustomcategoryna');
  });
});
