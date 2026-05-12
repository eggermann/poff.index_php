<?php

require_once __DIR__ . '/base.php';

function cmsPromptCompactConfig(array $config, bool $includeResolvedLayoutSource = false): array
{
    $summary = [];

    foreach (['title', 'description', 'folderName', 'updatedAt', 'treeHash'] as $key) {
        if (array_key_exists($key, $config) && is_scalar($config[$key])) {
            $summary[$key] = is_string($config[$key]) ? cmsPromptTrimText((string) $config[$key], 240) : $config[$key];
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
                        $layoutSummary[$layoutKey] = is_string($value[$layoutKey]) ? cmsPromptTrimText((string) $value[$layoutKey], 180) : $value[$layoutKey];
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
                            $entry[$fieldKey] = is_string($field[$fieldKey]) ? cmsPromptTrimText((string) $field[$fieldKey], 96) : $field[$fieldKey];
                        }
                    }
                    if (array_key_exists('value', $field)) {
                        $entry['value'] = is_string($field['value']) ? cmsPromptTrimText((string) $field['value'], 160) : $field['value'];
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
            if ($key === 'templateMap' && is_array($value)) {
                $templateMap = cmsPromptCompactTemplateMap($value);
                if ($templateMap !== []) {
                    $summary['work']['templateMap'] = $templateMap;
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
        $summary['tree'] = ['count' => count($tree), 'sample' => $sample];
    }

    return $summary;
}
