<?php

function cmsPromptTrimText(string $text, int $maxLength = 240): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return substr($normalized, 0, max(0, $maxLength - 3)) . '...';
}

function cmsPromptCompactRef(array $ref): array
{
    $compact = [];
    foreach (['name', 'title', 'type', 'kind', 'path', 'pageLink', 'srcUrl', 'isFolder', 'isFile', 'visible'] as $key) {
        if (!array_key_exists($key, $ref)) {
            continue;
        }
        $value = $ref[$key];
        $compact[$key] = is_string($value) ? cmsPromptTrimText($value, 160) : $value;
    }

    return $compact;
}

function cmsPromptCompactConfig(array $config, bool $includeResolvedLayoutSource = false): array
{
    $summary = [];

    foreach (['title', 'description', 'folderName', 'updatedAt', 'treeHash'] as $key) {
        if (array_key_exists($key, $config) && is_scalar($config[$key])) {
            $summary[$key] = is_string($config[$key])
                ? cmsPromptTrimText((string) $config[$key], 240)
                : $config[$key];
        }
    }

    $work = is_array($config['work'] ?? null) ? $config['work'] : [];
    if ($work !== []) {
        $summary['work'] = [];
        foreach ($work as $key => $value) {
            if ($key === 'layout' && is_array($value)) {
                $layoutSummary = [];
                foreach (['name', 'mode', 'value', 'engine', 'section', 'storage', 'directory', 'inheritedDirectory', 'sectionDirectory', 'phpTemplate'] as $layoutKey) {
                    if (array_key_exists($layoutKey, $value) && is_scalar($value[$layoutKey])) {
                        $layoutSummary[$layoutKey] = is_string($value[$layoutKey])
                            ? cmsPromptTrimText((string) $value[$layoutKey], 180)
                            : $value[$layoutKey];
                    }
                }
                foreach (['template', 'sectionTemplate', 'css', 'style', 'js', 'script'] as $layoutKey) {
                    if (isset($value[$layoutKey]) && is_string($value[$layoutKey]) && $value[$layoutKey] !== '') {
                        $layoutSummary[$layoutKey . 'Length'] = strlen($value[$layoutKey]);
                        if ($includeResolvedLayoutSource) {
                            $layoutSummary[$layoutKey] = cmsPromptTrimText($value[$layoutKey], 6000);
                        }
                    }
                }
                if (is_array($value['assets'] ?? null)) {
                    $layoutSummary['assetCount'] = count($value['assets']);
                }
                $summary['work']['layout'] = $layoutSummary;
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $summary['work'][$key] = $value;
                continue;
            }

            if (is_string($value)) {
                $summary['work'][$key] = cmsPromptTrimText($value, 180);
            }
        }
    }

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    if ($tree !== []) {
        $sample = [];
        foreach (array_slice($tree, 0, 24) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sample[] = [
                'name' => cmsPromptTrimText((string) ($item['name'] ?? $item['path'] ?? ''), 120),
                'type' => (string) ($item['type'] ?? 'file'),
                'path' => cmsPromptTrimText((string) ($item['path'] ?? ''), 160),
                'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
            ];
        }
        $summary['tree'] = [
            'count' => count($tree),
            'sample' => $sample,
        ];
    }

    return $summary;
}

function cmsPromptCompactContext(array $context): array
{
    $current = is_array($context['current'] ?? null) ? $context['current'] : [];
    if (is_array($current['activeLayout'] ?? null)) {
        $activeLayout = [];
        foreach (['name', 'mode', 'storage', 'directory', 'inheritedDirectory', 'sectionDirectory'] as $key) {
            if (array_key_exists($key, $current['activeLayout'])) {
                $activeLayout[$key] = $current['activeLayout'][$key];
            }
        }
        foreach (['template', 'sectionTemplate', 'css', 'js'] as $key) {
            if (isset($current['activeLayout'][$key]) && is_string($current['activeLayout'][$key]) && $current['activeLayout'][$key] !== '') {
                $activeLayout[$key] = cmsPromptTrimText($current['activeLayout'][$key], 6000);
                $activeLayout[$key . 'Length'] = strlen($current['activeLayout'][$key]);
            }
        }
        $current['activeLayout'] = $activeLayout;
    }

    $compact = [
        'current' => $current,
    ];

    $subjectType = strtolower(trim((string) ($current['subjectType'] ?? '')));
    if ($subjectType !== 'folder') {
        return $compact;
    }

    $items = array_values(array_filter(array_map(
        static fn(array $ref): array => cmsPromptCompactRef($ref),
        array_slice(is_array($context['items'] ?? null) ? $context['items'] : [], 0, 24)
    )));

    $compact['counts'] = [
        'items' => count(is_array($context['items'] ?? null) ? $context['items'] : []),
        'files' => count(is_array($context['allFiles'] ?? null) ? $context['allFiles'] : []),
        'folders' => count(is_array($context['allFolders'] ?? null) ? $context['allFolders'] : []),
        'images' => count(is_array($context['allImages'] ?? null) ? $context['allImages'] : []),
        'videos' => count(is_array($context['allVideos'] ?? null) ? $context['allVideos'] : []),
        'audio' => count(is_array($context['allAudio'] ?? null) ? $context['allAudio'] : []),
        'pdfs' => count(is_array($context['allPdfs'] ?? null) ? $context['allPdfs'] : []),
        'texts' => count(is_array($context['allTexts'] ?? null) ? $context['allTexts'] : []),
        'links' => count(is_array($context['allLinks'] ?? null) ? $context['allLinks'] : []),
        'other' => count(is_array($context['allOther'] ?? null) ? $context['allOther'] : []),
    ];
    $compact['items'] = $items;

    return $compact;
}

function cmsPromptHistoryText(array $history): string
{
    $recentHistory = array_slice($history, -6);
    $historyText = '';
    foreach ($recentHistory as $msg) {
        if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
            continue;
        }
        $role = strtolower((string) $msg['role']);
        $content = cmsPromptTrimText((string) $msg['content'], 800);
        if ($content === '') {
            continue;
        }
        $historyText .= strtoupper($role) . ": " . $content . "\n";
    }

    return $historyText;
}
