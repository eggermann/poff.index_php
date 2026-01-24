export function createStreamState() {
    return { timer: null, state: null };
}

export function stopStreaming(stream) {
    if (!stream) {
        return;
    }
    if (stream.timer) {
        clearInterval(stream.timer);
        stream.timer = null;
    }
    stream.state = null;
}

export function startStreaming({ stream, targetIndex, fullText, history, renderHistory }) {
    if (!stream || typeof renderHistory !== 'function') {
        return;
    }
    stopStreaming(stream);
    if (!fullText) {
        renderHistory();
        return;
    }
    if (history && history[targetIndex]) {
        history[targetIndex].content = '';
    }
    stream.state = { index: targetIndex, text: '' };
    const total = fullText.length;
    const step = Math.max(1, Math.ceil(total / 80));
    stream.timer = window.setInterval(() => {
        if (!stream.state) {
            return;
        }
        stream.state.text = fullText.slice(0, stream.state.text.length + step);
        renderHistory();
        if (stream.state.text.length >= total) {
            stopStreaming(stream);
            if (history && history[targetIndex]) {
                history[targetIndex].content = fullText;
            }
            renderHistory();
        }
    }, 18);
}
