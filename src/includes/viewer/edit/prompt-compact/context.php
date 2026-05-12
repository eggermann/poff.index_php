<?php

require_once __DIR__ . '/base.php';

function cmsPromptCompactContext(array $context): array
{
    $current = is_array($context['current'] ?? null) ? $context['current'] : [];
    $summary = ['current' => []];

    foreach (['targetType', 'subjectType', 'layoutPreset', 'name', 'path', 'templateTarget', 'sectionTemplateTarget', 'layoutBaseHref', 'layoutSectionBaseHref', 'layoutAssets', 'outerWrapper'] as $key) {
        if (!array_key_exists($key, $current)) {
            continue;
        }
        $value = $current[$key];
        if ($key === 'outerWrapper' && is_array($value)) {
            $summary['current'][$key] = [
                'name' => (string) ($value['name'] ?? ''),
                'storage' => (string) ($value['storage'] ?? ''),
                'sectionPartial' => (string) ($value['sectionPartial'] ?? ''),
                'source' => (string) ($value['source'] ?? ''),
                'template' => isset($value['template']) && is_string($value['template']) ? cmsPromptTrimText($value['template'], CMS_PROMPT_OUTER_WRAPPER_EXCERPT_MAX) : '',
                'templateLength' => isset($value['template']) && is_string($value['template']) ? strlen($value['template']) : 0,
                'cssLength' => isset($value['css']) && is_string($value['css']) ? strlen($value['css']) : 0,
                'jsLength' => isset($value['js']) && is_string($value['js']) ? strlen($value['js']) : 0,
            ];
            continue;
        }
        if ($key === 'layoutAssets' && is_array($value)) {
            $summary['current'][$key] = [
                'count' => count($value),
                'sample' => cmsPromptCompactTreeItems($value, 1, 4),
            ];
            continue;
        }
        $summary['current'][$key] = is_string($value) ? cmsPromptTrimText((string) $value, 240) : $value;
    }
    foreach (['title', 'sectionPartial', 'virtualPath', 'inheritedLayoutDirectory'] as $key) {
        if (array_key_exists($key, $current) && is_scalar($current[$key])) {
            $summary['current'][$key] = is_string($current[$key]) ? cmsPromptTrimText((string) $current[$key], 240) : $current[$key];
        }
    }
    foreach (['root', 'work', 'parentWork', 'workFields', 'editorDraft', 'activeLayout', 'tree', 'workTree'] as $key) {
        if (isset($current[$key]) && is_array($current[$key])) {
            $value = $current[$key];
            if ($key === 'work' && isset($value['templateMap']) && is_array($value['templateMap'])) {
                $value['templateMap'] = cmsPromptCompactTemplateMap($value['templateMap']);
            }
            $summary['current'][$key] = $value;
        }
    }

    if (isset($context['items']) && is_array($context['items']) && $context['items'] !== []) {
        $summary['counts'] = [
            'items' => count($context['items']),
            'files' => isset($context['allFiles']) && is_array($context['allFiles']) ? count($context['allFiles']) : 0,
            'folders' => isset($context['allFolders']) && is_array($context['allFolders']) ? count($context['allFolders']) : 0,
        ];
    }

    foreach (['items', 'allItems', 'allFiles', 'allFolders', 'allImages', 'allVideos', 'allAudio', 'allPdfs', 'allTexts', 'allLinks', 'allOther'] as $key) {
        if (isset($context[$key]) && is_array($context[$key]) && $context[$key] !== []) {
            $summary[$key] = cmsPromptCompactTreeItems($context[$key]);
        }
    }
    foreach (['siblingWorks', 'siblingImages', 'siblingVideos', 'siblingAudio', 'siblingPdfs', 'siblingTexts', 'siblingLinks', 'siblingFolders', 'siblingOther'] as $key) {
        if (isset($context[$key]) && is_array($context[$key]) && $context[$key] !== []) {
            $summary[$key] = cmsPromptCompactTreeItems($context[$key], 2, 8);
        }
    }

    return $summary;
}
