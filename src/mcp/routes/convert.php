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
