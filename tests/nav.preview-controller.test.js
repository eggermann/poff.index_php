const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadPreviewController() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/nav/preview-controller.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\/preview-helpers\.js';\n?/g, '')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\.\/core\/selection\.js';\n?/g, '')
    .replace(/export function /g, 'function ');

  class FakeHTMLElement {
    constructor(tagName = 'div', innerHTML = '', queryMap = {}) {
      this.tagName = String(tagName || 'div').toUpperCase();
      this.innerHTML = innerHTML;
      this.textContent = '';
      this.dataset = {};
      this.attributes = {};
      this.children = [];
      this.scrollTop = 0;
      this.scrollLeft = 0;
      this.queryMap = { ...queryMap };
    }

    setAttribute(name, value) {
      this.attributes[name] = String(value);
    }

    getAttribute(name) {
      return this.attributes[name] || '';
    }

    hasAttribute(name) {
      return Object.prototype.hasOwnProperty.call(this.attributes, name);
    }

    appendChild(node) {
      this.children.push(node);
      return node;
    }

    querySelectorAll() {
      return [];
    }

    querySelector(selector) {
      return this.queryMap[selector] || null;
    }

    cloneNode() {
      const clone = new FakeHTMLElement(this.tagName.toLowerCase(), this.innerHTML, this.queryMap);
      clone.textContent = this.textContent;
      clone.dataset = { ...this.dataset };
      clone.attributes = { ...this.attributes };
      return clone;
    }

    get outerHTML() {
      const attrs = Object.entries(this.attributes)
        .map(([key, value]) => ` ${key}="${String(value).replace(/"/g, '&quot;')}"`)
        .join('');
      return `<${this.tagName.toLowerCase()}${attrs}>${this.innerHTML || this.textContent || ''}</${this.tagName.toLowerCase()}>`;
    }
  }

  class FakeHTMLStyleElement extends FakeHTMLElement {
    constructor(textContent = '') {
      super('style');
      this.textContent = textContent;
    }

    cloneNode() {
      return new FakeHTMLStyleElement(this.textContent);
    }

    get outerHTML() {
      return `<style>${this.textContent}</style>`;
    }
  }

  class FakeHTMLLinkElement extends FakeHTMLElement {
    constructor(href = '') {
      super('link');
      this.rel = 'stylesheet';
      this.href = href;
    }

    get href() {
      return this.attributes.href || '';
    }

    set href(value) {
      this.attributes.href = String(value);
    }

    get outerHTML() {
      return `<link rel="stylesheet" href="${this.href}">`;
    }
  }

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    URL,
    URLSearchParams,
    HTMLElement: FakeHTMLElement,
    HTMLStyleElement: FakeHTMLStyleElement,
    HTMLLinkElement: FakeHTMLLinkElement,
    HTMLMediaElement: FakeHTMLElement,
    HTMLDetailsElement: FakeHTMLElement,
    document: {
      createElement(tagName) {
        if (tagName === 'style') {
          return new FakeHTMLStyleElement();
        }
        if (tagName === 'link') {
          return new FakeHTMLLinkElement();
        }
        return new FakeHTMLElement(tagName);
      },
    },
    DOMParser: class {
    parseFromString() {
        const remoteSnapshot = new FakeHTMLElement('article', '<img class="remote-image" src="images/remote-image.jpg" alt="Remote Snapshot">');
        remoteSnapshot.setAttribute('class', 'remote-snapshot');
        const main = new FakeHTMLElement('main', remoteSnapshot.outerHTML);
        main.setAttribute('class', 'poff-default-layout__main');
        const layout = new FakeHTMLElement('div', main.outerHTML, {
          '.poff-default-layout__main': main,
        });
        layout.setAttribute('class', 'poff-default-layout poff-default-layout--file');
        const appShell = new FakeHTMLElement('div', layout.outerHTML, {
          '.poff-default-layout__main': main,
          '.poff-default-layout': layout,
        });
        appShell.setAttribute('id', 'appShell');
        appShell.setAttribute('class', 'container');
        const viewer = new FakeHTMLElement('div', appShell.outerHTML, {
          '.poff-default-layout__main': main,
          '.poff-default-layout': layout,
          '#appShell': appShell,
        });
        viewer.setAttribute('class', 'viewer');
        return {
          head: {
            children: [
              new FakeHTMLStyleElement('.poff-default-layout { color: red; }'),
              new FakeHTMLLinkElement('/layout/style.css'),
            ],
          },
          querySelector(selector) {
            if (selector === '#contentFrame > .viewer' || selector === '.viewer') {
              return viewer;
            }
            return null;
          },
          body: new FakeHTMLElement('body', viewer.outerHTML, {
            '.poff-default-layout__main': main,
            '.poff-default-layout': layout,
            '#appShell': appShell,
            '.viewer': viewer,
          }),
        };
      }
    },
    fetch: async (url, options) => ({
      ok: true,
      url,
      text: async () => '<!doctype html><html><head></head><body><div id="contentFrame"><div class="viewer"></div></div></body></html>',
      options,
    }),
    requestAnimationFrame: (fn) => fn(),
    window: {
      location: {
        href: 'http://example.test/',
        origin: 'http://example.test',
        pathname: '/',
      },
    },
    getSelectionFromPath(pathValue) {
      return {
        previewPath: pathValue,
        previewIsFile: true,
      };
    },
    inferFilePath() {
      return true;
    },
    normalizeCmsRelativePath(value) {
      return value;
    },
    normalizePreviewStyleNode(node) {
      return node.outerHTML;
    },
    previewStateFromUrl(url) {
      return {
        key: url,
        path: '',
        isFile: false,
      };
    },
  });

  vm.runInContext(`${source}
module.exports = {
  createPreviewController,
};
`, context);

  return {
    createPreviewController: module.exports.createPreviewController,
    FakeHTMLElement,
  };
}

