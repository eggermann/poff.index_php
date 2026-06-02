(function () {
  const initPageoffRemoteTextConverter = () => {
    const root = document.querySelector('[data-poff-pageoff-remote-text]');
    if (!root) {
      return;
    }

    const editor = root.querySelector('[data-poff-pageoff-remote-editor]');
    const fontSizeSelect = root.querySelector('[data-poff-pageoff-font-size]');
    const status = root.querySelector('[data-poff-pageoff-remote-status]');
    if (!(editor instanceof HTMLTextAreaElement) || !(fontSizeSelect instanceof HTMLSelectElement)) {
      return;
    }

    const updateStatus = (message) => {
      if (status instanceof HTMLElement) {
        status.textContent = message;
      }
    };

    const applyFontSize = () => {
      editor.style.fontSize = String(fontSizeSelect.value || '16px');
      updateStatus(`Remote PHP preview ready. Font size ${fontSizeSelect.value || '16px'}.`);
    };

    const buildEndpoint = () => {
      const pathname = String(window.location.pathname || '/');
      const normalizedBasePath = pathname.endsWith('/')
        ? pathname
        : `${pathname}/`;
      return new URL(
        'worktypes/templates/converter/convert-pageoff-remote-text/converter.php',
        `${window.location.origin}${normalizedBasePath}`
      ).toString();
    };

    const payloadProvider = ({ converter = {} } = {}) => ({
      converter: {
        ...converter,
        fontSize: String(fontSizeSelect.value || '16px'),
        node: {
          id: 'pageoff-local-php',
          endpoint: buildEndpoint(),
        },
      },
      editor: {
        content: editor.value,
        fontSize: String(fontSizeSelect.value || '16px'),
        editorKind: 'pageoff-remote-textarea',
      },
    });
    window.poffConverterPayloadProvider = payloadProvider;
    root.__poffConverterPayloadProvider = payloadProvider;
    root.dataset.poffConverterProvider = 'true';
    try {
      if (window.top && window.top !== window) {
        window.top.poffConverterPayloadProvider = payloadProvider;
      }
    } catch (error) {
      // Ignore cross-window registration failures and keep the local provider.
    }

    fontSizeSelect.addEventListener('change', applyFontSize);
    editor.addEventListener('input', () => {
      updateStatus(`Remote PHP preview ready. ${editor.value.length} chars queued.`);
    });

    applyFontSize();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPageoffRemoteTextConverter, { once: true });
    return;
  }

  initPageoffRemoteTextConverter();
})();
