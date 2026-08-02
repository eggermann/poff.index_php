<?php

function cmsEditSaveApplyBasicFields(array &$config, array $data, string $subjectType = '', ?string $targetFile = null): void
{
    $fileTitle = $targetFile !== null ? trim($targetFile) : '';
    $titleProvided = array_key_exists('title', $data);
    if ($titleProvided) {
        $title = trim((string) $data['title']);
        if ($title === '' && $subjectType === 'file' && $fileTitle !== '') {
            $title = $fileTitle;
        }
        $config['title'] = $title;
        if ($config['title'] !== '' && trim((string) ($config['slug'] ?? '')) === '') {
            $config['slug'] = PoffConfig::slugify((string) $config['title']);
        }
    } elseif ($subjectType === 'file' && trim((string) ($config['title'] ?? '')) === '' && $fileTitle !== '') {
        $config['title'] = $fileTitle;
        if (trim((string) ($config['slug'] ?? '')) === '') {
            $config['slug'] = PoffConfig::slugify($fileTitle);
        }
    }
    if (array_key_exists('description', $data)) {
        $config['description'] = trim((string) $data['description']);
    }
    foreach (['link', 'url'] as $key) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $value = trim((string) $data[$key]);
        if ($value !== '') {
            $config[$key] = $value;
        } else {
            unset($config[$key]);
        }
    }
    if (array_key_exists('promptHistory', $data)) {
        $promptHistory = is_array($data['promptHistory']) ? array_values($data['promptHistory']) : [];
        $config['promptHistory'] = array_slice($promptHistory, -12);
    }
}

function cmsEditSaveApplyExternalSubmissionSettings(array &$config, array $data, string $subjectType): void
{
    if ($subjectType !== 'folder' || !array_key_exists('externalSubmissions', $data) || !is_array($data['externalSubmissions'])) {
        return;
    }

    $source = $data['externalSubmissions'];
    $whitelist = [];
    foreach (is_array($source['whitelist'] ?? null) ? $source['whitelist'] : [] as $entry) {
        if (!is_scalar($entry)) {
            continue;
        }
        $candidate = trim((string) $entry);
        if ($candidate !== '' && !in_array($candidate, $whitelist, true)) {
            $whitelist[] = $candidate;
        }
    }
    $config['externalSubmissions'] = [
        'enabled' => !empty($source['enabled']),
        'whitelistEnabled' => !empty($source['whitelistEnabled']),
        'whitelist' => array_slice($whitelist, 0, 24),
        'maxPending' => min(20, max(1, (int) ($source['maxPending'] ?? 5))),
    ];
}
