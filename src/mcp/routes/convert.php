<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Converter.php';

function handleConvert(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
    $sourcePath = trim((string) ($source['path'] ?? ($opts['path'] ?? '')), "/\\");
    $access = mcpEditorAccessState($rootDir, $sourcePath);
    if (!$access['allowed']) {
        return array_merge(['route' => 'convert'], $access);
    }
    if ($sourcePath === '') {
        return ['route' => 'convert', 'ok' => false, 'error' => 'Missing source path.'];
    }

    $file = mcpResolveFileInsideRoot($rootDir, $sourcePath);
    if (!is_string($file)) {
        return ['route' => 'convert', 'ok' => false, 'error' => 'Source file not found.'];
    }

    $source['name'] = $source['name'] ?? basename($file);
    $source['path'] = $sourcePath;
    $source['mimeType'] = $source['mimeType'] ?? (MediaType::detectMimeType($file, basename($file)) ?? '');
    $source['extension'] = $source['extension'] ?? strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $source['size'] = $source['size'] ?? (@filesize($file) ?: 0);
    $payload['source'] = $source;

    $result = Converter::convert($payload, $rootDir);
    $result['route'] = 'convert';
    return $result;
}

function handleSaveConvertedWork(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    $sourcePath = trim((string) ($payload['sourcePath'] ?? ($payload['source']['path'] ?? '')), "/\\");
    $access = mcpEditorAccessState($rootDir, $sourcePath);
    if (!$access['allowed']) {
        return array_merge(['route' => 'save-converted-work'], $access);
    }

    $conversion = is_array($payload['conversion'] ?? null) ? $payload['conversion'] : $payload;
    $result = Converter::saveGeneratedWork($conversion, $rootDir, $sourcePath);
    $result['route'] = 'save-converted-work';
    return $result;
}

function mcpHideMatchingThumbnailEntries(string $folderDir): array
{
    if (!class_exists('PoffConfig')) {
        return ['count' => 0, 'error' => 'Poff config unavailable.'];
    }

    $config = PoffConfig::ensure($folderDir);
    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    $mainStems = [];
    foreach ($tree as $item) {
        if (!is_array($item) || (string) ($item['type'] ?? 'file') === 'folder') {
            continue;
        }
        $stem = strtolower(pathinfo((string) ($item['name'] ?? ''), PATHINFO_FILENAME));
        if ($stem !== '' && preg_match('/^thumb(?:[._ -]+)?(.+)$/i', $stem) !== 1) {
            $mainStems[$stem] = true;
        }
    }

    $count = 0;
    foreach ($tree as &$item) {
        if (!is_array($item) || (string) ($item['type'] ?? 'file') === 'folder') {
            continue;
        }
        $stem = strtolower(pathinfo((string) ($item['name'] ?? ''), PATHINFO_FILENAME));
        if ($stem === '' || preg_match('/^thumb(?:[._ -]+)?(.+)$/i', $stem, $matches) !== 1) {
            continue;
        }
        $mainStem = strtolower(trim((string) ($matches[1] ?? ''), "._- "));
        if ($mainStem === '' || !isset($mainStems[$mainStem])) {
            continue;
        }
        $item['visible'] = false;
        $count++;
    }
    unset($item);

    if ($count === 0) {
        return ['count' => 0, 'error' => null];
    }
    $config['tree'] = $tree;
    $config['treeHash'] = hash('sha256', json_encode($tree));
    $config['updatedAt'] = date('c');
    $error = mcpWriteJsonFile(PoffConfig::configPath($folderDir), $config);
    return ['count' => $count, 'error' => $error];
}

