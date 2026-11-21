<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaType.php';

class Converter
{
    public const MAX_SOURCE_BYTES = 104857600;
    public const MAX_REMOTE_REQUEST_BYTES = 150000000;
    private const MAX_REMOTE_OUTPUT_BYTES = self::MAX_SOURCE_BYTES;
    private const MAX_REMOTE_JSON_BYTES = 150000000;

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
        $root = self::normalizeRootDir($rootDir);
        $relativeBase = self::relativePathFromRoot($root, $base);
        if ($relativeBase === '') {
            $relativeBase = 'worktypes/templates/converter';
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
            $decoded['folder'] = $relativeBase . '/' . $entry;
            $decoded['templateFolder'] = $relativeBase . '/' . $entry;
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
        $grainFiles = self::defaultGrainFiles();
        if ($grainFiles === null) {
            return self::error('Default converter grain is missing.');
        }
        if (!is_dir($target . DIRECTORY_SEPARATOR . '.layout')) {
            mkdir($target . DIRECTORY_SEPARATOR . '.layout', 0755, true);
        }

        foreach (['template.hbs', 'work.hbs', 'external.hbs', 'style.css', 'script.js'] as $file) {
            $destination = $target . DIRECTORY_SEPARATOR . '.layout' . DIRECTORY_SEPARATOR . $file;
            if (!is_file($destination) && array_key_exists($file, $grainFiles)) {
                file_put_contents($destination, (string) $grainFiles[$file]);
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

    public static function ensureVisibleConverterFolder(string $rootDir, string $name): array
    {
        $root = self::normalizeRootDir($rootDir);
        $name = self::normalizeConverterName($name);
        if ($name === '') {
            return self::error('Missing converter name.');
        }

        $targetRelative = 'converters/' . $name;
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetRelative);
        $grainFiles = self::defaultGrainFiles();
        if ($grainFiles === null) {
            return self::error('Default converter grain is missing.');
        }
        if (!is_dir($target . DIRECTORY_SEPARATOR . '.layout')) {
            mkdir($target . DIRECTORY_SEPARATOR . '.layout', 0755, true);
        }

        foreach (['template.hbs', 'work.hbs', 'external.hbs', 'style.css', 'script.js'] as $file) {
            $destination = $target . DIRECTORY_SEPARATOR . '.layout' . DIRECTORY_SEPARATOR . $file;
            if (!is_file($destination) && array_key_exists($file, $grainFiles)) {
                file_put_contents($destination, (string) $grainFiles[$file]);
            }
        }

        $converterJson = $target . DIRECTORY_SEPARATOR . 'converter.json';
        if (!is_file($converterJson)) {
            self::writeJson($converterJson, self::starterDefinitionForName($name));
        }

        $folderConfigPath = $target . DIRECTORY_SEPARATOR . 'poff.config.json';
        if (!is_file($folderConfigPath)) {
            self::writeJson($folderConfigPath, self::starterFolderConfigForName($name));
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

    private static function starterFolderConfigForName(string $name): array
    {
        $label = ucwords(str_replace(['-', '_'], ' ', preg_replace('/^convert-/', '', $name) ?: $name));
        return [
            'title' => 'Convert ' . $label,
            'folderName' => $name,
            'description' => 'Converter app folder. Open this work to configure and run conversions.',
            'work' => [
                'type' => 'converter',
                'template' => 'converter',
            ],
        ];
    }

    private static function defaultGrainFiles(): ?array
    {
        $grainDir = __DIR__ . '/worktypes/templates/converter/default';
        if (!is_dir($grainDir)) {
            return null;
        }

        $files = [];
        foreach (['template.hbs', 'work.hbs', 'external.hbs', 'style.css', 'script.js'] as $file) {
            $path = $grainDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($path)) {
                return null;
            }
            $files[$file] = (string) file_get_contents($path);
        }

        return $files;
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
        $folder = (string) ($definition['folder'] ?? '');
        $templateFolder = (string) ($definition['templateFolder'] ?? ($folder !== '' ? $folder . '/.layout' : '.layout/converters/' . $name));
        $path = self::definitionPath($folder);
        $viewerHref = $path !== '' ? '?view=1&path=' . rawurlencode($path) : '';
        $remote = is_array($definition['remote'] ?? null) ? $definition['remote'] : [];
        $url = trim((string) ($definition['url'] ?? $remote['url'] ?? $remote['publicUrl'] ?? $viewerHref));

        return [
            'id' => $id,
            'type' => 'converter',
            'name' => $name,
            'label' => $label,
            'description' => (string) ($definition['description'] ?? ''),
            'accepts' => array_values(array_map('strtolower', (array) ($definition['accepts'] ?? []))),
            'outputs' => $outputs,
            'formats' => $formats,
            'engine' => $engine,
            'folder' => $folder,
            'templateFolder' => $templateFolder,
            'path' => $path,
            'viewerHref' => $viewerHref,
            'url' => $url,
            'enabled' => $enabled,
            'disabledReason' => $disabledReason,
            'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            'ui' => is_array($definition['ui'] ?? null) ? $definition['ui'] : self::defaultUi(),
            'remote' => $remote,
            'network' => is_array($definition['network'] ?? null) ? $definition['network'] : [],
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

    private static function readTemplatePackageFile(string $absoluteFolder, string $fileName): ?string
    {
        $path = rtrim($absoluteFolder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($fileName, '/');
        return is_file($path) ? (string) file_get_contents($path) : null;
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

    public static function templatePackage(array|string $converter, string $rootDir = ''): ?array
    {
        $definition = is_array($converter)
            ? self::definition((string) ($converter['id'] ?? $converter['name'] ?? ''), $rootDir)
            : self::definition((string) $converter, $rootDir);
        if ($definition === null) {
            return null;
        }

        $root = self::normalizeRootDir($rootDir);
        $templateFolder = trim(str_replace('\\', '/', (string) ($definition['templateFolder'] ?? '')), '/');
        if ($templateFolder === '') {
            return null;
        }

        $absoluteFolder = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $templateFolder);
        if (!is_dir($absoluteFolder)) {
            return null;
        }

        $template = self::readTemplatePackageFile($absoluteFolder, 'template.hbs');
        if ($template === null || trim($template) === '') {
            return null;
        }

        return [
            'directory' => $templateFolder,
            'template' => $template,
            'workTemplate' => self::readTemplatePackageFile($absoluteFolder, 'work.hbs') ?? '',
            'externalTemplate' => self::readTemplatePackageFile($absoluteFolder, 'external.hbs') ?? '',
            'css' => self::readTemplatePackageFile($absoluteFolder, 'style.css') ?? '',
            'js' => self::readTemplatePackageFile($absoluteFolder, 'script.js') ?? '',
        ];
    }

    public static function availableFor(string $mimeType, string $kind, string $extension, string $rootDir = ''): array
    {
        $mime = strtolower(trim($mimeType));
        $kind = strtolower(trim($kind));
        $extension = strtolower(trim($extension, '. '));
        if ($mime === '' && $extension !== '') {
            $mime = MediaType::mimeFromExtension($extension);
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
        if ($mime !== '') {
            return self::mimeAccepted($mime, $accepts);
        }
        if ($kind !== '') {
            foreach ($accepts as $pattern) {
                if ($pattern === $kind || str_starts_with($pattern, $kind . '/')) {
                    return true;
                }
            }
        }
        if ($extension !== '') {
            if (in_array($extension, $accepts, true)) {
                return true;
            }
            $mimeFromExtension = MediaType::mimeFromExtension($extension);
            if ($mimeFromExtension !== '' && self::mimeAccepted($mimeFromExtension, $accepts)) {
                return true;
            }
        }
        return false;
    }

    public static function offerableFor(string $mimeType, string $kind, string $extension, string $rootDir = ''): array
    {
        return array_values(array_filter(
            self::availableFor($mimeType, $kind, $extension, $rootDir),
            static fn(array $definition): bool => self::canOffer($definition)
                && self::networkPublicationEnabled($definition)
        ));
    }

    public static function networkPublicationEnabled(array $definition): bool
    {
        return ($definition['network']['publish'] ?? false) === true;
    }

    public static function peerRequestSignature(
        array $payload,
        string $timestamp,
        string $nonce,
        string $secret,
        ?string $canonicalBody = null
    ): string
    {
        $body = $canonicalBody ?? json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || $secret === '') {
            return '';
        }
        $canonical = "poff-converter/1\nPOST\nconvert-offered\n"
            . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $body);
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function canOffer(array $definition): bool
    {
        if (($definition['enabled'] ?? false) !== true) {
            return false;
        }

        $engine = strtolower(trim((string) ($definition['engine'] ?? '')));
        if ($engine === 'text-copy') {
            return true;
        }
        if (in_array($engine, ['imagemagick', 'simple-image'], true)) {
            return class_exists('Imagick') || self::findCliBinary() !== null;
        }
        return false;
    }

    public static function remoteDefinition(array $offered, array $node): ?array
    {
        $providerId = self::normalizeConverterId((string) ($offered['id'] ?? ''));
        $name = self::normalizeConverterName((string) ($offered['name'] ?? $providerId));
        $endpoint = trim((string) ($node['endpoint'] ?? ''));
        if ($providerId === '' || $name === '' || self::validateRemoteEndpoint($endpoint) !== null) {
            return null;
        }

        $nodeId = trim((string) ($node['id'] ?? parse_url($endpoint, PHP_URL_HOST) ?? 'remote'));
        $nodeSlug = preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($nodeId)) ?: 'remote';
        $definition = self::converterDefinition([
            'id' => 'converter/remote-' . $nodeSlug . '-' . $name,
            'name' => 'remote-' . $nodeSlug . '-' . $name,
            'label' => trim((string) ($offered['label'] ?? $name)) . ' · ' . $nodeId,
            'description' => (string) ($offered['description'] ?? ''),
            'accepts' => array_values((array) ($offered['accepts'] ?? [])),
            'outputs' => array_values((array) ($offered['outputs'] ?? [])),
            'formats' => array_values((array) ($offered['formats'] ?? [])),
            'engine' => 'remote-node',
            'enabled' => true,
            'defaults' => is_array($offered['defaults'] ?? null) ? $offered['defaults'] : [],
            'ui' => is_array($offered['ui'] ?? null) ? $offered['ui'] : [],
            'remote' => [
                'nodeId' => $nodeId,
                'endpoint' => $endpoint,
                'publicUrl' => (string) ($node['baseUrl'] ?? ''),
            ],
            'source' => 'remote-node-catalog',
        ]);
        $definition['providerConverterId'] = $providerId;
        $definition['node'] = [
            'id' => $nodeId,
            'baseUrl' => (string) ($node['baseUrl'] ?? ''),
            'catalogUrl' => (string) ($node['catalogUrl'] ?? ''),
            'endpoint' => $endpoint,
            'converterId' => $providerId,
        ];
        return $definition;
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
            'convert-text-editor' => ['converter/convert-text-editor', 'source'],
            'converter/convert-text-editor' => ['converter/convert-text-editor', 'source'],
            'text-editor' => ['converter/convert-text-editor', 'source'],
            'converter/text-editor' => ['converter/convert-text-editor', 'source'],
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
            $converter['saveMode'] = (string) ($converter['id'] ?? '') === 'converter/convert-text-editor'
                ? 'replace-source-file'
                : 'new-hidden-work';
        }
        if (!array_key_exists('hiddenByDefault', $converter)) {
            $converter['hiddenByDefault'] = true;
        }
        if (!isset($converter['quality'])) {
            $converter['quality'] = 'default';
        }
        if (!isset($converter['format'])) {
            $converter['format'] = str_contains((string) ($converter['id'] ?? ''), 'convert-text') ? 'source' : 'webp';
        }
        if (isset($converter['path'])) {
            $converter['path'] = trim(str_replace('\\', '/', (string) $converter['path']), '/');
        }
        if (isset($converter['viewerHref'])) {
            $converter['viewerHref'] = trim((string) $converter['viewerHref']);
        }
        if (isset($converter['url'])) {
            $converter['url'] = trim((string) $converter['url']);
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
        if ((string) ($definition['engine'] ?? '') === 'remote-node' && !str_starts_with($baseName, 'external-')) {
            $baseName = 'external-' . $baseName;
        }
        $saveAs = $format === 'source'
            ? $sourceName
            : self::sanitizeOutputName($baseName . '.' . $format);

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
                'saveAs' => $saveAs,
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
            $definition = self::definitionFromRemoteSelection($converter);
        }
        if ($definition === null) {
            return self::error('Unknown converter.');
        }

        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $mime = strtolower(trim((string) ($source['mimeType'] ?? '')));
        if (!self::mimeAccepted($mime, $definition['accepts'] ?? [])) {
            return self::error('Source MIME type is not accepted.');
        }

        $root = realpath($rootDir);
        $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
        if (is_string($root) && $root !== '' && $sourcePath !== '') {
            $fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath));
            if (is_string($fullPath)
                && is_file($fullPath)
                && str_starts_with($fullPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            ) {
                $actualSize = @filesize($fullPath);
                if (!is_int($actualSize)) {
                    return self::error('Could not determine source file size.');
                }
                $source['size'] = $actualSize;
                $payload['source'] = $source;
            }
        }
        if ((int) ($source['size'] ?? 0) > self::MAX_SOURCE_BYTES) {
            return self::error('Source file is too large.');
        }

        if (($definition['engine'] ?? '') === 'remote-node') {
            $root = realpath($rootDir);
            $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
            if (is_string($root) && $root !== '' && $sourcePath !== '') {
                $fullPath = realpath($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sourcePath));
                if (is_string($fullPath) && is_file($fullPath) && str_starts_with($fullPath, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
                    $body = @file_get_contents($fullPath);
                    if (is_string($body) && $body !== '') {
                        $source['bodyBase64'] = base64_encode($body);
                        $payload['source'] = $source;
                    }
                }
            }
        }

        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'webp')));
        $engine = (string) ($definition['engine'] ?? '');
        $outputMime = (
            $engine === 'text-copy'
            || ($engine === 'remote-node' && self::remoteUsesTextFormats($format, $definition))
        )
            ? self::textMimeFromFormat($format, (string) ($source['path'] ?? ''), $mime)
            : self::mimeFromFormat($format);
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

    public static function convertOffered(array $payload, string $rootDir): array
    {
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
        $converter = self::normalizeSelectedConverter(
            is_array($payload['converter'] ?? null) ? $payload['converter'] : []
        );
        $definition = self::definition((string) ($converter['id'] ?? ''), $rootDir);
        if (
            $definition === null
            || !self::canOffer($definition)
            || !self::networkPublicationEnabled($definition)
        ) {
            return self::error('Converter is not offered by this Poff node.');
        }

        $bodyBase64 = (string) ($source['bodyBase64'] ?? '');
        if ($bodyBase64 === '' || strlen($bodyBase64) > (int) ceil(self::MAX_SOURCE_BYTES * 4 / 3) + 4) {
            return self::error('Missing or oversized inline source payload.');
        }
        $body = base64_decode($bodyBase64, true);
        if (!is_string($body) || $body === '' || strlen($body) > self::MAX_SOURCE_BYTES) {
            return self::error('Invalid inline source payload.');
        }

        $mime = strtolower(trim((string) ($source['mimeType'] ?? '')));
        $sourceName = basename((string) ($source['name'] ?? $source['path'] ?? 'source'));
        $extension = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        $kind = $extension !== '' ? MediaType::classifyExtension($sourceName) : '';
        if (!self::matches($definition, $mime, $kind, $extension)) {
            return self::error('Source MIME type is not accepted.');
        }

        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'webp')));
        $engine = strtolower((string) ($definition['engine'] ?? ''));
        $outputMime = $engine === 'text-copy'
            ? self::textMimeFromFormat($format, $sourceName, $mime)
            : self::mimeFromFormat($format);
        if (!self::mimeAccepted($outputMime, $definition['outputs'] ?? [])) {
            return self::error('Output MIME type is not allowed.');
        }

        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $actualFormat = $engine === 'text-copy'
            ? self::resolveTextOutputFormat($format, $sourceName, $mime)
            : $format;
        $defaultName = $format === 'source'
            ? $sourceName
            : pathinfo($sourceName, PATHINFO_FILENAME) . '.' . $actualFormat;
        $outputName = self::sanitizeOutputName((string) ($target['saveAs'] ?? $defaultName));
        $outputBody = null;

        if ($engine === 'text-copy') {
            $editor = is_array($payload['editor'] ?? null) ? $payload['editor'] : [];
            $outputBody = array_key_exists('content', $editor) ? (string) $editor['content'] : $body;
            if ($actualFormat === 'html') {
                $outputBody = self::renderTextEditorHtmlDocument(
                    $outputBody,
                    $sourceName,
                    (string) ($definition['label'] ?? ($definition['name'] ?? 'Remote Text Converter'))
                );
            }
        } else {
            $input = tempnam(sys_get_temp_dir(), 'poff-node-convert-');
            if (!is_string($input) || file_put_contents($input, $body) === false) {
                return self::error('Failed to prepare inline source payload.');
            }
            $preset = self::qualityPreset((string) ($converter['quality'] ?? 'default'));
            $outputBody = self::convertWithImagick($input, $format, $preset);
            if ($outputBody === null) {
                $outputBody = self::convertWithCli($input, $format, $preset);
            }
            @unlink($input);
        }

        if (!is_string($outputBody) || $outputBody === '') {
            return self::error('Offered converter failed.');
        }
        if (strlen($outputBody) > self::MAX_REMOTE_OUTPUT_BYTES) {
            return self::error('Converted output exceeds the allowed size.');
        }

        return [
            'ok' => true,
            'type' => 'converted-work',
            'source' => [
                'path' => (string) ($source['path'] ?? $sourceName),
                'mimeType' => $mime,
            ],
            'output' => [
                'name' => $outputName,
                'mimeType' => $outputMime,
                'kind' => $engine === 'text-copy' ? 'text' : 'image',
                'size' => strlen($outputBody),
                'bodyBase64' => base64_encode($outputBody),
            ],
            'generatedBy' => [
                'type' => 'converter',
                'name' => (string) ($definition['name'] ?? ''),
                'id' => (string) ($definition['id'] ?? ''),
                'node' => 'provider',
                'engine' => $engine,
                'quality' => self::normalizeQuality((string) ($converter['quality'] ?? 'default')),
                'format' => $format,
                'convertedAt' => date('c'),
            ],
        ];
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
        $target = is_array($conversion['target'] ?? null) ? $conversion['target'] : [];
        $saveMode = self::normalizeSaveMode((string) ($target['mode'] ?? 'new-hidden-work'));
        if ($saveMode === 'temporary-preview-only') {
            return self::error('Preview-only conversion results cannot be saved.');
        }
        $rawOutputName = basename(str_replace('\\', '/', trim((string) ($output['name'] ?? 'converted.webp'))));
        $outputName = self::sanitizeOutputName($rawOutputName);
        $outputMime = strtolower(trim((string) ($output['mimeType'] ?? '')));
        if (!self::mimeAccepted($outputMime, ['image/webp', 'image/jpeg', 'image/png', 'application/json', 'text/plain', 'text/markdown', 'text/html'])) {
            return self::error('Converted MIME type is not allowed.');
        }
        if (isset($output['size']) && (int) $output['size'] > self::MAX_REMOTE_OUTPUT_BYTES) {
            return self::error('Converted output exceeds the allowed size.');
        }

        $folderDir = dirname($sourceFullPath);
        $targetPath = $folderDir . DIRECTORY_SEPARATOR . $outputName;
        if ($saveMode !== 'replace-source-file') {
            if (is_link($targetPath)) {
                return self::error('Converted work target cannot be a symbolic link.');
            }
            if (file_exists($targetPath)) {
                if ($saveMode !== 'replace-generated-work') {
                    return self::error('Converted work already exists. Choose replace-generated-work to replace a generated result.');
                }
                if (!self::generatedWorkBelongsToSource($folderDir, $outputName, basename($sourcePath))) {
                    return self::error('Existing target is not a generated work from this source.');
                }
            }
        }

        $body = '';
        if (isset($output['bodyBase64']) && is_string($output['bodyBase64']) && $output['bodyBase64'] !== '') {
            if (strlen($output['bodyBase64']) > (int) ceil(self::MAX_REMOTE_OUTPUT_BYTES * 4 / 3) + 4) {
                return self::error('Converted output exceeds the allowed size.');
            }
            $decoded = base64_decode($output['bodyBase64'], true);
            if ($decoded === false || strlen($decoded) > self::MAX_REMOTE_OUTPUT_BYTES) {
                return self::error('Converted body is invalid base64.');
            }
            $body = $decoded;
        } elseif (isset($output['downloadUrl']) && is_string($output['downloadUrl'])) {
            $download = self::httpGet($output['downloadUrl']);
            if (($download['ok'] ?? false) !== true) {
                return self::error('Converted download failed.');
            }
            $body = (string) ($download['body'] ?? '');
            if (strlen($body) > self::MAX_REMOTE_OUTPUT_BYTES) {
                return self::error('Converted output exceeds the allowed size.');
            }
        } else {
            return self::error('Converted response has no body.');
        }

        if ($saveMode === 'replace-source-file') {
            if ($rawOutputName !== basename($sourcePath)) {
                return self::error('Replace source file requires output format "same as source".');
            }
            if (!self::mimeAccepted($outputMime, ['application/json', 'text/plain', 'text/markdown', 'text/html'])) {
                return self::error('Replace source file only supports text-like outputs.');
            }
            if (file_put_contents($sourceFullPath, $body) === false) {
                return self::error('Failed to write source file.');
            }
            return [
                'ok' => true,
                'type' => 'saved-source-file',
                'path' => $sourcePath,
            ];
        }

        if ($saveMode === 'new-hidden-work') {
            $targetHandle = @fopen($targetPath, 'xb');
            if (!is_resource($targetHandle)) {
                return self::error('Converted work already exists. Choose replace-generated-work to replace a generated result.');
            }
            $written = fwrite($targetHandle, $body);
            fclose($targetHandle);
            if ($written !== strlen($body)) {
                @unlink($targetPath);
                return self::error('Failed to write converted work.');
            }
        } elseif (file_put_contents($targetPath, $body, LOCK_EX) === false) {
            return self::error('Failed to write converted work.');
        }

        $generatedBy = is_array($conversion['generatedBy'] ?? null) ? $conversion['generatedBy'] : [];
        $visibilityMode = self::normalizeVisibilityMode((string) ($target['visibilityMode'] ?? 'preserve'));
        $generatedVisible = match ($visibilityMode) {
            'converted-only', 'both' => true,
            'originals-only' => false,
            default => false,
        };
        $entry = [
            'name' => $outputName,
            'title' => pathinfo($outputName, PATHINFO_FILENAME),
            'type' => 'file',
            'kind' => (string) ($output['kind'] ?? 'image'),
            'mimeType' => $outputMime,
            'visible' => $generatedVisible,
            'generated' => true,
            'sourceWork' => basename($sourcePath),
            'generatedBy' => $generatedBy,
            'external' => [
                'type' => 'node-work',
                'relation' => 'converted-from',
                'visible' => $generatedVisible,
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

        self::upsertFolderTreeEntry($folderDir, $entry, basename($sourcePath), $visibilityMode);
        self::writeGeneratedFileConfig($folderDir, $outputName, $entry, $targetPath);

        return [
            'ok' => true,
            'type' => 'saved-generated-work',
            'path' => trim(dirname($sourcePath), '.') === '' ? $outputName : trim(dirname($sourcePath), '/\\') . '/' . $outputName,
            'entry' => $entry,
        ];
    }

    private static function generatedWorkBelongsToSource(string $folderDir, string $fileName, string $sourceName): bool
    {
        if (!class_exists('PoffConfig')) {
            return false;
        }
        $configPath = PoffConfig::fileConfigPath($folderDir, $fileName);
        if (!is_file($configPath)) {
            return false;
        }
        $config = json_decode((string) @file_get_contents($configPath), true);
        return is_array($config)
            && ($config['generated'] ?? false) === true
            && (string) ($config['sourceWork'] ?? '') === $sourceName
            && (string) ($config['external']['relation'] ?? '') === 'converted-from'
            && is_array($config['generatedBy'] ?? null);
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
        $editor = is_array($payload['editor'] ?? null) ? $payload['editor'] : [];
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $format = self::normalizeFormat((string) ($converter['format'] ?? ($definition['defaults']['format'] ?? 'source')));
        $actualFormat = self::resolveTextOutputFormat($format, $sourcePath, (string) ($source['mimeType'] ?? ''));
        $outputMime = self::textMimeFromFormat($format, $sourcePath, (string) ($source['mimeType'] ?? ''));
        $defaultOutputName = $format === 'source'
            ? basename($sourcePath)
            : (pathinfo($fullPath, PATHINFO_FILENAME) . '.' . $actualFormat);
        $outputName = self::sanitizeOutputName((string) ($target['saveAs'] ?? $defaultOutputName));
        $body = array_key_exists('content', $editor) ? (string) ($editor['content'] ?? '') : (string) file_get_contents($fullPath);
        if ($actualFormat === 'html') {
            $body = self::renderTextEditorHtmlDocument(
                $body,
                basename($sourcePath),
                (string) ($definition['label'] ?? ($definition['name'] ?? 'Text Editor Converter'))
            );
        }
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
                'outputFormat' => $actualFormat,
                'convertedAt' => date('c'),
            ],
        ];
    }

    private static function renderTextEditorHtmlDocument(string $content, string $sourceName, string $converterLabel): string
    {
        $safeTitle = htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8');
        $safeConverter = htmlspecialchars($converterLabel, ENT_QUOTES, 'UTF-8');
        $safeBody = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeTitle}</title>
    <style>
        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background: #0b1020;
            color: #e5eefb;
        }
        .poff-text-converted {
            max-width: 72rem;
            margin: 0 auto;
            padding: 2rem;
        }
        .poff-text-converted__meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            color: #8ba4c7;
            font-size: 0.95rem;
        }
        .poff-text-converted__surface {
            border: 1px solid rgba(124, 162, 255, 0.28);
            border-radius: 1rem;
            background: rgba(14, 22, 42, 0.88);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .poff-text-converted__code {
            margin: 0;
            padding: 1.5rem;
            overflow: auto;
            font-family: "IBM Plex Mono", "SFMono-Regular", monospace;
            font-size: 0.95rem;
            line-height: 1.65;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <main class="poff-text-converted">
        <div class="poff-text-converted__meta">
            <span>{$safeConverter}</span>
            <span>{$safeTitle}</span>
        </div>
        <section class="poff-text-converted__surface">
            <pre class="poff-text-converted__code">{$safeBody}</pre>
        </section>
    </main>
</body>
</html>
HTML;
    }

    private static function convertRemote(array $payload, array $definition, string $rootDir): array
    {
        $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
        $node = is_array($converter['node'] ?? null) ? $converter['node'] : [];
        $remote = is_array($definition['remote'] ?? null) ? $definition['remote'] : [];
        $endpoint = trim((string) ($node['endpoint'] ?? $remote['endpoint'] ?? ''));
        $validation = self::validateRemoteEndpoint($endpoint, self::isTrustedLocalPageoffConverterEndpoint($endpoint));
        if ($validation !== null) {
            return self::error($validation);
        }
        $scheme = strtolower((string) (parse_url($endpoint, PHP_URL_SCHEME) ?? ''));
        if (
            $scheme !== 'https'
            && !self::isTrustedLocalPageoffConverterEndpoint($endpoint)
        ) {
            return self::error('Remote converter requests require HTTPS.');
        }

        $remotePayload = $payload;
        $remotePayload['source'] = is_array($remotePayload['source'] ?? null)
            ? $remotePayload['source']
            : [];
        $remotePayload['source']['path'] = basename(str_replace(
            '\\',
            '/',
            (string) ($remotePayload['source']['path'] ?? $remotePayload['source']['name'] ?? 'source')
        ));
        $remotePayload['source']['srcUrl'] = '';
        $remotePayload['target'] = is_array($remotePayload['target'] ?? null)
            ? $remotePayload['target']
            : [];
        $remotePayload['target']['folder'] = '';
        $remotePayload['converter'] = is_array($remotePayload['converter'] ?? null)
            ? $remotePayload['converter']
            : [];
        $providerConverterId = trim((string) (
            $node['converterId']
            ?? $definition['providerConverterId']
            ?? $remotePayload['converter']['id']
            ?? ''
        ));
        if ($providerConverterId !== '') {
            $remotePayload['converter']['id'] = $providerConverterId;
            $remotePayload['converter']['name'] = self::normalizeConverterName($providerConverterId);
        }
        $remotePayload['converter']['node'] = 'provider';

        $providerNodeId = trim((string) ($node['id'] ?? $remote['nodeId'] ?? ''));
        $response = self::httpPost($endpoint, $remotePayload, $providerNodeId);
        if (($response['ok'] ?? false) !== true) {
            $message = trim((string) ($response['error'] ?? ''));
            return self::error(
                $message !== '' ? $message : 'Remote converter request failed.',
                ['status' => $response['status'] ?? 0]
            );
        }
        $decoded = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($decoded) || ($decoded['ok'] ?? false) !== true) {
            return self::error('Remote converter returned invalid response.');
        }
        $decoded['source'] = is_array($decoded['source'] ?? null) ? $decoded['source'] : [];
        $decoded['source']['path'] = (string) ($payload['source']['path'] ?? '');
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
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return 'Converter endpoint is invalid.';
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return 'Converter endpoint credentials and fragments are not allowed.';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
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

    public static function resolveRemoteAddresses(string $host, bool $allowPrivate = false): array
    {
        $host = trim(strtolower($host), '[]');
        if ($host === '') {
            return ['ok' => false, 'addresses' => [], 'error' => 'Converter endpoint host is missing.'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses = [$host];
        } else {
            if (preg_match('/^(?:[0-9.]+|0x[0-9a-f.]+)$/i', $host)) {
                return ['ok' => false, 'addresses' => [], 'error' => 'Converter endpoint host uses an invalid numeric IP form.'];
            }
            $lookup = $GLOBALS['__poff_converter_dns_lookup'] ?? null;
            $records = is_callable($lookup) ? $lookup($host) : @dns_get_record($host, DNS_A | DNS_AAAA);
            $addresses = [];
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!is_array($record)) {
                        $addresses[] = (string) $record;
                        continue;
                    }
                    foreach (['ip', 'ipv6'] as $addressKey) {
                        if (isset($record[$addressKey])) {
                            $addresses[] = (string) $record[$addressKey];
                        }
                    }
                }
            }
            if ($addresses === []) {
                $ipv4 = @gethostbynamel($host);
                if (is_array($ipv4)) {
                    $addresses = $ipv4;
                }
            }
            $addresses = array_values(array_unique(array_filter($addresses, static fn(string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)));
        }

        if ($addresses === []) {
            return ['ok' => false, 'addresses' => [], 'error' => 'Converter endpoint host did not resolve to an IP address.'];
        }
        foreach ($addresses as $address) {
            if (!$allowPrivate && !self::isPublicIpAddress($address)) {
                return ['ok' => false, 'addresses' => [], 'error' => 'Converter endpoint resolves to a private or reserved IP address.'];
            }
        }
        return ['ok' => true, 'addresses' => $addresses, 'error' => ''];
    }

    public static function httpRequestPinned(
        string $method,
        string $url,
        ?string $body,
        array $headers,
        int $timeoutSeconds,
        int $maxResponseBytes,
        bool $allowPrivate = false
    ): array {
        $validation = self::validateRemoteEndpoint($url, $allowPrivate);
        if ($validation !== null) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $validation];
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Converter endpoint is invalid.'];
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $resolution = self::resolveRemoteAddresses($host, $allowPrivate);
        if (($resolution['ok'] ?? false) !== true) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $resolution['error'] ?? 'Converter endpoint host is not allowed.'];
        }
        $pinnedAddress = (string) $resolution['addresses'][0];

        if (function_exists('curl_init')) {
            $responseBody = '';
            $tooLarge = false;
            $responseHeaders = [];
            $handle = curl_init($url);
            if ($handle === false) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to initialize the pinned converter connection.'];
            }
            $resolveHost = str_contains($host, ':') ? '[' . trim($host, '[]') . ']' : $host;
            $resolveAddress = str_contains($pinnedAddress, ':') ? '[' . trim($pinnedAddress, '[]') . ']' : $pinnedAddress;
            $resolve = $resolveHost . ':' . $port . ':' . $resolveAddress;
            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
                CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeoutSeconds)),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => [$resolve],
                CURLOPT_PROXY => '',
                CURLOPT_NOSIGNAL => true,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders, &$tooLarge, $maxResponseBytes): int {
                    $length = strlen($line);
                    $responseHeaders[] = trim($line);
                    if (preg_match('/^Content-Length:\\s*(\\d+)/i', $line, $matches)
                        && (int) $matches[1] > $maxResponseBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                    return $length;
                },
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$tooLarge, $maxResponseBytes): int {
                    if (strlen($responseBody) + strlen($chunk) > $maxResponseBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                    $responseBody .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if ($body !== null && strtoupper($method) !== 'GET') {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
            }
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            $executed = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);
            if ($tooLarge) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => 'Remote response exceeds the allowed size.'];
            }
            return [
                'ok' => $executed !== false && $status >= 200 && $status < 300,
                'status' => $status,
                'body' => $executed !== false ? $responseBody : '',
                'error' => $executed === false ? $error : '',
            ];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Pinned DNS transport requires the PHP cURL extension for hostnames.'];
        }
        $httpHeaders = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => $httpHeaders,
                'content' => $body ?? '',
                'timeout' => max(1, $timeoutSeconds),
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $response = self::readHttpResponse($url, $context, $maxResponseBytes);
        return [
            'ok' => !$response['tooLarge'] && $response['body'] !== false && $response['status'] >= 200 && $response['status'] < 300,
            'status' => $response['status'],
            'body' => $response['body'] !== false && !$response['tooLarge'] ? $response['body'] : '',
            'error' => $response['tooLarge'] ? 'Remote response exceeds the allowed size.' : '',
        ];
    }

    private static function definitionFromRemoteSelection(array $converter): ?array
    {
        $node = is_array($converter['node'] ?? null) ? $converter['node'] : [];
        $endpoint = trim((string) ($node['endpoint'] ?? ''));
        $providerId = trim((string) ($node['converterId'] ?? $converter['providerConverterId'] ?? ''));
        if ($endpoint === '' || $providerId === '') {
            return null;
        }

        if (self::validateRemoteEndpoint($endpoint) !== null) {
            return null;
        }

        $definition = self::converterDefinition([
            'id' => (string) ($converter['id'] ?? ''),
            'name' => (string) ($converter['name'] ?? $providerId),
            'label' => (string) ($converter['label'] ?? $converter['name'] ?? $providerId),
            'description' => (string) ($converter['description'] ?? ''),
            'accepts' => (array) ($converter['accepts'] ?? []),
            'outputs' => (array) ($converter['outputs'] ?? []),
            'formats' => (array) ($converter['formats'] ?? []),
            'engine' => 'remote-node',
            'enabled' => true,
            'defaults' => is_array($converter['defaults'] ?? null) ? $converter['defaults'] : [],
            'ui' => is_array($converter['ui'] ?? null) ? $converter['ui'] : [],
            'remote' => [
                'nodeId' => (string) ($node['id'] ?? 'remote'),
                'endpoint' => $endpoint,
                'publicUrl' => (string) ($node['baseUrl'] ?? ''),
            ],
            'source' => 'remote-node-catalog',
        ]);
        $definition['providerConverterId'] = self::normalizeConverterId($providerId);
        $definition['node'] = $node;
        return $definition;
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

    private static function mimeFromFormat(string $format): string
    {
        return match (self::normalizeFormat($format)) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'json' => 'application/json',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'html' => 'text/html',
            default => 'image/webp',
        };
    }

    private static function textMimeFromFormat(string $format, string $sourcePath, string $sourceMime): string
    {
        $actualFormat = self::resolveTextOutputFormat($format, $sourcePath, $sourceMime);
        return match ($actualFormat) {
            'json' => 'application/json',
            'md' => 'text/markdown',
            'html' => 'text/html',
            default => 'text/plain',
        };
    }

    private static function resolveTextOutputFormat(string $format, string $sourcePath, string $sourceMime): string
    {
        $normalized = self::normalizeFormat($format);
        if ($normalized !== 'source') {
            return $normalized;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (in_array($extension, ['json', 'html', 'txt', 'md'], true)) {
            return $extension;
        }
        $mime = strtolower(trim($sourceMime));
        return match ($mime) {
            'application/json' => 'json',
            'text/html' => 'html',
            'text/markdown' => 'md',
            default => 'txt',
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
                'application/json' => 'json',
                'text/plain' => 'txt',
                'text/markdown' => 'md',
                'text/html' => 'html',
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
        if ($format === 'jpg') {
            return 'jpeg';
        }
        return in_array($format, ['source', 'webp', 'jpeg', 'png', 'json', 'txt', 'md', 'html'], true) ? $format : 'webp';
    }

    private static function normalizeQuality(string $quality): string
    {
        $quality = strtolower(trim($quality));
        return in_array($quality, ['preview', 'default', 'archival', 'small-web'], true) ? $quality : 'default';
    }

    private static function normalizeSaveMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['new-hidden-work', 'replace-generated-work', 'replace-source-file', 'temporary-preview-only'], true) ? $mode : 'new-hidden-work';
    }

    private static function normalizeVisibilityMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['preserve', 'converted-only', 'originals-only', 'both'], true)
            ? $mode
            : 'preserve';
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

    private static function definitionPath(string $folder): string
    {
        $normalized = trim(str_replace('\\', '/', $folder), '/');
        if ($normalized === '' || str_starts_with($normalized, 'src/')) {
            return '';
        }
        return $normalized;
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

    private static function relativePathFromRoot(string $rootDir, string $path): string
    {
        $root = realpath($rootDir);
        $target = realpath($path);
        if ($root === false || $target === false) {
            return '';
        }
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $target = rtrim(str_replace('\\', '/', $target), '/');
        if ($target === $root) {
            return '';
        }
        if (!str_starts_with($target . '/', $root . '/')) {
            return '';
        }
        return trim(substr($target, strlen($root)), '/');
    }

    private static function cliEnabled(): bool
    {
        return getenv('POFF_ENABLE_IMAGEMAGICK_CLI') === '1' && (self::findCliBinary() !== null);
    }

    private static function findCliBinary(): ?string
    {
        foreach ([
            '/opt/homebrew/bin/magick',
            '/opt/homebrew/bin/convert',
            '/usr/local/bin/magick',
            '/usr/local/bin/convert',
            '/usr/bin/magick',
            '/usr/bin/convert',
        ] as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }
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
        $requestedExtension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $format = self::normalizeFormat($requestedExtension);
        $extension = $requestedExtension === 'jpg' ? 'jpg' : $format;
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = preg_replace('/[^a-z0-9._-]+/i', '-', $base) ?: 'converted';
        return trim($base, '.-') . '.' . $extension;
    }

    private static function upsertFolderTreeEntry(
        string $folderDir,
        array $entry,
        string $sourceName = '',
        string $visibilityMode = 'preserve'
    ): void
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
        if ($visibilityMode !== 'preserve' && $sourceName !== '') {
            $sourceVisible = $visibilityMode !== 'converted-only';
            foreach ($tree as &$item) {
                if (is_array($item) && (string) ($item['name'] ?? '') === $sourceName) {
                    $item['visible'] = $sourceVisible;
                    break;
                }
            }
            unset($item);
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
        $resolution = self::resolveRemoteAddresses($host, true);
        if (($resolution['ok'] ?? false) !== true) {
            return false;
        }
        foreach ($resolution['addresses'] as $address) {
            if (!self::isPublicIpAddress((string) $address)) {
                return true;
            }
        }
        return false;
    }

    private static function httpPost(string $url, array $payload, string $providerNodeId = ''): array
    {
        $override = $GLOBALS['__poff_converter_http_post'] ?? null;
        if (is_callable($override)) {
            $response = $override($url, ['Accept: application/json'], $payload);
            return is_array($response) ? $response : ['ok' => false, 'status' => 0, 'body' => ''];
        }
        $credentialsJson = getenv('POFF_CONVERTER_PEER_CREDENTIALS');
        $credentials = is_string($credentialsJson) ? json_decode($credentialsJson, true) : null;
        $credential = is_array($credentials) && is_array($credentials[$providerNodeId] ?? null)
            ? $credentials[$providerNodeId]
            : [];
        $peerId = trim((string) ($credential['peerId'] ?? ''));
        $secret = (string) ($credential['secret'] ?? '');
        $configuredOrigin = self::remoteEndpointOrigin((string) ($credential['origin'] ?? ''));
        $requestOrigin = self::remoteEndpointOrigin($url);
        if (
            $providerNodeId === ''
            || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $peerId)
            || strlen($secret) < 32
            || $configuredOrigin === ''
            || $requestOrigin === ''
            || !hash_equals($configuredOrigin, $requestOrigin)
        ) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Remote converter credentials do not match the selected provider.'];
        }
        $timestamp = (string) time();
        try {
            $nonce = bin2hex(random_bytes(16));
        } catch (Throwable $error) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to create remote converter request signature.'];
        }
        $requestBody = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($requestBody) || strlen($requestBody) > self::MAX_REMOTE_REQUEST_BYTES) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Remote converter request exceeds the allowed size.'];
        }
        $signature = self::peerRequestSignature($payload, $timestamp, $nonce, $secret, $requestBody);
        if ($signature === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Unable to create remote converter request signature.'];
        }
        return self::httpRequestPinned(
            'POST',
            $url,
            $requestBody,
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Poff-Node-Id: ' . $peerId,
                'X-Poff-Timestamp: ' . $timestamp,
                'X-Poff-Nonce: ' . $nonce,
                'X-Poff-Signature: ' . $signature,
            ],
            30,
            self::MAX_REMOTE_JSON_BYTES,
            self::isTrustedLocalPageoffConverterEndpoint($url) || getenv('POFF_ALLOW_PRIVATE_CONVERTER_HOSTS') === '1'
        );
    }

    private static function remoteUsesTextFormats(string $format, array $definition): bool
    {
        $normalized = self::normalizeFormat($format);
        if (!in_array($normalized, ['source', 'json', 'txt', 'md', 'html'], true)) {
            return false;
        }

        foreach (($definition['outputs'] ?? []) as $mime) {
            if (in_array(strtolower(trim((string) $mime)), ['application/json', 'text/plain', 'text/markdown', 'text/html'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function isTrustedLocalPageoffConverterEndpoint(string $url): bool
    {
        $scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? ''));
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_ends_with($host, '.localhost')) {
            return false;
        }
        $path = str_replace('\\', '/', trim((string) (parse_url($url, PHP_URL_PATH) ?? ''), '/'));
        foreach ([
            'worktypes/templates/converter/convert-pageoff-remote-text/converter.php',
            'worktypes/templates/converter/convert-text-editor/converter.php',
        ] as $trustedPath) {
            if ($path === $trustedPath || str_ends_with($path, '/' . $trustedPath)) {
                return true;
            }
        }
        return false;
    }

    private static function httpGet(string $url): array
    {
        $validation = self::validateRemoteEndpoint($url);
        if ($validation !== null) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => $validation];
        }
        return self::httpRequestPinned(
            'GET',
            $url,
            null,
            ['Accept: */*'],
            30,
            self::MAX_REMOTE_OUTPUT_BYTES,
            getenv('POFF_ALLOW_PRIVATE_CONVERTER_HOSTS') === '1'
        );
    }

    private static function readHttpResponse(string $url, $context, int $maxBytes): array
    {
        $stream = @fopen($url, 'rb', false, $context);
        if ($stream === false) {
            return ['body' => false, 'status' => 0, 'tooLarge' => false];
        }

        $metadata = stream_get_meta_data($stream);
        $headers = is_array($metadata['wrapper_data'] ?? null) ? $metadata['wrapper_data'] : [];
        foreach ($headers as $header) {
            if (preg_match('/^Content-Length:\s*(\d+)/i', (string) $header, $matches)
                && (int) $matches[1] > $maxBytes) {
                fclose($stream);
                return ['body' => false, 'status' => self::statusFromHeaders($headers), 'tooLarge' => true];
            }
        }

        $body = '';
        while (!feof($stream)) {
            $remaining = $maxBytes + 1 - strlen($body);
            $chunk = fread($stream, min(8192, $remaining));
            if ($chunk === false) {
                fclose($stream);
                return ['body' => false, 'status' => self::statusFromHeaders($headers), 'tooLarge' => false];
            }
            $body .= $chunk;
            if (strlen($body) > $maxBytes) {
                fclose($stream);
                return ['body' => false, 'status' => self::statusFromHeaders($headers), 'tooLarge' => true];
            }
        }
        fclose($stream);
        return ['body' => $body, 'status' => self::statusFromHeaders($headers), 'tooLarge' => false];
    }

    private static function remoteEndpointOrigin(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }
        $host = strtolower((string) $parts['host']);
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']';
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);
        return $scheme . '://' . $host . ($defaultPort ? '' : ':' . $port);
    }

    private static function isPublicIpAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }
        $packed = @inet_pton($address);
        if (!is_string($packed) || strlen($packed) !== 16) {
            return false;
        }
        if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return self::isPublicIpAddress((string) inet_ntop(substr($packed, 12, 4)));
        }
        if ((ord($packed[0]) & 0xe0) !== 0x20) {
            return false;
        }
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
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
