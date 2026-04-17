<?php
declare(strict_types=1);

function mcpParseEditConfigPayload(array $data): array
{
    $title = trim((string) ($data['title'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $link = trim((string) ($data['link'] ?? ''));
    $url = trim((string) ($data['url'] ?? ''));
    $workInput = isset($data['work']) && is_array($data['work']) ? $data['work'] : null;
    $workType = trim((string) ($data['work']['type'] ?? $data['work_type'] ?? ''));
    $workLayoutRaw = $data['work']['layout'] ?? $data['work_layout'] ?? '';
    $workLayout = trim((string) $workLayoutRaw);

    $layoutPayload = $data['layout'] ?? null;
    $layoutPayloadMode = is_array($layoutPayload) ? ($layoutPayload['mode'] ?? $layoutPayload['name'] ?? '') : '';
    $layoutMode = trim((string) ($data['layout_mode'] ?? $layoutPayloadMode));
    $layoutModel = is_array($layoutPayload) ? ($layoutPayload['model'] ?? null) : null;
    $layoutModelProvided = array_key_exists('layout_model', $data);
    $layoutModeProvided = array_key_exists('layout_mode', $data);

    $layoutSectionTemplateProvided = false;
    $layoutSectionTemplate = null;
    $layoutCssProvided = false;
    $layoutCss = null;
    $layoutJsProvided = false;
    $layoutJs = null;
    $originalLayoutTarget = '';
    $originalLayoutTemplateProvided = false;
    $originalLayoutTemplate = null;
    $originalLayoutCssProvided = false;
    $originalLayoutCss = null;
    $originalLayoutJsProvided = false;
    $originalLayoutJs = null;

    if ($layoutModel === null && $layoutModelProvided) {
        $layoutModel = $data['layout_model'];
    }

    $layoutTemplateProvided = false;
    $layoutTemplate = null;
    if (is_array($layoutPayload) && array_key_exists('template', $layoutPayload)) {
        $layoutTemplateProvided = true;
        $layoutTemplate = (string) $layoutPayload['template'];
    }
    if (is_array($layoutPayload) && array_key_exists('sectionTemplate', $layoutPayload)) {
        $layoutSectionTemplateProvided = true;
        $layoutSectionTemplate = (string) $layoutPayload['sectionTemplate'];
    }
    if (is_array($layoutPayload) && (array_key_exists('css', $layoutPayload) || array_key_exists('style', $layoutPayload))) {
        $layoutCssProvided = true;
        $layoutCss = (string) ($layoutPayload['css'] ?? $layoutPayload['style'] ?? '');
    }
    if (is_array($layoutPayload) && (array_key_exists('js', $layoutPayload) || array_key_exists('script', $layoutPayload))) {
        $layoutJsProvided = true;
        $layoutJs = (string) ($layoutPayload['js'] ?? $layoutPayload['script'] ?? '');
    }
    if (is_array($layoutPayload) && array_key_exists('originalTarget', $layoutPayload)) {
        $originalLayoutTarget = trim((string) $layoutPayload['originalTarget']);
    }
    if (is_array($layoutPayload) && array_key_exists('originalTemplate', $layoutPayload)) {
        $originalLayoutTemplateProvided = true;
        $originalLayoutTemplate = (string) $layoutPayload['originalTemplate'];
    }
    if (is_array($layoutPayload) && (array_key_exists('originalCss', $layoutPayload) || array_key_exists('originalStyle', $layoutPayload))) {
        $originalLayoutCssProvided = true;
        $originalLayoutCss = (string) ($layoutPayload['originalCss'] ?? $layoutPayload['originalStyle'] ?? '');
    }
    if (is_array($layoutPayload) && (array_key_exists('originalJs', $layoutPayload) || array_key_exists('originalScript', $layoutPayload))) {
        $originalLayoutJsProvided = true;
        $originalLayoutJs = (string) ($layoutPayload['originalJs'] ?? $layoutPayload['originalScript'] ?? '');
    }

    if (array_key_exists('layout_template', $data)) {
        $layoutTemplateProvided = true;
        $layoutTemplate = (string) $data['layout_template'];
    }
    if (array_key_exists('layoutTemplate', $data)) {
        $layoutTemplateProvided = true;
        $layoutTemplate = (string) $data['layoutTemplate'];
    }
    if (array_key_exists('section_template', $data)) {
        $layoutSectionTemplateProvided = true;
        $layoutSectionTemplate = (string) $data['section_template'];
    }
    if (array_key_exists('sectionTemplate', $data)) {
        $layoutSectionTemplateProvided = true;
        $layoutSectionTemplate = (string) $data['sectionTemplate'];
    }
    if (array_key_exists('layout_css', $data)) {
        $layoutCssProvided = true;
        $layoutCss = (string) $data['layout_css'];
    }
    if (array_key_exists('layout_js', $data)) {
        $layoutJsProvided = true;
        $layoutJs = (string) $data['layout_js'];
    }
    if (array_key_exists('original_layout_target', $data)) {
        $originalLayoutTarget = trim((string) $data['original_layout_target']);
    }
    if (array_key_exists('originalLayoutTarget', $data)) {
        $originalLayoutTarget = trim((string) $data['originalLayoutTarget']);
    }
    if (array_key_exists('original_layout_template', $data)) {
        $originalLayoutTemplateProvided = true;
        $originalLayoutTemplate = (string) $data['original_layout_template'];
    }
    if (array_key_exists('originalLayoutTemplate', $data)) {
        $originalLayoutTemplateProvided = true;
        $originalLayoutTemplate = (string) $data['originalLayoutTemplate'];
    }
    if (array_key_exists('original_layout_css', $data)) {
        $originalLayoutCssProvided = true;
        $originalLayoutCss = (string) $data['original_layout_css'];
    }
    if (array_key_exists('originalLayoutCss', $data)) {
        $originalLayoutCssProvided = true;
        $originalLayoutCss = (string) $data['originalLayoutCss'];
    }
    if (array_key_exists('original_layout_js', $data)) {
        $originalLayoutJsProvided = true;
        $originalLayoutJs = (string) $data['original_layout_js'];
    }
    if (array_key_exists('originalLayoutJs', $data)) {
        $originalLayoutJsProvided = true;
        $originalLayoutJs = (string) $data['originalLayoutJs'];
    }

    $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
    $visibleKeys = [];
    $hasTreeUpdate = array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data);
    if (is_array($treeVisible)) {
        foreach ($treeVisible as $key) {
            if (is_scalar($key)) {
                $visibleKeys[(string) $key] = true;
            }
        }
    }

    return [
        'title' => $title,
        'description' => $description,
        'link' => $link,
        'url' => $url,
        'workInput' => $workInput,
        'workType' => $workType,
        'workLayout' => $workLayout,
        'layoutPayloadProvided' => is_array($layoutPayload),
        'layoutMode' => $layoutMode,
        'layoutModel' => $layoutModel,
        'layoutModelProvided' => $layoutModelProvided,
        'layoutModeProvided' => $layoutModeProvided,
        'layoutTemplateProvided' => $layoutTemplateProvided,
        'layoutTemplate' => $layoutTemplate,
        'layoutSectionTemplateProvided' => $layoutSectionTemplateProvided,
        'layoutSectionTemplate' => $layoutSectionTemplate,
        'layoutCssProvided' => $layoutCssProvided,
        'layoutCss' => $layoutCss,
        'layoutJsProvided' => $layoutJsProvided,
        'layoutJs' => $layoutJs,
        'originalLayoutTarget' => $originalLayoutTarget,
        'originalLayoutTemplateProvided' => $originalLayoutTemplateProvided,
        'originalLayoutTemplate' => $originalLayoutTemplate,
        'originalLayoutCssProvided' => $originalLayoutCssProvided,
        'originalLayoutCss' => $originalLayoutCss,
        'originalLayoutJsProvided' => $originalLayoutJsProvided,
        'originalLayoutJs' => $originalLayoutJs,
        'hasTreeUpdate' => $hasTreeUpdate,
        'visibleKeys' => $visibleKeys,
    ];
}