function handleBulkConvert(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $payload = is_array($opts['payload'] ?? null) ? $opts['payload'] : mcpReadRequestData();
    $folderPath = trim((string) ($payload['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $folderPath);
    if (!$access['allowed']) {
        return array_merge(['route' => 'bulk-convert'], $access);
    }

    $folderDir = mcpResolveDirectoryInsideRoot($rootDir, $folderPath);
    if (!is_string($folderDir)) {
        return ['route' => 'bulk-convert', 'ok' => false, 'error' => 'Folder not found.'];
    }

    $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
    if (trim((string) ($converter['id'] ?? '')) === '') {
        return ['route' => 'bulk-convert', 'ok' => false, 'error' => 'Choose a converter first.'];
    }

    $format = strtolower(trim((string) ($converter['format'] ?? 'png')));
    $outputExtension = $format === 'jpeg' || $format === 'jpg' ? 'jpg' : $format;
    $requestedMimeType = strtolower(trim((string) ($payload['mimeType'] ?? '')));
    $hideThumbnails = ($payload['hideThumbnails'] ?? false) === true;
    $visibilityMode = strtolower(trim((string) ($payload['visibilityMode'] ?? 'converted-only')));
    if (!in_array($visibilityMode, ['converted-only', 'originals-only', 'both'], true)) {
        return ['route' => 'bulk-convert', 'ok' => false, 'error' => 'Invalid visibility mode.'];
    }

    $folderConfig = class_exists('PoffConfig') ? PoffConfig::ensure($folderDir) : [];
    $tree = is_array($folderConfig['tree'] ?? null) ? $folderConfig['tree'] : [];
    $generatedNames = [];
    foreach ($tree as $item) {
        if (is_array($item) && ($item['generated'] ?? false) === true) {
            $generatedNames[(string) ($item['name'] ?? '')] = true;
        }
    }

    $names = array_values(array_filter(
        scandir($folderDir) ?: [],
        static fn(string $name): bool => $name !== ''
            && $name[0] !== '.'
            && !in_array(strtolower($name), ['poff.config.json', 'poff.config.toon'], true)
            && is_file($folderDir . DIRECTORY_SEPARATOR . $name)
            && !isset($generatedNames[$name])
    ));
    natcasesort($names);
    $names = array_slice(array_values($names), 0, 100);

    $converted = [];
    $skipped = [];
    $failed = [];
    foreach ($names as $name) {
        $relativePath = trim($folderPath . '/' . $name, '/');
        $fullPath = $folderDir . DIRECTORY_SEPARATOR . $name;
        $mimeType = MediaType::detectMimeType($fullPath, $name) ?? '';
        if ($requestedMimeType !== '' && strtolower($mimeType) !== $requestedMimeType) {
            continue;
        }
        $source = [
            'name' => $name,
            'path' => $relativePath,
            'mimeType' => $mimeType,
            'extension' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            'size' => @filesize($fullPath) ?: 0,
        ];
        $itemConverter = array_merge($converter, ['format' => $format]);
        $conversion = Converter::convert([
            'source' => $source,
            'converter' => $itemConverter,
            'target' => [
                'folder' => $folderPath,
                'saveAs' => pathinfo($name, PATHINFO_FILENAME) . '.' . $outputExtension,
                'mode' => 'replace-generated-work',
                'visibilityMode' => $visibilityMode,
            ],
        ], $rootDir);
        if (($conversion['ok'] ?? false) !== true) {
            $error = (string) ($conversion['error'] ?? 'Conversion failed.');
            if (str_contains(strtolower($error), 'not accepted')) {
                $skipped[] = ['path' => $relativePath, 'reason' => $error];
            } else {
                $failed[] = ['path' => $relativePath, 'error' => $error];
            }
            continue;
        }
        $conversion['target'] = [
            'folder' => $folderPath,
            'saveAs' => pathinfo($name, PATHINFO_FILENAME) . '.' . $outputExtension,
            'mode' => 'replace-generated-work',
            'visibilityMode' => $visibilityMode,
        ];
        $saved = Converter::saveGeneratedWork($conversion, $rootDir, $relativePath);
        if (($saved['ok'] ?? false) !== true) {
            $failed[] = ['path' => $relativePath, 'error' => (string) ($saved['error'] ?? 'Saving failed.')];
            continue;
        }
        $converted[] = [
            'sourcePath' => $relativePath,
            'outputPath' => (string) ($saved['path'] ?? ''),
        ];
    }

    $thumbnailResult = $hideThumbnails
        ? mcpHideMatchingThumbnailEntries($folderDir)
        : ['count' => 0, 'error' => null];
    if (is_string($thumbnailResult['error'] ?? null) && $thumbnailResult['error'] !== '') {
        $failed[] = ['path' => $folderPath, 'error' => $thumbnailResult['error']];
    }

    return [
        'route' => 'bulk-convert',
        'ok' => $failed === [],
        'convertedCount' => count($converted),
        'skippedCount' => count($skipped),
        'failedCount' => count($failed),
        'visibilityMode' => $visibilityMode,
        'hiddenThumbnailCount' => (int) ($thumbnailResult['count'] ?? 0),
        'converted' => $converted,
        'skipped' => $skipped,
        'failed' => $failed,
    ];
}
