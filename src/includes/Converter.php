<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaType.php';

class Converter
{
    public const MAX_SOURCE_BYTES = 104857600;

    public static function definitions(string $rootDir = ''): array
    {
        return self::discover($rootDir);
    }

    public static function discover(string $rootDir): array
    {
        $root = self::normalizeRootDir($rootDir);
        $definitions = [];
        foreach (['poff/converters', 'converters', '.converters'] as $relativeDir) {
            foreach (self::discoverInDirectory($root, $relativeDir) as $id => $definition) {
                $definitions[$id] = $definition;
            }
        }

        foreach (self::discoverBundledConverterGrains($root) as $id => $definition) {
            if (!isset($definitions[$id])) {
                $definitions[$id] = $definition;
            }
        }

        foreach (self::legacyDefinitions() as $id => $definition) {
            if (!isset($definitions[$id])) {
                $definitions[$id] = $definition;
            }
        }

        return $definitions;
    }

    public static function discoverInDirectory(string $rootDir, string $relativeDir): array
    {
        $root = self::normalizeRootDir($rootDir);
        $relativeDir = trim(str_replace('\\', '/', $relativeDir), '/');
        $base = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($base)) {
            return [];
        }

        $definitions = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $folder = $relativeDir . '/' . $entry;
            $definition = self::definitionFromFolder($root, $folder);
            if ($definition !== null) {
                $definitions[(string) $definition['id']] = $definition;
            }
        }
        return $definitions;
    }

    public static function definitionFromFolder(string $rootDir, string $folder): ?array
    {
        $root = self::normalizeRootDir($rootDir);
        $relativeFolder = trim(str_replace('\\', '/', $folder), '/');
        $fullFolder = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFolder);
        if (!is_dir($fullFolder)) {
            return null;
        }

        $definitionPath = $fullFolder . DIRECTORY_SEPARATOR . 'converter.json';
        if (!is_file($definitionPath)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($definitionPath), true);
        if (!is_array($decoded) || strtolower((string) ($decoded['type'] ?? 'converter')) !== 'converter') {
            return null;
        }

        $folderName = basename($relativeFolder);
        $decoded['id'] = 'converter/' . self::normalizeConverterName($folderName);
        $decoded['name'] = self::normalizeConverterName((string) ($decoded['name'] ?? $folderName));
        $decoded['folder'] = $relativeFolder;
        $decoded['templateFolder'] = $relativeFolder . '/.layout';
        $decoded['source'] = $decoded['source'] ?? 'folder';

        return self::converterDefinition($decoded);
    }

    private static function discoverBundledConverterGrains(string $rootDir): array
    {
        $base = __DIR__ . '/worktypes/templates/converter';
        if (!is_dir($base)) {
            return [];
        }

        $definitions = [];
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'default') {
                continue;
            }
            $folder = $base . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($folder) || !is_file($folder . DIRECTORY_SEPARATOR . 'converter.json')) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($folder . DIRECTORY_SEPARATOR . 'converter.json'), true);
            if (!is_array($decoded)) {
                continue;
            }
            $decoded['id'] = 'converter/' . self::normalizeConverterName($entry);
            $decoded['name'] = self::normalizeConverterName((string) ($decoded['name'] ?? $entry));
            $decoded['folder'] = 'src/includes/worktypes/templates/converter/' . $entry;
            $decoded['templateFolder'] = 'src/includes/worktypes/templates/converter/' . $entry;
            $decoded['source'] = 'bundled-grain';
            $decoded['grain'] = true;
            $definition = self::converterDefinition($decoded);
            $definitions[(string) $definition['id']] = $definition;
        }

        return $definitions;
    }

    public static function defaultGrain(): array
    {
        return [
            'id' => 'converter/default',
            'type' => 'converter-grain',
            'name' => 'default',
            'label' => 'Default Converter',
            'folder' => 'src/includes/worktypes/templates/converter/default',
            'templateFolder' => 'src/includes/worktypes/templates/converter/default',
        ];
    }

    public static function ensureDefaultConverterFolder(string $rootDir, string $name): array
    {
        $root = self::normalizeRootDir($rootDir);
        $name = self::normalizeConverterName($name);
        if ($name === '') {
            return self::error('Missing converter name.');
        }

        $targetRelative = 'poff/converters/' . $name;
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);
        $grain = __DIR__ . '/worktypes/templates/converter/default';
        if (!is_dir($grain)) {
            return self::error('Default converter grain is missing.');
        }
        if (!is_dir($target . DIRECTORY_SEPARATOR . '.layout')) {
            mkdir($target . DIRECTORY_SEPARATOR . '.layout', 0755, true);
        }

        foreach (['template.hbs', 'work.hbs', 'external.hbs', 'style.css', 'script.js'] as $file) {
            $source = $grain . DIRECTORY_SEPARATOR . $file;
            $destination = $target . DIRECTORY_SEPARATOR . '.layout' . DIRECTORY_SEPARATOR . $file;
            if (is_file($source) && !is_file($destination)) {
                copy($source, $destination);
            }
        }

        $converterJson = $target . DIRECTORY_SEPARATOR . 'converter.json';
        if (!is_file($converterJson)) {
            $definition = self::starterDefinitionForName($name);
            self::writeJson($converterJson, $definition);
        }

        $definition = self::definitionFromFolder($root, $targetRelative);
        return [
            'ok' => $definition !== null,
            'folder' => $targetRelative,
            'definition' => $definition,
        ];
    }

    private static function starterDefinitionForName(string $name): array
    {
        $label = ucwords(str_replace(['-', '_'], ' ', preg_replace('/^convert-/', '', $name) ?: $name));
        return [
            'type' => 'converter',
            'name' => $name,
            'label' => 'Convert ' . $label,
            'description' => 'Converts incoming payloads into browser-readable poff works.',
            'accepts' => ['image/tiff', 'image/*'],
            'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
            'engine' => str_contains($name, 'remote') ? 'remote-node' : (str_contains($name, 'simple') ? 'simple-image' : 'imagemagick'),
            'defaults' => [
                'format' => 'webp',
                'quality' => 'default',
                'saveMode' => 'new-hidden-work',
                'hiddenByDefault' => true,
            ],
            'ui' => self::defaultUi(),
        ];
    }

    private static function legacyDefinitions(): array
    {
        $imagemagickEnabled = self::imagemagickAvailable();
        $imagemagickReason = $imagemagickEnabled
            ? ''
            : 'Imagick extension missing; ImageMagick CLI disabled unless POFF_ENABLE_IMAGEMAGICK_CLI=1.';

        return [
            'converter/convert-image' => self::converterDefinition([
                'name' => 'convert-image',
                'label' => 'Convert Image',
                'engine' => 'imagemagick',
                'folder' => 'poff/converters/convert-image',
                'templateFolder' => 'poff/converters/convert-image/.layout',
                'accepts' => ['image/tiff', 'image/*'],
                'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
                'formats' => ['webp', 'jpeg', 'png'],
                'enabled' => $imagemagickEnabled,
                'disabledReason' => $imagemagickReason,
                'defaults' => [
                    'quality' => 'default',
                    'format' => 'webp',
                    'resize' => null,
                    'stripMetadata' => true,
                    'background' => 'white',
                    'saveMode' => 'new-hidden-work',
                    'hiddenByDefault' => true,
                ],
                'source' => 'legacy-static',
            ]),
            'converter/convert-imagemagick' => self::converterDefinition([
                'name' => 'convert-imagemagick',
                'label' => 'Convert ImageMagick',
                'engine' => 'imagemagick',
                'folder' => 'poff/converters/convert-imagemagick',
                'templateFolder' => 'poff/converters/convert-imagemagick/.layout',
                'accepts' => ['image/tiff', 'image/*'],
                'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
                'formats' => ['webp', 'jpeg', 'png'],
                'enabled' => $imagemagickEnabled,
                'disabledReason' => $imagemagickReason,
                'defaults' => [
                    'quality' => 'default',
                    'format' => 'webp',
                    'resize' => null,
                    'stripMetadata' => true,
                    'background' => 'white',
                    'saveMode' => 'new-hidden-work',
                    'hiddenByDefault' => true,
                ],
                'source' => 'legacy-static',
            ]),
            'converter/convert-simple-image' => self::converterDefinition([
                'name' => 'convert-simple-image',
                'label' => 'Convert Simple Image',
                'engine' => 'simple-image',
                'folder' => 'poff/converters/convert-simple-image',
                'templateFolder' => 'poff/converters/convert-simple-image/.layout',
                'accepts' => ['image/*'],
                'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
                'formats' => ['webp', 'jpeg', 'png'],
                'enabled' => $imagemagickEnabled,
                'disabledReason' => $imagemagickReason,
                'defaults' => [
                    'quality' => 'default',
                    'format' => 'webp',
                    'saveMode' => 'new-hidden-work',
                    'hiddenByDefault' => true,
                ],
                'source' => 'legacy-static',
            ]),
            'converter/convert-remote-node' => self::converterDefinition([
                'name' => 'convert-remote-node',
                'label' => 'Convert Remote Node',
                'engine' => 'remote-node',
                'folder' => 'poff/converters/convert-remote-node',
                'templateFolder' => 'poff/converters/convert-remote-node/.layout',
                'accepts' => ['image/tiff', 'image/*'],
                'outputs' => ['image/webp', 'image/jpeg', 'image/png'],
                'formats' => ['webp', 'jpeg', 'png'],
                'enabled' => true,
                'defaults' => [
                    'quality' => 'default',
                    'format' => 'webp',
                    'saveMode' => 'new-hidden-work',
                    'hiddenByDefault' => true,
                ],
                'source' => 'legacy-static',
            ]),
            'converter/convert-text' => self::converterDefinition([
                'name' => 'convert-text',
                'label' => 'Convert Text',
                'engine' => 'text-copy',
                'folder' => 'poff/converters/convert-text',
                'templateFolder' => 'poff/converters/convert-text/.layout',
                'accepts' => ['text/plain', 'text/*'],
                'outputs' => ['text/plain'],
                'formats' => ['txt'],
                'enabled' => true,
                'defaults' => [
                    'quality' => 'default',
                    'format' => 'txt',
                    'saveMode' => 'new-hidden-work',
                    'hiddenByDefault' => true,
                ],
                'ui' => [
                    'format' => [
                        'type' => 'select',
                        'label' => 'Output format',
                        'options' => ['txt'],
                    ],
                    'quality' => [
                        'type' => 'select',
                        'label' => 'Quality',
                        'options' => ['preview', 'default', 'archival', 'small-web'],
                    ],
                ],
                'source' => 'legacy-static',
            ]),
        ];
    }

    private static function converterDefinition(array $definition): array
    {
        $name = self::normalizeConverterName((string) ($definition['name'] ?? 'converter'));
        $id = self::normalizeConverterId((string) ($definition['id'] ?? ('converter/' . $name)));
        $label = trim((string) ($definition['label'] ?? ucfirst($name)));
        $outputs = array_values(array_map('strtolower', (array) ($definition['outputs'] ?? [])));
        $formats = array_values(array_map('strtolower', (array) ($definition['formats'] ?? [])));
        if ($formats === []) {
            $formats = self::formatsFromOutputs($outputs);
        }
        $engine = (string) ($definition['engine'] ?? $name);
        $enabled = (bool) ($definition['enabled'] ?? true);
        $disabledReason = (string) ($definition['disabledReason'] ?? '');
        if (in_array($engine, ['imagemagick', 'simple-image'], true) && !self::imagemagickAvailable()) {
            $enabled = false;
            $disabledReason = $disabledReason !== '' ? $disabledReason : 'Imagick extension missing; ImageMagick CLI disabled unless POFF_ENABLE_IMAGEMAGICK_CLI=1.';
        }

        return [
            'id' => $id,
            'type' => 'converter',
            'name' => $name,
            'label' => $label,
            'accepts' => array_values(array_map('strtolower', (array) ($definition['accepts'] ?? []))),
            'outputs' => $outputs,
            'formats' => $formats,
            'engine' => $engine,
            'folder' => (string) ($definition['folder'] ?? ''),
            'templateFolder' => (string) ($definition['templateFolder'] ?? (($definition['folder'] ?? '') !== '' ? (string) $definition['folder'] . '/.layout' : '.layout/converters/' . $name)),
            'enabled' => $enabled,
            'disabledReason' => $disabledReason,
            'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            'ui' => is_array($definition['ui'] ?? null) ? $definition['ui'] : self::defaultUi(),
            'remote' => is_array($definition['remote'] ?? null) ? $definition['remote'] : [],
            'source' => (string) ($definition['source'] ?? ''),
            'grain' => (bool) ($definition['grain'] ?? false),
        ];
    }

    private static function defaultUi(): array
    {
        return [
            'quality' => [
                'type' => 'select',
                'label' => 'Quality',
                'options' => ['preview', 'default', 'archival', 'small-web'],
            ],
            'format' => [
                'type' => 'select',
                'label' => 'Output format',
                'options' => ['webp', 'jpeg', 'png'],
            ],
            'saveMode' => [
                'type' => 'select',
                'label' => 'Save mode',
                'options' => ['new-hidden-work', 'replace-generated-work', 'temporary-preview-only'],
            ],
        ];
    }

    public static function definition(string $id, string $rootDir = ''): ?array
    {
        $selected = self::normalizeSelectedConverter(['id' => $id]);
        $definitions = self::definitions($rootDir);
        $normalizedId = self::normalizeConverterId((string) ($selected['id'] ?? $id));
        if ($normalizedId !== '' && isset($definitions[$normalizedId])) {
            return $definitions[$normalizedId];
        }
        $normalizedName = self::normalizeConverterName($id);
        foreach ($definitions as $definition) {
            if ((string) ($definition['name'] ?? '') === $normalizedName) {
                return $definition;
            }
        }
        return null;
    }

    public static function defaultOptions(string $id, string $rootDir = ''): array
    {
        $definition = self::definition($id, $rootDir);
        return is_array($definition) && is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
    }

    public static function availableFor(string $mimeType, string $kind, string $extension, string $rootDir = ''): array
    {
        $mime = strtolower(trim($mimeType));
        $kind = strtolower(trim($kind));
        $extension = strtolower(trim($extension, '. '));
        if ($mime === '' && $extension !== '') {
            $mime = self::mimeFromExtension($extension);
        }

        $available = [];
        foreach (self::definitions($rootDir) as $definition) {
            if (!self::matches($definition, $mime, $kind, $extension)) {
                continue;
            }
            $available[] = $definition;
        }

        return $available;
    }

    public static function matches(array $definition, string $mimeType, string $kind, string $extension): bool
    {
        $mime = strtolower(trim($mimeType));
        $kind = strtolower(trim($kind));
        $extension = strtolower(trim($extension, '. '));
        $accepts = array_values(array_map('strtolower', (array) ($definition['accepts'] ?? [])));
        if ($mime !== '' && self::mimeAccepted($mime, $accepts)) {
            return true;
        }
        if ($kind !== '') {
            foreach ($accepts as $pattern) {
                if ($pattern === $kind || str_starts_with($pattern, $kind . '/')) {
                    return true;
                }
            }
        }
        if ($extension !== '' && in_array($extension, $accepts, true)) {
            return true;
        }
        return false;
    }

    public static function normalizeSelectedConverter(array $converter): array
    {
        $id = strtolower(trim((string) ($converter['id'] ?? $converter['name'] ?? '')));
        $format = strtolower(trim((string) ($converter['format'] ?? '')));
        $map = [
            'imagemagick-image-webp' => ['converter/convert-imagemagick', 'webp'],
            'imagemagick-image-jpeg' => ['converter/convert-imagemagick', 'jpeg'],
            'imagemagick-image-png' => ['converter/convert-imagemagick', 'png'],
            'converter/imagemagick-image-webp' => ['converter/convert-imagemagick', 'webp'],
            'converter/imagemagick-image-jpeg' => ['converter/convert-imagemagick', 'jpeg'],
            'converter/imagemagick-image-png' => ['converter/convert-imagemagick', 'png'],
            'remote-node-converter' => ['converter/convert-remote-node', ''],
            'converter/remote-node-converter' => ['converter/convert-remote-node', ''],
            'converter/imagemagick' => ['converter/convert-imagemagick', ''],
            'imagemagick' => ['converter/convert-imagemagick', ''],
            'converter/simple-image' => ['converter/convert-simple-image', ''],
            'simple-image' => ['converter/convert-simple-image', ''],
            'converter/remote-node' => ['converter/convert-remote-node', ''],
            'remote-node' => ['converter/convert-remote-node', ''],
            'convert-text' => ['converter/convert-text', 'txt'],
            'converter/convert-text' => ['converter/convert-text', 'txt'],
            'text-copy' => ['converter/convert-text', 'txt'],
            'converter/text-copy' => ['converter/convert-text', 'txt'],
        ];

        if (isset($map[$id])) {
            [$mappedId, $mappedFormat] = $map[$id];
            $converter['id'] = $mappedId;
            if ($format === '' && $mappedFormat !== '') {
                $converter['format'] = $mappedFormat;
            }
        } else {
            $converter['id'] = self::normalizeConverterId($id);
        }

        $converter['type'] = 'converter';
        $converter['name'] = self::normalizeConverterName((string) ($converter['name'] ?? $converter['id'] ?? ''));
        if ($converter['name'] === '' || isset($map[$id])) {
            $converter['name'] = self::normalizeConverterName((string) ($converter['id'] ?? ''));
        }
        if (!isset($converter['saveMode'])) {
            $converter['saveMode'] = 'new-hidden-work';
        }
        if (!array_key_exists('hiddenByDefault', $converter)) {
            $converter['hiddenByDefault'] = true;
        }
        if (!isset($converter['quality'])) {
            $converter['quality'] = 'default';
        }
        if (!isset($converter['format'])) {
            $converter['format'] = 'webp';
        }
        return $converter;
    }

    public static function isWebReadableMime(string $mimeType): bool
    {
        $mime = strtolower(trim($mimeType));
        return in_array($mime, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'video/mp4',
            'video/webm',
            'audio/mpeg',
            'audio/mp4',
            'audio/ogg',
            'audio/wav',
            'application/pdf',
            'text/plain',
            'text/html',
            'text/css',
            'application/javascript',
            'application/json',
        ], true) || str_starts_with($mime, 'text/');
    }

    public static function buildPayload(array $source, array $converterOptions): array
    {
        $converterOptions = self::normalizeSelectedConverter($converterOptions);
        $sourceName = basename((string) ($source['name'] ?? $source['path'] ?? 'source'));
        $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? $sourceName), '/'));
        $folder = trim(dirname($sourcePath), '.');
        $folder = $folder === DIRECTORY_SEPARATOR ? '' : str_replace('\\', '/', trim($folder, '/'));
        $definition = self::definition((string) ($converterOptions['id'] ?? $converterOptions['name'] ?? ''));
        $id = trim((string) ($definition['id'] ?? $converterOptions['id'] ?? ''));
        $name = trim((string) ($definition['name'] ?? $converterOptions['name'] ?? 'converter'));
        $format = self::normalizeFormat((string) ($converterOptions['format'] ?? self::defaultOptions($id)['format'] ?? 'webp'));
        $baseName = pathinfo($sourceName, PATHINFO_FILENAME);

        return [
            'source' => [
                'name' => $sourceName,
                'path' => $sourcePath,
                'mimeType' => strtolower(trim((string) ($source['mimeType'] ?? ''))),
                'extension' => strtolower(pathinfo($sourceName, PATHINFO_EXTENSION)),
                'size' => (int) ($source['size'] ?? 0),
                'srcUrl' => (string) ($source['srcUrl'] ?? ''),
            ],
            'converter' => [
                'type' => 'converter',
                'name' => $name,
                'id' => $id,
                'folder' => (string) ($definition['folder'] ?? $converterOptions['folder'] ?? ''),
                'quality' => self::normalizeQuality((string) ($converterOptions['quality'] ?? 'default')),
                'format' => $format,
                'node' => $converterOptions['node'] ?? 'local',
            ],
            'target' => [
                'folder' => $folder,
                'saveAs' => self::sanitizeOutputName($baseName . '.' . $format),
                'mode' => self::normalizeSaveMode((string) ($converterOptions['saveMode'] ?? 'new-hidden-work')),
            ],
            'requestingNode' => [
                'id' => (string) ($converterOptions['requestingNode']['id'] ?? 'local'),
                'baseUrl' => (string) ($converterOptions['requestingNode']['baseUrl'] ?? ''),
            ],
        ];
    }

    public static function convert(array $payload, string $rootDir): array
    {
        $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
        $converter = self::normalizeSelectedConverter($converter);
        $payload['converter'] = $converter;
        $definition = self::definition((string) ($converter['id'] ?? $converter['name'] ?? ''), $rootDir);
        if ($definition === null) {
            return self::error('Unknown converter.');
        }

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $mime = strtolower(trim((string) ($source['mimeType'] ?? '')));
        if (!self::mimeAccepted($mime, $definition['accepts'] ?? [])) {
            return self::error('Source MIME type is not accepted.');
        }
        if ((int) ($source['size'] ?? 0) > self::MAX_SOURCE_BYTES) {
            return self::error('Source file is too large.');
        }

        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'webp')));
        $outputMime = self::mimeFromFormat($format);
        if (!self::mimeAccepted($outputMime, $definition['outputs'] ?? [])) {
            return self::error('Output MIME type is not allowed.');
        }

        if (($definition['engine'] ?? '') === 'remote-node') {
            return self::convertRemote($payload, $definition, $rootDir);
        }

        if (($definition['engine'] ?? '') === 'text-copy') {
            return self::convertText($payload, $definition, $rootDir);
        }

        return self::convertLocalImage($payload, $definition, $rootDir);
    }

    public static function saveGeneratedWork(array $conversion, string $rootDir, string $sourcePath = ''): array
    {
        if (($conversion['ok'] ?? false) !== true || !is_array($conversion['output'] ?? null)) {
            return self::error('Conversion response is not successful.');
        }

        $source = is_array($conversion['source'] ?? null) ? $conversion['source'] : [];
        $sourcePath = str_replace('\\', '/', trim($sourcePath !== '' ? $sourcePath : (string) ($source['path'] ?? ''), '/'));
        if ($sourcePath === '') {
            return self::error('Missing source path.');
        }

        $root = realpath($rootDir);
        if (!is_string($root) || $root === '') {
            return self::error('Invalid root directory.');
        }
        $sourceFullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath));
        if (!is_string($sourceFullPath) || !is_file($sourceFullPath) || !str_starts_with($sourceFullPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return self::error('Source file not found.');
        }

        $output = $conversion['output'];
        $outputName = self::sanitizeOutputName((string) ($output['name'] ?? 'converted.webp'));
        $outputMime = strtolower(trim((string) ($output['mimeType'] ?? '')));
        if (!self::mimeAccepted($outputMime, ['image/webp', 'image/jpeg', 'image/png', 'text/plain', 'text/markdown', 'text/html'])) {
            return self::error('Converted MIME type is not allowed.');
        }

        $body = '';
        if (isset($output['bodyBase64']) && is_string($output['bodyBase64']) && $output['bodyBase64'] !== '') {
            $decoded = base64_decode($output['bodyBase64'], true);
            if ($decoded === false) {
                return self::error('Converted body is invalid base64.');
            }
            $body = $decoded;
        } elseif (isset($output['downloadUrl']) && is_string($output['downloadUrl'])) {
            $download = self::httpGet($output['downloadUrl']);
            if (($download['ok'] ?? false) !== true) {
                return self::error('Converted download failed.');
            }
            $body = (string) ($download['body'] ?? '');
        } else {
            return self::error('Converted response has no body.');
        }

        $folderDir = dirname($sourceFullPath);
        $targetPath = $folderDir . DIRECTORY_SEPARATOR . $outputName;
        if (file_put_contents($targetPath, $body) === false) {
            return self::error('Failed to write converted work.');
        }

        $generatedBy = is_array($conversion['generatedBy'] ?? null) ? $conversion['generatedBy'] : [];
        $entry = [
            'name' => $outputName,
            'title' => pathinfo($outputName, PATHINFO_FILENAME),
            'type' => 'file',
            'kind' => (string) ($output['kind'] ?? 'image'),
            'mimeType' => $outputMime,
            'visible' => false,
            'generated' => true,
            'sourceWork' => basename($sourcePath),
            'generatedBy' => $generatedBy,
            'external' => [
                'type' => 'node-work',
                'relation' => 'converted-from',
                'visible' => false,
                'sourceWork' => [
                    'path' => $sourcePath,
                    'mimeType' => (string) ($source['mimeType'] ?? ''),
                ],
                'resultWork' => [
                    'name' => $outputName,
                    'mimeType' => $outputMime,
                    'kind' => (string) ($output['kind'] ?? 'image'),
                    'srcUrl' => $outputName,
                ],
                'generatedBy' => $generatedBy,
            ],
        ];

        self::upsertFolderTreeEntry($folderDir, $entry);
        self::writeGeneratedFileConfig($folderDir, $outputName, $entry, $targetPath);

        return [
            'ok' => true,
            'type' => 'saved-generated-work',
            'path' => trim(dirname($sourcePath), '.') === '' ? $outputName : trim(dirname($sourcePath), '/\\') . '/' . $outputName,
            'entry' => $entry,
        ];
    }

    private static function convertLocalImage(array $payload, array $definition, string $rootDir): array
    {
        if (($definition['enabled'] ?? false) !== true) {
            return self::error((string) ($definition['disabledReason'] ?? 'Converter disabled.'));
        }

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
        $root = realpath($rootDir);
        if (!is_string($root) || $root === '' || $sourcePath === '') {
            return self::error('Invalid source.');
        }
        $fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath));
        if (!is_string($fullPath) || !is_file($fullPath) || !str_starts_with($fullPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return self::error('Source file not found.');
        }

        $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'webp')));
        $preset = self::qualityPreset((string) ($converter['quality'] ?? 'default'));
        $outputName = self::sanitizeOutputName((string) ($target['saveAs'] ?? (pathinfo($fullPath, PATHINFO_FILENAME) . '.' . $format)));
        $outputMime = self::mimeFromFormat($format);

        $body = self::convertWithImagick($fullPath, $format, $preset);
        if ($body === null && self::cliEnabled()) {
            $body = self::convertWithCli($fullPath, $format, $preset);
        }
        if ($body === null) {
            return self::error('Local conversion unavailable or failed.');
        }

        $size = strlen($body);
        return [
            'ok' => true,
            'type' => 'converted-work',
            'source' => [
                'path' => $sourcePath,
                'mimeType' => (string) ($source['mimeType'] ?? ''),
            ],
            'output' => [
                'name' => $outputName,
                'mimeType' => $outputMime,
                'kind' => 'image',
                'size' => $size,
                'bodyBase64' => base64_encode($body),
            ],
            'generatedBy' => [
                'type' => 'converter',
                'name' => (string) ($definition['name'] ?? ''),
                'id' => (string) ($definition['id'] ?? ''),
                'folder' => (string) ($definition['folder'] ?? ''),
                'node' => 'local',
                'engine' => (string) ($definition['engine'] ?? 'imagemagick'),
                'quality' => self::normalizeQuality((string) ($converter['quality'] ?? 'default')),
                'format' => $format,
                'convertedAt' => date('c'),
            ],
        ];
    }

    private static function convertText(array $payload, array $definition, string $rootDir): array
    {
        if (($definition['enabled'] ?? false) !== true) {
            return self::error((string) ($definition['disabledReason'] ?? 'Converter disabled.'));
        }

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
        $root = realpath($rootDir);
        if (!is_string($root) || $root === '' || $sourcePath === '') {
            return self::error('Invalid source.');
        }
        $fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath));
        if (!is_string($fullPath) || !is_file($fullPath) || !str_starts_with($fullPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return self::error('Source file not found.');
        }

        $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'txt')));
        $outputMime = self::mimeFromFormat($format);
        $outputName = self::sanitizeOutputName((string) ($target['saveAs'] ?? (pathinfo($fullPath, PATHINFO_FILENAME) . '.' . $format)));
        $body = (string) file_get_contents($fullPath);
        return [
            'ok' => true,
            'type' => 'converted-work',
            'source' => [
                'path' => $sourcePath,
                'mimeType' => (string) ($source['mimeType'] ?? ''),
            ],
            'output' => [
                'name' => $outputName,
                'mimeType' => $outputMime,
                'kind' => 'text',
                'size' => strlen($body),
                'bodyBase64' => base64_encode($body),
            ],
            'generatedBy' => [
                'type' => 'converter',
                'name' => (string) ($definition['name'] ?? ''),
                'id' => (string) ($definition['id'] ?? ''),
                'folder' => (string) ($definition['folder'] ?? ''),
                'node' => 'local',
                'engine' => (string) ($definition['engine'] ?? 'text-copy'),
                'quality' => self::normalizeQuality((string) ($converter['quality'] ?? 'default')),
                'format' => $format,
                'convertedAt' => date('c'),
            ],
        ];
    }

    private static function convertRemote(array $payload, array $definition, string $rootDir): array
    {
        $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
        $node = is_array($converter['node'] ?? null) ? $converter['node'] : [];
        $remote = is_array($definition['remote'] ?? null) ? $definition['remote'] : [];
        $endpoint = trim((string) ($node['endpoint'] ?? $remote['endpoint'] ?? ''));
        $validation = self::validateRemoteEndpoint($endpoint);
        if ($validation !== null) {
            return self::error($validation);
        }

        $response = self::httpPost($endpoint, $payload);
        if (($response['ok'] ?? false) !== true) {
            return self::error('Remote converter request failed.', ['status' => $response['status'] ?? 0]);
        }
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            return self::error('Remote converter returned invalid response.');
        }
        $outputMime = strtolower(trim((string) ($decoded['output']['mimeType'] ?? '')));
        if (!self::mimeAccepted($outputMime, $definition['outputs'] ?? [])) {
            return self::error('Remote output MIME type is not allowed.');
        }

        $decoded['generatedBy'] = array_merge(
            is_array($decoded['generatedBy'] ?? null) ? $decoded['generatedBy'] : [],
            [
                'type' => 'converter',
                'name' => (string) ($definition['name'] ?? 'convert-remote-node'),
                'id' => (string) ($definition['id'] ?? 'converter/convert-remote-node'),
                'folder' => (string) ($definition['folder'] ?? ''),
                'node' => (string) ($node['id'] ?? $remote['nodeId'] ?? 'remote'),
                'engine' => (string) ($definition['engine'] ?? 'remote-node'),
                'endpoint' => $endpoint,
                'quality' => self::normalizeQuality((string) ($converter['quality'] ?? 'default')),
                'format' => self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'webp'))),
                'convertedAt' => date('c'),
            ]
        );

        return $decoded;
    }

    public static function validateRemoteEndpoint(string $url, bool $allowPrivate = false): ?string
    {
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'Only http and https converter endpoints are allowed.';
        }
        if ($host === '') {
            return 'Converter endpoint host is missing.';
        }
        $devAllowsPrivate = $allowPrivate || getenv('POFF_ALLOW_PRIVATE_CONVERTER_HOSTS') === '1';
        if (!$devAllowsPrivate && self::hostIsPrivate($host)) {
            return 'Converter endpoint host is not allowed.';
        }
        return null;
    }

    private static function matchesDefinition(array $definition, string $mime, string $kind, string $extension): bool
    {
        return self::matches($definition, $mime, $kind, $extension);
    }

    private static function mimeAccepted(string $mime, array $patterns): bool
    {
        $mime = strtolower(trim($mime));
        if ($mime === '') {
            return false;
        }
        foreach ($patterns as $pattern) {
            $pattern = strtolower(trim((string) $pattern));
            if ($pattern === $mime) {
                return true;
            }
            if (str_ends_with($pattern, '/*')) {
                $prefix = substr($pattern, 0, -2);
                if ($prefix !== '' && str_starts_with($mime, $prefix . '/')) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function mimeFromExtension(string $extension): string
    {
        return match (strtolower(trim($extension, '.'))) {
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain',
            default => '',
        };
    }

    private static function mimeFromFormat(string $format): string
    {
        return match (self::normalizeFormat($format)) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'txt' => 'text/plain',
            default => 'image/webp',
        };
    }

    private static function formatsFromOutputs(array $outputs): array
    {
        $formats = [];
        foreach ($outputs as $mime) {
            $format = match (strtolower(trim((string) $mime))) {
                'image/jpeg' => 'jpeg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'text/plain' => 'txt',
                default => '',
            };
            if ($format !== '' && !in_array($format, $formats, true)) {
                $formats[] = $format;
            }
        }
        return $formats;
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        return in_array($format, ['webp', 'jpeg', 'png', 'txt'], true) ? $format : 'webp';
    }

    private static function normalizeQuality(string $quality): string
    {
        $quality = strtolower(trim($quality));
        return in_array($quality, ['preview', 'default', 'archival', 'small-web'], true) ? $quality : 'default';
    }

    private static function normalizeSaveMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['new-hidden-work', 'replace-generated-work', 'temporary-preview-only'], true) ? $mode : 'new-hidden-work';
    }

    private static function qualityPreset(string $quality): array
    {
        return match (self::normalizeQuality($quality)) {
            'preview' => ['quality' => 60, 'maxWidth' => 1600],
            'archival' => ['quality' => 95, 'maxWidth' => null],
            'small-web' => ['quality' => 72, 'maxWidth' => 1200],
            default => ['quality' => 82, 'maxWidth' => null],
        };
    }

    private static function imagemagickAvailable(): bool
    {
        return class_exists('Imagick') || self::cliEnabled();
    }

    private static function normalizeConverterId(string $id): string
    {
        $value = strtolower(trim($id));
        if ($value === '') {
            return '';
        }
        if (!str_starts_with($value, 'converter/')) {
            $value = 'converter/' . ltrim($value, '/');
        }
        return preg_replace('/[^a-z0-9\/._-]+/i', '-', $value) ?: '';
    }

    private static function normalizeConverterName(string $name): string
    {
        $value = strtolower(trim($name));
        $value = preg_replace('/^converter\//', '', $value) ?? $value;
        return preg_replace('/[^a-z0-9._-]+/i', '-', $value) ?: '';
    }

    private static function normalizeRootDir(string $rootDir): string
    {
        $candidate = trim($rootDir);
        if ($candidate === '' && function_exists('cmsProjectRootDir')) {
            $candidate = cmsProjectRootDir();
        }
        if ($candidate === '') {
            $candidate = getcwd() ?: '.';
        }
        $resolved = realpath($candidate);
        return is_string($resolved) && $resolved !== '' ? $resolved : $candidate;
    }

    private static function cliEnabled(): bool
    {
        return getenv('POFF_ENABLE_IMAGEMAGICK_CLI') === '1' && (self::findCliBinary() !== null);
    }

    private static function findCliBinary(): ?string
    {
        foreach (['magick', 'convert'] as $binary) {
            $path = trim((string) @shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));
            if ($path !== '') {
                return $path;
            }
        }
        return null;
    }

    private static function convertWithImagick(string $path, string $format, array $preset): ?string
    {
        if (!class_exists('Imagick')) {
            return null;
        }
        try {
            $image = new Imagick($path);
            if ($image->getNumberImages() > 1) {
                $image->setIteratorIndex(0);
            }
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            if (!empty($preset['maxWidth'])) {
                $width = (int) $image->getImageWidth();
                if ($width > (int) $preset['maxWidth']) {
                    $image->thumbnailImage((int) $preset['maxWidth'], 0);
                }
            }
            $image->setImageFormat($format === 'jpeg' ? 'jpeg' : $format);
            $image->setImageCompressionQuality((int) $preset['quality']);
            $image->stripImage();
            $blob = $image->getImagesBlob();
            $image->clear();
            return is_string($blob) && $blob !== '' ? $blob : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function convertWithCli(string $path, string $format, array $preset): ?string
    {
        $binary = self::findCliBinary();
        if ($binary === null) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'poff-convert-');
        if (!is_string($tmp)) {
            return null;
        }
        $output = $tmp . '.' . $format;
        @unlink($tmp);

        $args = [escapeshellarg($binary), escapeshellarg($path . '[0]'), '-auto-orient'];
        if (!empty($preset['maxWidth'])) {
            $args[] = '-resize';
            $args[] = escapeshellarg((string) ((int) $preset['maxWidth']) . 'x>');
        }
        $args[] = '-strip';
        $args[] = '-quality';
        $args[] = escapeshellarg((string) ((int) $preset['quality']));
        $args[] = escapeshellarg($output);
        $cmd = implode(' ', $args) . ' 2>/dev/null';
        @exec($cmd, $unused, $code);
        $body = $code === 0 && is_file($output) ? (string) file_get_contents($output) : '';
        @unlink($output);
        return $body !== '' ? $body : null;
    }

    private static function sanitizeOutputName(string $name): string
    {
        $name = basename(str_replace('\\', '/', trim($name)));
        $extension = self::normalizeFormat(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-z0-9._-]+/i', '-', $base) ?: 'converted';
        return trim($base, '.-') . '.' . $extension;
    }

    private static function upsertFolderTreeEntry(string $folderDir, array $entry): void
    {
        if (!class_exists('PoffConfig')) {
            return;
        }
        $config = PoffConfig::ensure($folderDir);
        $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
        $found = false;
        foreach ($tree as &$item) {
            if (is_array($item) && (string) ($item['name'] ?? '') === (string) $entry['name']) {
                $item = array_merge($item, $entry);
                $found = true;
                break;
            }
        }
        unset($item);
        if (!$found) {
            $tree[] = $entry;
        }
        $config['tree'] = $tree;
        $config['treeHash'] = hash('sha256', json_encode($tree));
        $config['updatedAt'] = date('c');
        self::writeJson(PoffConfig::configPath($folderDir), $config);
    }

    private static function writeGeneratedFileConfig(string $folderDir, string $fileName, array $entry, string $targetPath): void
    {
        if (!class_exists('PoffConfig')) {
            return;
        }
        $config = array_merge($entry, [
            '$schema' => 'https://dominikeggermann.com/poff-config.schema.json',
            'path' => $fileName,
            'size' => @filesize($targetPath) ?: null,
            'modifiedAt' => date('c', (int) (@filemtime($targetPath) ?: time())),
            'updatedAt' => date('c'),
            'id' => bin2hex(random_bytes(8)),
            'work' => [
                'type' => $entry['kind'] ?? 'image',
                'kind' => $entry['kind'] ?? 'image',
                'mimeType' => $entry['mimeType'] ?? '',
                'template' => 'external',
                'layout' => method_exists('Worktype', 'normalizeLayout') ? Worktype::normalizeLayout(null, 'work') : [],
            ],
        ]);
        $dir = dirname(PoffConfig::fileConfigPath($folderDir, $fileName));
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        self::writeJson(PoffConfig::fileConfigPath($folderDir, $fileName), $config);
    }

    private static function writeJson(string $path, array $data): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private static function hostIsPrivate(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }
        $ips = gethostbynamel($host);
        if (!is_array($ips) || $ips === []) {
            $ips = [$host];
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return true;
                }
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                $normalized = strtolower($ip);
                if ($normalized === '::1' || str_starts_with($normalized, 'fe80:') || str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd')) {
                    return true;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function httpPost(string $url, array $payload): array
    {
        $override = $GLOBALS['__poff_converter_http_post'] ?? null;
        if (is_callable($override)) {
            $response = $override($url, ['Accept: application/json'], $payload);
            return is_array($response) ? $response : ['ok' => false, 'status' => 0, 'body' => ''];
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json",
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = self::statusFromHeaders($http_response_header ?? []);
        return [
            'ok' => $body !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body !== false ? $body : '',
        ];
    }

    private static function httpGet(string $url): array
    {
        $validation = self::validateRemoteEndpoint($url);
        if ($validation !== null) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $validation];
        }
        $body = @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]));
        $status = self::statusFromHeaders($http_response_header ?? []);
        return [
            'ok' => $body !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $body !== false ? $body : '',
        ];
    }

    private static function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', (string) $line, $matches)) {
                return (int) $matches[1];
            }
        }
        return 0;
    }

    private static function error(string $message, array $extra = []): array
    {
        return array_merge(['ok' => false, 'error' => $message], $extra);
    }
}
