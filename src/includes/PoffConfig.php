<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

class PoffConfig
{
    private const DEFAULT_LAYOUT_ROOT_DIR = '.default';
    private const DEFAULT_LAYOUT_FOLDER = '.layout';
    private const LAYOUT_TEMPLATE_FILE = 'template.hbs';
    private const LAYOUT_STYLE_FILE = 'style.css';
    private const LAYOUT_SCRIPT_FILE = 'script.js';
    private const WORK_SECTION_TEMPLATE_FILE = 'work.hbs';
    private const WORKS_SECTION_TEMPLATE_FILE = 'works.hbs';

    private static function generateId(): string
    {
        try {
            return 'poff_' . bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return 'poff_' . uniqid();
        }
    }

    private static function defaultWork(string $kind, ?string $mime = null): array
    {
        $work = Worktype::definition($kind, $mime);
        $section = $kind === 'folder' ? 'works' : 'work';
        $work['layout'] = Worktype::normalizeLayout($work['layout'] ?? null, $section);

        return $work;
    }

    public static function configPath(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff.config.json';
    }

    public static function fileConfigPath(string $dir, string $fileName): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.config.json';
    }

    public static function folderLayoutDir(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.layout';
    }

    public static function fileLayoutDir(string $dir, string $fileName): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.layout';
    }

    public static function relativeLayoutPath(string $itemPath, bool $isFile): string
    {
        $normalized = str_replace('\\', '/', trim($itemPath, "/\\"));
        if (!$isFile) {
            return $normalized === '' ? '.layout' : $normalized . '/.layout';
        }

        if ($normalized === '') {
            return '.works/unknown.layout';
        }

        $fileName = basename($normalized);
        $dirName = dirname($normalized);
        if ($dirName === '.' || $dirName === DIRECTORY_SEPARATOR) {
            $dirName = '';
        }

        return ($dirName !== '' ? $dirName . '/' : '') . '.works/' . $fileName . '.layout';
    }

    public static function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        return trim($slug, '-') ?: 'untitled';
    }

    /**
     * Build a first-level tree (no recursion) for the given directory.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function buildFirstLevelTree(string $dir): array
    {
        $entries = @scandir($dir) ?: [];
        $tree = [];

        foreach ($entries as $entry) {
            if (
                $entry === '.' ||
                $entry === '..' ||
                $entry === 'poff.config.json' ||
                $entry === '.works' ||
                $entry === '.layout' ||
                $entry === '.DS_Store' ||
                $entry === 'Thumbs.db' ||
                $entry === '.git' ||
                $entry === '.idea' ||
                $entry === 'node_modules' ||
                $entry === '.edit.allow'
            ) {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            $isDir = is_dir($fullPath);
            $modifiedAt = @filemtime($fullPath);

            $tree[] = [
                'name' => $entry,
                'slug' => self::slugify($entry),
                'type' => $isDir ? 'folder' : 'file',
                'path' => $entry,
                'modifiedAt' => $modifiedAt ? date('c', (int) $modifiedAt) : null,
                'visible' => true,
            ];
        }

        return $tree;
    }

    /**
     * Create default config for a directory.
     *
     * @return array<string,mixed>
     */
    public static function defaultConfig(string $dir): array
    {
        $folderName = basename(rtrim($dir, DIRECTORY_SEPARATOR));
        $tree = self::buildFirstLevelTree($dir);
        $treeHash = hash('sha256', json_encode($tree));
        $now = date('c');

        return [
            '$schema' => 'https://dominikeggermann.com/poff-config.schema.json',
            'folderName' => $folderName,
            'slug' => self::slugify($folderName),
            'title' => $folderName,
            'description' => '',
            'type' => 'folder',
            'id' => self::generateId(),
            'tree' => $tree,
            'treeHash' => $treeHash,
            'updatedAt' => $now,
            'work' => self::defaultWork('folder'),
        ];
    }

    /**
     * Default per-file config.
     *
     * @return array<string,mixed>
     */
    public static function defaultFileConfig(string $dir, string $fileName): array
    {
        $fullPath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
        $modified = @filemtime($fullPath);
        $size = @filesize($fullPath);
        $now = date('c');
        $mime = MediaType::detectMimeType($fullPath, $fileName);
        $kind = MediaType::classifyExtension($fileName);
        $base = [
            '$schema' => 'https://dominikeggermann.com/poff-config.schema.json',
            'name' => $fileName,
            'slug' => self::slugify($fileName),
            'type' => 'file',
            'kind' => $kind,
            'path' => $fileName,
            'size' => $size !== false ? $size : null,
            'modifiedAt' => $modified ? date('c', (int) $modified) : null,
            'visible' => true,
            'mimeType' => $mime,
        ];
        $hash = hash('sha256', json_encode($base));
        $base['hash'] = $hash;
        $base['updatedAt'] = $now;
        $base['id'] = self::generateId();
        $base['work'] = self::defaultWork($kind, $mime);

        return $base;
    }

    /**
     * Attempt to detect a MIME type for a file.
     */
    public static function detectMimeType(string $fullPath, string $fileName): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $fullPath);
                finfo_close($finfo);
                if ($mime !== false) {
                    return $mime;
                }
            }
        }
        return null;
    }

    /**
     * Ensure a poff.config.json exists for the given directory, creating one if missing.
     *
     * @return array<string,mixed>
     */
    public static function ensure(string $dir): array
    {
        $configPath = self::configPath($dir);
        $defaults = self::defaultConfig($dir);
        $existing = null;
        $forceWrite = false;

        if (file_exists($configPath)) {
            $existing = json_decode((string) file_get_contents($configPath), true);
        }

        // Merge tree with existing to preserve visibility/custom flags per item
        $mergedTree = $defaults['tree'];
        if (is_array($existing) && isset($existing['tree']) && is_array($existing['tree'])) {
            $existingByName = [];
            foreach ($existing['tree'] as $item) {
                if (!isset($item['name'])) {
                    continue;
                }
                $existingByName[$item['name']] = $item;
            }

            foreach ($mergedTree as &$item) {
                $name = $item['name'];
                if (isset($existingByName[$name]) && is_array($existingByName[$name])) {
                    $existingItem = $existingByName[$name];
                    if (array_key_exists('visible', $existingItem)) {
                        $item['visible'] = $existingItem['visible'];
                    }
                    // Preserve any custom keys the user may have added
                    foreach ($existingItem as $k => $v) {
                        if (in_array($k, ['name', 'slug', 'type', 'path', 'modifiedAt'], true)) {
                            continue;
                        }
                        if (!array_key_exists($k, $item)) {
                            $item[$k] = $v;
                        }
                    }
                }
            }
            unset($item);
        }

        // Start with defaults but preserve user-provided title/description/link/url if present
        $data = $defaults;
        $data['tree'] = $mergedTree;
        $data['treeHash'] = hash('sha256', json_encode($mergedTree));
        $workDefault = self::defaultWork('folder');
        $existingWork = [];
        if (is_array($existing)) {
            $data['title'] = $existing['title'] ?? $data['title'] ?? '';
            $data['description'] = $existing['description'] ?? $data['description'] ?? '';
            if (isset($existing['link'])) {
                $data['link'] = $existing['link'];
            }
            if (isset($existing['url'])) {
                $data['url'] = $existing['url'];
            }
            if (isset($existing['id'])) {
                $data['id'] = $existing['id'];
            }
            $existingWork = (isset($existing['work']) && is_array($existing['work'])) ? $existing['work'] : [];
        }
        $data['work'] = array_merge($workDefault, $existingWork);
        $data['work']['layout'] = Worktype::normalizeLayout($data['work']['layout'] ?? null, 'works');
        if ($existingWork !== $data['work']) {
            $forceWrite = true;
        }
        if (empty($data['id'])) {
            $data['id'] = self::generateId();
        }
        if (empty($data['work'])) {
            $data['work'] = $workDefault;
            $forceWrite = true;
        }
        if (!isset($data['title'])) {
            $data['title'] = '';
        }
        if (!isset($data['description'])) {
            $data['description'] = '';
        }

        // Write only when new or when state changed
        $shouldWrite = true;
        if (is_array($existing) && ($existing['treeHash'] ?? '') === $data['treeHash']) {
            // If hashes match and core metadata unchanged, skip write
            $serializedExisting = json_encode([
                'folderName' => $existing['folderName'] ?? null,
                'slug' => $existing['slug'] ?? null,
                'title' => $existing['title'] ?? null,
                'description' => $existing['description'] ?? null,
                'link' => $existing['link'] ?? null,
                'url' => $existing['url'] ?? null,
            ]);
            $serializedData = json_encode([
                'folderName' => $data['folderName'],
                'slug' => $data['slug'],
                'title' => $data['title'],
                'description' => $data['description'],
                'link' => $data['link'] ?? null,
                'url' => $data['url'] ?? null,
            ]);
            if ($serializedExisting === $serializedData) {
                $shouldWrite = false;
            }
        }

        if ($shouldWrite) {
            $data['updatedAt'] = date('c');
            file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::hydrateConfigLayout($data, $dir);
    }

    /**
     * Ensure a per-file config exists under .works/{filename}.config.json.
     *
     * @return array<string,mixed>
     */
    public static function ensureFileConfig(string $dir, string $fileName): array
    {
        $configPath = self::fileConfigPath($dir, $fileName);
        $defaults = self::defaultFileConfig($dir, $fileName);
        $existing = null;

        if (file_exists($configPath)) {
            $existing = json_decode((string) file_get_contents($configPath), true);
        }

        $data = $defaults;
        $workDefault = self::defaultWork($data['kind'] ?? 'other', $data['mimeType'] ?? null);
        $existingWork = [];
        $forceWrite = false;
        if (is_array($existing)) {
            // Preserve user-editable metadata and visibility
            $data['visible'] = $existing['visible'] ?? $data['visible'];
            $data['title'] = $existing['title'] ?? '';
            $data['description'] = $existing['description'] ?? '';
            if (isset($existing['title'])) {
                $data['title'] = $existing['title'];
            }
            if (isset($existing['description'])) {
                $data['description'] = $existing['description'];
            }
            if (isset($existing['link'])) {
                $data['link'] = $existing['link'];
            }
            if (isset($existing['url'])) {
                $data['url'] = $existing['url'];
            }
            if (isset($existing['id'])) {
                $data['id'] = $existing['id'];
            }
            $existingWork = (isset($existing['work']) && is_array($existing['work'])) ? $existing['work'] : [];
            // Carry any extra custom fields
            foreach ($existing as $k => $v) {
                if (array_key_exists($k, $data)) {
                    continue;
                }
                $data[$k] = $v;
            }
        }
        $data['work'] = array_merge($workDefault, $existingWork);
        $data['work']['layout'] = Worktype::normalizeLayout($data['work']['layout'] ?? null, 'work');
        if ($existingWork !== $data['work']) {
            $forceWrite = true;
        }
        if (empty($data['id'])) {
            $data['id'] = self::generateId();
        }
        if (!isset($data['title'])) {
            $data['title'] = '';
        }
        if (!isset($data['description'])) {
            $data['description'] = '';
        }
        if (empty($data['work'])) {
            $data['work'] = $workDefault;
            $forceWrite = true;
        }

        // Only write when changed
        $serializedExisting = is_array($existing) ? json_encode($existing) : '';
        $serializedData = json_encode($data);
        if ($forceWrite || $serializedExisting !== $serializedData) {
            $dirPath = dirname($configPath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            $data['updatedAt'] = date('c');
            file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return self::hydrateConfigLayout($data, $dir, $fileName);
    }

    public static function persistLayoutFiles(string $dir, ?string $fileName, mixed $layout, string $section = 'work'): array
    {
        $normalized = Worktype::normalizeLayout($layout, $section);
        $layoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $normalized) ? (string) $normalized['template'] : null,
            self::LAYOUT_STYLE_FILE => array_key_exists('css', $normalized) ? (string) $normalized['css'] : null,
            self::LAYOUT_SCRIPT_FILE => array_key_exists('js', $normalized) ? (string) $normalized['js'] : null,
            self::sectionTemplateFile($section) => array_key_exists('sectionTemplate', $normalized) ? (string) $normalized['sectionTemplate'] : null,
        ]);

        return self::serializeLayout($normalized, $section);
    }

    public static function persistOriginalLayoutFiles(string $relativeDir, array $payload): string
    {
        $layoutDir = self::resolveRelativeDirectory($relativeDir);
        if ($layoutDir === null) {
            throw new InvalidArgumentException('Invalid layout source path.');
        }

        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $payload) ? (string) $payload['template'] : null,
            self::LAYOUT_STYLE_FILE => array_key_exists('css', $payload) ? (string) $payload['css'] : null,
            self::LAYOUT_SCRIPT_FILE => array_key_exists('js', $payload) ? (string) $payload['js'] : null,
        ]);

        return str_replace('\\', '/', trim($relativeDir, "/\\"));
    }

    public static function hydrateConfigLayout(array $config, string $dir, ?string $fileName = null): array
    {
        $section = $fileName === null ? 'works' : 'work';
        $work = isset($config['work']) && is_array($config['work']) ? $config['work'] : [];
        $work['layout'] = self::hydrateLayoutFilesystem($work['layout'] ?? null, $dir, $fileName, $section);
        $config['work'] = $work;

        return $config;
    }

    public static function prepareLayoutForView(mixed $layout, string $itemPath, bool $isFile, string $section = 'work'): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        if (($resolved['storage'] ?? '') !== 'filesystem') {
            if (!array_key_exists('css', $resolved) || trim((string) ($resolved['css'] ?? '')) === '') {
                $bundleCss = Worktype::layoutBundleAsset((string) ($resolved['name'] ?? ''), self::LAYOUT_STYLE_FILE);
                if (is_string($bundleCss) && $bundleCss !== '') {
                    $resolved['css'] = $bundleCss;
                }
            }
            if (!array_key_exists('js', $resolved) || trim((string) ($resolved['js'] ?? '')) === '') {
                $bundleJs = Worktype::layoutBundleAsset((string) ($resolved['name'] ?? ''), self::LAYOUT_SCRIPT_FILE);
                if (is_string($bundleJs) && $bundleJs !== '') {
                    $resolved['js'] = $bundleJs;
                }
            }
        }
        $basePath = isset($resolved['directory']) && is_string($resolved['directory']) && trim($resolved['directory']) !== ''
            ? str_replace('\\', '/', trim($resolved['directory'], "/\\"))
            : self::relativeLayoutPath($itemPath, $isFile);
        $resolved['baseHref'] = self::encodeRelativePath($basePath);
        $sectionBasePath = isset($resolved['sectionDirectory']) && is_string($resolved['sectionDirectory']) && trim($resolved['sectionDirectory']) !== ''
            ? str_replace('\\', '/', trim($resolved['sectionDirectory'], "/\\"))
            : $basePath;
        $resolved['sectionBaseHref'] = self::encodeRelativePath($sectionBasePath);
        $defaultBasePath = isset($resolved['defaultDirectory']) && is_string($resolved['defaultDirectory']) && trim($resolved['defaultDirectory']) !== ''
            ? str_replace('\\', '/', trim($resolved['defaultDirectory'], "/\\"))
            : $basePath;
        $resolved['defaultBaseHref'] = self::encodeRelativePath($defaultBasePath);

        $assets = [];
        $files = [];
        foreach (($resolved['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $assetPath = str_replace('\\', '/', (string) ($asset['path'] ?? ''));
            if ($assetPath === '') {
                continue;
            }

            $asset['href'] = self::encodeRelativePath($basePath . '/' . $assetPath);
            $assets[] = $asset;
            $files[$assetPath] = $asset['href'];
        }

        $resolved['assets'] = $assets;
        $resolved['files'] = $files;
        $resolved['assetCount'] = count($assets);

        if (($resolved['storage'] ?? '') === 'filesystem') {
            if (array_key_exists('css', $resolved) && trim((string) $resolved['css']) !== '') {
                $resolved['cssHref'] = self::encodeRelativePath($basePath . '/' . self::LAYOUT_STYLE_FILE);
            }
            if (array_key_exists('js', $resolved) && trim((string) $resolved['js']) !== '') {
                $resolved['jsHref'] = self::encodeRelativePath($basePath . '/' . self::LAYOUT_SCRIPT_FILE);
            }
        }

        return $resolved;
    }

    private static function hydrateLayoutFilesystem(mixed $layout, string $dir, ?string $fileName, string $section): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        $resolved['phpTemplate'] = Worktype::template((string) ($resolved['name'] ?? Worktype::defaultLayoutName())) ?? '';
        $localLayoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $localRelativeDir = $fileName === null ? '.layout' : '.works/' . $fileName . '.layout';
        $layoutDir = null;
        $resolved['directory'] = $localRelativeDir;
        $defaultLayout = self::findDefaultLayoutDir($dir);
        if (is_array($defaultLayout)) {
            $resolved['defaultDirectory'] = $defaultLayout['relative'];
        }

        $assets = [];
        $files = [];
        $sectionTemplateFile = self::sectionTemplateFile($section);
        $sectionTemplatePath = null;
        $resolved['sectionDirectory'] = '';

        if (self::hasWrapperFiles($localLayoutDir)) {
            $layoutDir = $localLayoutDir;
        } elseif (is_array($defaultLayout) && self::hasWrapperFiles($defaultLayout['absolute'])) {
            $layoutDir = $defaultLayout['absolute'];
            $resolved['directory'] = $defaultLayout['relative'];
        }

        if ($layoutDir !== null && is_dir($layoutDir)) {
            $resolved['storage'] = 'filesystem';
            if (in_array($resolved['name'] ?? '', [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)) {
                $resolved['mode'] = Worktype::filesystemLayoutName();
                $resolved['name'] = Worktype::filesystemLayoutName();
            }

            $templatePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_TEMPLATE_FILE;
            if (is_file($templatePath)) {
                $resolved['template'] = (string) file_get_contents($templatePath);
            }

            $stylePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_STYLE_FILE;
            if (is_file($stylePath)) {
                $resolved['css'] = (string) file_get_contents($stylePath);
            }

            $scriptPath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_SCRIPT_FILE;
            if (is_file($scriptPath)) {
                $resolved['js'] = (string) file_get_contents($scriptPath);
            }

            [$assets, $files] = self::scanLayoutAssets($layoutDir);
        } elseif (
            (!empty($resolved['template']) && is_string($resolved['template']))
            || (!empty($resolved['css']) && is_string($resolved['css']))
            || (!empty($resolved['js']) && is_string($resolved['js']))
        ) {
            $resolved['storage'] = 'inline';
        } else {
            $resolved['storage'] = 'default';
            if (in_array($resolved['name'] ?? '', [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)) {
                $resolved['mode'] = Worktype::defaultLayoutName();
                $resolved['name'] = Worktype::defaultLayoutName();
            }
        }

        $localSectionPath = $localLayoutDir . DIRECTORY_SEPARATOR . $sectionTemplateFile;
        if (is_file($localSectionPath)) {
            $sectionTemplatePath = $localSectionPath;
            $resolved['sectionDirectory'] = $localRelativeDir;
        } elseif ($layoutDir !== null) {
            $layoutSectionPath = $layoutDir . DIRECTORY_SEPARATOR . $sectionTemplateFile;
            if (is_file($layoutSectionPath)) {
                $sectionTemplatePath = $layoutSectionPath;
                $resolved['sectionDirectory'] = $resolved['directory'];
            }
        }

        if (is_string($sectionTemplatePath) && $sectionTemplatePath !== '') {
            $resolved['sectionTemplate'] = (string) file_get_contents($sectionTemplatePath);
        }

        $resolved['assets'] = $assets;
        $resolved['files'] = $files;
        $resolved['assetCount'] = count($assets);

        return $resolved;
    }

    private static function findDefaultLayoutDir(string $dir): ?array
    {
        $cwd = realpath(getcwd() ?: '.');
        $current = realpath($dir);
        if ($current === false) {
            return null;
        }

        while ($current !== false) {
            $candidate = $current
                . DIRECTORY_SEPARATOR
                . self::DEFAULT_LAYOUT_ROOT_DIR
                . DIRECTORY_SEPARATOR
                . self::DEFAULT_LAYOUT_FOLDER;
            if (is_dir($candidate)) {
                return [
                    'absolute' => $candidate,
                    'relative' => self::relativePathFromBase($candidate, $cwd ?: $current),
                ];
            }

            if ($cwd && $current === $cwd) {
                break;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = realpath($parent);
        }

        return null;
    }

    private static function relativePathFromBase(string $path, string $base): string
    {
        $normalizedPath = str_replace('\\', '/', rtrim($path, DIRECTORY_SEPARATOR));
        $normalizedBase = str_replace('\\', '/', rtrim($base, DIRECTORY_SEPARATOR));
        if ($normalizedPath === $normalizedBase) {
            return '.';
        }
        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return ltrim($normalizedPath, '/');
    }

    private static function serializeLayout(mixed $layout, string $section): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        $serialized = [
            'mode' => $resolved['mode'],
            'name' => $resolved['name'],
            'engine' => $resolved['engine'],
            'section' => $resolved['section'],
        ];

        foreach (['model', 'stylePrompt'] as $key) {
            if (array_key_exists($key, $resolved)) {
                $serialized[$key] = $resolved[$key];
            }
        }

        return $serialized;
    }

    private static function scanLayoutAssets(string $layoutDir): array
    {
        $assets = [];
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($layoutDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $pathName = $fileInfo->getPathname();
            $relativePath = substr($pathName, strlen($layoutDir) + 1);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $relativePath);
            if (in_array($relativePath, [self::LAYOUT_TEMPLATE_FILE, self::LAYOUT_STYLE_FILE, self::LAYOUT_SCRIPT_FILE], true)) {
                continue;
            }

            $asset = [
                'name' => basename($relativePath),
                'path' => $relativePath,
                'size' => $fileInfo->getSize(),
                'updatedAt' => date('c', $fileInfo->getMTime()),
            ];
            $assets[] = $asset;
            $files[$relativePath] = $relativePath;
        }

        usort($assets, static fn(array $left, array $right): int => strcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? '')));

        return [$assets, $files];
    }

    private static function isDirectoryEmpty(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return false;
            }
        }

        return true;
    }

    private static function hasWrapperFiles(string $layoutDir): bool
    {
        foreach ([self::LAYOUT_TEMPLATE_FILE, self::LAYOUT_STYLE_FILE, self::LAYOUT_SCRIPT_FILE] as $fileName) {
            if (is_file($layoutDir . DIRECTORY_SEPARATOR . $fileName)) {
                return true;
            }
        }

        return false;
    }

    private static function sectionTemplateFile(string $section): string
    {
        return $section === 'works'
            ? self::WORKS_SECTION_TEMPLATE_FILE
            : self::WORK_SECTION_TEMPLATE_FILE;
    }

    private static function writeManagedLayoutFiles(string $layoutDir, array $managedFiles): void
    {
        foreach ($managedFiles as $name => $contents) {
            if ($contents === null) {
                continue;
            }

            $targetPath = $layoutDir . DIRECTORY_SEPARATOR . $name;
            if (trim($contents) === '') {
                if (is_file($targetPath)) {
                    unlink($targetPath);
                }
                continue;
            }

            if (!is_dir($layoutDir)) {
                mkdir($layoutDir, 0755, true);
            }

            file_put_contents($targetPath, $contents);
        }

        if (self::isDirectoryEmpty($layoutDir)) {
            @rmdir($layoutDir);
        }
    }

    private static function resolveRelativeDirectory(string $relativeDir): ?string
    {
        $base = realpath(getcwd() ?: '.');
        if ($base === false) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($relativeDir, "/\\"));
        if ($normalized === '') {
            return null;
        }

        $parts = array_filter(explode('/', $normalized), static fn(string $part): bool => $part !== '');
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                return null;
            }
        }

        return $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    private static function encodeRelativePath(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

        return implode('/', $encoded);
    }
}
