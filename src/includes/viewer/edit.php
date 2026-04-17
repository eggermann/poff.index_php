<?php
/**
 * Edit actions: config, save, and prompt endpoints.
 */

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/edit/prompt-parse.php';
require_once __DIR__ . '/edit/prompt-refs.php';
require_once __DIR__ . '/edit/prompt-context.php';
require_once __DIR__ . '/edit/prompt-compact.php';
require_once __DIR__ . '/edit/upload.php';

function cmsIniBytes(string $value): int
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 0;
    }

    $unit = strtolower(substr($trimmed, -1));
    $number = (float) $trimmed;

    return match ($unit) {
        'g' => (int) round($number * 1024 * 1024 * 1024),
        'm' => (int) round($number * 1024 * 1024),
        'k' => (int) round($number * 1024),
        default => (int) round((float) $trimmed),
    };
}

function cmsUploadLimits(): array
{
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');

    return [
        'postMax' => $postMax,
        'postMaxBytes' => cmsIniBytes($postMax),
        'uploadMax' => $uploadMax,
        'uploadMaxBytes' => cmsIniBytes($uploadMax),
        'maxFileUploads' => (int) ini_get('max_file_uploads'),
    ];
}

function cmsIsOpenAiCompatibleEndpoint(string $url): bool
{
    $normalized = strtolower(trim($url));
    return $normalized !== '' && str_contains($normalized, '/v1/chat/completions');
}

