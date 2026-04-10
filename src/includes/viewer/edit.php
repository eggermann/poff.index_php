<?php
/**
 * Edit actions: config, save, and prompt endpoints.
 */

require_once __DIR__ . '/utils.php';

function cmsPromptImagePayload(array $data): ?array
{
    $image = $data['image'] ?? null;
    if (!is_array($image)) {
        return null;
    }
    $dataUrl = isset($image['dataUrl']) ? trim((string) $image['dataUrl']) : '';
    if ($dataUrl === '' || !preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#i', $dataUrl, $matches)) {
        return null;
    }

    return [
        'name' => trim((string) ($image['name'] ?? 'clipboard-image.png')),
        'mimeType' => strtolower($matches[1]),
        'dataUrl' => $dataUrl,
        'base64' => $matches[2],
    ];
}

function cmsParsePromptModelResult(string $raw): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return ['template' => ''];
    }

    $candidate = $trimmed;
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/si', $trimmed, $matches)) {
        $candidate = trim((string) $matches[1]);
    }

    $decoded = json_decode($candidate, true);
    if (!is_array($decoded)) {
        return ['template' => $trimmed];
    }

    $template = '';
    if (isset($decoded['template'])) {
        $template = trim((string) $decoded['template']);
    } elseif (isset($decoded['content'])) {
        $template = trim((string) $decoded['content']);
    }

    if ($template === '') {
        return ['template' => $trimmed];
    }

    $result = ['template' => $template];
    foreach (['title', 'description', 'model'] as $key) {
        if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
            $result[$key] = trim((string) $decoded[$key]);
        }
    }
    if (isset($decoded['work']) && is_array($decoded['work'])) {
        $result['work'] = $decoded['work'];
    }

    return $result;
}

function cmsSanitizeUploadName(string $name): string
{
    $base = basename(str_replace('\\', '/', trim($name)));
    $clean = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $base);
    $clean = trim((string) $clean, " .-\t\n\r\0\x0B");

    return $clean !== '' ? $clean : 'upload.bin';
}

function cmsResolveUniqueUploadPath(string $targetDir, string $name): string
{
    $info = pathinfo($name);
    $filename = $info['filename'] ?? 'upload';
    $extension = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    $candidate = $targetDir . DIRECTORY_SEPARATOR . $filename . $extension;
    $index = 2;

    while (file_exists($candidate)) {
        $candidate = $targetDir . DIRECTORY_SEPARATOR . $filename . '-' . $index . $extension;
        $index++;
    }

    return $candidate;
}

function cmsCollectUploadedEntries(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    $entries = [];
    $names = $files['name'];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];
    $types = $files['type'] ?? [];

    if (!is_array($names)) {
        return [[
            'name' => (string) $names,
            'tmp_name' => (string) $tmpNames,
            'error' => (int) $errors,
            'size' => (int) $sizes,
            'type' => (string) $types,
        ]];
    }

    foreach ($names as $index => $name) {
        $entries[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($tmpNames[$index] ?? ''),
            'error' => (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($sizes[$index] ?? 0),
            'type' => (string) ($types[$index] ?? ''),
        ];
    }

    return $entries;
}

