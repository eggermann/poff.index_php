<?php

@require_once __DIR__ . '/../../Converter.php';

function renderFileViewer(string $relativePath, string $fullPath, ?array $fileConfigOverride = null, ?array $treeConfigOverride = null): void
{
    $converterTarget = cmsResolveConverterDefinitionViewerTarget($relativePath, $fullPath);
    if (is_array($converterTarget)) {
        renderFolderViewer((string) $converterTarget['relativePath'], (string) $converterTarget['fullPath']);
        return;
    }

    $hasPhysicalFile = is_file($fullPath);
    $fileConfig = $fileConfigOverride;
    if ($fileConfig === null && $hasPhysicalFile && class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }
    $treeConfig = $treeConfigOverride;
    if ($treeConfig === null && class_exists('PoffConfig')) {
        $treeConfig = cmsResolveConfiguredTreeItem(dirname($fullPath), $relativePath);
    }

    [$type, $mimeType, $work, $treeConfig, $fileConfig] = cmsResolveFileViewerState($relativePath, $fullPath, $fileConfig, $treeConfig);

    $linkState = cmsResolveFileViewerLinkState($fullPath, $type, $treeConfig, $fileConfig);
    $linkUrl = $linkState['linkUrl'];
    $configuredBaseUrl = $linkState['baseUrl'];
    $renderedHtml = $linkState['renderedHtml'];
    $previewUrl = $linkState['previewUrl'];

    $externalMeta = is_array($fileConfig['external'] ?? null)
        ? $fileConfig['external']
        : (is_array($treeConfig['external'] ?? null) ? $treeConfig['external'] : []);
    if ($externalMeta !== []) {
        $externalMeta['generatedBy'] = $externalMeta['generatedBy'] ?? ($fileConfig['generatedBy'] ?? ($treeConfig['generatedBy'] ?? []));
    }
    if ($type !== 'htaccess' && ($externalMeta !== [] || $renderedHtml !== '' || $previewUrl !== '' || ($type === 'link' && trim((string) ($linkUrl ?? '')) !== ''))) {
        $work['template'] = 'external';
        $work['layout']['section'] = 'work';
        $work['layout']['sectionTemplate'] = '';
    }

    $converterPreview = cmsResolveConverterPreviewState($relativePath, $fullPath, $type, $mimeType, $fileConfig, $treeConfig);
    if (is_array($converterPreview['work'] ?? null)) {
        $work = array_merge($work, $converterPreview['work']);
    }

    if (class_exists('PoffConfig')) {
        $work['layout'] = PoffConfig::prepareLayoutForView($work['layout'] ?? null, $relativePath, true, 'work');
    }

    $rawName = basename($relativePath);
    $context = cmsBuildFileViewerContext(
        $relativePath,
        $fullPath,
        $type,
        $mimeType,
        $work,
        $treeConfig,
        $fileConfig,
        $linkUrl,
        $previewUrl,
        $configuredBaseUrl,
        $renderedHtml,
        $externalMeta
    );
    if (is_array($converterPreview['context'] ?? null)) {
        $context = array_merge($context, $converterPreview['context']);
    }

    $bodyContent = Worktype::render($type, $context);

    renderViewerShell([
        'type' => $type,
        'name' => $rawName,
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'bodyContent' => $bodyContent,
        'openHref' => ($linkUrl !== null && trim($linkUrl) !== '') ? $linkUrl : viewerAssetHref($relativePath),
        'openLabel' => ($linkUrl !== null && trim($linkUrl) !== '') ? 'Open Source' : 'Open Raw',
    ]);
}