function cmsHandleEditAction(): void
{
    $action = $_GET['edit'] ?? '';
    if (!in_array($action, ['config', 'save', 'prompt', 'upload'], true)) {
        return;
    }

    $rootDir = getcwd();
    $allowFile = $rootDir . DIRECTORY_SEPARATOR . '.edit.allow';
    if (!is_file($allowFile)) {
        cmsJsonResponse([
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ]);
    }

    if (!class_exists('PoffConfig')) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ]);
    }

    $data = ($action === 'save' || $action === 'prompt') ? cmsReadJsonBody() : [];
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $target = cmsResolveTarget($rootDir, $path);
    if ($target === null) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ]);
    }
    $targetType = (string) $target['type'];
    $isLayoutTarget = $targetType === 'layout';
    $subjectType = $isLayoutTarget
        ? (string) ($target['subjectType'] ?? 'folder')
        : $targetType;
    $subjectRelativePath = $isLayoutTarget
        ? (string) ($target['subjectRelativePath'] ?? '')
        : trim($path, "/\\");
    $targetDir = $target['dir'];
    $targetFile = $target['file'] ?? null;

    if ($subjectType === 'file') {
        $config = PoffConfig::ensureFileConfig($targetDir, (string) $targetFile);
    } else {
        $config = PoffConfig::ensure($targetDir);
    }

    if ($action === 'config') {
        cmsJsonResponse([
            'allowed' => true,
            'target' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectTarget' => $subjectType,
            'config' => $config,
            'uploadLimits' => cmsUploadLimits(),
        ]);
    }

    if ($action === 'upload') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Upload requires POST.',
            ], 405);
        }
        if (!$isLayoutTarget && $subjectType !== 'folder') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Uploads are only supported for folders.',
            ], 400);
        }
        $uploadTargetDir = $targetDir;
        if ($isLayoutTarget) {
            $uploadTargetDir = $subjectType === 'file'
                ? PoffConfig::fileLayoutDir($targetDir, (string) $targetFile)
                : PoffConfig::folderLayoutDir($targetDir);
        }
        $source = trim((string) ($data['source'] ?? 'upload'));
        if (!in_array($source, ['upload', 'blank'], true)) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Unsupported add-content source.',
            ], 400);
        }

        if ($source === 'blank') {
            $fileName = (string) ($data['fileName'] ?? $data['filename'] ?? '');
            $contents = (string) ($data['contents'] ?? '');
            $result = cmsCreateBlankFile($uploadTargetDir, $fileName, $contents);
        } else {
            $entries = cmsCollectUploadedEntries($_FILES['files'] ?? []);
            if ($entries === []) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'No files selected.',
                ], 400);
            }

            $result = cmsStoreUploadEntries($uploadTargetDir, $entries);
        }

        if ($result['stored'] === []) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => $result['errors'][0] ?? 'Upload failed.',
            ], 400);
        }

        $updatedConfig = $subjectType === 'file'
            ? PoffConfig::ensureFileConfig($targetDir, (string) $targetFile)
            : PoffConfig::ensure($targetDir);
        cmsJsonResponse([
            'allowed' => true,
            'target' => $isLayoutTarget ? 'layout' : 'folder',
            'subjectTarget' => $subjectType,
            'uploaded' => $result['stored'],
            'errors' => $result['errors'],
            'config' => $updatedConfig,
            'uploadLimits' => cmsUploadLimits(),
        ]);
    }

    if ($action === 'save') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Save requires POST.',
            ], 405);
        }

        if (array_key_exists('title', $data)) {
            $config['title'] = trim((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $config['description'] = trim((string) $data['description']);
        }
        if (array_key_exists('link', $data)) {
            $link = trim((string) $data['link']);
            if ($link !== '') {
                $config['link'] = $link;
            } else {
                unset($config['link']);
            }
        }
        if (array_key_exists('url', $data)) {
            $url = trim((string) $data['url']);
            if ($url !== '') {
                $config['url'] = $url;
            } else {
                unset($config['url']);
            }
        }

        $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
        if (isset($data['work']) && is_array($data['work'])) {
            foreach ($data['work'] as $key => $value) {
                if ($key === 'type') {
                    continue;
                }
                $work[$key] = $value;
            }
        }
        $hasWorkType = false;
        $workType = '';
        if (isset($data['work']) && is_array($data['work']) && array_key_exists('type', $data['work'])) {
            $workType = trim((string) $data['work']['type']);
            $hasWorkType = true;
        } elseif (array_key_exists('work_type', $data)) {
            $workType = trim((string) $data['work_type']);
            $hasWorkType = true;
        }
        if ($hasWorkType && $workType !== '') {
            $work['type'] = $workType;
        }

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

        if (array_key_exists('layout_mode', $data)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) $data['layout_mode']);
        }
        if (array_key_exists('layout_preset', $data)) {
            $hasLayoutUpdate = true;
            $layoutPreset = trim((string) $data['layout_preset']);
        }
        if (array_key_exists('layoutPreset', $data)) {
            $hasLayoutUpdate = true;
            $layoutPreset = trim((string) $data['layoutPreset']);
        }
        if (array_key_exists('layout_model', $data)) {
            $hasLayoutUpdate = true;
            $layoutModel = $data['layout_model'];
        }
        if (array_key_exists('layout_template', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layout_template'];
        }
        if (array_key_exists('layoutTemplate', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layoutTemplate'];
        }
        if (array_key_exists('section_template', $data)) {
            $hasLayoutUpdate = true;
            $layoutSectionTemplateProvided = true;
            $layoutSectionTemplate = (string) $data['section_template'];
        }
        if (array_key_exists('sectionTemplate', $data)) {
            $hasLayoutUpdate = true;
            $layoutSectionTemplateProvided = true;
            $layoutSectionTemplate = (string) $data['sectionTemplate'];
        }
        if (array_key_exists('layout_css', $data)) {
            $hasLayoutUpdate = true;
            $layoutCssProvided = true;
            $layoutCss = (string) $data['layout_css'];
        }
        if (array_key_exists('layout_js', $data)) {
            $hasLayoutUpdate = true;
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

        $workLayout = '';
        if (array_key_exists('work_layout', $data)) {
            $workLayout = trim((string) $data['work_layout']);
        }

        $layoutSection = $subjectType === 'folder' ? 'works' : 'work';
        if ($hasLayoutUpdate) {
            $layoutValue = $work['layout'] ?? null;
            $layout = is_array($layoutValue) ? $layoutValue : [];
            if (is_string($layoutValue) && $layoutValue !== '') {
                $layout['mode'] = $layoutValue;
            }
            if ($layoutMode !== '') {
                $layout['mode'] = $layoutMode;
            }
            if (in_array($layoutPreset, ['actual', 'none', 'custom'], true)) {
                $layout['preset'] = $layoutPreset;
            }
            if ($layoutTemplateProvided) {
                $layout['template'] = $layoutTemplate;
            }
            if ($layoutSectionTemplateProvided) {
                $layout['sectionTemplate'] = $layoutSectionTemplate;
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
            $work['layout'] = Worktype::normalizeLayout($layout, $layoutSection);
        } elseif ($workLayout !== '') {
            $work['layout'] = Worktype::normalizeLayout($workLayout, $layoutSection);
        }

        $work['layout'] = PoffConfig::persistLayoutFiles(
            $targetDir,
            $subjectType === 'file' ? (string) $targetFile : null,
            $work['layout'] ?? null,
            $layoutSection
        );
        if (
            $originalLayoutTarget !== ''
            && ($originalLayoutTemplateProvided || $originalLayoutCssProvided || $originalLayoutJsProvided)
        ) {
            try {
                PoffConfig::persistOriginalLayoutFiles($originalLayoutTarget, [
                    'template' => $originalLayoutTemplateProvided ? $originalLayoutTemplate : null,
                    'css' => $originalLayoutCssProvided ? $originalLayoutCss : null,
                    'js' => $originalLayoutJsProvided ? $originalLayoutJs : null,
                ]);
            } catch (InvalidArgumentException $error) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => $error->getMessage(),
                ], 400);
            }
        }
        $config['work'] = $work;

        if ($subjectType === 'folder') {
            $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
            $hasTreeUpdate = array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data);
            if ($hasTreeUpdate && is_array($config['tree'] ?? null)) {
                $visibleKeys = [];
                if (is_array($treeVisible)) {
                    foreach ($treeVisible as $key) {
                        if (is_scalar($key)) {
                            $visibleKeys[(string) $key] = true;
                        }
                    }
                }
                foreach ($config['tree'] as &$item) {
                    $key = $item['path'] ?? $item['name'] ?? null;
                    if ($key === null) {
                        continue;
                    }
                    $item['visible'] = isset($visibleKeys[$key]);
                }
                unset($item);
            }
        }

        $config['updatedAt'] = date('c');
        if ($subjectType === 'folder') {
            $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
        }
        $configPath = $subjectType === 'file'
            ? PoffConfig::fileConfigPath($targetDir, (string) $targetFile)
            : PoffConfig::configPath($targetDir);
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Failed to encode config JSON.',
            ], 500);
        }
        file_put_contents($configPath, $encoded);

        $responseConfig = PoffConfig::hydrateConfigLayout(
            $config,
            $targetDir,
            $subjectType === 'file' ? (string) $targetFile : null
        );

        cmsJsonResponse([
            'allowed' => true,
            'target' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectTarget' => $subjectType,
            'saved' => true,
            'config' => $responseConfig,
        ]);
    }

    if ($action === 'prompt') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Prompt requires POST.',
            ], 405);
        }

        $provider = strtolower((string) ($data['provider'] ?? 'local'));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $apiKey = trim((string) ($data['apiKey'] ?? ''));
        $history = is_array($data['history'] ?? null) ? $data['history'] : [];
        $systemPromptValue = trim((string) ($data['systemPrompt'] ?? ''));
        $layoutPreset = trim((string) ($data['layoutPreset'] ?? $data['layout_preset'] ?? ''));
        $image = cmsPromptImagePayload($data);

        if ($prompt === '' && !$image) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Missing prompt or image.',
            ]);
        }

        $promptContext = cmsBuildPromptContext($subjectRelativePath, $subjectType, $config, $targetFile, $isLayoutTarget, $layoutPreset);
        if ($isLayoutTarget && is_array($config['work']['layout'] ?? null)) {
            $promptContext['current']['activeLayout'] = [
                'name' => (string) ($config['work']['layout']['name'] ?? ''),
                'mode' => (string) ($config['work']['layout']['mode'] ?? ''),
                'storage' => (string) ($config['work']['layout']['storage'] ?? ''),
                'directory' => (string) ($config['work']['layout']['directory'] ?? ''),
                'inheritedDirectory' => (string) ($config['work']['layout']['inheritedDirectory'] ?? ''),
                'sectionDirectory' => (string) ($config['work']['layout']['sectionDirectory'] ?? ''),
                'template' => (string) ($config['work']['layout']['template'] ?? ''),
                'sectionTemplate' => (string) ($config['work']['layout']['sectionTemplate'] ?? ''),
                'css' => (string) ($config['work']['layout']['css'] ?? ($config['work']['layout']['style'] ?? '')),
                'js' => (string) ($config['work']['layout']['js'] ?? ($config['work']['layout']['script'] ?? '')),
            ];
        }
        $promptContextJson = json_encode(cmsPromptCompactContext($promptContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $configJson = json_encode($isLayoutTarget ? cmsPromptCompactConfig($config, true) : $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $responseFormatInstruction = implode("\n", $isLayoutTarget
            ? [
                'Response format: return strict JSON.',
                'Required key: "template" with the outer layout wrapper HBS string.',
                'Optional keys: "css" and "js" for sibling style.css and script.js content.',
                'Optional key: "work" for work.* updates when the user explicitly requests them.',
                'Template requirement: keep a <main class="poff-default-layout__main"> block that renders {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}.',
                'Example: {"template":"<div class=\"poff-default-layout\"><main class=\"poff-default-layout__main\">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main></div>","css":".poff-default-layout{min-height:100dvh;}","js":"document.documentElement.dataset.layout = \'custom\';","work":{"layout":"custom-layout"}}',
            ]
            : []);
        $sharedWorkSystemPrompt = [
            'You are a Handlebars (HBS) template generator for this single-page CMS.',
            'Return one HBS template string for the wrapped inner section partial rendered through LightnCandy.',
            'Return only the template (no Markdown, no fences).',
            'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
            'Use config/title/description, layout name/template, and work type when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
            'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
            'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
        ];
        $fileWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, [
            'Save target is work.hbs for the current file inside the active item layout folder.',
            'Focus on a single file view. Do not assume folder tree loops or folder aggregate lists unless the user explicitly asks for them.',
            'Prefer file-relevant fields such as {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.*.',
        ]);
        $folderWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, [
            'Save target is works.hbs for the current folder inside the active item layout folder.',
            'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
            'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
            'Use folder tree data and resolved refs when relevant instead of inventing paths.',
        ]);
        $defaultSystemPrompt = implode("\n", $isLayoutTarget
            ? [
                'You are a Handlebars (HBS) layout generator for this single-page CMS.',
                'Transform the user description into an updated outer layout wrapper rendered through LightnCandy.',
                'Treat the currently resolved active wrapper as your primary reference. Prompt context JSON current.activeLayout and Config JSON work.layout contain the actual active template, sectionTemplate, css, and js after filesystem, inheritance, and preset resolution.',
                'When the active layout is empty or too minimal, fall back to the built-in default wrapper shape from src/includes/worktypes/templates/layout/default/template.hbs.',
                'The prompt edits the outer layout wrapper template, not the wrapped inner work.hbs or works.hbs partial.',
                'Keep the wrapped content chain active and preserve the data flow from the current item context all the way down to the inner partial. Use {{> works}} for folders and {{> work}} for files inside the layout wrapper unless the user explicitly asks to remove or replace it.',
                'The wrapper owns the page shell and must wrap the inner partial. Return one outer template that includes {{> works}} or {{> work}} exactly once unless the user explicitly asks for a different structure.',
                'Always keep a <main class="poff-default-layout__main"> block whose content is exactly {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}. Do not omit this block.',
                'Return the wrapper as real Handlebars template code. Use the same runtime fields, partials, conditionals, and folder/file context that the active template already uses when they are still relevant.',
                'Prefer returning sibling "css" and "js" strings too, so the layout prompt can design template.hbs, style.css, and script.js together for files and folders.',
                'Use the actual resolved template/css/js as style and structure cues. Redesign them when requested, but keep useful Handlebars structure, routing fields, and wrapper semantics unless the user explicitly asks for a break.',
                'When the current layout mode stays inherited or actual, edits should target the inherited/original filesystem layout source. When the user chooses Custom, edits target the local .layout/template.hbs wrapper.',
                'Use current.templateTarget as the active save target for this layout page. It follows the current layout mode: the resolved active wrapper for Actual, the local custom wrapper for Custom, and never the inner partial by default.',
                'current.layoutTemplateTarget is the local custom wrapper path if you explicitly switch to Custom. current.sectionTemplateTarget is the advanced inner partial path, not the default save target here.',
                'Prompt context JSON current.activeLayout.template is the active outer wrapper, current.activeLayout.sectionTemplate is the current wrapped work/works partial, and current.activeLayout.css/js are the currently active style and script sources.',
                'For images, icons, CSS backgrounds, or other assets owned by the layout wrapper, do not build URLs from {{path}}. {{path}} points to the current folder/file, not the layout asset folder.',
                'Use runtime layout URLs such as {{layout.baseHref}}/file.ext for local or inherited folder layout assets. Reusing the bundled default profile image should look like {{layout.baseHref}}/eggman_profile-image.jpg when the active wrapper comes from the built-in default layout bundle.',
                'Prompt context JSON includes current.layoutBaseHref, current.inheritedLayoutDirectory, and current.layoutAssets to help you choose the right asset path and understand whether the wrapper comes from a parent folder .layout.',
                'Theme overrides can target .poff-default-layout from top to down with CSS variables like --poff-shell-bg, --poff-shell-color, --poff-shell-title-color, --poff-shell-description-color, --poff-shell-footer-color, --poff-shell-header-border, --poff-shell-footer-border, --poff-shell-card-bg, and --poff-shell-card-border.',
                'Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.',
                'Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.',
                'Inputs available: {{pageLink}} / {{pageUrl}} / {{workUrl}} / {{viewUrl}} / {{viewerHref}} for the templated CMS viewer URL, {{srcUrl}} / {{sourceUrl}} / {{assetUrl}} / {{assetLink}} / {{rawHref}} for direct source URLs, {{path}} for the raw relative file path, plus {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
                'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
                'Prompt context JSON includes resolved refs for the current item and current folder contents. Use those refs directly instead of inventing paths.',
                'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
                'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
            ]
            : ($subjectType === 'folder' ? $folderWorkSystemPrompt : $fileWorkSystemPrompt));
        $systemPrompt = $systemPromptValue !== '' ? $systemPromptValue : $defaultSystemPrompt;
        $historyText = cmsPromptHistoryText($history);
        $userPrompt = $isLayoutTarget
            ? "Config JSON:\n" . $configJson . "\n\nPrompt context JSON:\n" . $promptContextJson . "\n\n" . $responseFormatInstruction . "\n\n" . $historyText . "USER: " . $prompt
            : "Config JSON:\n" . $configJson . "\n\n" . $historyText . "USER: " . $prompt;
        if ($image) {
            $userPrompt .= "\n\nAttached image: " . ($image['name'] ?: 'clipboard-image.png');
        }

        $env = cmsLoadEnv($rootDir);
        $template = '';
        $usedModel = $model;

        if ($provider === 'openai') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'OPENAI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'OpenAI API key not set.',
                ]);
            }
            if ($usedModel === '') {
                $usedModel = 'gpt-4o-mini';
            }
            $payload = [
                'model' => $usedModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $image ? [
                        ['type' => 'text', 'text' => $userPrompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $image['dataUrl']]],
                    ] : $userPrompt],
                ],
                'temperature' => 0.4,
            ];
            $response = cmsHttpPost('https://api.openai.com/v1/chat/completions', [
                'Authorization: Bearer ' . $key,
            ], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => cmsFormatPromptHttpError('OpenAI', $response),
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        } elseif ($provider === 'gemini') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'GEMINI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Gemini API key not set.',
                ]);
            }
            if ($usedModel === '') {
                $usedModel = 'gemini-1.5-flash';
            }
            $promptText = $systemPrompt . "\n\n" . $userPrompt;
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $promptText],
                            ...($image ? [[
                                'inline_data' => [
                                    'mime_type' => $image['mimeType'],
                                    'data' => $image['base64'],
                                ],
                            ]] : []),
                        ],
                    ],
                ],
            ];
            $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($usedModel), $key);
            $response = cmsHttpPost($url, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => cmsFormatPromptHttpError('Gemini', $response),
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
        } else {
            if ($endpoint === '') {
                $endpoint = 'http://127.0.0.1:1234/v1/chat/completions';
            }
            if ($usedModel === '') {
                $usedModel = 'gemma4';
            }
            if (cmsIsOpenAiCompatibleEndpoint($endpoint)) {
                $messages = [['role' => 'system', 'content' => $systemPrompt]];
                foreach ($history as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $role = strtolower(trim((string) ($message['role'] ?? 'user')));
                    $content = trim((string) ($message['content'] ?? ''));
                    if ($content === '' || !in_array($role, ['system', 'user', 'assistant'], true)) {
                        continue;
                    }
                    $messages[] = ['role' => $role, 'content' => $content];
                }
                $messages[] = ['role' => 'user', 'content' => $image ? [
                    ['type' => 'text', 'text' => $userPrompt],
                    ['type' => 'image_url', 'image_url' => ['url' => $image['dataUrl']]],
                ] : $userPrompt];
                $payload = [
                    'model' => $usedModel,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ];
            } else {
                $payload = [
                    'prompt' => $prompt,
                    'history' => $history,
                    'config' => $config,
                    'instruction' => $systemPrompt,
                    'image' => $image,
                ];
                if ($isLayoutTarget) {
                    $payload['promptContext'] = $promptContext;
                }
            }
            $response = cmsHttpPost($endpoint, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => cmsFormatPromptHttpError('Local endpoint', $response),
                ]);
            }
            $decoded = json_decode($response['body'], true);
            if (is_array($decoded)) {
                if (isset($decoded['choices'][0]['message']['content'])) {
                    $template = (string) $decoded['choices'][0]['message']['content'];
                } elseif (isset($decoded['template'])) {
                    $template = (string) $decoded['template'];
                } elseif (isset($decoded['content'])) {
                    $template = (string) $decoded['content'];
                }
            }
            if ($template === '') {
                $template = trim((string) $response['body']);
            }
        }

        $parsedResult = cmsParsePromptModelResult($template, $isLayoutTarget);
        $templateText = trim((string) ($parsedResult['template'] ?? ''));
        if ($templateText === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Template was empty.',
            ]);
        }

        $responsePayload = [
            'allowed' => true,
            'target' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectTarget' => $subjectType,
            'provider' => $provider,
            'model' => $usedModel,
            'template' => $templateText,
            'systemPrompt' => $systemPrompt,
        ];
        foreach (['title', 'description', 'work', 'css', 'js'] as $key) {
            if (array_key_exists($key, $parsedResult)) {
                $responsePayload[$key] = $parsedResult[$key];
            }
        }

        cmsJsonResponse($responsePayload);
    }
}
