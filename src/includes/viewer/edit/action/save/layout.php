<?php

function cmsEditSaveApplyLayoutFields(array &$config, array $data, string $subjectType, string $targetDir, ?string $targetFile): void
{
    $layoutPayload = isset($data['layout']) && is_array($data['layout']) ? $data['layout'] : null;
    $layoutMode = '';
    $layoutPreset = '';
    $layoutModel = null;
    $layoutTemplateProvided = false;
    $layoutTemplate = null;
    $layoutSectionTemplateProvided = false;
    $layoutSectionTemplate = null;
    $layoutCssProvided = false;
    $layoutCss = null;
    $layoutJsProvided = false;
    $layoutJs = null;
    $hasLayoutUpdate = false;
    $originalLayoutTarget = '';
    $originalLayoutTemplateProvided = false;
    $originalLayoutTemplate = null;
    $originalLayoutCssProvided = false;
    $originalLayoutCss = null;
    $originalLayoutJsProvided = false;
    $originalLayoutJs = null;

    if (is_array($layoutPayload)) {
        $hasLayoutUpdate = true;
        $layoutMode = trim((string) ($layoutPayload['mode'] ?? $layoutPayload['name'] ?? ''));
        $layoutPreset = trim((string) ($layoutPayload['preset'] ?? ''));
        $layoutModel = $layoutPayload['model'] ?? null;
        if (array_key_exists('template', $layoutPayload)) {
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $layoutPayload['template'];
        }
        if (array_key_exists('sectionTemplate', $layoutPayload)) {
            $layoutSectionTemplateProvided = true;
            $layoutSectionTemplate = (string) $layoutPayload['sectionTemplate'];
        }
        foreach (['workTemplate', 'worksTemplate'] as $siblingSectionKey) {
            if (array_key_exists($siblingSectionKey, $layoutPayload)) {
                $hasLayoutUpdate = true;
            }
        }
        if (array_key_exists('css', $layoutPayload) || array_key_exists('style', $layoutPayload)) {
            $layoutCssProvided = true;
            $layoutCss = (string) ($layoutPayload['css'] ?? $layoutPayload['style'] ?? '');
        }
        if (array_key_exists('js', $layoutPayload) || array_key_exists('script', $layoutPayload)) {
            $layoutJsProvided = true;
            $layoutJs = (string) ($layoutPayload['js'] ?? $layoutPayload['script'] ?? '');
        }
        if (array_key_exists('originalTarget', $layoutPayload)) {
            $originalLayoutTarget = trim((string) $layoutPayload['originalTarget']);
        }
        if (array_key_exists('originalTemplate', $layoutPayload)) {
            $originalLayoutTemplateProvided = true;
            $originalLayoutTemplate = (string) $layoutPayload['originalTemplate'];
        }
        if (array_key_exists('originalCss', $layoutPayload) || array_key_exists('originalStyle', $layoutPayload)) {
            $originalLayoutCssProvided = true;
            $originalLayoutCss = (string) ($layoutPayload['originalCss'] ?? $layoutPayload['originalStyle'] ?? '');
        }
        if (array_key_exists('originalJs', $layoutPayload) || array_key_exists('originalScript', $layoutPayload)) {
            $originalLayoutJsProvided = true;
            $originalLayoutJs = (string) ($layoutPayload['originalJs'] ?? $layoutPayload['originalScript'] ?? '');
        }
    }

    foreach ([
        'layout_mode' => 'layoutMode',
        'layout_preset' => 'layoutPreset',
        'layoutPreset' => 'layoutPreset',
        'layout_model' => 'layoutModel',
        'layout_template' => 'layoutTemplate',
        'layoutTemplate' => 'layoutTemplate',
        'section_template' => 'layoutSectionTemplate',
        'sectionTemplate' => 'layoutSectionTemplate',
        'layout_css' => 'layoutCss',
        'layout_js' => 'layoutJs',
        'original_layout_target' => 'originalLayoutTarget',
        'originalLayoutTarget' => 'originalLayoutTarget',
        'original_layout_template' => 'originalLayoutTemplate',
        'originalLayoutTemplate' => 'originalLayoutTemplate',
        'original_layout_css' => 'originalLayoutCss',
        'originalLayoutCss' => 'originalLayoutCss',
        'original_layout_js' => 'originalLayoutJs',
        'originalLayoutJs' => 'originalLayoutJs',
    ] as $key => $slotName) {
        if (!array_key_exists($key, $data)) {
            continue;
        }
        $hasLayoutUpdate = true;
        $$slotName = is_string($$slotName) ? trim((string) $data[$key]) : $data[$key];
    }

    $workLayout = array_key_exists('work_layout', $data) ? trim((string) $data['work_layout']) : '';
    $layoutSection = $subjectType === 'folder' ? 'works' : 'work';
    $sectionOnlyLayoutUpdate = $hasLayoutUpdate
        && $layoutSectionTemplateProvided
        && !$layoutTemplateProvided
        && !$layoutCssProvided
        && !$layoutJsProvided
        && $layoutMode === ''
        && $layoutPreset === ''
        && (!is_string($layoutModel) || trim((string) $layoutModel) === '')
        && $originalLayoutTarget === ''
        && !$originalLayoutTemplateProvided
        && !$originalLayoutCssProvided
        && !$originalLayoutJsProvided;

    if ($hasLayoutUpdate) {
        $layoutValue = $config['work']['layout'] ?? null;
        $layout = is_array($layoutValue) ? $layoutValue : [];
        if (is_string($layoutValue) && $layoutValue !== '') {
            $layout['mode'] = $layoutValue;
        }
        if ($layoutMode !== '') {
            $layout['mode'] = $layoutMode;
            $layout['name'] = $layoutMode;
            if ($layoutMode === 'none') {
                $layout['preset'] = 'none';
            }
        }
        if ($layoutPreset === 'inherit') {
            $layoutPreset = 'actual';
        }
        if (in_array($layoutPreset, ['actual', 'none', 'custom', 'shared'], true)) {
            $layout['preset'] = $layoutPreset;
        }
        if (is_array($layoutPayload) && array_key_exists('source', $layoutPayload)) {
            $layout['source'] = trim((string) $layoutPayload['source']);
        }
        if (is_array($layoutPayload) && array_key_exists('sharedName', $layoutPayload)) {
            $layout['sharedName'] = trim((string) $layoutPayload['sharedName']);
        }
        if ($layoutTemplateProvided) {
            $layout['template'] = $layoutTemplate;
        }
        if ($layoutSectionTemplateProvided) {
            $layout['sectionTemplate'] = $layoutSectionTemplate;
        }
        if (is_array($layoutPayload)) {
            foreach (['workTemplate', 'worksTemplate'] as $siblingSectionKey) {
                if (array_key_exists($siblingSectionKey, $layoutPayload)) {
                    $layout[$siblingSectionKey] = (string) $layoutPayload[$siblingSectionKey];
                }
            }
        }
        if ($layoutCssProvided) {
            $layout['css'] = $layoutCss;
        }
        if ($layoutJsProvided) {
            $layout['js'] = $layoutJs;
        }
        if (is_string($layoutModel) && $layoutModel !== '') {
            $layout['model'] = $layoutModel;
        }
        foreach (['template', 'sectionTemplate', 'workTemplate', 'worksTemplate', 'css', 'js'] as $layoutFileKey) {
            $wasProvided = match ($layoutFileKey) {
                'template' => $layoutTemplateProvided,
                'sectionTemplate' => $layoutSectionTemplateProvided,
                'workTemplate' => is_array($layoutPayload) && array_key_exists('workTemplate', $layoutPayload),
                'worksTemplate' => is_array($layoutPayload) && array_key_exists('worksTemplate', $layoutPayload),
                'css' => $layoutCssProvided,
                'js' => $layoutJsProvided,
            };
            if (!$wasProvided && array_key_exists($layoutFileKey, $layout) && trim((string) $layout[$layoutFileKey]) === '') {
                unset($layout[$layoutFileKey]);
            }
            }
            $config['work']['layout'] = Worktype::normalizeLayout($layout, $layoutSection);
        } elseif ($workLayout !== '') {
            $config['work']['layout'] = Worktype::normalizeLayout($workLayout, $layoutSection);
        }
    cmsEditSaveApplySectionOnlyLayoutTemplate(
        $config,
        $sectionOnlyLayoutUpdate,
        $targetDir,
        $targetFile,
        $layoutSectionTemplate,
        $layoutSection,
        $subjectType
    );

    cmsEditSavePersistOriginalLayoutFiles(
        $config,
        $originalLayoutTarget,
        $originalLayoutTemplateProvided,
        $originalLayoutTemplate,
        $originalLayoutCssProvided,
        $originalLayoutCss,
        $originalLayoutJsProvided,
        $originalLayoutJs
    );
    cmsEditSaveApplyLayoutTreeVisibility($config, $data, $subjectType);
    $config['updatedAt'] = date('c');
    if ($subjectType === 'folder') {
        $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
    }
}
