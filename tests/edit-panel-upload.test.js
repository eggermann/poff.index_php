const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadUploadPanelModule() {
  const filePath = path.join(__dirname, '..', 'src', 'assets', 'js', 'edit', 'panel', 'upload.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/^import .*$/gm, '')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    window: {
      location: { href: 'http://example.test/?edit=true' },
      history: { replaceState() {} },
      requestAnimationFrame(fn) { fn(); },
    },
    URL,
    uploadValidationError() {
      return '';
    },
    setStatusMessage() {},
    registerEscapeClose() {
      return () => {};
    },
  });

  vm.runInContext(`${source}
module.exports = {
  renderUploadSectionHtml,
};
`, context);

  return module.exports;
}

test('upload chooser uses native label binding for file input', () => {
  const { renderUploadSectionHtml } = loadUploadPanelModule();

  const html = renderUploadSectionHtml({
    isFileTarget: false,
    isEmptyFolder: false,
    externalOnly: false,
  });

  expect(html).toContain('id="editUploadChooseFiles"');
  expect(html).toContain('for="edit-upload-files"');
  expect(html).toContain('role="button"');
  expect(html).toContain('tabindex="0"');
});

test('Poff work source presents reduced copy and live choices', () => {
  const { renderUploadSectionHtml } = loadUploadPanelModule();

  const html = renderUploadSectionHtml({
    isFileTarget: false,
    isEmptyFolder: false,
    externalOnly: false,
  });

  expect(html).toContain('<option value="poff">External Poff work</option>');
  expect(html).toContain('Add work from another Pageoff page');
  expect(html).toContain('appears here as an <b>External work</b>');
  expect(html).toContain('value="follow" checked');
  expect(html).toContain('External work · live');
  expect(html).toContain('value="copy"');
  expect(html).toContain('Local copy · fork');
  expect(html).toContain('Later source changes do not sync.');
  expect(html).not.toContain('value="embed"');
  expect(html).not.toContain('Embed only');
  expect(html).not.toContain('id="edit-poff-create-player"');
  expect(html).toContain('convert unsupported files only when needed');
  expect(html).not.toContain('id="edit-poff-open-converter"');
  expect(html).not.toContain('index.php');
});
