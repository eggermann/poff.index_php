<?php

function cmsBuildEditPromptRequest(array $ctx): array
{
    $data = $ctx['data'];
    $layoutPreset = trim((string) ($data['layoutPreset'] ?? $data['layout_preset'] ?? ''));
    $editorDraft = is_array($data['draft'] ?? null) ? $data['draft'] : [];
    $promptMode = strtolower(trim((string) ($data['promptMode'] ?? $data['prompt_mode'] ?? '')));
    $promptTarget = strtolower(trim((string) ($data['promptTarget'] ?? $data['prompt_target'] ?? '')));
    $promptIsLayoutTarget = $ctx['isLayoutTarget']
        || $promptMode === 'layout'
        || in_array($promptTarget, ['layout', 'wrapper', 'layout-wrapper'], true)
        || ($layoutPreset !== '' && !$ctx['isLayoutTarget']);
    $image = cmsPromptImagePayload($data);
    $folderViewData = $ctx['subjectType'] === 'folder'
        ? cmsPromptFolderViewData($ctx['subjectRelativePath'], $ctx['targetDir'], $ctx['config'], [
            'name' => (string) ($ctx['config']['folderName'] ?? basename($ctx['subjectRelativePath'])),
            'title' => (string) ($ctx['config']['title'] ?? $ctx['config']['folderName'] ?? basename($ctx['subjectRelativePath'])),
            'slug' => (string) ($ctx['config']['slug'] ?? PoffConfig::slugify((string) ($ctx['config']['folderName'] ?? basename($ctx['subjectRelativePath'])))),
        ])
        : [];
    $promptConfig = $ctx['config'];
    if (is_array($promptConfig['work'] ?? null)) {
        $promptConfig['work']['templateMap'] = PoffConfig::resolveEffectiveTemplateMap($ctx['targetDir'], $promptConfig['work']['templateMap'] ?? null);
    }
    $promptContext = cmsBuildPromptContext(
        $ctx['subjectRelativePath'],
        $ctx['subjectType'],
        $promptConfig,
        $ctx['targetFile'],
        $promptIsLayoutTarget,
        $layoutPreset,
        $editorDraft,
        cmsPromptParentConfig($ctx['rootDir'], $ctx['subjectRelativePath'], $ctx['subjectType'], $ctx['targetDir']),
        $folderViewData
    );
    if ($promptIsLayoutTarget && is_array($promptConfig['work']['layout'] ?? null)) {
        $promptContext['current']['activeLayout'] = [
            'name' => (string) ($promptConfig['work']['layout']['name'] ?? ''),
            'mode' => (string) ($promptConfig['work']['layout']['mode'] ?? ''),
            'storage' => (string) ($promptConfig['work']['layout']['storage'] ?? ''),
            'source' => (string) ($promptConfig['work']['layout']['source'] ?? ''),
            'directory' => (string) ($promptConfig['work']['layout']['directory'] ?? ''),
            'inheritedDirectory' => (string) ($promptConfig['work']['layout']['inheritedDirectory'] ?? ''),
            'sectionDirectory' => (string) ($promptConfig['work']['layout']['sectionDirectory'] ?? ''),
            'sharedName' => (string) ($promptConfig['work']['layout']['sharedName'] ?? ''),
            'template' => (string) ($promptConfig['work']['layout']['template'] ?? ''),
            'sectionTemplate' => (string) ($promptConfig['work']['layout']['sectionTemplate'] ?? ''),
            'css' => (string) ($promptConfig['work']['layout']['css'] ?? ($promptConfig['work']['layout']['style'] ?? '')),
            'js' => (string) ($promptConfig['work']['layout']['js'] ?? ($promptConfig['work']['layout']['script'] ?? '')),
        ];
    }
    $promptContextJson = json_encode(cmsPromptCompactContext($promptContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $configJson = json_encode(cmsPromptCompactConfig($promptConfig, $promptIsLayoutTarget), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $responseFormatInstruction = implode("\n", $promptIsLayoutTarget
        ? [
            'Response format: return strict JSON.',
            'Required key: "template" with the outer layout wrapper HBS string.',
            'Optional keys: "css", "js", and "work". Put wrapper styling in "css" as scoped plain CSS. Put behavior in "js" only.',
            'If the user chooses a shared/marketplace layout, include "source":"shared" and "sharedName":"<layout>" so the same worktype family can resolve the imported template.',
            'For layouts shared by folders and files, return sibling partials in "work": {"works.hbs":"folder inner partial","work.hbs":"file inner partial"}.',
            'Optional key: "work" for work.* updates when the user explicitly requests them, including custom work.fields entries.',
            'Template requirement: keep a <main class="poff-default-layout__main"> block that renders {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}.',
            'Do not use Tailwind utility classes, inline style attributes, or <style> tags. Use semantic HTML and stable readable class names.',
            'Scope CSS under a unique root class used by the returned wrapper. Do not define global selectors like body, a, img, h1 unless nested under that root class.',
        ]
        : [
            'Response format: return strict JSON.',
            'Required key: "template" with only the current inner partial HBS string.',
            'Optional key: "work" for work.* updates when the user explicitly requests them.',
            'Optional key: "treeVisible" as an array of same-folder parent tree item names/paths to keep visible when the user asks to hide used sibling works.',
            'Do not return code fences, the outer layout wrapper, a full HTML page shell, nav chrome, or footer.',
            'Use Prompt context JSON current.templateTarget / current.sectionTemplateTarget to understand the exact save target.',
            'Treat layout metadata as reference only; the answer must be inner partial content for the existing wrapper.',
            'Do not include {{> works}} or {{> work}} inside the inner partial unless the current source already requires it.',
            'Do not use Tailwind utility classes, inline style attributes, or <style> tags. Use semantic HTML and stable readable class names.',
            'Do not return "css" or "js" for work prompts. Work prompts update only the inner HBS partial; layout prompts own wrapper CSS and JS.',
        ]);
    $sharedWorkSystemPrompt = array_merge([
        'You are a Handlebars (HBS) template generator for this single-page CMS.',
        cmsPromptSharedWorkPromptLead(),
    ], cmsPromptSharedWorkSystemPromptLines(), [
        'If Prompt context JSON current.editorDraft is present, treat it as the latest unsaved editor state and revise that draft before falling back to saved config or saved template sources.',
    ]);
    $fileWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, cmsPromptSharedFileSystemPromptLines());
    $folderWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, cmsPromptSharedFolderSystemPromptLines());
    $defaultSystemPrompt = implode("\n", $promptIsLayoutTarget
        ? [
            'You are a Handlebars (HBS) layout generator for this single-page CMS.',
            'Treat the currently resolved active wrapper as your primary reference. Prompt context JSON current.activeLayout and Config JSON work.layout contain the actual active template, sectionTemplate, css, and js after filesystem, inheritance, and preset resolution.',
            'When the active layout is empty or too minimal, fall back to the built-in default wrapper shape from src/includes/worktypes/templates/layout/default/template.hbs.',
            'The prompt edits the outer layout wrapper template, not the wrapped inner work.hbs or works.hbs partial.',
            'Keep the wrapped content chain active and preserve the data flow from the current item context all the way down to the inner partial. Use {{> works}} for folders and {{> work}} for files inside the layout wrapper unless the user explicitly asks to remove or replace it.',
            'The wrapper owns the page shell and must wrap the inner partial. Return one outer template that includes {{> works}} or {{> work}} exactly once unless the user explicitly asks for a different structure.',
            'For layout wrappers that should look consistent for folders and files, put sibling partials in work: {"works.hbs":"folder inner partial","work.hbs":"file inner partial"}.',
            'Always keep a <main class="poff-default-layout__main"> block whose content is exactly {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}. Do not omit this block.',
            'Return the wrapper as real Handlebars template code. Use the same runtime fields, partials, conditionals, and folder/file context that the active template already uses when they are still relevant.',
            ...cmsPromptSharedLayoutSystemPromptLines(),
            'current.sectionTemplateTarget is the advanced inner partial path, not the default save target here.',
            'Prompt context JSON current.activeLayout.sectionTemplate is the current wrapped work/works partial, and current.activeLayout.css/js are the currently active style and script sources.',
        ]
        : ($ctx['subjectType'] === 'folder' ? $folderWorkSystemPrompt : $fileWorkSystemPrompt));

    return [
        'layoutPreset' => $layoutPreset,
        'image' => $image,
        'promptIsLayoutTarget' => $promptIsLayoutTarget,
        'folderViewData' => $folderViewData,
        'promptConfig' => $promptConfig,
        'promptContext' => $promptContext,
        'promptContextJson' => $promptContextJson,
        'configJson' => $configJson,
        'responseFormatInstruction' => $responseFormatInstruction,
        'systemPrompt' => $ctx['data']['systemPrompt'] ?? '',
        'defaultSystemPrompt' => $defaultSystemPrompt,
        'history' => is_array($ctx['data']['history'] ?? null) ? $ctx['data']['history'] : [],
        'prompt' => trim((string) ($ctx['data']['prompt'] ?? '')),
        'provider' => strtolower((string) ($ctx['data']['provider'] ?? 'local')),
        'model' => trim((string) ($ctx['data']['model'] ?? '')),
        'endpoint' => trim((string) ($ctx['data']['endpoint'] ?? '')),
        'apiKey' => trim((string) ($ctx['data']['apiKey'] ?? '')),
        'streamRequested' => !empty($ctx['data']['stream']),
    ];
}
