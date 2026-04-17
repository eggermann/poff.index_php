<?php
declare(strict_types=1);

function mcpApplyEditConfigPayload(array $config, array $payload, string $targetDir): array
{
    $config['title'] = $payload['title'];
    $config['description'] = $payload['description'];

    if ($payload['link'] !== '') {
        $config['link'] = $payload['link'];
    } else {
        unset($config['link']);
    }

    if ($payload['url'] !== '') {
        $config['url'] = $payload['url'];
    } else {
        unset($config['url']);
    }

    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    if (is_array($payload['workInput'])) {
        foreach ($payload['workInput'] as $key => $value) {
            if ($key === 'type') {
                continue;
            }
            $work[$key] = $value;
        }
    }
    if ($payload['workType'] !== '') {
        $work['type'] = $payload['workType'];
    }

    $layoutValue = $work['layout'] ?? null;
    $layout = is_array($layoutValue) ? $layoutValue : [];
    if (is_string($layoutValue) && $layoutValue !== '') {
        $layout['mode'] = $layoutValue;
    }
    if ($payload['layoutMode'] !== '') {
        $layout['mode'] = $payload['layoutMode'];
    }
    if ($payload['layoutTemplateProvided']) {
        $layout['template'] = $payload['layoutTemplate'];
    }
    if ($payload['layoutSectionTemplateProvided']) {
        $layout['sectionTemplate'] = $payload['layoutSectionTemplate'];
    }
    if ($payload['layoutCssProvided']) {
        $layout['css'] = $payload['layoutCss'];
    }
    if ($payload['layoutJsProvided']) {
        $layout['js'] = $payload['layoutJs'];
    }
    if (is_string($payload['layoutModel']) && $payload['layoutModel'] !== '') {
        $layout['model'] = $payload['layoutModel'];
    }

    $layoutSection = 'works';
    $hasLayoutUpdate = $payload['layoutPayloadProvided']
        || $payload['layoutTemplateProvided']
        || $payload['layoutSectionTemplateProvided']
        || $payload['layoutCssProvided']
        || $payload['layoutJsProvided']
        || $payload['layoutModeProvided']
        || $payload['layoutModelProvided'];

    if ($hasLayoutUpdate) {
        $work['layout'] = Worktype::normalizeLayout($layout, $layoutSection);
    } elseif ($payload['workLayout'] !== '') {
        $work['layout'] = Worktype::normalizeLayout($payload['workLayout'], $layoutSection);
    }

    $work['layout'] = PoffConfig::persistLayoutFiles($targetDir, null, $work['layout'] ?? null, $layoutSection);

    if (
        $payload['originalLayoutTarget'] !== ''
        && ($payload['originalLayoutTemplateProvided'] || $payload['originalLayoutCssProvided'] || $payload['originalLayoutJsProvided'])
    ) {
        try {
            PoffConfig::persistOriginalLayoutFiles($payload['originalLayoutTarget'], [
                'template' => $payload['originalLayoutTemplateProvided'] ? $payload['originalLayoutTemplate'] : null,
                'css' => $payload['originalLayoutCssProvided'] ? $payload['originalLayoutCss'] : null,
                'js' => $payload['originalLayoutJsProvided'] ? $payload['originalLayoutJs'] : null,
            ]);
        } catch (InvalidArgumentException $error) {
            return [
                'config' => $config,
                'error' => $error->getMessage(),
            ];
        }
    }

    $config['work'] = $work;

    if ($payload['hasTreeUpdate'] && isset($config['tree']) && is_array($config['tree'])) {
        foreach ($config['tree'] as &$item) {
            $key = $item['path'] ?? $item['name'] ?? null;
            if ($key === null) {
                continue;
            }
            $item['visible'] = isset($payload['visibleKeys'][$key]);
        }
        unset($item);
    }

    $config['updatedAt'] = date('c');
    $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));

    return [
        'config' => $config,
        'error' => null,
    ];
}
