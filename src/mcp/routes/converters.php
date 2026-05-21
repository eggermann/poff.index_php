<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Converter.php';

function mcpConvertersInput(): array
{
    $data = mcpReadRequestData();
    return [
        'path' => trim((string) ($data['path'] ?? mcpQueryString('path', '') ?? '')),
        'mimeType' => trim((string) ($data['mimeType'] ?? mcpQueryString('mimeType', '') ?? '')),
        'kind' => trim((string) ($data['kind'] ?? mcpQueryString('kind', '') ?? '')),
    ];
}

function handleConverters(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $input = is_array($opts['input'] ?? null) ? $opts['input'] : mcpConvertersInput();
    $path = trim((string) ($input['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'converters'], $access);
    }

    $mimeType = strtolower(trim((string) ($input['mimeType'] ?? '')));
    $kind = strtolower(trim((string) ($input['kind'] ?? '')));
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($mimeType === '' && $path !== '') {
        $file = mcpResolveFileInsideRoot($rootDir, $path);
        if (is_string($file)) {
            $mimeType = MediaType::detectMimeType($file, basename($file)) ?? '';
            $kind = $kind !== '' ? $kind : MediaType::classifyExtension(basename($file));
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        }
    }

    return [
        'route' => 'converters',
        'path' => $path,
        'mimeType' => $mimeType,
        'kind' => $kind,
        'webReadable' => Converter::isWebReadableMime($mimeType),
        'available' => array_values(array_map(static function (array $definition): array {
            return [
                'id' => $definition['id'] ?? '',
                'label' => $definition['label'] ?? ($definition['name'] ?? ''),
                'accepts' => $definition['accepts'] ?? [],
                'outputs' => $definition['outputs'] ?? [],
                'formats' => $definition['formats'] ?? [],
                'enabled' => (bool) ($definition['enabled'] ?? false),
                'disabledReason' => (string) ($definition['disabledReason'] ?? ''),
                'defaults' => $definition['defaults'] ?? [],
                'ui' => $definition['ui'] ?? [],
            ];
        }, Converter::availableFor($mimeType, $kind, $extension))),
    ];
}
