export function createStreamState() {
    return { state: null };
}

export function stopStreaming(stream) {
    if (!stream) {
        return;
    }
    stream.state = null;
}

export function beginStreaming({ stream, targetIndex, history, renderHistory }) {
    if (!stream || typeof renderHistory !== 'function') {
        return;
    }
    stopStreaming(stream);
    if (history && history[targetIndex]) {
        history[targetIndex].content = '';
    }
    stream.state = { index: targetIndex, text: '' };
    renderHistory();
}

export function appendStreamingChunk({ stream, chunk = '', history, renderHistory }) {
    if (!stream || !stream.state || typeof renderHistory !== 'function' || chunk === '') {
        return;
    }
    stream.state.text += chunk;
    if (history && history[stream.state.index]) {
        history[stream.state.index].content = stream.state.text;
    }
    renderHistory();
}

export function finishStreaming({ stream, history, fullText = '', renderHistory }) {
    if (!stream || typeof renderHistory !== 'function' || !stream.state) {
        return;
    }
    const nextText = fullText || stream.state.text || '';
    if (history && history[stream.state.index]) {
        history[stream.state.index].content = nextText;
    }
    stopStreaming(stream);
    renderHistory();
}
