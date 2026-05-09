const fs = require('fs');
const path = require('path');
const vm = require('vm');

function loadPromptHistoryHelpers() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/prompt/history.js');
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

  vm.runInContext(`${source}
module.exports = {
  buildTemplateHistorySnapshot,
  serializeHistoryForRequest,
  summarizeSerializedHistory,
};
`, context);

  return module.exports;
}

function loadPromptHistoryRenderer() {
  const filePath = path.join(__dirname, '..', 'src/assets/js/edit/prompt/render/history.js');
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
    escapeHtml: (value) => String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;'),
    summarizeSerializedHistory: (history) => ({
      count: Array.isArray(history) ? history.length : 0,
      chars: Array.isArray(history) ? history.reduce((total, item) => total + String(item?.content || '').length, 0) : 0,
    }),
  });

  vm.runInContext(`${source}
module.exports = {
  renderPromptHistory,
};
`, context);

  return module.exports;
}

describe('prompt history helpers', () => {
  const {
    buildTemplateHistorySnapshot,
    serializeHistoryForRequest,
    summarizeSerializedHistory,
  } = loadPromptHistoryHelpers();

  test('builds a compact template snapshot for assistant turns', () => {
    const snapshot = buildTemplateHistorySnapshot({
      templateText: '<section class="card">{{title}}</section>',
      nextCss: '.card{display:grid;}',
      nextJs: 'document.body.dataset.card = "on";',
      nextTitle: 'Card view',
      nextDescription: 'Compact card layout',
      nextWork: {
        layout: 'poff-layout',
        featured: true,
      },
      isLayoutTarget: false,
    });

    expect(snapshot).toEqual(expect.objectContaining({
      targetType: 'partial',
      template: '<section class="card">{{title}}</section>',
      css: '.card{display:grid;}',
      js: 'document.body.dataset.card = "on";',
      title: 'Card view',
      description: 'Compact card layout',
      layoutName: 'poff-layout',
      workSnapshot: expect.objectContaining({
        layout: 'poff-layout',
        featured: true,
      }),
      workKeys: ['featured'],
    }));
  });

  test('serializes template snapshots back into request history', () => {
    const history = [
      { role: 'user', content: 'Make it tighter.' },
      {
        role: 'assistant',
        content: '<section class="card">{{title}}</section>',
        templateSnapshot: {
          targetType: 'partial',
          template: '<section class="card">{{title}}</section>',
          css: '.card{display:grid;}',
          layoutName: 'poff-layout',
          workKeys: ['featured'],
        },
      },
    ];

    const serialized = serializeHistoryForRequest(history);

    expect(serialized[0]).toEqual({
      role: 'user',
      content: 'Make it tighter.',
    });
    expect(serialized[1].role).toBe('assistant');
    expect(serialized[1].content).toContain('<section class="card">{{title}}</section>');
    expect(serialized[1].content).toContain('Template snapshot target: partial');
    expect(serialized[1].content).toContain('CSS snapshot:');
    expect(serialized[1].content).toContain('Layout name snapshot: poff-layout');
    expect(serialized[1].content).toContain('Work keys updated: featured');
  });

  test('summarizes serialized history character totals', () => {
    const summary = summarizeSerializedHistory([
      { role: 'user', content: 'abc' },
      {
        role: 'assistant',
        content: 'done',
        templateSnapshot: {
          targetType: 'partial',
          template: '<div>{{title}}</div>',
        },
      },
    ]);

    expect(summary.count).toBe(2);
    expect(summary.chars).toBeGreaterThan(7);
  });
});

describe('prompt history renderer', () => {
  const { renderPromptHistory } = loadPromptHistoryRenderer();

  test('shows a reset action for assistant snapshots', () => {
    const container = {
      innerHTML: '',
      scrollHeight: 0,
      clientHeight: 0,
      scrollTop: 0,
    };

    renderPromptHistory(container, [
      {
        role: 'assistant',
        content: 'ready',
        _index: 3,
        templateSnapshot: {
          targetType: 'partial',
          template: '<section>ready</section>',
        },
      },
    ], null);

    expect(container.innerHTML).toContain('data-history-reset-index="3"');
    expect(container.innerHTML).toContain('>reset<');
  });
});