function buildFileViewerSnapshotPayload(string $relativePath, string $fullPath, ?array $fileConfigOverride = null, ?array $treeConfigOverride = null): array
{
    $converterTarget = cmsResolveConverterDefinitionViewerTarget($relativePath, $fullPath);
    if (is_array($converterTarget) && function_exists('cmsBuildFolderViewerSnapshotPayload')) {
        return cmsBuildFolderViewerSnapshotPayload((string) $converterTarget['relativePath'], (string) $converterTarget['fullPath']);
    }

    $hasPhysicalFile = is_file($fullPath);
    $fileConfig = $fileConfigOverride;
    if ($fileConfig === null && $hasPhysicalFile && class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }
    $treeConfig = $treeConfigOverride;
    if ($treeConfig === null && class_exists('PoffConfig')) {
        $treeConfig = cmsResolveConfiguredTreeItem(dirname($fullPath), $relativePath);
    }

    [$type, $mimeType, $work, $treeConfig, $fileConfig] = cmsResolveFileViewerState($relativePath, $fullPath, $fileConfig, $treeConfig);

    $linkState = cmsResolveFileViewerLinkState($fullPath, $type, $treeConfig, $fileConfig);
    $linkUrl = $linkState['linkUrl'];
    $configuredBaseUrl = $linkState['baseUrl'];
    $renderedHtml = $linkState['renderedHtml'];
    $previewUrl = $linkState['previewUrl'];

    $externalMeta = is_array($fileConfig['external'] ?? null)
        ? $fileConfig['external']
        : (is_array($treeConfig['external'] ?? null) ? $treeConfig['external'] : []);
    if ($externalMeta !== []) {
        $externalMeta['generatedBy'] = $externalMeta['generatedBy'] ?? ($fileConfig['generatedBy'] ?? ($treeConfig['generatedBy'] ?? []));
        $work['template'] = 'external';
        $work['layout']['section'] = 'work';
        $work['layout']['sectionTemplate'] = '';
    }

    $converterPreview = cmsResolveConverterPreviewState($relativePath, $fullPath, $type, $mimeType, $fileConfig, $treeConfig);
    if (is_array($converterPreview['work'] ?? null)) {
        $work = array_merge($work, $converterPreview['work']);
    }

    if (class_exists('PoffConfig')) {
        $work['layout'] = PoffConfig::prepareLayoutForView($work['layout'] ?? null, $relativePath, true, 'work');
    }

    $snapshotContext = cmsBuildFileViewerContext(
        $relativePath,
        $fullPath,
        $type,
        $mimeType,
        $work,
        $treeConfig,
        $fileConfig,
        $linkUrl,
        $previewUrl,
        $configuredBaseUrl,
        $renderedHtml,
        $externalMeta
    );
    if (is_array($converterPreview['context'] ?? null)) {
        $snapshotContext = array_merge($snapshotContext, $converterPreview['context']);
    }

    $rawName = basename($relativePath);
    $rendered = Worktype::render($type, $snapshotContext);
    return [
        'type' => $type,
        'name' => $rawName,
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'path' => $relativePath,
        'layout' => $work['layout'] ?? [],
        'openHref' => ($linkUrl !== null && trim($linkUrl) !== '') ? $linkUrl : viewerAssetHref($relativePath),
        'openLabel' => ($linkUrl !== null && trim($linkUrl) !== '') ? 'Open Source' : 'Open Raw',
        'snapshotContext' => $snapshotContext,
        'renderedHtml' => $rendered,
    ];
}

function cmsResolveConverterDefinitionViewerTarget(string $relativePath, string $fullPath): ?array
{
    if (!class_exists('Converter')) {
        return null;
    }

    if (strtolower(basename($relativePath)) !== 'converter.json') {
        return null;
    }

    $parentFullPath = dirname($fullPath);
    if (!is_dir($parentFullPath)) {
        return null;
    }

    $parentRelativePath = trim(str_replace('\\', '/', dirname($relativePath)), '.');
    if ($parentRelativePath === '') {
        return null;
    }

    $rootCandidates = [];
    if (function_exists('cmsProjectRootDir')) {
        $rootCandidates[] = cmsProjectRootDir($parentFullPath);
    }
    $currentRoot = $parentFullPath;
    while ($currentRoot !== '' && $currentRoot !== DIRECTORY_SEPARATOR) {
        $rootCandidates[] = $currentRoot;
        $parentRoot = dirname($currentRoot);
        if ($parentRoot === $currentRoot) {
            break;
        }
        $currentRoot = $parentRoot;
    }
    $seen = [];
    foreach ($rootCandidates as $candidateRoot) {
        $candidateRoot = is_string($candidateRoot) ? trim($candidateRoot) : '';
        if ($candidateRoot === '' || isset($seen[$candidateRoot])) {
            continue;
        }
        $seen[$candidateRoot] = true;
        if (Converter::definitionFromFolder($candidateRoot, $parentRelativePath) !== null) {
            return [
                'relativePath' => $parentRelativePath,
                'fullPath' => $parentFullPath,
            ];
        }
    }

    return null;
}

