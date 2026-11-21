const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadApiEdit(overrides = {}) {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'api', 'edit.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export async function /g, 'async function ')
    .replace(/export function /g, 'function ');

  const fetchCalls = [];
  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    URL,
    URLSearchParams,
    window: overrides.window || {
      location: {
        href: 'https://example.test/app?edit=true',
        origin: 'https://example.test',
        pathname: '/app',
      },
      localStorage: {
        getItem(key) {
          return key === 'poff.editorAuthToken' ? 'token-123' : null;
        },
        setItem() {},
        removeItem() {},
      },
    },
    fetch: overrides.fetch || (async (url, options) => {
      fetchCalls.push({ url, options });
      return {
        ok: true,
        text: async () => JSON.stringify({ ok: true }),
        json: async () => ({ ok: true }),
      };
    }),
    setTimeout,
    clearTimeout,
  });

  vm.runInContext(`${source}
module.exports = {
  buildCmsUrl,
  requestEditConfig,
};
`, context);

  return {
    buildCmsUrl: module.exports.buildCmsUrl,
    requestEditConfig: module.exports.requestEditConfig,
    fetchCalls,
  };
}

describe('api/edit save transport', () => {
  test('requestEditConfig(save) form-encodes payloads', async () => {
    const { requestEditConfig, fetchCalls } = loadApiEdit();

    const response = await requestEditConfig('save', {
      path: 'index.php',
      title: 'saved title',
      description: 'saved description',
      work: {
        type: 'other',
        layout: {
          name: 'poff-layout',
          preset: 'actual',
        },
      },
    });

    expect(response).toEqual({ ok: true });
    expect(fetchCalls).toHaveLength(1);
    const [{ url, options }] = fetchCalls;
    expect(url).toContain('edit=save');
    expect(url).toContain('path=index.php');
    expect(options.method).toBe('POST');
    expect(options.headers['Content-Type']).toBe('application/x-www-form-urlencoded;charset=UTF-8');

    const body = new URLSearchParams(options.body);
    expect(body.get('path')).toBe('index.php');
    expect(body.get('title')).toBe('saved title');
    expect(body.get('description')).toBe('saved description');
    expect(body.get('work[type]')).toBe('other');
    expect(body.get('work[layout][name]')).toBe('poff-layout');
    expect(body.get('work[layout][preset]')).toBe('actual');
    expect(options.headers['X-Poff-Editor-Auth']).toBe('token-123');
  });
});
