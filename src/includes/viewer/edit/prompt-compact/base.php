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
    $items = is_array($value) ? $value : (is_string($value) && trim($value) !== '' ? preg_split('/\r?\n|,/', $value) : []);
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

function cmsPromptCompactTemplateMap(array|string|null $value, int $maxEntries = 12): array
{
    if (!class_exists('Worktype')) {
        return [];
    }

    $map = Worktype::normalizeTemplateMap($value);
    if ($map === []) {
        return [];
    }

    $entries = [];
    foreach (array_slice($map, 0, $maxEntries, true) as $mime => $template) {
        $entries[] = ['mime' => $mime, 'template' => $template];
    }

    return ['count' => count($map), 'entries' => $entries];
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
