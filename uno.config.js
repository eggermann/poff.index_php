const presetUnoModule = require('@unocss/preset-uno');
const presetUno = presetUnoModule.default || presetUnoModule;

module.exports = {
  presets: [presetUno()],
  theme: {
    fontFamily: {
      sans: ['Inter', 'sans-serif'],
    },
    keyframes: {
      blink: {
        '50%': { opacity: 0 },
      },
    },
    animation: {
      blink: 'blink 1s steps(1) infinite',
    },
  },
  shortcuts: {
    container: 'flex h-screen',
    sidebar: 'w-[280px] bg-white p-5 overflow-y-auto border-r border-gray-300 shadow-[2px_0_8px_rgba(0,0,0,0.05)] flex-shrink-0',
    'sidebar-tools': 'mb-3',
    'nav-list': 'list-none p-0 m-0',
    'nav-link': 'flex items-center px-3 py-2 no-underline text-gray-700 rounded-md mb-1.5 truncate transition-colors duration-200 hover:bg-gray-200 hover:text-gray-900',
    'nav-link-active': 'bg-blue-500 text-white font-medium',
    'nav-link-up': 'text-emerald-500 font-medium hover:text-emerald-600 hover:bg-emerald-100',
    'item-icon': 'mr-2.5 w-[18px] h-[18px] shrink-0',
    'main-content': 'flex-1 flex flex-col h-screen bg-white relative overflow-y-auto',
    'folder-meta': 'hidden px-5 py-4 bg-gray-50 border-b border-gray-200',
    'folder-meta-title': 'm-0 text-[1.25em] text-gray-900',
    'folder-meta-desc': 'mt-1 text-[0.95em] text-gray-600',
    'folder-meta-link': 'text-blue-600 no-underline hover:underline',
    'content-frame': 'flex-1 border-0 min-h-[60vh] pb-6',
    'loading-row': 'hidden text-center p-2.5',
    loader: 'inline-block w-6 h-6 border-4 border-blue-500 border-t-gray-200 rounded-full animate-spin',
    'loader-label': 'ml-2',
    'edit-toggle': 'w-full border border-gray-900 bg-gray-900 text-white px-3 py-2.5 rounded-lg font-semibold cursor-pointer transition-colors duration-200 hover:bg-gray-800',
    'edit-toggle-on': 'bg-orange-500 border-orange-500 text-gray-900 hover:bg-orange-400',
    'edit-panel': 'border-b border-gray-200 bg-orange-50 px-5 py-3.5',
    'edit-panel-title': 'm-0 mb-1.5 text-[1.05em] text-gray-900 font-semibold',
    'edit-status': 'text-sm text-red-700 mb-2',
    'edit-status-success': 'text-green-700',
    'edit-form': 'grid gap-3',
    'edit-grid': 'grid gap-3',
    'edit-grid-cols': 'grid-cols-[repeat(auto-fit,minmax(220px,1fr))]',
    'edit-label': 'block text-[0.85em] text-gray-700 mb-1',
    'form-input': 'w-full border border-gray-300 rounded-md px-2.5 py-2 text-[0.95em] box-border',
    'form-textarea': 'form-input min-h-[72px] resize-y',
    'edit-tree': 'border border-gray-200 rounded-md p-2.5 bg-white max-h-[220px] overflow-auto',
    'edit-tree-item': 'flex items-center gap-2 py-1 text-[0.9em] text-gray-700',
    'edit-actions': 'flex items-center gap-3',
    'edit-inline': 'grid gap-3',
    'edit-inline-actions': 'flex items-center gap-2.5',
    'edit-drawer': 'absolute top-0 right-0 bottom-0 w-[360px] bg-white border-l border-gray-200 shadow-[-8px_0_18px_rgba(15,23,42,0.08)] p-4 overflow-auto transform translate-x-full transition-transform duration-200 z-30',
    'edit-drawer-open': 'translate-x-0',
    'drawer-header': 'flex items-center justify-between mb-2',
    'drawer-title': 'm-0 text-[1em] text-gray-900 font-semibold',
    'drawer-close': 'border-0 bg-transparent text-[1.2em] cursor-pointer text-gray-500',
    'prompt-window': 'grid gap-3 mt-2.5 pt-1.5 border-t border-dashed border-gray-200',
    'prompt-inline': 'bg-gray-50 border border-gray-200 rounded-xl p-3 mt-4 shadow-[0_10px_30px_rgba(15,23,42,0.06)]',
    'prompt-header': 'flex items-start justify-between gap-2',
    'prompt-grid': 'grid-cols-[repeat(auto-fit,minmax(180px,1fr))]',
    'prompt-messages': 'border border-gray-200 rounded-lg p-2.5 bg-slate-50 max-h-[160px] overflow-auto text-[0.9em] shadow-[inset_0_1px_0_rgba(255,255,255,0.4)]',
    'prompt-message': 'mb-2 last:mb-0 p-1.5 px-2 rounded-md bg-white border border-gray-200',
    'prompt-message-user': 'bg-blue-50 border-blue-200',
    'prompt-message-assistant': 'bg-emerald-50 border-emerald-200',
    'prompt-message-role': 'font-semibold mr-1.5',
    'prompt-message-content': 'whitespace-pre-wrap break-words',
    'prompt-summary': 'border border-gray-200 rounded-lg p-2.5 bg-gradient-to-br from-slate-50 to-indigo-100 shadow-[0_12px_24px_rgba(79,70,229,0.08)] transition-transform transition-shadow duration-200 hover:-translate-y-0.5 hover:shadow-[0_16px_32px_rgba(79,70,229,0.12)]',
    'prompt-summary-title': 'font-bold text-gray-900 mb-1.5',
    'prompt-summary-body': 'text-gray-700 text-[0.92em]',
    'prompt-allowed': 'flex items-center gap-2 text-[0.9em] text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-2.5 py-2',
    'prompt-dot': 'w-2.5 h-2.5 rounded-full bg-emerald-600 shadow-[0_0_0_4px_rgba(22,163,74,0.15)]',
    'stream-cursor': 'inline-block w-2 h-[14px] bg-gray-400 ml-0.5 animate-blink',
    'prompt-actions': 'flex items-center justify-between gap-2 flex-wrap',
    'prompt-actions-left': 'flex items-center gap-2 flex-wrap',
    'prompt-settings-actions': 'flex justify-end -mt-1.5',
    'prompt-context': 'border border-gray-200 rounded-lg p-2 bg-slate-50 text-[0.9em]',
    'prompt-context-title': 'font-semibold mb-1 text-gray-900',
    'prompt-context-row': 'text-gray-700 my-0.5 break-words',
    'prompt-inline-toggle': 'inline-flex items-center gap-1.5 text-[0.9em] text-gray-700',
    'prompt-inline-toggle-input': 'm-0',
    'prompt-system': 'border border-gray-200 rounded-lg p-2.5 bg-gray-50',
    'prompt-system-summary': 'cursor-pointer font-semibold mb-1.5',
    'prompt-system-footer': 'flex items-center justify-between gap-2.5 mt-1.5 flex-wrap',
    'prompt-textarea': 'mt-1.5 min-h-[90px]',
    'small-note': 'text-xs text-gray-500',
    btn: 'inline-flex items-center justify-center gap-2 border-0 rounded-md px-3.5 py-2 text-[0.95em] cursor-pointer bg-gray-900 text-white transition-colors duration-200 hover:bg-gray-800',
    'btn-secondary': 'bg-orange-500 text-gray-900 hover:bg-orange-400',
    'prompt-input': 'form-textarea min-h-[90px]',
  },
  safelist: [
    'nav-link-active',
    'edit-toggle-on',
    'edit-drawer-open',
    'edit-status-success',
    'prompt-message-user',
    'prompt-message-assistant',
    'animate-blink',
  ],
  preflights: [
    {
      getCSS: ({ theme }) => `
        html, body {
          height: 100%;
        }
        body {
          margin: 0;
          padding: 0;
          overflow: auto;
          font-family: ${theme.fontFamily.sans.join(', ')};
          background-color: #f0f2f5;
        }
      `,
    },
  ],
};