function cmsResolveFileViewerState(string $relativePath, string $fullPath, ?array $fileConfig, ?array $treeConfig): array
{
    $hasPhysicalFile = is_file($fullPath);
    $detectedType = detectFileType($fullPath);
    $configuredType = strtolower(trim((string) ($treeConfig['kind'] ?? ($treeConfig['type'] ?? ($fileConfig['kind'] ?? ($fileConfig['type'] ?? ''))))));
    $type = $configuredType === 'link' ? 'link' : $detectedType;
    $mimeType = $hasPhysicalFile ? MediaType::detectMimeType($fullPath, basename($fullPath)) : null;
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    if ($workData === [] && isset($treeConfig['work']) && is_array($treeConfig['work'])) {
        $workData = $treeConfig['work'];
    }

    $workTemplateKey = trim((string) ($workData['template'] ?? ''));
    $workDefinitionKey = $workTemplateKey !== '' ? $workTemplateKey : $type;
    $workDefaults = Worktype::definition($workDefinitionKey, $mimeType);
    $work = array_merge($workDefaults, $workData);
    if (class_exists('PoffConfig')) {
        $resolvedWorkState = PoffConfig::resolveWorkTemplateState(dirname($fullPath), $work, $type, $mimeType, basename($fullPath));
        if (is_array($resolvedWorkState['work'] ?? null)) {
            $work = $resolvedWorkState['work'];
        }
    }

    if ((!isset($work['template']) || trim((string) $work['template']) === '') && isset($workDefaults['template']) && is_string($workDefaults['template'])) {
        $work['template'] = $workDefaults['template'];
    }
    $work['type'] = $work['type'] ?? $type;

    return [$type, $mimeType, $work, $treeConfig ?? [], $fileConfig ?? []];
}

function cmsResolveFileViewerLinkState(string $fullPath, string $type, array $treeConfig, array $fileConfig): array
{
    $hasPhysicalFile = is_file($fullPath);
    $linkUrl = null;
    $configuredLinkUrl = trim((string) ($treeConfig['linkUrl'] ?? ($treeConfig['pageLink'] ?? ($treeConfig['pageUrl'] ?? ''))));
    $configuredBaseUrl = trim((string) ($treeConfig['baseUrl'] ?? ''));
    $configuredRenderedHtml = trim((string) ($treeConfig['renderedHtml'] ?? ''));
    if ($type === 'link' && $hasPhysicalFile) {
        $linkUrl = extractLinkFileUrl($fullPath);
    }
    if ($linkUrl === null && $configuredLinkUrl !== '') {
        $linkUrl = $configuredLinkUrl;
    }
    if ($type === 'htaccess') {
        $linkUrl = null;
    }

    $renderedHtml = cmsResolveRemoteRenderedHtml([
        'linkUrl' => $linkUrl ?? '',
        'pageLink' => $fileConfig['pageLink'] ?? '',
        'pageUrl' => $fileConfig['pageUrl'] ?? '',
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'renderedHtml' => $configuredRenderedHtml !== '' ? $configuredRenderedHtml : trim((string) ($fileConfig['renderedHtml'] ?? '')),
    ]);

    $previewUrl = '';
    if ($linkUrl !== null) {
        $trimmedLinkUrl = trim($linkUrl);
        if ($trimmedLinkUrl !== '' && (cmsIsExternalLinkTarget($trimmedLinkUrl) || cmsIsCmsQueryLinkTarget($trimmedLinkUrl))) {
            $previewUrl = $trimmedLinkUrl;
        }
    }

    return [
        'linkUrl' => $linkUrl,
        'baseUrl' => $configuredBaseUrl !== '' ? $configuredBaseUrl : ($fileConfig['baseUrl'] ?? ($linkUrl ?? '')),
        'renderedHtml' => $renderedHtml,
        'previewUrl' => $previewUrl,
    ];
}

