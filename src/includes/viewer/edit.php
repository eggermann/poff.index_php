<?php
/**
 * Edit actions: config, save, and prompt endpoints.
 */

require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/edit/prompt-parse.php';
require_once __DIR__ . '/edit/prompt-refs.php';
require_once __DIR__ . '/edit/prompt-context.php';
require_once __DIR__ . '/edit/prompt-compact.php';
require_once __DIR__ . '/render/data.php';
require_once __DIR__ . '/edit/upload.php';
require_once __DIR__ . '/edit/delete.php';
require_once __DIR__ . '/edit/reset.php';

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

function cmsWorktypeCatalogForConfig(array $config, string $subjectType, string $targetDir, ?string $targetFile = null): array
{
    if (!class_exists('Worktype')) {
        return [];
    }

    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    $mime = $subjectType === 'file'
        ? trim((string) ($config['mimeType'] ?? ''))
        : '';
    $fileName = $subjectType === 'file' ? trim((string) ($targetFile ?? '')) : null;
    $resolved = class_exists('PoffConfig')
        ? PoffConfig::resolveWorkTemplateState($targetDir, $work, $subjectType === 'folder' ? 'folder' : (string) ($config['kind'] ?? $subjectType), $mime !== '' ? $mime : null, $fileName)
        : [];
    $selected = trim((string) ($resolved['template'] ?? $work['template'] ?? ''));
    $catalog = Worktype::worktypeCatalog($mime !== '' ? $mime : null, $fileName, $selected !== '' ? $selected : null, $subjectType);

    return $catalog;
}

function cmsWorktypeMapCatalogForConfig(array $config, string $subjectType, string $targetDir, ?string $targetFile = null, array $folderViewData = []): array
{
    if ($subjectType !== 'folder') {
        return [];
    }

    $items = is_array($folderViewData['allFiles'] ?? null) ? $folderViewData['allFiles'] : [];
    $groups = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $mime = strtolower(trim((string) ($item['mimeType'] ?? '')));
        if ($mime === '') {
            continue;
        }
        $kind = strtolower(trim((string) ($item['kind'] ?? 'other')));
        if (!isset($groups[$mime])) {
            $groups[$mime] = [
                'mime' => $mime,
                'kind' => $kind === '' ? 'other' : $kind,
                'count' => 0,
                'sampleName' => '',
            ];
        }
        $groups[$mime]['count']++;
        if ($groups[$mime]['sampleName'] === '' && is_string($item['name'] ?? null)) {
            $groups[$mime]['sampleName'] = (string) $item['name'];
        }
    }

    if ($groups === []) {
        return ['rows' => [], 'count' => 0];
    }

    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    $effectiveTemplateMap = class_exists('PoffConfig')
        ? PoffConfig::resolveEffectiveTemplateMap($targetDir, $work['templateMap'] ?? null)
        : Worktype::normalizeTemplateMap($work['templateMap'] ?? null);

    $rows = [];
    foreach ($groups as $group) {
        $mime = (string) ($group['mime'] ?? '');
        $kind = (string) ($group['kind'] ?? 'other');
        $sampleName = (string) ($group['sampleName'] ?? '');
        $selectedState = Worktype::resolveTemplateSelection($kind, $mime, $sampleName !== '' ? $sampleName : null, $effectiveTemplateMap);
        $catalog = Worktype::worktypeCatalog($mime, $sampleName !== '' ? $sampleName : null, (string) ($selectedState['template'] ?? ''), 'file');
        $rows[] = [
            'mime' => $mime,
            'kind' => $kind,
            'label' => ucfirst($kind) . ' · ' . $mime,
            'count' => (int) ($group['count'] ?? 0),
            'sampleName' => $sampleName,
            'selected' => (string) ($selectedState['template'] ?? ''),
            'catalog' => $catalog,
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        $kindCompare = strcasecmp((string) ($left['kind'] ?? ''), (string) ($right['kind'] ?? ''));
        if ($kindCompare !== 0) {
            return $kindCompare;
        }

        return strcasecmp((string) ($left['mime'] ?? ''), (string) ($right['mime'] ?? ''));
    });

    return [
        'rows' => $rows,
        'count' => count($rows),
    ];
}

function cmsAnnotateConfigWorktypeCatalog(array $config, string $subjectType, string $targetDir, ?string $targetFile = null, array $folderViewData = []): array
{
    $config['workTemplateCatalog'] = cmsWorktypeCatalogForConfig($config, $subjectType, $targetDir, $targetFile);
    $config['workTemplateMapCatalog'] = cmsWorktypeMapCatalogForConfig($config, $subjectType, $targetDir, $targetFile, $folderViewData);
    return $config;
}

