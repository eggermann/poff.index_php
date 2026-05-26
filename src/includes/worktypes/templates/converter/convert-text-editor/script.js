(function () {
  const initTextEditorConverter = () => {
    const root = document.querySelector('[data-poff-text-editor-converter], .poff-text-editor-converter');
    if (!root) {
      return;
    }

    const host = root.querySelector('[data-poff-text-editor-host]');
    const fallback = root.querySelector('[data-poff-text-editor-fallback]');
    const status = root.querySelector('[data-poff-text-editor-status]');
    if (!(host instanceof HTMLElement) || !(fallback instanceof HTMLTextAreaElement)) {
      return;
    }

    const aceSources = [
      'https://cdn.jsdelivr.net/npm/ace-builds@1.43.4/src-min-noconflict/ace.js',
      'https://cdnjs.cloudflare.com/ajax/libs/ace/1.43.4/ace.js',
    ];
    let editor = null;
    let settled = false;
    const extension = String(root.dataset.sourceExtension || '').toLowerCase();
    const mime = String(root.dataset.sourceMime || '').toLowerCase();
    const isJsonSource = extension === 'json' || mime === 'application/json';

    const resolveAceMode = () => {
      if (isJsonSource) {
        return 'ace/mode/json';
      }
      if (extension === 'md' || mime === 'text/markdown') {
        return 'ace/mode/markdown';
      }
      if (extension === 'html' || extension === 'htm' || mime === 'text/html') {
        return 'ace/mode/html';
      }
      if (extension === 'css' || mime === 'text/css') {
        return 'ace/mode/css';
      }
      if (extension === 'js' || mime === 'application/javascript') {
        return 'ace/mode/javascript';
      }
      if (extension === 'rtf' || mime === 'application/rtf' || mime === 'text/rtf') {
        return 'ace/mode/text';
      }
      return 'ace/mode/text';
    };

    const updateStatus = (message) => {
      if (status instanceof HTMLElement) {
        status.textContent = message;
      }
    };

    const formatFallbackContent = () => {
      if (!isJsonSource) {
        return;
      }
      try {
        const parsed = JSON.parse(fallback.value);
        fallback.value = JSON.stringify(parsed, null, 2);
      } catch {
        // Keep invalid JSON editable exactly as-is.
      }
    };

    const currentValue = () => {
      if (editor && typeof editor.getValue === 'function') {
        return editor.getValue();
      }
      return fallback.value;
    };

    window.poffConverterPayloadProvider = () => ({
      editor: {
        content: currentValue(),
        editorKind: editor ? 'ace-cdn' : 'textarea-fallback',
      },
    });

    formatFallbackContent();
    root.dataset.editorFallbackReady = 'true';
    updateStatus(isJsonSource ? 'Textarea editor active. JSON formatted.' : 'Textarea editor active.');

    const attachEditor = () => {
      if (!window.ace || typeof window.ace.edit !== 'function') {
        updateStatus('Editor unavailable. Textarea fallback is active.');
        return false;
      }

      root.dataset.editorReady = 'true';
      editor = window.ace.edit(host);
      editor.setTheme('ace/theme/monokai');
      editor.session.setMode(resolveAceMode());
      editor.session.setUseWrapMode(true);
      editor.setValue(fallback.value || '', -1);
      editor.setOptions({
        fontSize: '14px',
        showPrintMargin: false,
        useSoftTabs: true,
        tabSize: 2,
      });
      updateStatus('Editor ready. Converted output will save the current editor content.');
      return true;
    };

    if (window.ace && typeof window.ace.edit === 'function') {
      attachEditor();
      return;
    }

    const loadAce = (index) => {
      if (index >= aceSources.length) {
        settled = true;
        updateStatus('CDN editor unavailable. Textarea fallback is active.');
        return;
      }

      updateStatus(index === 0 ? 'Textarea editor active. Loading Ace upgrade...' : 'Textarea editor active. Trying backup editor CDN...');
      const script = document.createElement('script');
      script.src = aceSources[index];
      script.async = true;
      let timeoutId = window.setTimeout(() => {
        if (settled) {
          return;
        }
        script.remove();
        loadAce(index + 1);
      }, 5000);

      script.onload = () => {
        window.clearTimeout(timeoutId);
        if (settled) {
          return;
        }
        settled = attachEditor();
        if (!settled) {
          loadAce(index + 1);
        }
      };
      script.onerror = () => {
        window.clearTimeout(timeoutId);
        if (!settled) {
          loadAce(index + 1);
        }
      };
      document.head.appendChild(script);
    };

    loadAce(0);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTextEditorConverter, { once: true });
    return;
  }

  initTextEditorConverter();
})();