function cmsBuildFileViewerContext(
    string $relativePath,
    string $fullPath,
    string $type,
    ?string $mimeType,
    array $work,
    array $treeConfig,
    array $fileConfig,
    ?string $linkUrl,
    string $previewUrl,
    string $configuredBaseUrl,
    string $renderedHtml,
    array $externalMeta
): array {
    $showInlineTextPreview = false;
    $textContent = '';
    if (in_array($type, ['text', 'htaccess'], true) && is_file($fullPath)) {
        $showInlineTextPreview = MediaType::shouldUseInlineTextPreview(basename($fullPath), $mimeType);
        if ($showInlineTextPreview) {
            $contents = @file_get_contents($fullPath);
            if ($contents !== false) {
                $textContent = $contents;
            }
        }
    }

    $rawName = basename($relativePath);
    $rawSlug = $fileConfig['slug'] ?? preg_replace('/[^a-z0-9\-]+/i', '-', $rawName);
    $rawSlug = trim((string) $rawSlug, '-');
    $descriptionHtml = '';
    if (!empty($fileConfig['description'])) {
        $descriptionHtml = '<div class="work-description">' . nl2br(htmlspecialchars($fileConfig['description'], ENT_QUOTES, 'UTF-8')) . '</div>';
    }

    return [
        'path' => $relativePath,
        'viewerHref' => '?view=1&file=' . rawurlencode($relativePath),
        'viewUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'workUrl' => '?view=1&file=' . rawurlencode($relativePath),
        'rawHref' => viewerAssetHref($relativePath),
        'assetUrl' => viewerAssetHref($relativePath),
        'mimeType' => $mimeType ?? '',
        'name' => $rawName,
        'title' => $treeConfig['title'] ?? ($fileConfig['title'] ?? $rawName),
        'description' => $treeConfig['description'] ?? ($fileConfig['description'] ?? ''),
        'descriptionHtml' => $descriptionHtml,
        'linkUrl' => $linkUrl ?? '',
        'previewUrl' => $previewUrl,
        'baseUrl' => $configuredBaseUrl,
        'slug' => $rawSlug === '' ? 'item' : $rawSlug,
        'showInlineTextPreview' => $showInlineTextPreview,
        'textContent' => $textContent,
        'renderedHtml' => $renderedHtml,
        'external' => $externalMeta,
        'work' => $work,
    ];
}

