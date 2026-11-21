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
    const normalizeSourceUrl = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }
        try {
            const baseHref = typeof window?.location?.href === 'string' && window.location.href
                ? window.location.href
                : 'http://localhost/';
            return new URL(raw, baseHref).toString();
        } catch {
            return raw;
        }
    };
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
    const converterLabel = String(selectedOption?.dataset?.converterLabel || '').trim();
    const converterDescription = String(selectedOption?.dataset?.converterDescription || '').trim();
    const parseConverterData = (key, fallback) => {
        try {
            const value = JSON.parse(selectedOption?.dataset?.[key] || '');
            return value && typeof value === 'object' ? value : fallback;
        } catch {
            return fallback;
        }
    };
    const converterNode = parseConverterData('converterNode', null);
    const converterAccepts = parseConverterData('converterAccepts', []);
    const converterOutputs = parseConverterData('converterOutputs', []);
    const converterDefaults = parseConverterData('converterDefaults', {});
    const converterUi = parseConverterData('converterUi', {});
    const converterSourceUrl = normalizeSourceUrl(elements.converter_source_url?.value || '');
    const existingConverter = nextWork.converter && typeof nextWork.converter === 'object' ? nextWork.converter : {};
    if (converterId) {
        const nextConverter = {
            ...existingConverter,
            type: converterType,
            name: converterName || String(existingConverter.name || converterId.split('/').pop() || 'converter'),
            enabled: true,
            id: converterId,
            path: converterPath,
            viewerHref: converterViewerHref,
            url: converterUrl,
            label: converterLabel,
            description: converterDescription,
            accepts: Array.isArray(converterAccepts) ? converterAccepts : [],
            outputs: Array.isArray(converterOutputs) ? converterOutputs : [],
            formats: String(selectedOption?.dataset?.converterFormats || '').split(',').map((item) => item.trim()).filter(Boolean),
            defaults: converterDefaults,
            ui: converterUi,
            sourceUrl: converterSourceUrl || String(existingConverter.sourceUrl || '').trim(),
            node: converterEngine === 'remote-node'
                ? (converterNode || (existingConverter.node && typeof existingConverter.node === 'object' ? existingConverter.node : { id: '', baseUrl: '', mcpUrl: '', endpoint: '', converterId: '' }))
                : 'local',
            quality: String(elements.converter_quality?.value || 'default').trim() || 'default',
            format: String(elements.converter_format?.value || '').trim() || 'source',
            saveMode: String(elements.converter_save_mode?.value || 'new-hidden-work').trim() || 'new-hidden-work',
            hiddenByDefault: true,
        };
        if (converterSourceUrl) {
            nextConverter.sourceUrl = converterSourceUrl;
        } else {
            delete nextConverter.sourceUrl;
        }
        nextWork.converter = nextConverter;
    } else if (Object.prototype.hasOwnProperty.call(nextWork, 'converter')) {
        delete nextWork.converter;
    }
    return nextWork;
}
