<?php
declare(strict_types=1);

require_once __DIR__ . '/MediaType.php';

class Converter
{
    public const MAX_SOURCE_BYTES = 104857600;

    public static function definitions(): array
    {
        $imagemagickEnabled = self::imagemagickAvailable();
        $imagemagickReason = $imagemagickEnabled
            ? ''
            : 'Imagick extension missing; ImageMagick CLI disabled unless POFF_ENABLE_IMAGEMAGICK_CLI=1.';

        return [
            'converter/imagemagick' => self::converterDefinition([
                'name' => 'imagemagick',
                'label' => 'ImageMagick',
                'engine' => 'imagemagick',
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
            ]),
            'converter/simple-image' => self::converterDefinition([
                'name' => 'simple-image',
                'label' => 'Simple Image',
                'engine' => 'simple-image',
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
            ]),
            'converter/remote-node' => self::converterDefinition([
                'name' => 'remote-node',
                'label' => 'Remote Node',
                'engine' => 'remote-node',
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
            ]),
        ];
    }

    private static function converterDefinition(array $definition): array
    {
        $name = self::normalizeConverterName((string) ($definition['name'] ?? 'converter'));
        $id = self::normalizeConverterId((string) ($definition['id'] ?? ('converter/' . $name)));
        $label = trim((string) ($definition['label'] ?? ucfirst($name)));

        return [
            'id' => $id,
            'type' => 'converter',
            'name' => $name,
            'label' => $label,
            'accepts' => array_values(array_map('strtolower', (array) ($definition['accepts'] ?? []))),
            'outputs' => array_values(array_map('strtolower', (array) ($definition['outputs'] ?? []))),
            'formats' => array_values(array_map('strtolower', (array) ($definition['formats'] ?? []))),
            'engine' => (string) ($definition['engine'] ?? $name),
            'templateFolder' => (string) ($definition['templateFolder'] ?? '.layout/converters/' . $name),
            'enabled' => (bool) ($definition['enabled'] ?? true),
            'disabledReason' => (string) ($definition['disabledReason'] ?? ''),
            'defaults' => is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [],
            'ui' => self::defaultUi(),
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

    public static function definition(string $id): ?array
    {
        $definitions = self::definitions();
        $normalizedId = self::normalizeConverterId($id);
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

    public static function defaultOptions(string $id): array
    {
        $definition = self::definition($id);
        return is_array($definition) && is_array($definition['defaults'] ?? null) ? $definition['defaults'] : [];
    }

    public static function availableFor(string $mimeType, string $kind, string $extension): array
    {
        $mime = strtolower(trim($mimeType));
        $kind = strtolower(trim($kind));
        $extension = strtolower(trim($extension, '. '));
        if ($mime === '' && $extension !== '') {
            $mime = self::mimeFromExtension($extension);
        }

        $available = [];
        foreach (self::definitions() as $definition) {
            if (!self::matchesDefinition($definition, $mime, $kind, $extension)) {
                continue;
            }
            $available[] = $definition;
        }

        return $available;
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
        $definition = self::definition((string) ($converter['id'] ?? $converter['name'] ?? ''));
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
        if (!self::mimeAccepted($outputMime, ['image/webp', 'image/jpeg', 'image/png'])) {
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
                'node' => 'local',
                'engine' => (string) ($definition['engine'] ?? 'imagemagick'),
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
        $endpoint = trim((string) ($node['endpoint'] ?? ''));
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
                'name' => (string) ($definition['name'] ?? 'remote-node'),
                'id' => (string) ($definition['id'] ?? 'converter/remote-node'),
                'node' => (string) ($node['id'] ?? 'remote'),
                'endpoint' => $endpoint,
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
            default => '',
        };
    }

    private static function mimeFromFormat(string $format): string
    {
        return match (self::normalizeFormat($format)) {
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/webp',
        };
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));
        return in_array($format, ['webp', 'jpeg', 'png'], true) ? $format : 'webp';
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