function cmsPromptParentConfig(string $rootDir, string $subjectRelativePath, string $subjectType, string $targetDir): array
{
    $normalizedPath = trim(str_replace('\\', '/', $subjectRelativePath), '/');
    if ($normalizedPath === '') {
        return [];
    }

    $parentRelativePath = dirname($normalizedPath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentDir = $subjectType === 'file'
        ? $targetDir
        : dirname($targetDir);
    $rootReal = realpath($rootDir);
    $parentReal = realpath($parentDir);
    if ($rootReal === false || $parentReal === false || !str_starts_with($parentReal, $rootReal)) {
        return [];
    }

    $configPath = PoffConfig::configPath($parentReal);
    if (!is_file($configPath)) {
        return [];
    }

    $parentConfig = json_decode((string) file_get_contents($configPath), true);
    if (!is_array($parentConfig)) {
        return [];
    }

    return [
        'relativePath' => $parentRelativePath,
        'config' => $parentConfig,
    ];
}

function cmsPromptFolderViewData(string $relativePath, string $fullPath, array $config, array $rootMeta): array
{
    if (!is_dir($fullPath)) {
        return [];
    }

    return buildFolderViewerData($relativePath, $fullPath, $config, $rootMeta);
}

function cmsApplyParentTreeVisible(string $rootDir, string $subjectRelativePath, string $subjectType, string $targetDir, mixed $treeVisible): void
{
    if (!is_array($treeVisible)) {
        return;
    }

    $parentPrompt = cmsPromptParentConfig($rootDir, $subjectRelativePath, $subjectType, $targetDir);
    $parentConfig = is_array($parentPrompt['config'] ?? null) ? $parentPrompt['config'] : [];
    if ($parentConfig === [] || !is_array($parentConfig['tree'] ?? null)) {
        return;
    }

    $visibleKeys = [];
    foreach ($treeVisible as $key) {
        if (is_scalar($key)) {
            $visibleKeys[trim((string) $key, "/\\")] = true;
        }
    }

    $changed = false;
    foreach ($parentConfig['tree'] as &$item) {
        if (!is_array($item)) {
            continue;
        }
        $key = trim((string) ($item['path'] ?? $item['name'] ?? ''), "/\\");
        $name = trim((string) ($item['name'] ?? ''), "/\\");
        if ($key === '' && $name === '') {
            continue;
        }
        $parentRelativePath = trim(str_replace('\\', '/', (string) ($parentPrompt['relativePath'] ?? '')), '/');
        $fullKey = trim($parentRelativePath . '/' . $key, '/');
        $nextVisible = isset($visibleKeys[$key])
            || isset($visibleKeys[$fullKey])
            || ($name !== '' && isset($visibleKeys[$name]));
        if (!array_key_exists('visible', $item) || (bool) $item['visible'] !== $nextVisible) {
            $item['visible'] = $nextVisible;
            $changed = true;
        }
    }
    unset($item);

    if (!$changed) {
        return;
    }

    $parentDir = $subjectType === 'file'
        ? $targetDir
        : dirname($targetDir);
    $parentConfig['treeHash'] = hash('sha256', json_encode($parentConfig['tree'] ?? []));
    $parentConfig['updatedAt'] = date('c');
    file_put_contents(PoffConfig::configPath($parentDir), json_encode($parentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cmsSyncParentTreeItemMeta(string $rootDir, string $subjectRelativePath, string $subjectType, array $config): void
{
    $normalizedPath = trim(str_replace('\\', '/', $subjectRelativePath), '/');
    if ($normalizedPath === '') {
        return;
    }

    $itemName = basename($normalizedPath);
    $parentRelativePath = dirname($normalizedPath);
    if ($parentRelativePath === '.' || $parentRelativePath === DIRECTORY_SEPARATOR) {
        $parentRelativePath = '';
    }

    $parentDir = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($parentRelativePath !== '') {
        $parentDir .= DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parentRelativePath);
    }
    if (!is_dir($parentDir)) {
        return;
    }

    $parentConfigPath = PoffConfig::configPath($parentDir);
    $parentConfig = is_file($parentConfigPath)
        ? json_decode((string) file_get_contents($parentConfigPath), true)
        : PoffConfig::ensure($parentDir);
    if (!is_array($parentConfig) || !is_array($parentConfig['tree'] ?? null)) {
        return;
    }

    $changed = false;
    foreach ($parentConfig['tree'] as &$item) {
        if (!is_array($item) || (string) ($item['name'] ?? '') !== $itemName) {
            continue;
        }

        if (isset($config['slug']) && is_string($config['slug']) && trim($config['slug']) !== '') {
            $item['slug'] = trim($config['slug']);
            $changed = true;
        }
        if (isset($config['title']) && is_string($config['title'])) {
            $item['title'] = $config['title'];
            $changed = true;
        }
        $item['type'] = $subjectType;
        break;
    }
    unset($item);

    if (!$changed) {
        return;
    }

    $parentConfig['treeHash'] = hash('sha256', json_encode($parentConfig['tree']));
    $parentConfig['updatedAt'] = date('c');
    file_put_contents($parentConfigPath, json_encode($parentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cmsIsOpenAiCompatibleEndpoint(string $url): bool
{
    $normalized = strtolower(trim($url));
    return $normalized !== '' && str_contains($normalized, '/v1/chat/completions');
}

function cmsPromptSendSseHeaders(): void
{
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache, no-transform');
    header('X-Accel-Buffering: no');
}

function cmsPromptSendSseEvent(string $event, array $payload): void
{
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function cmsHandleEditAction(): void
{
    $action = $_GET['edit'] ?? '';
    if (!in_array($action, ['config', 'save', 'prompt', 'upload', 'delete'], true)) {
        return;
    }

    $runtimeRootDir = realpath(getcwd() ?: '.') ?: '.';
    $rootDir = $runtimeRootDir;

    $data = ($action === 'save' || $action === 'prompt') ? cmsReadJsonBody() : [];
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $runtimeTarget = cmsResolveTarget($runtimeRootDir, $path);
    $runtimeAllowDir = $runtimeTarget['dir'] ?? $runtimeRootDir;
    if (!cmsEditModeAllowedForDirectory((string) $runtimeAllowDir, $runtimeRootDir)) {
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
    $folderViewData = $subjectType === 'folder'
        ? cmsPromptFolderViewData(
            $subjectRelativePath,
            $targetDir,
            $config,
            [
                'name' => (string) ($config['folderName'] ?? basename($subjectRelativePath)),
                'title' => (string) ($config['title'] ?? $config['folderName'] ?? basename($subjectRelativePath)),
                'slug' => (string) ($config['slug'] ?? PoffConfig::slugify((string) ($config['folderName'] ?? basename($subjectRelativePath)))),
            ]
        )
        : [];

    if ($action === 'config') {
        $config = cmsAnnotateConfigWorktypeCatalog($config, $subjectType, $targetDir, $targetFile, $folderViewData);
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
        if (!in_array($source, ['upload', 'blank', 'folder'], true)) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Unsupported add-content source.',
            ], 400);
        }

        if ($source === 'blank') {
            $fileName = (string) ($data['fileName'] ?? $data['filename'] ?? '');
            $contents = (string) ($data['contents'] ?? '');
            $result = cmsCreateBlankFile($uploadTargetDir, $fileName, $contents);
        } elseif ($source === 'folder') {
            $folderName = (string) ($data['fileName'] ?? $data['folderName'] ?? $data['filename'] ?? '');
            $result = cmsCreateFolder($uploadTargetDir, $folderName);
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

    if ($action === 'delete') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Delete requires POST.',
            ], 405);
        }

        $deleteResult = cmsDeleteTarget($rootDir, $path);
        if (($deleteResult['errors'] ?? []) !== []) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => $deleteResult['errors'][0] ?? 'Delete failed.',
            ], 400);
        }

        $refreshDir = (string) ($deleteResult['refreshDir'] ?? $rootDir);
        $updatedConfig = PoffConfig::ensure($refreshDir);
        $returnPath = trim((string) ($data['return'] ?? $_GET['return'] ?? ''), "/\\");

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $wantsJson = str_contains($accept, 'application/json') || str_contains($accept, 'text/json');
        if ($wantsJson) {
            cmsJsonResponse([
                'allowed' => true,
                'deleted' => $deleteResult['deleted'],
                'config' => $updatedConfig,
            ]);
        }

        $redirectUrl = $returnPath !== ''
            ? '?path=' . urlencode($returnPath) . '&edit=true'
            : '?edit=true';
        header('Location: ' . $redirectUrl, true, 303);
        exit;
    }

    if ($action === 'reset') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Reset requires POST.',
            ], 405);
        }

        $resetResult = cmsResetFolderTarget($rootDir, $path);
        if (($resetResult['errors'] ?? []) !== []) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => $resetResult['errors'][0] ?? 'Reset failed.',
            ], 400);
        }

        cmsJsonResponse([
            'allowed' => true,
            'reset' => $resetResult['reset'],
            'config' => $resetResult['config'],
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
            if ($config['title'] !== '') {
                $config['slug'] = PoffConfig::slugify((string) $config['title']);
            }
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
        $templateMapUpdate = null;
        if (isset($data['work']) && is_array($data['work'])) {
            foreach ($data['work'] as $key => $value) {
                if ($key === 'type') {
                    continue;
                }
                if ($key === 'templateMap') {
                    $templateMapUpdate = $value;
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

        $inheritedTemplateMap = PoffConfig::resolveInheritedTemplateMap($targetDir);
        if ($templateMapUpdate !== null) {
            $nextTemplateMap = is_array($work['templateMap'] ?? null) ? $work['templateMap'] : [];
            if (is_array($templateMapUpdate)) {
                foreach ($templateMapUpdate as $mime => $template) {
                    if (!is_scalar($mime)) {
                        continue;
                    }
                    $normalizedMime = strtolower(trim((string) $mime));
                    if ($normalizedMime === '') {
                        continue;
                    }
                    if (!is_scalar($template) || trim((string) $template) === '') {
                        unset($nextTemplateMap[$normalizedMime]);
                        continue;
                    }

                    $normalizedTemplate = Worktype::normalizeTemplateKey((string) $template);
                    if ($normalizedTemplate === '') {
                        unset($nextTemplateMap[$normalizedMime]);
                        continue;
                    }

                    $nextTemplateMap[$normalizedMime] = $normalizedTemplate;
                }
            }

            $nextTemplateMap = PoffConfig::trimTemplateMapOverrides($nextTemplateMap, $inheritedTemplateMap);
            if ($nextTemplateMap !== []) {
                $work['templateMap'] = $nextTemplateMap;
            } else {
                unset($work['templateMap']);
            }
        }

        if (array_key_exists('templateMap', $work) && is_array($work['templateMap'])) {
            $currentTemplateMap = PoffConfig::trimTemplateMapOverrides($work['templateMap'], $inheritedTemplateMap);
            if ($currentTemplateMap !== []) {
                $work['templateMap'] = $currentTemplateMap;
            } else {
                unset($work['templateMap']);
            }
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
        $sectionOnlyLayoutUpdate = $hasLayoutUpdate
            && $layoutSectionTemplateProvided
            && !$layoutTemplateProvided
            && !$layoutCssProvided
            && !$layoutJsProvided
            && $layoutMode === ''
            && $layoutPreset === ''
            && (!is_string($layoutModel) || trim($layoutModel) === '')
            && $originalLayoutTarget === ''
            && !$originalLayoutTemplateProvided
            && !$originalLayoutCssProvided
            && !$originalLayoutJsProvided;
        if ($hasLayoutUpdate) {
            $layoutValue = $work['layout'] ?? null;
            $layout = is_array($layoutValue) ? $layoutValue : [];
            if (is_string($layoutValue) && $layoutValue !== '') {
                $layout['mode'] = $layoutValue;
            }
            if ($layoutMode !== '') {
                $layout['mode'] = $layoutMode;
                $layout['name'] = $layoutMode;
            }
            if ($layoutPreset === 'inherit') {
                $layoutPreset = 'actual';
            }
            if (in_array($layoutPreset, ['actual', 'none', 'custom', 'shared'], true)) {
                $layout['preset'] = $layoutPreset;
            }
            if (array_key_exists('source', $layoutPayload)) {
                $layout['source'] = trim((string) $layoutPayload['source']);
            }
            if (array_key_exists('sharedName', $layoutPayload)) {
                $layout['sharedName'] = trim((string) $layoutPayload['sharedName']);
            }
            if ($layoutTemplateProvided) {
                $layout['template'] = $layoutTemplate;
            }
            if ($layoutSectionTemplateProvided) {
                $layout['sectionTemplate'] = $layoutSectionTemplate;
            }
            foreach (['workTemplate', 'worksTemplate'] as $siblingSectionKey) {
                if (is_array($layoutPayload) && array_key_exists($siblingSectionKey, $layoutPayload)) {
                    $layout[$siblingSectionKey] = (string) $layoutPayload[$siblingSectionKey];
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
            foreach ([
                'template' => $layoutTemplateProvided,
                'sectionTemplate' => $layoutSectionTemplateProvided,
                'workTemplate' => is_array($layoutPayload) && array_key_exists('workTemplate', $layoutPayload),
                'worksTemplate' => is_array($layoutPayload) && array_key_exists('worksTemplate', $layoutPayload),
                'css' => $layoutCssProvided,
                'js' => $layoutJsProvided,
            ] as $layoutFileKey => $wasProvided) {
                if (!$wasProvided && array_key_exists($layoutFileKey, $layout) && trim((string) $layout[$layoutFileKey]) === '') {
                    unset($layout[$layoutFileKey]);
                }
            }
            $work['layout'] = Worktype::normalizeLayout($layout, $layoutSection);
        } elseif ($workLayout !== '') {
            $work['layout'] = Worktype::normalizeLayout($workLayout, $layoutSection);
        }

        if ($sectionOnlyLayoutUpdate) {
            $sanitizedSectionTemplate = PoffConfig::persistSectionTemplate(
                $targetDir,
                $subjectType === 'file' ? (string) $targetFile : null,
                (string) $layoutSectionTemplate,
                $layoutSection
            );
            if (!isset($work['layout']) || !is_array($work['layout'])) {
                $work['layout'] = [];
            }
            $work['layout']['sectionTemplate'] = $sanitizedSectionTemplate;
            $work['layout'] = Worktype::normalizeLayout($work['layout'], $layoutSection);
        } else {
            $work['layout'] = PoffConfig::persistLayoutFiles(
                $targetDir,
                $subjectType === 'file' ? (string) $targetFile : null,
                $work['layout'] ?? null,
                $layoutSection
            );
        }
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
        cmsSyncParentTreeItemMeta($rootDir, $subjectRelativePath, $subjectType, $config);
        if ($subjectType === 'file' && (array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data))) {
            cmsApplyParentTreeVisible($rootDir, $subjectRelativePath, $subjectType, $targetDir, $data['treeVisible'] ?? $data['tree_visible'] ?? null);
        }

        $responseConfig = PoffConfig::hydrateConfigLayout(
            $config,
            $targetDir,
            $subjectType === 'file' ? (string) $targetFile : null
        );
        $responseConfig = cmsAnnotateConfigWorktypeCatalog($responseConfig, $subjectType, $targetDir, $targetFile, $subjectType === 'folder' ? ($folderViewData ?? []) : []);

        cmsJsonResponse([
            'allowed' => true,
            'target' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectTarget' => $subjectType,
            'routePath' => $subjectRelativePath,
            'routeSlug' => (string) ($responseConfig['slug'] ?? $config['slug'] ?? ''),
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
        $editorDraft = is_array($data['draft'] ?? null) ? $data['draft'] : [];
        $promptMode = strtolower(trim((string) ($data['promptMode'] ?? $data['prompt_mode'] ?? '')));
        $promptTarget = strtolower(trim((string) ($data['promptTarget'] ?? $data['prompt_target'] ?? '')));
        $promptIsLayoutTarget = $isLayoutTarget
            || $promptMode === 'layout'
            || in_array($promptTarget, ['layout', 'wrapper', 'layout-wrapper'], true)
            || ($layoutPreset !== '' && !$isLayoutTarget);
        $image = cmsPromptImagePayload($data);

        if ($prompt === '' && !$image) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Missing prompt or image.',
            ]);
        }

        $folderViewData = $subjectType === 'folder'
            ? cmsPromptFolderViewData(
                $subjectRelativePath,
                $targetDir,
                $config,
                [
                    'name' => (string) ($config['folderName'] ?? basename($subjectRelativePath)),
                    'title' => (string) ($config['title'] ?? $config['folderName'] ?? basename($subjectRelativePath)),
                    'slug' => (string) ($config['slug'] ?? PoffConfig::slugify((string) ($config['folderName'] ?? basename($subjectRelativePath)))),
                ]
            )
            : [];

        $promptConfig = $config;
        if (is_array($promptConfig['work'] ?? null)) {
            $promptConfig['work']['templateMap'] = PoffConfig::resolveEffectiveTemplateMap(
                $targetDir,
                $promptConfig['work']['templateMap'] ?? null
            );
        }

        $promptContext = cmsBuildPromptContext(
            $subjectRelativePath,
            $subjectType,
            $promptConfig,
            $targetFile,
            $promptIsLayoutTarget,
            $layoutPreset,
            $editorDraft,
            cmsPromptParentConfig($rootDir, $subjectRelativePath, $subjectType, $targetDir),
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
                'Example: {"template":"<div class=\"generated-layout\"><main class=\"poff-default-layout__main\">{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}</main></div>","css":".generated-layout{min-height:100vh;} .generated-layout .poff-default-layout__main{padding:2rem;}","js":"document.documentElement.dataset.layout = \'custom\';","work":{"layout":"custom-layout"}}',
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
        $sharedWorkSystemPrompt = [
            'You are a Handlebars (HBS) template generator for this single-page CMS.',
            'Return strict JSON with a required "template" string and optional "work" field.',
            'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
            'Extra fields added below Description are stored as work.fields metadata and also flattened into work.<name> values.',
            'Use config/title/description, layout name/template, and work type/template when relevant; prefer existing worktypes and template variants: image, video, audio, pdf, text, link, folder, other.',
            'Use work.type for the base family and work.template for the exact template override. Use work.templateMap as inherited MIME => template defaults for child items. When a worktype exposes fields such as autoplay, loop, muted, poster, fit, background, or caption, set those as config values on work instead of hardcoding them into the template unless the user explicitly asks for one-off markup.',
            'Use work.categories as the main filter and grouping hint when it exists; prefer existing categories instead of inventing new ones.',
            'Use work.templateMap as the inherited MIME => template defaults from folder/layout parents. work.template is the exact override for the current item.',
            'Prompt context JSON current.parentWork contains the immediate parent folder/work. siblingWorks and siblingImages/siblingVideos/siblingLinks/etc contain only same-folder siblings, excluding the current item and without recursive children.',
            'Use sibling srcUrl/pageLink/linkUrl refs directly for prompts like "use the image in this folder as background" or "overlay the video in the center".',
            'If the user asks to hide used sibling works, return "treeVisible" as the full list of parent tree item names/paths that should remain visible. Include the current item unless the user explicitly asks to hide it.',
            'Use variables exactly as they exist in the current HBS scope. Prefer direct references like {{description}} when the variable is top-level.',
            'Only use parent lookups like {{../description}} when you are actually inside a nested Handlebars block such as {{#each}}, {{#with}}, or another scope-changing block.',
            'Do not invent alternate variable paths. Follow the variable path that exists in the provided HBS context.',
            'If Prompt context JSON current.editorDraft is present, treat it as the latest unsaved editor state and revise that draft before falling back to saved config or saved template sources.',
            'Use semantic HTML and stable readable class names. Do not use Tailwind utility classes in generated runtime templates.',
            'Do not return "css" or "js" for work prompts. Work prompts update only the inner HBS partial; layout prompts own wrapper CSS and JS.',
            'Do not put <style> tags inside template and do not use inline style attributes.',
            'Template sources live in .layout and .works layout folders; keep the source files as the authoring target.',
        ];
        $fileWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, [
            'Save target is work.hbs for the current file inside the active item layout folder.',
            'Focus on a single file view. Do not assume folder tree loops or folder aggregate lists unless the user explicitly asks for them.',
            'Prefer file-relevant fields such as {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.*.',
            'Prompt context JSON current.outerWrapper contains a compact summary of the active outer layout wrapper, with template/css/js excerpts. Use it as structure and styling reference only.',
            'Align your inner partial with the current outer wrapper semantics and class language when useful, but do not return or rewrite the wrapper itself.',
            'Do not return the outer layout wrapper, page shell, navigation chrome, or a full page template.',
            'Never return {{> work}}, {{> works}}, {{> poff-layout}}, {{> filesystem-layout}}, or a poff-default-layout wrapper from this file prompt.',
            'Never emit outer shell blocks like <header class="poff-default-layout__header">, <main class="poff-default-layout__main">, footer/nav/sidebar chrome, or wrapper-only include chains from this file prompt.',
            'Return only the inner partial content that will be rendered inside the existing layout wrapper.',
        ]);
        $folderWorkSystemPrompt = array_merge($sharedWorkSystemPrompt, [
            'Save target is works.hbs for the current folder inside the active item layout folder.',
            'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
            'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
            'Use folder tree data and resolved refs when relevant instead of inventing paths.',
            'Prompt context JSON current.outerWrapper contains a compact summary of the active outer layout wrapper, with template/css/js excerpts. Use it as structure and styling reference only.',
            'When the current folder is root or otherwise sparse, use current.outerWrapper as the main visual grounding instead of inventing a generic standalone page.',
            'Align your inner partial with the current outer wrapper semantics and class language when useful, but do not return or rewrite the wrapper itself.',
            'Do not return the outer layout wrapper, page shell, navigation chrome, or a full page template.',
            'Never return {{> work}}, {{> works}}, {{> poff-layout}}, {{> filesystem-layout}}, or a poff-default-layout wrapper from this folder prompt.',
            'Never emit outer shell blocks like <header class="poff-default-layout__header">, <main class="poff-default-layout__main">, footer/nav/sidebar chrome, or wrapper-only include chains from this folder prompt.',
            'Return only the inner folder partial content that will be rendered inside the existing layout wrapper.',
        ]);
        $defaultSystemPrompt = implode("\n", $promptIsLayoutTarget
            ? [
                'You are a Handlebars (HBS) layout generator for this single-page CMS.',
                'Transform the user description into an updated outer layout wrapper rendered through LightnCandy.',
                'Treat the currently resolved active wrapper as your primary reference. Prompt context JSON current.activeLayout and Config JSON work.layout contain the actual active template, sectionTemplate, css, and js after filesystem, inheritance, and preset resolution.',
                'When the active layout is empty or too minimal, fall back to the built-in default wrapper shape from src/includes/worktypes/templates/layout/default/template.hbs.',
                'The prompt edits the outer layout wrapper template, not the wrapped inner work.hbs or works.hbs partial.',
                'Keep the wrapped content chain active and preserve the data flow from the current item context all the way down to the inner partial. Use {{> works}} for folders and {{> work}} for files inside the layout wrapper unless the user explicitly asks to remove or replace it.',
                'The wrapper owns the page shell and must wrap the inner partial. Return one outer template that includes {{> works}} or {{> work}} exactly once unless the user explicitly asks for a different structure.',
                'For layout wrappers that should look consistent for folders and files, put sibling partials in work: {"works.hbs":"folder inner partial","work.hbs":"file inner partial"}.',
                'Always keep a <main class="poff-default-layout__main"> block whose content is exactly {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}. Do not omit this block.',
                'Return the wrapper as real Handlebars template code. Use the same runtime fields, partials, conditionals, and folder/file context that the active template already uses when they are still relevant.',
                'If Prompt context JSON current.editorDraft is present, treat those unsaved draft template/css/js values as the latest version to evolve from before falling back to current.activeLayout.',
                'Template sources live in .layout and .works layout folders; keep the source files as the authoring target.',
                'Use semantic HTML and stable readable class names. Do not use Tailwind utility classes in generated runtime templates.',
                'Put all wrapper-specific styling in the JSON "css" field as plain CSS that works without a build step.',
                'Scope CSS under a unique root class used by the returned wrapper. Do not define global selectors like body, a, img, h1 unless nested under that root class.',
                'Do not put <style> tags inside template and do not use inline style attributes.',
                'Use the actual resolved template/css/js as style and structure cues. Redesign them when requested, but keep useful Handlebars structure, routing fields, and wrapper semantics unless the user explicitly asks for a break.',
                'When the current layout mode stays inherited/inherit, edits should target the inherited/original filesystem layout source. When the user chooses Custom, edits target the local .layout/template.hbs wrapper.',
                'Use current.templateTarget as the active save target for this layout page. It follows the current layout mode: the resolved active wrapper for Inherit, the local custom wrapper for Custom, and never the inner partial by default.',
                'current.layoutTemplateTarget is the local custom wrapper path if you explicitly switch to Custom. current.sectionTemplateTarget is the advanced inner partial path, not the default save target here.',
                'Prompt context JSON current.activeLayout.template is the active outer wrapper, current.activeLayout.sectionTemplate is the current wrapped work/works partial, and current.activeLayout.css/js are the currently active style and script sources.',
                'For images, icons, CSS backgrounds, or other assets owned by the layout wrapper, do not build URLs from {{path}}. {{path}} points to the current folder/file, not the layout asset folder.',
                'Use runtime layout URLs such as {{layout.baseHref}}/file.ext for local or inherited folder layout assets. Reusing the bundled default profile image should look like {{layout.baseHref}}/eggman_profile-image.jpg when the active wrapper comes from the built-in default layout bundle.',
                'Prompt context JSON includes current.layoutBaseHref, current.inheritedLayoutDirectory, and current.layoutAssets to help you choose the right asset path and understand whether the wrapper comes from a parent folder .layout.',
                'Choose URL fields by intent: use {{pageLink}} for navigation and clickable cards that should open the CMS-templated page. Use {{srcUrl}} / {{assetUrl}} for direct sources such as <img src>, <video src>, <source src>, poster, download links, CSS url(...), and background-image.',
                'Never build internal CMS links manually with ?path=, ?file=, {{slug}}, or string concatenation. {{slug}} is an identifier, not a navigable path.',
                'If a provided item/pageLink/path/linkUrl value already contains a full CMS viewer URL like ?view=1&path=... or ?view=1&file=..., or an external URL, use it verbatim. Never prepend another ?view=1&path= or ?view=1&file= around it.',
                'Configured tree items may be virtual navigation links without a backing local file or folder. Respect their provided pageLink/linkUrl instead of forcing them into a filesystem path.',
                'Inputs available: {{pageLink}} / {{pageUrl}} / {{workUrl}} / {{viewUrl}} / {{viewerHref}} for the templated CMS viewer URL, {{srcUrl}} / {{sourceUrl}} / {{assetUrl}} / {{assetLink}} / {{rawHref}} for direct source URLs, {{path}} for the raw relative file path, plus {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
                'Use work.categories as the main filter and grouping hint when it exists; prefer existing categories instead of inventing new ones.',
                'Use work.templateMap as the inherited MIME => template defaults from folder/layout parents. work.template is the exact override for the current item.',
                'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
                'Prompt context JSON includes resolved refs for the current item and current folder contents. Use those refs directly instead of inventing paths.',
                'JS belongs in the JSON "js" field only. Guard DOM readiness, avoid network calls, and degrade gracefully if JS is disabled.',
            ]
            : ($subjectType === 'folder' ? $folderWorkSystemPrompt : $fileWorkSystemPrompt));
        $systemPrompt = $systemPromptValue !== '' ? $systemPromptValue : $defaultSystemPrompt;
        $historyText = cmsPromptHistoryText($history);
        $userPrompt = "Config JSON:\n" . $configJson . "\n\nPrompt context JSON:\n" . $promptContextJson . "\n\n" . $responseFormatInstruction . "\n\n" . $historyText . "USER: " . $prompt;
        if ($image) {
            $userPrompt .= "\n\nAttached image: " . ($image['name'] ?: 'clipboard-image.png');
        }

        $env = cmsLoadEnv($rootDir);
        $template = '';
        $modelReturnedReasoningOnly = false;
        $usedModel = $model;
        $localDebugEntry = null;
        $streamRequested = !empty($data['stream']);
        $streamBuffer = '';
        $streamTemplate = '';
        $streamChunkParser = function (string $chunk) use (&$streamBuffer, &$streamTemplate): void {
            if ($chunk === '') {
                return;
            }
            $chunk = str_replace("\r\n", "\n", $chunk);
            echo $chunk;
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
            flush();
            $streamBuffer .= $chunk;
            while (strpos($streamBuffer, "\n\n") !== false) {
                $splitAt = strpos($streamBuffer, "\n\n");
                $block = trim(substr($streamBuffer, 0, $splitAt));
                $streamBuffer = substr($streamBuffer, $splitAt + 2);
                if ($block === '') {
                    continue;
                }
                $dataLines = [];
                foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                    if (str_starts_with($line, 'data:')) {
                        $dataLines[] = ltrim(substr($line, 5));
                    }
                }
                $dataLine = implode("\n", $dataLines);
                if ($dataLine === '' || $dataLine === '[DONE]') {
                    continue;
                }
                $decodedChunk = json_decode($dataLine, true);
                if (!is_array($decodedChunk)) {
                    continue;
                }
                $delta = $decodedChunk['choices'][0]['delta']['content'] ?? null;
                if (is_string($delta) && $delta !== '') {
                    $streamTemplate .= $delta;
                }
            }
        };

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
            if ($streamRequested) {
                cmsPromptSendSseHeaders();
                $payload['stream'] = true;
                $response = cmsHttpPostStream('https://api.openai.com/v1/chat/completions', [
                    'Authorization: Bearer ' . $key,
                ], $payload, $streamChunkParser);
                if (!$response['ok']) {
                    cmsPromptSendSseEvent('final', [
                        'allowed' => true,
                        'error' => cmsFormatPromptHttpError('OpenAI', $response),
                    ]);
                    exit;
                }
                $template = $streamTemplate;
            } else {
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
            }
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
                    'config' => cmsPromptCompactConfig($config, $promptIsLayoutTarget),
                    'instruction' => $systemPrompt,
                    'image' => $image,
                    'promptContext' => cmsPromptCompactContext($promptContext),
                ];
            }
            $localDebugEntry = [
                'provider' => $provider,
                'path' => $path,
                'targetType' => $promptIsLayoutTarget ? 'layout' : $subjectType,
                'subjectType' => $subjectType,
                'endpoint' => $endpoint,
                'requestPayload' => $payload,
            ];
            if ($streamRequested && cmsIsOpenAiCompatibleEndpoint($endpoint)) {
                cmsPromptSendSseHeaders();
                $payload['stream'] = true;
                $response = cmsHttpPostStream($endpoint, [], $payload, $streamChunkParser);
                if (!$response['ok']) {
                    $debugPath = cmsPromptDebugCapture($rootDir, array_merge($localDebugEntry ?? [], [
                        'failure' => 'http',
                        'response' => $response,
                    ]));
                    cmsPromptSendSseEvent('final', [
                        'allowed' => true,
                        'error' => cmsFormatPromptHttpError('Local endpoint', $response) . ' Debug saved to ' . $debugPath . '.',
                    ]);
                    exit;
                }
                $template = $streamTemplate;
            } else {
                $response = cmsHttpPost($endpoint, [], $payload);
                if (!$response['ok']) {
                    $debugPath = cmsPromptDebugCapture($rootDir, array_merge($localDebugEntry ?? [], [
                        'failure' => 'http',
                        'response' => $response,
                    ]));
                    cmsJsonResponse([
                        'allowed' => true,
                        'error' => cmsFormatPromptHttpError('Local endpoint', $response) . ' Debug saved to ' . $debugPath . '.',
                    ]);
                }
                $decoded = json_decode($response['body'], true);
                if (cmsIsOpenAiCompatibleEndpoint($endpoint) && !is_array($decoded)) {
                    $debugPath = cmsPromptDebugCapture($rootDir, array_merge($localDebugEntry ?? [], [
                        'failure' => 'invalid_json_envelope',
                        'response' => $response,
                    ]));
                    cmsJsonResponse([
                        'allowed' => true,
                        'error' => 'Local endpoint returned an invalid JSON chat envelope. Debug saved to ' . $debugPath . '.',
                    ], 502);
                }
                if (is_array($decoded)) {
                    $message = $decoded['choices'][0]['message'] ?? null;
                    if (is_array($message) && array_key_exists('content', $message)) {
                        $template = (string) $message['content'];
                        $reasoningContent = trim((string) ($message['reasoning_content'] ?? ''));
                        $modelReturnedReasoningOnly = trim($template) === '' && $reasoningContent !== '';
                    } elseif (isset($decoded['template'])) {
                        $template = (string) $decoded['template'];
                    } elseif (isset($decoded['content'])) {
                        $template = (string) $decoded['content'];
                    }
                } elseif ($template === '') {
                    $template = trim((string) $response['body']);
                }
            }
        }

        $parsedResult = cmsParsePromptModelResult($template, $promptIsLayoutTarget);
        $templateText = trim((string) ($parsedResult['template'] ?? ''));
        if ($templateText === '') {
            if ($streamRequested && ($provider === 'openai' || ($provider === 'local' && cmsIsOpenAiCompatibleEndpoint($endpoint)))) {
                cmsPromptSendSseEvent('final', [
                    'allowed' => true,
                    'error' => $modelReturnedReasoningOnly
                        ? 'Model returned reasoning only and no template text. Disable reasoning/thinking in LM Studio or ask the model to return final template text.'
                        : 'Template was empty.',
                ]);
                exit;
            }
            cmsJsonResponse([
                'allowed' => true,
                'error' => $modelReturnedReasoningOnly
                    ? 'Model returned reasoning only and no template text. Disable reasoning/thinking in LM Studio or ask the model to return final template text.'
                    : 'Template was empty.',
            ]);
        }

        $responsePayload = [
            'allowed' => true,
            'target' => $promptIsLayoutTarget ? 'layout' : $subjectType,
            'subjectTarget' => $subjectType,
            'provider' => $provider,
            'model' => $usedModel,
            'template' => $templateText,
            'systemPrompt' => $systemPrompt,
        ];
        foreach (['title', 'description', 'work', 'css', 'js', 'treeVisible'] as $key) {
            if (array_key_exists($key, $parsedResult)) {
                $responsePayload[$key] = $parsedResult[$key];
            }
        }

        if ($streamRequested && ($provider === 'openai' || ($provider === 'local' && cmsIsOpenAiCompatibleEndpoint($endpoint)))) {
            cmsPromptSendSseEvent('final', $responsePayload);
            exit;
        }

        cmsJsonResponse($responsePayload);
    }
}