describe('preview controller', () => {
  test('renders fetched preview HTML without an iframe', async () => {
    const { createPreviewController } = loadPreviewController();
    const contentFrame = {
      innerHTML: '',
      dataset: {},
      scrollTop: 12,
      scrollLeft: 8,
      attributes: {},
      addEventListener(type, handler) {
        this.handler = handler;
      },
      setAttribute(name, value) {
        this.attributes[name] = String(value);
      },
      getAttribute(name) {
        return this.attributes[name] || '';
      },
      querySelectorAll() {
        return [];
      },
    };
    const loadingCalls = [];
    const controller = createPreviewController({
      contentFrame,
      iframeLoading: null,
      initialQueryPath: '',
      navigateToPath() {
        throw new Error('navigateToPath should not be called');
      },
      setLoadingVisible(_element, visible) {
        loadingCalls.push(visible);
      },
      getCurrentSelection() {
        return null;
      },
    });

    await controller.renderPreview('http://example.test/source/?view=1&file=flux-jpeg');

    expect(contentFrame.innerHTML).toContain('<style>');
    expect(contentFrame.innerHTML).toContain('remote-snapshot');
    expect(contentFrame.innerHTML).toContain('http://example.test/source/images/remote-image.jpg');
    expect(contentFrame.innerHTML).not.toContain('id="appShell"');
    expect(contentFrame.innerHTML).not.toContain('class="viewer"');
    expect(contentFrame.innerHTML).not.toContain('preview-iframe');
    expect(loadingCalls).toEqual([false]);
  });

  test('builds viewer urls with transient converter preview params', () => {
    const { createPreviewController, FakeHTMLElement } = loadPreviewController();
    const controller = createPreviewController({
      contentFrame: new FakeHTMLElement(),
      iframeLoading: new FakeHTMLElement(),
      initialQueryPath: '',
      navigateToPath() {},
      setLoadingVisible() {},
      getCurrentSelection() {
        return {
          previewPath: 'hello.txt',
          previewIsFile: true,
        };
      },
      getPreviewParams({ path: targetPath, isFile }) {
        if (!isFile || targetPath !== 'hello.txt') {
          return null;
        }
        return {
          converter_preview: '1',
          converter_id: 'converter/convert-text',
          converter_format: 'txt',
          converter_quality: 'preview',
        };
      },
    });

    expect(controller.buildViewerUrl('hello.txt', true, false)).toBe(
      '/?view=1&file=hello.txt&converter_preview=1&converter_id=converter%2Fconvert-text&converter_format=txt&converter_quality=preview'
    );
  });
});