function cmsStoreUploadEntries(string $targetDir, array $entries): array
{
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $stored = [];
    $errors = [];

    foreach ($entries as $entry) {
        $errorCode = (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed for ' . ((string) ($entry['name'] ?? 'file'));
            continue;
        }

        $tmpPath = (string) ($entry['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            $errors[] = 'Missing uploaded file for ' . ((string) ($entry['name'] ?? 'file'));
            continue;
        }

        $safeName = cmsSanitizeUploadName((string) ($entry['name'] ?? 'upload.bin'));
        $targetPath = cmsResolveUniqueUploadPath($targetDir, $safeName);
        $moved = false;

        if (is_uploaded_file($tmpPath)) {
            $moved = move_uploaded_file($tmpPath, $targetPath);
        }
        if (!$moved) {
            $moved = @rename($tmpPath, $targetPath);
        }
        if (!$moved) {
            $moved = @copy($tmpPath, $targetPath);
            if ($moved) {
                @unlink($tmpPath);
            }
        }
        if (!$moved) {
            $errors[] = 'Could not store ' . $safeName;
            continue;
        }

        $stored[] = [
            'name' => basename($targetPath),
            'path' => basename($targetPath),
            'size' => filesize($targetPath) ?: 0,
        ];
    }

    return [
        'stored' => $stored,
        'errors' => $errors,
    ];
}

function cmsPromptViewerUrl(string $relativePath, bool $isFile): string
{
    return $isFile
        ? '?view=1&file=' . rawurlencode($relativePath)
        : '?view=1&path=' . rawurlencode($relativePath);
}

function cmsPromptAssetUrl(string $relativePath, bool $isFile): string
{
    if (!$isFile) {
        return '?path=' . rawurlencode($relativePath);
    }

    $parts = explode('/', $relativePath);
    $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

    return implode('/', $encoded);
}

function cmsPromptTemplateTarget(string $relativePath, bool $isFile, string $section): string
{
    $layoutPath = PoffConfig::relativeLayoutPath($relativePath, $isFile);
    $sectionFile = $section === 'works' ? 'works.hbs' : 'work.hbs';

    return $layoutPath . '/' . $sectionFile;
}

function cmsPromptLayoutTemplateTarget(string $relativePath, bool $isFile): string
{
    return PoffConfig::relativeLayoutPath($relativePath, $isFile) . '/template.hbs';
}

function cmsPromptRefKind(string $name, string $type): string
{
    if ($type === 'folder') {
        return 'folder';
    }

    return MediaType::classifyExtension($name);
}

function cmsBuildPromptRef(string $basePath, array $item): ?array
{
    $name = trim((string) ($item['name'] ?? $item['path'] ?? ''));
    if ($name === '') {
        return null;
    }

    $relativePath = trim((string) ($item['path'] ?? $name), "/\\");
    if ($relativePath === '') {
        $relativePath = $name;
    }
    if ($basePath !== '') {
        $normalizedBase = trim($basePath, "/\\");
        if (!str_starts_with($relativePath, $normalizedBase . '/') && $relativePath !== $normalizedBase) {
            $relativePath = $normalizedBase . '/' . ltrim($relativePath, "/\\");
        }
    }

    $type = (string) ($item['type'] ?? 'file');
    $isFolder = $type === 'folder';
    $kind = cmsPromptRefKind($name, $type);
    $pageLink = cmsPromptViewerUrl($relativePath, !$isFolder);
    $assetUrl = cmsPromptAssetUrl($relativePath, !$isFolder);

    return [
        'name' => $name,
        'title' => (string) ($item['title'] ?? $name),
        'slug' => (string) ($item['slug'] ?? PoffConfig::slugify($name)),
        'type' => $type,
        'kind' => $kind,
        'path' => $relativePath,
        'pageLink' => $pageLink,
        'pageUrl' => $pageLink,
        'workUrl' => $pageLink,
        'viewUrl' => $pageLink,
        'viewerHref' => $pageLink,
        'assetUrl' => $assetUrl,
        'assetLink' => $assetUrl,
        'rawHref' => $assetUrl,
        'srcUrl' => $assetUrl,
        'sourceUrl' => $assetUrl,
        'isFolder' => $isFolder,
        'isFile' => !$isFolder,
        'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
    ];
}

function cmsBuildPromptContext(string $relativePath, string $targetType, array $config, ?string $targetFile = null): array
{
    $normalizedPath = trim($relativePath, "/\\");
    $currentName = $targetType === 'file'
        ? (string) ($targetFile ?? basename($normalizedPath))
        : (string) ($config['folderName'] ?? basename($normalizedPath));
    $currentPath = $normalizedPath;
    $currentIsFile = $targetType === 'file';
    $currentSection = $currentIsFile ? 'work' : 'works';
    $currentPageLink = cmsPromptViewerUrl($currentPath, $currentIsFile);
    $currentAssetUrl = cmsPromptAssetUrl($currentPath, $currentIsFile);

    $context = [
        'current' => [
            'targetType' => $targetType,
            'sectionPartial' => $currentSection,
            'name' => $currentName,
            'path' => $currentPath,
            'pageLink' => $currentPageLink,
            'pageUrl' => $currentPageLink,
            'workUrl' => $currentPageLink,
            'viewUrl' => $currentPageLink,
            'viewerHref' => $currentPageLink,
            'assetUrl' => $currentAssetUrl,
            'assetLink' => $currentAssetUrl,
            'rawHref' => $currentAssetUrl,
            'srcUrl' => $currentAssetUrl,
            'sourceUrl' => $currentAssetUrl,
            'templateTarget' => cmsPromptTemplateTarget($currentPath, $currentIsFile, $currentSection),
            'layoutTemplateTarget' => cmsPromptLayoutTemplateTarget($currentPath, $currentIsFile),
        ],
        'items' => [],
        'allItems' => [],
        'allFiles' => [],
        'allFolders' => [],
        'allImages' => [],
        'allVideos' => [],
        'allAudio' => [],
        'allPdfs' => [],
        'allTexts' => [],
        'allLinks' => [],
        'allOther' => [],
    ];

    if ($targetType !== 'folder') {
        return $context;
    }

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    foreach ($tree as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ref = cmsBuildPromptRef($normalizedPath, $item);
        if ($ref === null) {
            continue;
        }
        $context['items'][] = $ref;
        $context['allItems'][] = $ref;
        if ($ref['isFolder']) {
            $context['allFolders'][] = $ref;
            continue;
        }

        $context['allFiles'][] = $ref;
        switch ($ref['kind']) {
            case 'image':
                $context['allImages'][] = $ref;
                break;
            case 'video':
                $context['allVideos'][] = $ref;
                break;
            case 'audio':
                $context['allAudio'][] = $ref;
                break;
            case 'pdf':
                $context['allPdfs'][] = $ref;
                break;
            case 'text':
                $context['allTexts'][] = $ref;
                break;
            case 'link':
                $context['allLinks'][] = $ref;
                break;
            default:
                $context['allOther'][] = $ref;
                break;
        }
    }

    return $context;
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
    $targetType = $target['type'];
    $targetDir = $target['dir'];
    $targetFile = $target['file'] ?? null;

    if ($targetType === 'file') {
        $config = PoffConfig::ensureFileConfig($targetDir, (string) $targetFile);
    } else {
        $config = PoffConfig::ensure($targetDir);
    }

    if ($action === 'config') {
        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'config' => $config,
        ]);
    }

    if ($action === 'upload') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Upload requires POST.',
            ], 405);
        }
        if ($targetType !== 'folder') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Uploads are only supported for folders.',
            ], 400);
        }
        $source = trim((string) ($data['source'] ?? 'upload'));
        if ($source !== 'upload') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Only file upload is available right now.',
            ], 400);
        }

        $entries = cmsCollectUploadedEntries($_FILES['files'] ?? []);
        if ($entries === []) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'No files selected.',
            ], 400);
        }

        $result = cmsStoreUploadEntries($targetDir, $entries);
        if ($result['stored'] === []) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => $result['errors'][0] ?? 'Upload failed.',
            ], 400);
        }

        $updatedConfig = PoffConfig::ensure($targetDir);
        cmsJsonResponse([
            'allowed' => true,
            'target' => 'folder',
            'uploaded' => $result['stored'],
            'errors' => $result['errors'],
            'config' => $updatedConfig,
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

        if (is_array($layoutPayload)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) ($layoutPayload['mode'] ?? $layoutPayload['name'] ?? ''));
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
        }

        if (array_key_exists('layout_mode', $data)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) $data['layout_mode']);
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

        $workLayout = '';
        if (array_key_exists('work_layout', $data)) {
            $workLayout = trim((string) $data['work_layout']);
        }

        $layoutSection = $targetType === 'folder' ? 'works' : 'work';
        if ($hasLayoutUpdate) {
            $layoutValue = $work['layout'] ?? null;
            $layout = is_array($layoutValue) ? $layoutValue : [];
            if (is_string($layoutValue) && $layoutValue !== '') {
                $layout['mode'] = $layoutValue;
            }
            if ($layoutMode !== '') {
                $layout['mode'] = $layoutMode;
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
            $targetType === 'file' ? (string) $targetFile : null,
            $work['layout'] ?? null,
            $layoutSection
        );
        $config['work'] = $work;

        if ($targetType === 'folder') {
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
        if ($targetType === 'folder') {
            $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
        }
        $configPath = $targetType === 'file'
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
            $targetType === 'file' ? (string) $targetFile : null
        );

        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
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
        $image = cmsPromptImagePayload($data);

        if ($prompt === '' && !$image) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Missing prompt or image.',
            ]);
        }

        $promptContext = cmsBuildPromptContext($path, $targetType, $config, $targetFile);
        $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $promptContextJson = json_encode($promptContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $responseFormatInstruction = implode("\n", [
            'Response format: return strict JSON.',
            'Required key: "template" with the wrapped inner HBS partial string.',
            'Optional keys: "title", "description", and "work".',
            'If the user requests work.* updates such as autoplay, loop, muted, poster, type, or layout, include them under "work".',
            'Example: {"template":"<div>{{title}}</div>","work":{"autoplay":true}}',
        ]);
        $defaultSystemPrompt = implode("\n", [
            'You are a Handlebars (HBS) template generator for this single-page CMS.',
            'Return one HBS template string for the wrapped inner section partial rendered through LightnCandy.',
            'The prompt edits the wrapped content partial, not the outer layout wrapper. Save target is work.hbs for files and works.hbs for folders inside the active item layout folder.',
            'Keep the current outer layout chain active unless the user explicitly changes layout mode separately. Do not return the outer wrapper template here.',
            'Default layout technique: the outer layout stays in template.hbs and wraps {{> works}} for folders or {{> work}} for files.',
            'Theme overrides can target .poff-default-layout from top to down with CSS variables like --poff-shell-bg, --poff-shell-color, --poff-shell-title-color, --poff-shell-description-color, --poff-shell-footer-color, --poff-shell-header-border, --poff-shell-footer-border, --poff-shell-card-bg, and --poff-shell-card-border.',
            'Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.',
            'Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.',
            'Inputs available: {{pageLink}} / {{pageUrl}} / {{workUrl}} / {{viewUrl}} / {{viewerHref}} for the templated CMS viewer URL, {{srcUrl}} / {{sourceUrl}} / {{assetUrl}} / {{assetLink}} / {{rawHref}} for direct source URLs, {{path}} for the raw relative file path, plus {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
            'Prompt context JSON includes current.templateTarget for the wrapped partial save target and current.layoutTemplateTarget for the outer wrapper path. Edit the wrapped partial target by default.',
            'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
            'Prompt context JSON includes resolved refs for the current item and current folder contents. Use those refs directly instead of inventing paths.',
            'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
            'Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
            'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
            'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
        ]);
        $systemPrompt = $systemPromptValue !== '' ? $systemPromptValue : $defaultSystemPrompt;
        $historyText = '';
        foreach ($history as $msg) {
            if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            $role = strtolower((string) $msg['role']);
            $content = trim((string) $msg['content']);
            if ($content === '') {
                continue;
            }
            $historyText .= strtoupper($role) . ": " . $content . "\n";
        }
        $userPrompt = "Config JSON:\n" . $configJson . "\n\nPrompt context JSON:\n" . $promptContextJson . "\n\n" . $responseFormatInstruction . "\n\n" . $historyText . "USER: " . $prompt;
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
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Local endpoint URL missing.',
                ]);
            }
            $payload = [
                'prompt' => $prompt,
                'history' => $history,
                'config' => $config,
                'instruction' => $systemPrompt,
                'image' => $image,
            ];
            $response = cmsHttpPost($endpoint, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => cmsFormatPromptHttpError('Local endpoint', $response),
                ]);
            }
            $decoded = json_decode($response['body'], true);
            if (is_array($decoded)) {
                if (isset($decoded['template'])) {
                    $template = (string) $decoded['template'];
                } elseif (isset($decoded['content'])) {
                    $template = (string) $decoded['content'];
                }
            }
            if ($template === '') {
                $template = trim((string) $response['body']);
            }
        }

        $parsedResult = cmsParsePromptModelResult($template);
        $templateText = trim((string) ($parsedResult['template'] ?? ''));
        if ($templateText === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Template was empty.',
            ]);
        }

        $responsePayload = [
            'allowed' => true,
            'target' => $targetType,
            'provider' => $provider,
            'model' => $usedModel,
            'template' => $templateText,
            'systemPrompt' => $systemPrompt,
        ];
        foreach (['title', 'description', 'work'] as $key) {
            if (array_key_exists($key, $parsedResult)) {
                $responsePayload[$key] = $parsedResult[$key];
            }
        }

        cmsJsonResponse($responsePayload);
    }
}
