<?php

const CMS_PROMPT_LAYOUT_EXCERPT_MAX = 1800;
const CMS_PROMPT_DRAFT_EXCERPT_MAX = 1600;
const CMS_PROMPT_OUTER_WRAPPER_EXCERPT_MAX = 1200;
const CMS_PROMPT_HISTORY_ENTRY_MAX = 400;
const CMS_PROMPT_HISTORY_MAX_ITEMS = 4;

function cmsPromptTrimText(string $text, int $maxLength = 240): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return substr($normalized, 0, max(0, $maxLength - 3)) . '...';
}

function cmsPromptNormalizeStringList(array|string|null $value): array
{
    $items = is_array($value)
        ? $value
        : (is_string($value) && trim($value) !== '' ? preg_split('/\r?\n|,/', $value) : []);
    $result = [];
    foreach ($items ?: [] as $item) {
        $normalized = strtolower(trim((string) $item));
        if ($normalized === '' || in_array($normalized, $result, true)) {
            continue;
        }
        $result[] = $normalized;
    }

    return $result;
}

function cmsPromptCompactRef(array $ref): array
{
    $compact = [];
    foreach (['name', 'title', 'type', 'kind', 'path', 'pageLink', 'linkUrl', 'srcUrl', 'isFolder', 'isFile', 'visible'] as $key) {
        if (!array_key_exists($key, $ref)) {
            continue;
        }
        $value = $ref[$key];
        $compact[$key] = is_string($value) ? cmsPromptTrimText($value, 160) : $value;
    }

    return $compact;
}