function cmsResolveConverterPreviewState(string $relativePath, string $fullPath, string $type, ?string $mimeType, array $fileConfig, array $treeConfig): array
{
    if (!class_exists('Converter')) {
        return [];
    }

    $enabled = trim((string) ($_GET['converter_preview'] ?? ''));
    $converterId = trim((string) ($_GET['converter_id'] ?? ''));
    if ($enabled !== '1' || $converterId === '') {
        return [];
    }

    $selected = Converter::normalizeSelectedConverter([
        'id' => $converterId,
        'path' => (string) ($_GET['converter_path'] ?? ''),
        'url' => (string) ($_GET['converter_url'] ?? ''),
        'format' => (string) ($_GET['converter_format'] ?? ''),
        'quality' => (string) ($_GET['converter_quality'] ?? ''),
        'saveMode' => (string) ($_GET['converter_save_mode'] ?? ''),
    ]);

    $rootCandidates = [dirname($fullPath)];
    if (function_exists('cmsProjectRootDir')) {
        $rootCandidates[] = cmsProjectRootDir(dirname($fullPath));
    }
    $definition = null;
    $resolvedRootDir = dirname($fullPath);
    foreach ($rootCandidates as $candidateRoot) {
        $candidateDefinition = null;
        if (trim((string) ($selected['path'] ?? '')) !== '') {
            $candidateDefinition = Converter::definitionFromFolder((string) $candidateRoot, (string) $selected['path']);
        }
        if ($candidateDefinition === null) {
            $candidateDefinition = Converter::definition((string) ($selected['id'] ?? ''), (string) $candidateRoot);
        }
        if ($candidateDefinition !== null) {
            $definition = $candidateDefinition;
            $resolvedRootDir = (string) $candidateRoot;
            break;
        }
    }
    if ($definition === null) {
        return [];
    }

    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    $kind = strtolower(trim((string) ($treeConfig['kind'] ?? $type)));
    $mime = strtolower(trim((string) ($mimeType ?? ($fileConfig['mimeType'] ?? ''))));
    if (!Converter::matches($definition, $mime, $kind, $extension)) {
        return [];
    }

    $package = Converter::templatePackage($definition, $resolvedRootDir);
    if (!is_array($package)) {
        return [];
    }

    $format = strtolower(trim((string) ($selected['format'] ?? ($definition['defaults']['format'] ?? 'webp'))));
    $quality = strtolower(trim((string) ($selected['quality'] ?? ($definition['defaults']['quality'] ?? 'default'))));
    $saveMode = trim((string) ($selected['saveMode'] ?? ($definition['defaults']['saveMode'] ?? 'new-hidden-work')));
    $sourceName = basename($relativePath);
    $sourceDir = trim(dirname($relativePath), '.');
    $saveAs = ($format === 'source' || $format === '')
        ? $sourceName
        : (pathinfo($sourceName, PATHINFO_FILENAME) . '.' . $format);
    $selected['name'] = (string) ($definition['name'] ?? ($selected['name'] ?? 'converter'));
    $selected['folder'] = (string) ($definition['folder'] ?? '');
    $selected['templateFolder'] = (string) ($definition['templateFolder'] ?? '');
    $selected['label'] = (string) ($definition['label'] ?? ($selected['name'] ?? 'Converter'));
    $selected['accepts'] = is_array($definition['accepts'] ?? null) ? $definition['accepts'] : [];
    $selected['outputs'] = is_array($definition['outputs'] ?? null) ? $definition['outputs'] : [];
    $selected['formats'] = is_array($definition['formats'] ?? null) ? $definition['formats'] : [];
    $selected['engine'] = (string) ($definition['engine'] ?? '');
    $selected['ui'] = is_array($definition['ui'] ?? null) ? $definition['ui'] : [];
    $selected['format'] = $format;
    $selected['quality'] = $quality;
    $selected['saveMode'] = $saveMode;
    $sourceTextContent = cmsReadConverterPreviewSourceText($relativePath, $fullPath, $mime);

    return [
        'work' => [
            'type' => $type,
            'template' => '',
            'layout' => [
                'mode' => 'converter-preview',
                'name' => (string) ($definition['id'] ?? 'converter-preview'),
                'engine' => 'lightncandy',
                'storage' => 'filesystem',
                'directory' => (string) ($package['directory'] ?? ''),
                'localDirectory' => (string) ($package['directory'] ?? ''),
                'inheritedDirectory' => (string) ($package['directory'] ?? ''),
                'sectionDirectory' => (string) ($package['directory'] ?? ''),
                'section' => 'work',
                'template' => (string) ($package['template'] ?? ''),
                'sectionTemplate' => (string) ($package['workTemplate'] ?? ''),
                'css' => (string) ($package['css'] ?? ''),
                'js' => (string) ($package['js'] ?? ''),
            ],
        ],
        'context' => [
            'converterPreview' => true,
            'converter' => $selected,
            'source' => [
                'name' => $sourceName,
                'path' => $relativePath,
                'mimeType' => $mime,
                'extension' => $extension,
                'size' => is_file($fullPath) ? filesize($fullPath) : 0,
                'srcUrl' => viewerAssetHref($relativePath),
                'content' => $sourceTextContent,
            ],
            'textContent' => $sourceTextContent,
            'target' => [
                'folder' => $sourceDir === '' ? '' : $sourceDir,
                'saveAs' => $saveAs,
                'mode' => $saveMode,
            ],
            'status' => [
                'state' => 'ready',
            ],
        ],
    ];
}

function cmsReadConverterPreviewSourceText(string $relativePath, string $fullPath, string $mime): string
{
    if (!is_file($fullPath) || !is_readable($fullPath)) {
        return '';
    }

    $size = @filesize($fullPath);
    if ($size === false || $size > 1024 * 1024) {
        return '';
    }

    $fileName = basename($relativePath);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $textExtensions = ['txt', 'md', 'csv', 'json', 'log', 'ini', 'yml', 'yaml', 'xml', 'html', 'htm', 'css', 'js', 'rtf', 'hbs', 'tpl', 'mustache', 'htaccess'];
    $textMimes = ['application/json', 'application/xml', 'application/javascript', 'application/x-javascript', 'application/rtf', 'text/rtf'];
    $isTextLike = str_starts_with($mime, 'text/')
        || in_array($mime, $textMimes, true)
        || in_array($extension, $textExtensions, true)
        || $fileName === '.htaccess';

    if (!$isTextLike) {
        return '';
    }

    $contents = @file_get_contents($fullPath);
    return $contents === false ? '' : $contents;
}
