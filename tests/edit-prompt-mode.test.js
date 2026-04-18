const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadPromptModeHelpers() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/prompt/mode.js');
  const source = fs.readFileSync(filePath, 'utf8')
    .replace(/export function /g, 'function ');

  const module = { exports: {} };
  const context = vm.createContext({
    module,
    exports: module.exports,
    console,
    require,
    __dirname: path.dirname(filePath),
    __filename: filePath,
  });

  vm.runInContext(`${source}\nmodule.exports = {\n  getPromptMode,\n  getDefaultSystemPromptForMode,\n  getSystemPromptSettingKeyForMode,\n  getPromptPlaceholderForMode,\n};`, context);

  return module.exports;
}

describe('prompt mode helpers', () => {
  const {
    getDefaultSystemPromptForMode,
    getPromptPlaceholderForMode,
    getPromptMode,
    getSystemPromptSettingKeyForMode,
  } = loadPromptModeHelpers();

  test('detects layout, file, and folder modes from selection state', () => {
    expect(getPromptMode({ isLayout: true, previewIsFile: true })).toBe('layout');
    expect(getPromptMode({ previewIsFile: true })).toBe('file');
    expect(getPromptMode({ previewIsFile: false })).toBe('folder');
    expect(getPromptMode()).toBe('folder');
  });

  test('maps mode-specific default system prompts and setting keys', () => {
    const prompts = { file: 'file prompt', folder: 'folder prompt', layout: 'layout prompt' };

    expect(getDefaultSystemPromptForMode('layout', prompts)).toBe('layout prompt');
    expect(getDefaultSystemPromptForMode('folder', prompts)).toBe('folder prompt');
    expect(getDefaultSystemPromptForMode('file', prompts)).toBe('file prompt');

    expect(getSystemPromptSettingKeyForMode('layout')).toBe('systemPromptLayout');
    expect(getSystemPromptSettingKeyForMode('folder')).toBe('systemPromptFolder');
    expect(getSystemPromptSettingKeyForMode('file')).toBe('systemPromptFile');
  });

  test('returns mode-specific prompt placeholders', () => {
    expect(getPromptPlaceholderForMode('layout')).toBe('Describe the layout you want...');
    expect(getPromptPlaceholderForMode('folder')).toBe('Describe the folder component you want...');
    expect(getPromptPlaceholderForMode('file', 'Describe a thing...')).toBe('Describe a thing...');
  });
});
