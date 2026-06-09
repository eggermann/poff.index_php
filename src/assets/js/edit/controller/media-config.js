export function buildConverterSaveAs(sourceName, format) {
    const normalizedFormat = String(format || '').trim().toLowerCase();
    if (!normalizedFormat || normalizedFormat === 'source') {
        return sourceName;
    }
    return `${sourceName.replace(/\.[^.]+$/, '')}.${normalizedFormat}`;
}

export function resolveConverterPayloadProvider() {
    if (typeof window.poffConverterPayloadProvider === 'function') {
        return window.poffConverterPayloadProvider;
    }

    if (typeof document?.getElementById !== 'function') {
        return null;
    }

    const contentFrame = document.getElementById('contentFrame');
    const previewRoot = contentFrame?.shadowRoot || contentFrame;
    if (!previewRoot || typeof previewRoot.querySelector !== 'function') {
        return null;
    }

    const providerHost = previewRoot.querySelector('[data-poff-converter-provider="true"]');
    if (providerHost && typeof providerHost.__poffConverterPayloadProvider === 'function') {
        return providerHost.__poffConverterPayloadProvider;
    }

    return null;
}

export function readMediaConfigFromElements(elements, form, currentWork = {}) {
    const nextWork = { ...(currentWork && typeof currentWork === 'object' ? currentWork : {}) };
    const typeField = elements.work_type;
    if (typeField && typeof typeField.value === 'string') {
        const type = typeField.value.trim();
        if (type) {
            nextWork.type = type;
        }
    }
    const configFields = form?.querySelectorAll('[data-work-config-field]') || [];
    configFields.forEach((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement || field instanceof HTMLSelectElement)) {
            return;
        }
        const key = String(field.dataset.workConfigKey || '').trim();
        if (!key) {
            return;
        }
        const kind = String(field.dataset.workConfigKind || 'text').trim();
        const isNullable = field.dataset.workConfigNullable === 'true';
        if (field instanceof HTMLInputElement && field.type === 'checkbox') {
            nextWork[key] = !!field.checked;
            return;
        }
        const rawValue = field.value;
        if (kind === 'number') {
            nextWork[key] = rawValue === '' ? null : Number(rawValue);
            return;
        }
        if (kind === 'json') {
            const trimmed = String(rawValue || '').trim();
            if (trimmed === '') {
                nextWork[key] = null;
                return;
            }
            try {
                nextWork[key] = JSON.parse(trimmed);
            } catch {
                nextWork[key] = trimmed;
            }
            return;
        }
        const trimmed = String(rawValue || '').trim();
        if (isNullable && trimmed === '') {
            nextWork[key] = null;
            return;
        }
        nextWork[key] = rawValue;
    });
    const converterSelect = elements.converter_id;
    const selectedOption = converterSelect?.selectedOptions && converterSelect.selectedOptions[0]
        ? converterSelect.selectedOptions[0]
        : null;
    const converterId = String(converterSelect?.value || '').trim();
    const converterName = String(selectedOption?.dataset?.converterName || '').trim();
    const converterType = String(selectedOption?.dataset?.converterType || 'converter').trim() || 'converter';
    const converterEngine = String(selectedOption?.dataset?.converterEngine || '').trim();
    const converterPath = String(selectedOption?.dataset?.converterPath || '').trim();
    const converterViewerHref = String(selectedOption?.dataset?.converterViewerHref || '').trim();
    const converterUrl = String(selectedOption?.dataset?.converterUrl || '').trim();
    const existingConverter = nextWork.converter && typeof nextWork.converter === 'object' ? nextWork.converter : {};
    if (converterId) {
        nextWork.converter = {
            ...existingConverter,
            type: converterType,
            name: converterName || String(existingConverter.name || converterId.split('/').pop() || 'converter'),
            enabled: true,
            id: converterId,
            path: converterPath,
            viewerHref: converterViewerHref,
            url: converterUrl,
            node: converterEngine === 'remote-node'
                ? (existingConverter.node && typeof existingConverter.node === 'object' ? existingConverter.node : { id: '', baseUrl: '', mcpUrl: '', endpoint: '' })
                : 'local',
            quality: String(elements.converter_quality?.value || 'default').trim() || 'default',
            format: String(elements.converter_format?.value || '').trim() || 'source',
            saveMode: String(elements.converter_save_mode?.value || 'new-hidden-work').trim() || 'new-hidden-work',
            hiddenByDefault: true,
        };
    } else if (Object.prototype.hasOwnProperty.call(nextWork, 'converter')) {
        delete nextWork.converter;
    }
    return nextWork;
}
