<?php

function cmsPromptOuterWrapperReference(array $layoutValue, string $currentSection): array
{
    $layoutName = trim((string) ($layoutValue['name'] ?? Worktype::defaultLayoutName()));
    if ($layoutName === '') {
        $layoutName = Worktype::defaultLayoutName();
    }

    $storage = trim((string) ($layoutValue['storage'] ?? ''));
    if ($storage === '') {
        $storage = 'default';
    }

    $section = trim((string) ($layoutValue['section'] ?? $currentSection));
    if ($section === '') {
        $section = $currentSection;
    }

    $templateName = $layoutName;
    $templateCandidate = Worktype::template($templateName);
    if (!is_string($templateCandidate) || $templateCandidate === '') {
        $templateName = Worktype::defaultLayoutName();
        $templateCandidate = Worktype::template($templateName);
    }

    $template = '';
    if (isset($layoutValue['template']) && is_string($layoutValue['template']) && trim($layoutValue['template']) !== '') {
        $template = $layoutValue['template'];
    } else {
        $template = (string) ($templateCandidate ?? '');
    }

    $css = '';
    if (isset($layoutValue['css']) && is_string($layoutValue['css']) && trim($layoutValue['css']) !== '') {
        $css = $layoutValue['css'];
    } else {
        $css = (string) (Worktype::layoutBundleAsset($templateName, 'style.css') ?? '');
    }

    $js = '';
    if (isset($layoutValue['js']) && is_string($layoutValue['js']) && trim($layoutValue['js']) !== '') {
        $js = $layoutValue['js'];
    } else {
        $js = (string) (Worktype::layoutBundleAsset($templateName, 'script.js') ?? '');
    }

    return [
        'name' => $layoutName,
        'storage' => $storage,
        'sectionPartial' => $section,
        'source' => $storage === 'filesystem'
            ? 'resolved active wrapper'
            : ($storage === 'inline' ? 'inline wrapper config' : 'bundled default wrapper reference'),
        'template' => $template,
        'css' => $css,
        'js' => $js,
    ];
}