function cmsPromptCompactTreeItems(array $items, int $maxDepth = 3, int $maxChildren = 12): array
{
    $compactItems = [];
    foreach (array_slice($items, 0, $maxChildren) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $compact = cmsPromptCompactRef($item);
        if (array_key_exists('childCount', $item) && is_scalar($item['childCount'])) {
            $compact['childCount'] = (int) $item['childCount'];
        }

        if ($maxDepth > 0 && is_array($item['children'] ?? null) && $item['children'] !== []) {
            $compact['children'] = cmsPromptCompactTreeItems($item['children'], $maxDepth - 1, $maxChildren);
        }

        $compactItems[] = $compact;
    }

    return $compactItems;
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
                            $layoutSummary[$layoutKey] = cmsPromptTrimText($value[$layoutKey], CMS_PROMPT_LAYOUT_EXCERPT_MAX);
                        }
                    }
                }
                if (is_array($value['assets'] ?? null)) {
                    $layoutSummary['assetCount'] = count($value['assets']);
                }
                $summary['work']['layout'] = $layoutSummary;
                continue;
            }

            if ($key === 'fields' && is_array($value)) {
                $fieldSummary = [];
                foreach (array_slice($value, 0, 12) as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $entry = [];
                    foreach (['type', 'name', 'label'] as $fieldKey) {
                        if (array_key_exists($fieldKey, $field) && is_scalar($field[$fieldKey])) {
                            $entry[$fieldKey] = is_string($field[$fieldKey])
                                ? cmsPromptTrimText((string) $field[$fieldKey], 96)
                                : $field[$fieldKey];
                        }
                    }
                    if (array_key_exists('value', $field)) {
                        $entry['value'] = is_string($field['value'])
                            ? cmsPromptTrimText((string) $field['value'], 160)
                            : $field['value'];
                    }
                    if ($entry !== []) {
                        $fieldSummary[] = $entry;
                    }
                }
                if ($fieldSummary !== []) {
                    $summary['work']['fields'] = $fieldSummary;
                    $summary['work']['fieldCount'] = count($value);
                }
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $summary['work'][$key] = $value;
                continue;
            }

            if ($key === 'categories' || $key === 'category') {
                $categories = cmsPromptNormalizeStringList($value);
                if ($categories !== []) {
                    $summary['work']['categories'] = $categories;
                }
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
    if (is_array($current['editorDraft'] ?? null)) {
        $draft = [];
    foreach (['template', 'sectionTemplate', 'css', 'js'] as $key) {
        if (!isset($current['editorDraft'][$key]) || !is_string($current['editorDraft'][$key])) {
            continue;
        }
        $draft[$key] = cmsPromptTrimText($current['editorDraft'][$key], CMS_PROMPT_DRAFT_EXCERPT_MAX);
        $draft[$key . 'Length'] = strlen($current['editorDraft'][$key]);
    }
        $current['editorDraft'] = $draft;
    }
    if (is_array($current['activeLayout'] ?? null)) {
        $activeLayout = [];
        foreach (['name', 'mode', 'storage', 'directory', 'inheritedDirectory', 'sectionDirectory'] as $key) {
            if (array_key_exists($key, $current['activeLayout'])) {
                $activeLayout[$key] = $current['activeLayout'][$key];
            }
        }
        foreach (['template', 'sectionTemplate', 'css', 'js'] as $key) {
            if (isset($current['activeLayout'][$key]) && is_string($current['activeLayout'][$key]) && $current['activeLayout'][$key] !== '') {
                $activeLayout[$key] = cmsPromptTrimText($current['activeLayout'][$key], CMS_PROMPT_LAYOUT_EXCERPT_MAX);
                $activeLayout[$key . 'Length'] = strlen($current['activeLayout'][$key]);
            }
        }
        $current['activeLayout'] = $activeLayout;
    }
    if (is_array($current['outerWrapper'] ?? null)) {
        $outerWrapper = [];
        foreach (['name', 'storage', 'sectionPartial', 'source'] as $key) {
            if (array_key_exists($key, $current['outerWrapper'])) {
                $outerWrapper[$key] = $current['outerWrapper'][$key];
            }
        }
        foreach (['template', 'css', 'js'] as $key) {
            if (isset($current['outerWrapper'][$key]) && is_string($current['outerWrapper'][$key]) && $current['outerWrapper'][$key] !== '') {
                $outerWrapper[$key] = cmsPromptTrimText($current['outerWrapper'][$key], CMS_PROMPT_OUTER_WRAPPER_EXCERPT_MAX);
                $outerWrapper[$key . 'Length'] = strlen($current['outerWrapper'][$key]);
            }
        }
        $current['outerWrapper'] = $outerWrapper;
    }
    if (is_array($current['root'] ?? null)) {
        $root = [];
        foreach (['title', 'name', 'folderName', 'path', 'slug', 'description', 'type'] as $key) {
            if (!array_key_exists($key, $current['root'])) {
                continue;
            }
            $value = $current['root'][$key];
            $root[$key] = is_string($value)
                ? cmsPromptTrimText($value, 220)
                : $value;
        }
        $current['root'] = $root;
    }
    if (is_array($current['work'] ?? null)) {
        $work = [];
        foreach (['title', 'name', 'path', 'slug', 'description', 'type', 'kind'] as $key) {
            if (!array_key_exists($key, $current['work'])) {
                continue;
            }
            $value = $current['work'][$key];
            $work[$key] = is_string($value)
                ? cmsPromptTrimText($value, 220)
                : $value;
        }
        $current['work'] = $work;
    }
    if (is_array($current['parentWork'] ?? null)) {
        $parentWork = [];
        foreach (['title', 'name', 'folderName', 'path', 'slug', 'description', 'type', 'kind', 'pageLink', 'srcUrl'] as $key) {
            if (!array_key_exists($key, $current['parentWork'])) {
                continue;
            }
            $value = $current['parentWork'][$key];
            $parentWork[$key] = is_string($value)
                ? cmsPromptTrimText($value, 220)
                : $value;
        }
        $current['parentWork'] = $parentWork;
    }
    if (is_array($current['tree'] ?? null)) {
        $current['tree'] = cmsPromptCompactTreeItems($current['tree']);
    }
    if (is_array($current['workTree'] ?? null)) {
        $current['workTree']['children'] = cmsPromptCompactTreeItems(
            is_array($current['workTree']['children'] ?? null) ? $current['workTree']['children'] : [],
            3,
            12
        );
    }
    if (is_array($current['workFields'] ?? null)) {
        $workFields = [];
        foreach (array_slice($current['workFields'], 0, 12) as $field) {
            if (!is_array($field)) {
                continue;
            }
            $compactField = [];
            foreach (['type', 'name', 'label'] as $key) {
                if (array_key_exists($key, $field) && is_scalar($field[$key])) {
                    $compactField[$key] = is_string($field[$key])
                        ? cmsPromptTrimText((string) $field[$key], 96)
                        : $field[$key];
                }
            }
            if (array_key_exists('value', $field)) {
                $compactField['value'] = is_string($field['value'])
                    ? cmsPromptTrimText((string) $field['value'], 180)
                    : $field['value'];
            }
            if ($compactField !== []) {
                $workFields[] = $compactField;
            }
        }
        $current['workFields'] = $workFields;
    }

    $compact = [
        'current' => $current,
    ];

    $siblingWorks = array_values(array_filter(array_map(
        static fn(array $ref): array => cmsPromptCompactRef($ref),
        array_slice(is_array($context['siblingWorks'] ?? null) ? $context['siblingWorks'] : [], 0, 24)
    )));
    if ($siblingWorks !== []) {
        $compact['siblingWorks'] = $siblingWorks;
        $compact['siblingCounts'] = [
            'items' => count(is_array($context['siblingWorks'] ?? null) ? $context['siblingWorks'] : []),
            'folders' => count(is_array($context['siblingFolders'] ?? null) ? $context['siblingFolders'] : []),
            'images' => count(is_array($context['siblingImages'] ?? null) ? $context['siblingImages'] : []),
            'videos' => count(is_array($context['siblingVideos'] ?? null) ? $context['siblingVideos'] : []),
            'audio' => count(is_array($context['siblingAudio'] ?? null) ? $context['siblingAudio'] : []),
            'pdfs' => count(is_array($context['siblingPdfs'] ?? null) ? $context['siblingPdfs'] : []),
            'texts' => count(is_array($context['siblingTexts'] ?? null) ? $context['siblingTexts'] : []),
            'links' => count(is_array($context['siblingLinks'] ?? null) ? $context['siblingLinks'] : []),
            'other' => count(is_array($context['siblingOther'] ?? null) ? $context['siblingOther'] : []),
        ];
    }

    $subjectType = strtolower(trim((string) ($current['subjectType'] ?? '')));
    if ($subjectType !== 'folder') {
        return $compact;
    }

    $items = is_array($context['items'] ?? null)
        ? cmsPromptCompactTreeItems($context['items'])
        : [];

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
    $recentHistory = array_slice($history, -CMS_PROMPT_HISTORY_MAX_ITEMS);
    $historyText = '';
    foreach ($recentHistory as $msg) {
        if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
            continue;
        }
        $role = strtolower((string) $msg['role']);
        $content = cmsPromptTrimText((string) $msg['content'], CMS_PROMPT_HISTORY_ENTRY_MAX);
        if ($content === '') {
            continue;
        }
        $historyText .= strtoupper($role) . ": " . $content . "\n";
    }

    return $historyText;
}
