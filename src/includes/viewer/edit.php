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

function cmsDefaultLayoutMainBlock(): string
{
    return <<<HBS
<main class="poff-default-layout__main">
    {{#if isFolder}}
        {{> works}}
    {{else}}
        {{> work}}
    {{/if}}
</main>
HBS;
}

function cmsNormalizeLayoutPromptTemplate(string $template): string
{
    $trimmed = trim($template);
    if ($trimmed === '') {
        return '';
    }

    $requiredPartials = str_contains($trimmed, '{{> works}}') && str_contains($trimmed, '{{> work}}');
    $mainPattern = '/<main\b[^>]*class\s*=\s*["\'][^"\']*\bpoff-default-layout__main\b[^"\']*["\'][^>]*>.*?<\/main>/is';
    $mainBlock = cmsDefaultLayoutMainBlock();

    if (preg_match($mainPattern, $trimmed) === 1) {
        if ($requiredPartials) {
            return $trimmed;
        }

        return preg_replace($mainPattern, $mainBlock, $trimmed, 1) ?? $trimmed;
    }

    if ($requiredPartials) {
        return $trimmed;
    }

    foreach (['</footer>', '</div>'] as $closingTag) {
        $position = strripos($trimmed, $closingTag);
        if ($position !== false) {
            return substr($trimmed, 0, $position) . $mainBlock . "\n\n" . substr($trimmed, $position);
        }
    }

    return $trimmed . "\n\n" . $mainBlock;
}

function cmsParsePromptModelResult(string $raw, bool $isLayoutTarget = false): array
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

    if ($isLayoutTarget) {
        $template = cmsNormalizeLayoutPromptTemplate($template);
    }

    $result = ['template' => $template];
    foreach (['title', 'description', 'model'] as $key) {
        if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
            $result[$key] = trim((string) $decoded[$key]);
        }
    }
    if (array_key_exists('css', $decoded) || array_key_exists('style', $decoded)) {
        $result['css'] = (string) ($decoded['css'] ?? $decoded['style'] ?? '');
    }
    if (array_key_exists('js', $decoded) || array_key_exists('script', $decoded)) {
        $result['js'] = (string) ($decoded['js'] ?? $decoded['script'] ?? '');
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

function cmsCreateBlankFile(string $targetDir, string $name, string $contents = ''): array
{
    $trimmedName = trim($name);
    if ($trimmedName === '') {
        return [
            'stored' => [],
            'errors' => ['Enter a file name.'],
        ];
    }

    $safeName = cmsSanitizeUploadName($trimmedName);
    if ($safeName === 'upload.bin' && trim($trimmedName) !== 'upload.bin') {
        return [
            'stored' => [],
            'errors' => ['Enter a valid file name.'],
        ];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetPath = cmsResolveUniqueUploadPath($targetDir, $safeName);
    $written = @file_put_contents($targetPath, $contents);
    if ($written === false) {
        return [
            'stored' => [],
            'errors' => ['Could not create ' . basename($targetPath) . '.'],
        ];
    }

    return [
        'stored' => [[
            'name' => basename($targetPath),
            'path' => basename($targetPath),
            'size' => filesize($targetPath) ?: 0,
        ]],
        'errors' => [],
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

function cmsPromptEncodeRelativePath(string $path): string
{
    $parts = explode('/', str_replace('\\', '/', trim($path, "/\\")));
    $parts = array_values(array_filter($parts, static fn(string $part): bool => $part !== ''));
    if ($parts === []) {
        return '';
    }

    return implode('/', array_map(static fn(string $part): string => rawurlencode($part), $parts));
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

function cmsBuildPromptContext(
    string $relativePath,
    string $subjectType,
    array $config,
    ?string $targetFile = null,
    bool $isLayoutTarget = false,
    string $layoutPreset = ''
): array
{
    $normalizedPath = trim($relativePath, "/\\");
    $currentName = $subjectType === 'file'
        ? (string) ($targetFile ?? basename($normalizedPath))
        : (string) ($config['folderName'] ?? basename($normalizedPath));
    $currentPath = $normalizedPath;
    $currentIsFile = $subjectType === 'file';
    $currentSection = $currentIsFile ? 'work' : 'works';
    $currentPageLink = cmsPromptViewerUrl($currentPath, $currentIsFile);
    $currentAssetUrl = cmsPromptAssetUrl($currentPath, $currentIsFile);
    $currentSectionTarget = cmsPromptTemplateTarget($currentPath, $currentIsFile, $currentSection);
    $currentLocalLayoutTarget = cmsPromptLayoutTemplateTarget($currentPath, $currentIsFile);
    $currentLocalLayoutDirectory = preg_replace('#/template\.hbs$#', '', $currentLocalLayoutTarget) ?: $currentLocalLayoutTarget;
    $layoutValue = is_array($config['work'] ?? null) && is_array($config['work']['layout'] ?? null)
        ? $config['work']['layout']
        : [];
    $resolvedLayoutDirectory = trim((string) ($layoutValue['directory'] ?? ''), "/\\");
    $layoutStorage = trim((string) ($layoutValue['storage'] ?? ''));
    $normalizedLayoutPreset = trim($layoutPreset);
    $activeLayoutDirectory = $currentLocalLayoutDirectory;
    if ($normalizedLayoutPreset === 'custom') {
        $activeLayoutDirectory = $currentLocalLayoutDirectory;
    } elseif ($isLayoutTarget && $layoutStorage === 'filesystem' && $resolvedLayoutDirectory !== '') {
        $activeLayoutDirectory = $resolvedLayoutDirectory;
    } elseif (!$isLayoutTarget && $resolvedLayoutDirectory !== '') {
        $activeLayoutDirectory = $resolvedLayoutDirectory;
    }
    $currentLayoutTarget = trim($activeLayoutDirectory, "/\\") . '/template.hbs';
    $layoutBasePath = $activeLayoutDirectory;
    $layoutSectionBasePath = trim((string) ($layoutValue['sectionDirectory'] ?? $layoutBasePath), "/\\");
    $layoutAssets = [];
    foreach (($layoutValue['assets'] ?? []) as $asset) {
        if (!is_array($asset) || !isset($asset['path'])) {
            continue;
        }
        $assetPath = trim((string) $asset['path'], "/\\");
        if ($assetPath === '') {
            continue;
        }
        $layoutAssets[] = [
            'name' => (string) ($asset['name'] ?? basename($assetPath)),
            'path' => $assetPath,
            'href' => cmsPromptEncodeRelativePath($layoutBasePath . '/' . $assetPath),
        ];
    }

    $context = [
        'current' => [
            'targetType' => $isLayoutTarget ? 'layout' : $subjectType,
            'subjectType' => $subjectType,
            'layoutPreset' => $normalizedLayoutPreset,
            'sectionPartial' => $currentSection,
            'name' => $currentName,
            'path' => $currentPath,
            'virtualPath' => $isLayoutTarget ? PoffConfig::relativeLayoutPath($currentPath, $currentIsFile) : '',
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
            'templateTarget' => $isLayoutTarget ? $currentLayoutTarget : $currentSectionTarget,
            'layoutTemplateTarget' => $currentLocalLayoutTarget,
            'sectionTemplateTarget' => $currentSectionTarget,
            'layoutBaseHref' => cmsPromptEncodeRelativePath($layoutBasePath),
            'inheritedLayoutDirectory' => trim((string) ($layoutValue['inheritedDirectory'] ?? ''), "/\\"),
            'layoutSectionBaseHref' => cmsPromptEncodeRelativePath($layoutSectionBasePath),
            'layoutAssets' => $layoutAssets,
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

    if ($subjectType !== 'folder') {
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

function cmsPromptTrimText(string $text, int $maxLength = 240): string
{
    $normalized = preg_replace('/\s+/', ' ', trim($text)) ?? trim($text);
    if (strlen($normalized) <= $maxLength) {
        return $normalized;
    }

    return substr($normalized, 0, max(0, $maxLength - 3)) . '...';
}

function cmsPromptCompactRef(array $ref): array
{
    $compact = [];
    foreach (['name', 'title', 'type', 'kind', 'path', 'pageLink', 'srcUrl', 'isFolder', 'isFile', 'visible'] as $key) {
        if (!array_key_exists($key, $ref)) {
            continue;
        }
        $value = $ref[$key];
        $compact[$key] = is_string($value) ? cmsPromptTrimText($value, 160) : $value;
    }

    return $compact;
}

function cmsPromptCompactConfig(array $config): array
{
    $summary = [];

    foreach (['title', 'description', 'folderName', 'updatedAt', 'treeHash'] as $key) {
        if (array_key_exists($key, $config) && is_scalar($config[$key])) {
            $summary[$key] = is_string($config[$key])
                ? cmsPromptTrimText((string) $config[$key], 240)
                : $config[$key];
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
                        $layoutSummary[$layoutKey] = is_string($value[$layoutKey])
                            ? cmsPromptTrimText((string) $value[$layoutKey], 180)
                            : $value[$layoutKey];
                    }
                }
                foreach (['template', 'sectionTemplate', 'css', 'style', 'js', 'script'] as $layoutKey) {
                    if (isset($value[$layoutKey]) && is_string($value[$layoutKey]) && $value[$layoutKey] !== '') {
                        $layoutSummary[$layoutKey . 'Length'] = strlen($value[$layoutKey]);
                    }
                }
                if (is_array($value['assets'] ?? null)) {
                    $layoutSummary['assetCount'] = count($value['assets']);
                }
                $summary['work']['layout'] = $layoutSummary;
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                $summary['work'][$key] = $value;
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
        $summary['tree'] = [
            'count' => count($tree),
            'sample' => $sample,
        ];
    }

    return $summary;
}

function cmsPromptCompactContext(array $context): array
{
    $items = array_values(array_filter(array_map(
        static fn(array $ref): array => cmsPromptCompactRef($ref),
        array_slice(is_array($context['items'] ?? null) ? $context['items'] : [], 0, 24)
    )));

    $counts = [
        'items' => count(is_array($context['items'] ?? null) ? $context['items'] : []),
        'files' => count(is_array($context['allFiles'] ?? null) ? $context['allFiles'] : []),
        'folders' => count(is_array($context['allFolders'] ?? null) ? $context['allFolders'] : []),
        'images' => count(is_array($context['allImages'] ?? null) ? $context['allImages'] : []),
        'videos' => count(is_array($context['allVideos'] ?? null) ? $context['allVideos'] : []),
        'audio' => count(is_array($context['allAudio'] ?? null) ? $context['allAudio'] : []),
        'pdfs' => count(is_array($context['allPdfs'] ?? null) ? $context['allPdfs'] : []),
        'texts' => count(is_array($context['allTexts'] ?? null) ? $context['allTexts'] : []),
        'links' => count(is_array($context['allLinks'] ?? null) ? $context['allLinks'] : []),
        'other' => count(is_array($context['allOther'] ?? null) ? $context['allOther'] : []),
    ];

    return [
        'current' => $context['current'] ?? [],
        'counts' => $counts,
        'items' => $items,
    ];
}

function cmsPromptHistoryText(array $history): string
{
    $recentHistory = array_slice($history, -6);
    $historyText = '';
    foreach ($recentHistory as $msg) {
        if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
            continue;
        }
        $role = strtolower((string) $msg['role']);
        $content = cmsPromptTrimText((string) $msg['content'], 800);
        if ($content === '') {
            continue;
        }
        $historyText .= strtoupper($role) . ": " . $content . "\n";
    }

    return $historyText;
}

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
        $promptContextJson = json_encode(cmsPromptCompactContext($promptContext), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $configJson = json_encode($isLayoutTarget ? cmsPromptCompactConfig($config) : $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
        $defaultSystemPrompt = implode("\n", $isLayoutTarget
            ? [
                'You are a Handlebars (HBS) layout generator for this single-page CMS.',
                'Return one HBS template string for the outer layout wrapper rendered through LightnCandy.',
                'When generating a custom layout, use src/includes/worktypes/templates/layout/default/template.hbs as the scaffold and keep the same wrapper behavior unless the user explicitly asks otherwise.',
                'The prompt edits the outer layout wrapper template, not the wrapped inner work.hbs or works.hbs partial.',
                'Keep the wrapped content chain active: use {{> works}} for folders and {{> work}} for files inside the layout wrapper unless the user explicitly asks to remove or replace it.',
                'The wrapper owns the page shell and must wrap the inner partial. Return one outer template that includes {{> works}} or {{> work}} exactly once unless the user explicitly asks for a different structure.',
                'Always keep a <main class="poff-default-layout__main"> block whose content is exactly {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}. Do not omit this block.',
                'Prefer returning sibling "css" and "js" strings too, so the custom layout can create template.hbs, style.css, and script.js together.',
                'When the current layout mode stays inherited or actual, edits should target the inherited/original filesystem layout source. When the user chooses Custom, edits target the local .layout/template.hbs wrapper.',
                'Use current.templateTarget as the active save target for this layout page. It follows the current layout mode: the resolved active wrapper for Actual, the local custom wrapper for Custom, and never the inner partial by default.',
                'current.layoutTemplateTarget is the local custom wrapper path if you explicitly switch to Custom. current.sectionTemplateTarget is the advanced inner partial path, not the default save target here.',
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
            : [
                'You are a Handlebars (HBS) template generator for this single-page CMS.',
                'Return one HBS template string for the wrapped inner section partial rendered through LightnCandy.',
                'Save target is work.hbs for files and works.hbs for folders inside the active item layout folder.',
                'Return only the template (no Markdown, no fences).',
                'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
                'Folder views get recursive tree data: tree/items include children on nested folders, workTree is the folder root, and helper lists like allItems, allFiles, allFolders, allVideos, allImages, allAudio, allPdfs, allTexts, allLinks, and allOther are available. Folder items also expose {{pageLink}} for navigation and {{srcUrl}} / {{assetUrl}} for direct sources.',
                'For folder item loops, prefer item booleans like {{#if isFile}} and {{#if isFolder}} over custom helpers.',
                'Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
                'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
                'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
            ]);
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
            if ($isLayoutTarget) {
                $payload['promptContext'] = $promptContext;
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
