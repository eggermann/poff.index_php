function normalizeDeployTargetName(value) {
    const normalized = String(value ?? '').trim().toLowerCase().replace(/\s+/g, '_').replace(/-+/g, '_');
    return normalized || 'default';
}

function resolveTargetSuffix(targetName) {
    const normalized = normalizeDeployTargetName(targetName);
    if (normalized === 'default') {
        return '';
    }

    if (normalized === 'dodi') {
        return '';
    }

    const namedSuffixes = {
        customer: 'customer',
        pageoff: 'pageoff',
    };

    if (Object.prototype.hasOwnProperty.call(namedSuffixes, normalized)) {
        return namedSuffixes[normalized];
    }

    const match = normalized.match(/(?:^|_)(\d+)$/);
    if (match) {
        return match[1];
    }

    return normalized;
}

function resolveDeployTargetProfile(targetName) {
    const normalizedTarget = normalizeDeployTargetName(targetName);
    const suffix = resolveTargetSuffix(normalizedTarget);
    return {
        targetName: normalizedTarget,
        suffix,
        isDefault: normalizedTarget === 'default',
    };
}

function buildTargetedKeys(baseKeys, suffix) {
    const keyList = Array.isArray(baseKeys) ? baseKeys : [baseKeys];
    if (!suffix) {
        return keyList;
    }

    const targeted = [];
    for (const key of keyList) {
        targeted.push(`${key}${suffix}`);
        targeted.push(`${key}_${suffix}`);
    }

    return [...targeted, ...keyList];
}

function getEnvValue(env, keys, fallback = '') {
    const keyList = Array.isArray(keys) ? keys : [keys];
    for (const key of keyList) {
        const processValue = process.env[key];
        if (typeof processValue === 'string' && processValue !== '') {
            return processValue;
        }

        const envValue = env[key];
        if (typeof envValue === 'string' && envValue !== '') {
            return envValue;
        }
    }

    return fallback;
}

function resolveTargetEnvValue(env, keys, suffix, fallback = '') {
    return getEnvValue(env, buildTargetedKeys(keys, suffix), fallback);
}

function requireTargetEnvValue(env, keys, suffix, label) {
    const value = resolveTargetEnvValue(env, keys, suffix);
    if (!value) {
        throw new Error(`Missing required deploy setting: ${label}`);
    }
    return value;
}

module.exports = {
    buildTargetedKeys,
    getEnvValue,
    normalizeDeployTargetName,
    resolveDeployTargetProfile,
    resolveTargetEnvValue,
    requireTargetEnvValue,
    resolveTargetSuffix,
};
