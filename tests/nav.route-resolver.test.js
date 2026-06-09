const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadRouteResolver({ navList = null, poffContext = null } = {}) {
  const filePath = path.join(__dirname, '..', 'src/assets/js/nav/route-resolver.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\.\/core\/selection\.js';\n?/g, '')
    .replace(/import\s+\{[\s\S]*?\}\s+from\s+'\.\/path-helpers\.js';\n?/g, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const fetch = jest.fn();
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    fetch,
    window: {
      location: {
        href: 'http://example.test/?edit=true#/xxxxxx',
        hash: '',
        origin: 'http://example.test',
        pathname: '/',
        search: '?edit=true',
      },
      POFF_CONTEXT: poffContext,
    },
    URL,
    inferFilePath(pathValue = '') {
      return /\.[^\\/]+$/.test(pathValue);
    },
    normalizeHashPath(value = '') {
      return String(value || '')
        .replace(/\\/g, '/')
        .replace(/^#\/?/, '')
        .replace(/^\/+|\/+$/g, '');
    },
    normalizeHashAlias(value = '') {
      return String(value || '')
        .replace(/\\/g, '/')
        .replace(/^#\/?/, '')
        .replace(/^\/+|\/+$/g, '')
        .toLowerCase();
    },
    routeResolution(pathValue = '', isFile = /\.[^\\/]+$/.test(pathValue)) {
      return { path: pathValue, isFile };
    },
    findNavLinkByAttribute(list, attributeName, value = '') {
      if (!list || !value || typeof list.querySelectorAll !== 'function') {
        return null;
      }
      const wanted = String(value).toLowerCase();
      for (const link of list.querySelectorAll(`[${attributeName}]`)) {
        if (String(link.getAttribute(attributeName) || '').toLowerCase() === wanted) {
          return link;
        }
      }
      return null;
    },
    navTargetPath(link) {
      if (!link) {
        return '';
      }
      return link.getAttribute('data-layout-path')
        || link.getAttribute('data-path')
        || link.getAttribute('data-src')
        || '';
    },
    navTargetIsFile(link, pathValue = '') {
      if (!link) {
        return /\.[^\\/]+$/.test(pathValue);
      }
      if (link.hasAttribute('data-layout-path')) {
        return false;
      }
      if (link.hasAttribute('data-file') || link.hasAttribute('data-src')) {
        return true;
      }
      return /\.[^\\/]+$/.test(pathValue);
    },
  });

  vm.runInContext(`${source}
module.exports = {
  createRouteResolver,
};
`, context);

  return {
    resolver: module.exports.createRouteResolver({ navList }),
    fetch,
  };
}

describe('route resolver', () => {
  test('resolves hidden pending link slugs from current config tree as file-like targets', () => {
    const { resolver } = loadRouteResolver({
      poffContext: {
        currentPoffConfig: {
          tree: [
            {
              name: 'rr',
              slug: 'rr',
              type: 'link',
              path: 'rr',
              visible: false,
              externalSubmission: true,
              approvalStatus: 'pending',
              linkUrl: 'https://dominikeggermann.com/',
            },
          ],
        },
      },
    });

    expect(resolver.resolveHashPath('rr')).toEqual({
      path: 'rr',
      isFile: true,
    });
  });

  test('preserves edit=true on ajax slug resolve fallback requests', async () => {
    const { resolver, fetch } = loadRouteResolver({
      poffContext: {
        currentPoffConfig: {
          tree: [],
        },
      },
    });

    fetch.mockResolvedValue({
      ok: false,
      json: async () => ({}),
    });

    await resolver.resolveHashPathAsync('xxxxxx');

    expect(fetch).toHaveBeenCalledWith(
      'http://example.test/?ajax=resolve&slug=xxxxxx&edit=true',
      expect.objectContaining({
        credentials: 'same-origin',
      }),
    );
  });

  test('does not ajax-resolve when config tree already resolved a hidden link as file-like', async () => {
    const { resolver, fetch } = loadRouteResolver({
      poffContext: {
        currentPoffConfig: {
          tree: [
            {
              name: 'mcmmcmc',
              slug: 'mcmmcmc',
              type: 'link',
              path: 'mcmmcmc',
              visible: false,
              externalSubmission: true,
              approvalStatus: 'pending',
              linkUrl: 'https://dominikeggermann.com/',
            },
          ],
        },
      },
    });

    const resolved = await resolver.resolveHashPathAsync('mcmmcmc');

    expect(resolved).toEqual({
      path: 'mcmmcmc',
      isFile: true,
    });
    expect(fetch).not.toHaveBeenCalled();
  });
});
