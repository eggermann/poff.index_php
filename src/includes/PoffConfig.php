<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

require_once __DIR__ . '/project-root.php';
require_once __DIR__ . '/PoffConfig/layout-helpers.php';
require_once __DIR__ . '/prompt-template-sanitize.php';
require_once __DIR__ . '/viewer/link-targets.php';

class PoffConfig
{
    private const DEFAULT_LAYOUT_FOLDER = '.layout';
    private const LAYOUT_TEMPLATE_FILE = 'template.hbs';
    private const LAYOUT_STYLE_FILE = 'style.css';
    private const LAYOUT_SCRIPT_FILE = 'script.js';
    private const WORK_SECTION_TEMPLATE_FILE = 'work.hbs';
    private const WORKS_SECTION_TEMPLATE_FILE = 'works.hbs';

    use PoffConfigLayoutHelpers;

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

            $mergedNames = [];
            foreach ($mergedTree as $item) {
                if (isset($item['name']) && is_string($item['name']) && $item['name'] !== '') {
                    $mergedNames[$item['name']] = true;
                }
            }

            foreach ($existing['tree'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '' || isset($mergedNames[$name])) {
                    continue;
                }
                if (cmsConfiguredTreeLinkTarget($item) === '') {
                    continue;
                }

                $virtualItem = [
                    'name' => $name,
                    'slug' => (string) ($item['slug'] ?? self::slugify($name)),
                    'type' => (string) ($item['type'] ?? 'file'),
                    'path' => (string) ($item['path'] ?? $name),
                    'modifiedAt' => $item['modifiedAt'] ?? null,
                    'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
                ];

                foreach ($item as $key => $value) {
                    if (array_key_exists($key, $virtualItem)) {
                        continue;
                    }
                    $virtualItem[$key] = $value;
                }

                $mergedTree[] = $virtualItem;
                $mergedNames[$name] = true;
            }
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
        $layoutMode = trim((string) ($normalized['mode'] ?? ''));
        $layoutName = trim((string) ($normalized['name'] ?? ''));
        $layoutPreset = trim((string) ($normalized['preset'] ?? ''));
        $isSharedPreset = $layoutPreset === 'shared'
            || $layoutMode === 'shared'
            || (($normalized['source'] ?? '') === 'shared')
            || (($normalized['sharedName'] ?? '') !== '');
        $inactivePreset = $layoutMode === 'none'
            || $layoutName === 'none'
            || $layoutPreset === 'none'
            || (
                $layoutPreset === 'actual'
                && in_array($layoutName, [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)
            );
        $isCustomPreset = $layoutMode === 'custom-layout'
            || $layoutName === 'custom-layout'
            || $layoutPreset === 'custom';
        if (array_key_exists('template', $normalized)) {
            $normalized['template'] = self::sanitizeStoredPromptTemplate((string) $normalized['template'], true);
        }
        foreach (['sectionTemplate', 'workTemplate', 'worksTemplate'] as $sectionKey) {
            if (array_key_exists($sectionKey, $normalized)) {
                $normalized[$sectionKey] = self::sanitizeStoredPromptTemplate((string) $normalized[$sectionKey], false);
            }
        }
        if ($inactivePreset) {
            foreach (['template', 'css', 'js', 'sectionTemplate', 'workTemplate', 'worksTemplate'] as $key) {
                unset($normalized[$key]);
            }
        }
        if ($isSharedPreset) {
            return $normalized;
        }
        $layoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $managedLayoutKeys = ['template', 'css', 'js', 'sectionTemplate', 'workTemplate', 'worksTemplate'];
        $providedManagedKeys = array_values(array_filter(
            $managedLayoutKeys,
            static fn(string $key): bool => array_key_exists($key, $normalized)
        ));
        $allProvidedManagedValuesEmpty = $providedManagedKeys !== [];
        foreach ($providedManagedKeys as $key) {
            if (trim((string) $normalized[$key]) !== '') {
                $allProvidedManagedValuesEmpty = false;
                break;
            }
        }
        $hasExistingManagedLayoutFile = self::hasWrapperFiles($layoutDir)
            || is_file($layoutDir . DIRECTORY_SEPARATOR . self::sectionTemplateFile($section));
        if ($isCustomPreset && $allProvidedManagedValuesEmpty && $hasExistingManagedLayoutFile) {
            foreach ($providedManagedKeys as $key) {
                unset($normalized[$key]);
            }
        }
        self::writeManagedLayoutFiles($layoutDir, self::defaultLayoutFiles($section));
        $sectionFiles = [];
        if (array_key_exists('sectionTemplate', $normalized)) {
            $sectionFiles[self::sectionTemplateFile($section)] = (string) $normalized['sectionTemplate'];
        }
        if (array_key_exists('workTemplate', $normalized)) {
            $sectionFiles[self::WORK_SECTION_TEMPLATE_FILE] = (string) $normalized['workTemplate'];
        }
        if (array_key_exists('worksTemplate', $normalized)) {
            $sectionFiles[self::WORKS_SECTION_TEMPLATE_FILE] = (string) $normalized['worksTemplate'];
        }

        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $normalized) ? (string) $normalized['template'] : null,
            self::LAYOUT_STYLE_FILE => array_key_exists('css', $normalized) ? (string) $normalized['css'] : null,
            self::LAYOUT_SCRIPT_FILE => array_key_exists('js', $normalized) ? (string) $normalized['js'] : null,
        ] + $sectionFiles);

        return self::serializeLayout($normalized, $section);
    }

    public static function persistSectionTemplate(string $dir, ?string $fileName, string $sectionTemplate, string $section = 'work'): string
    {
        $layoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $sanitized = self::sanitizeStoredPromptTemplate($sectionTemplate, false);
        self::writeManagedLayoutFiles($layoutDir, [
            self::sectionTemplateFile($section) => $sanitized,
        ]);

        return $sanitized;
    }

    public static function persistOriginalLayoutFiles(string $relativeDir, array $payload): string
    {
        $layoutDir = self::resolveRelativeDirectory($relativeDir);
        if ($layoutDir === null) {
            throw new InvalidArgumentException('Invalid layout source path.');
        }

        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $payload) ? self::sanitizeStoredPromptTemplate((string) $payload['template'], true) : null,
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
        $workType = trim((string) ($work['type'] ?? ($fileName === null ? 'folder' : 'other')));
        $defaultSectionTemplate = Worktype::template($workType);
        if (is_string($defaultSectionTemplate) && $defaultSectionTemplate !== '') {
            $work['layout']['defaultSectionTemplate'] = $defaultSectionTemplate;
        }
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
        $mode = trim((string) ($resolved['mode'] ?? ''));
        $name = trim((string) ($resolved['name'] ?? ''));
        $preset = trim((string) ($resolved['preset'] ?? ''));
        $isNoneLayout = $mode === 'none' || $name === 'none' || $preset === 'none';

        if ($isNoneLayout) {
            $resolved['mode'] = 'none';
            $resolved['name'] = 'none';
            $resolved['storage'] = 'none';
            $resolved['directory'] = '';
            $resolved['inheritedDirectory'] = '';
            $resolved['sectionDirectory'] = '';
            $resolved['template'] = '';
            $resolved['css'] = '';
            $resolved['js'] = '';
            $resolved['sectionTemplate'] = '';
            $resolved['assets'] = [];
            $resolved['files'] = [];
            $resolved['assetCount'] = 0;
            unset($resolved['cssHref'], $resolved['jsHref'], $resolved['phpTemplate']);

            return $resolved;
        }

        if (array_key_exists('template', $resolved)) {
            $resolved['template'] = self::sanitizeStoredPromptTemplate((string) $resolved['template'], true);
        }
        if (array_key_exists('sectionTemplate', $resolved)) {
            $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) $resolved['sectionTemplate'], false);
        }
        $resolved['phpTemplate'] = Worktype::template((string) ($resolved['name'] ?? Worktype::defaultLayoutName())) ?? '';
        $resolved['sharedLayouts'] = Worktype::sharedLayoutChoices($section);
        $sharedName = trim((string) ($resolved['sharedName'] ?? ''));
        $sharedPreset = $preset === 'shared'
            || $mode === 'shared'
            || (($resolved['source'] ?? '') === 'shared');
        if ($sharedPreset) {
            if ($sharedName === '' && isset($resolved['sharedLayouts'][0]['name'])) {
                $sharedName = trim((string) $resolved['sharedLayouts'][0]['name']);
            }
            $sharedPackage = $sharedName !== '' ? Worktype::sharedLayoutPackage($section, $sharedName) : null;
            if (is_array($sharedPackage)) {
                $resolved['storage'] = 'shared';
                $resolved['source'] = 'shared';
                $resolved['sharedName'] = $sharedName;
                $resolved['directory'] = 'shared/' . $section . '/' . $sharedName;
                $resolved['sectionDirectory'] = $resolved['directory'];
                if (!array_key_exists('template', $resolved) || trim((string) $resolved['template']) === '') {
                    $resolved['template'] = self::sanitizeStoredPromptTemplate((string) ($sharedPackage['template'] ?? ''), true);
                }
                if (!array_key_exists('css', $resolved) || trim((string) ($resolved['css'] ?? '')) === '') {
                    $resolved['css'] = (string) ($sharedPackage['css'] ?? '');
                }
                if (!array_key_exists('js', $resolved) || trim((string) ($resolved['js'] ?? '')) === '') {
                    $resolved['js'] = (string) ($sharedPackage['js'] ?? '');
                }
                if (!array_key_exists('sectionTemplate', $resolved) || trim((string) ($resolved['sectionTemplate'] ?? '')) === '') {
                    $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) ($sharedPackage['sectionTemplate'] ?? ''), false);
                }
                $resolved['phpTemplate'] = (string) ($sharedPackage['template'] ?? $resolved['phpTemplate'] ?? '');
                $resolved['assets'] = [];
                $resolved['files'] = [];
                $resolved['assetCount'] = 0;

                return $resolved;
            }
        }
        $localLayoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $localRelativeDir = $fileName === null ? '.layout' : '.works/' . $fileName . '.layout';
        $layoutDir = null;
        $resolved['directory'] = $localRelativeDir;
        $inheritedLayout = self::findInheritedLayoutDir($dir, $localLayoutDir);
        if (is_array($inheritedLayout)) {
            $resolved['inheritedDirectory'] = $inheritedLayout['relative'];
        }

        $assets = [];
        $files = [];
        $sectionTemplateFile = self::sectionTemplateFile($section);
        $sectionTemplatePath = null;
        $resolved['sectionDirectory'] = '';

        if (self::hasWrapperFiles($localLayoutDir)) {
            $layoutDir = $localLayoutDir;
        } elseif (is_array($inheritedLayout) && self::hasWrapperFiles($inheritedLayout['absolute'])) {
            $layoutDir = $inheritedLayout['absolute'];
            $resolved['directory'] = $inheritedLayout['relative'];
        }

        if ($layoutDir !== null && is_dir($layoutDir)) {
            $resolved['storage'] = 'filesystem';
            if (in_array($resolved['name'] ?? '', [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)) {
                $resolved['mode'] = Worktype::filesystemLayoutName();
                $resolved['name'] = Worktype::filesystemLayoutName();
            }

            $templatePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_TEMPLATE_FILE;
            if (is_file($templatePath)) {
                $resolved['template'] = self::sanitizeStoredPromptTemplate((string) file_get_contents($templatePath), true);
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
            $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) file_get_contents($sectionTemplatePath), false);
        } elseif ($section === 'work') {
            $sharedLayout = Worktype::sharedLayoutPackage($section, (string) ($resolved['name'] ?? Worktype::defaultLayoutName()));
            if (is_array($sharedLayout) && isset($sharedLayout['sectionTemplate']) && is_string($sharedLayout['sectionTemplate']) && trim($sharedLayout['sectionTemplate']) !== '') {
                $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) $sharedLayout['sectionTemplate'], false);
            }
        }

        $resolved['assets'] = $assets;
        $resolved['files'] = $files;
        $resolved['assetCount'] = count($assets);

        return $resolved;
    }

    private static function sanitizeStoredPromptTemplate(string $value, bool $isLayoutTarget = true, int $depth = 0): string
    {
        if ($depth > 3) {
            return cmsSanitizePromptTemplateForTarget((string) $value, $isLayoutTarget);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $decoded = self::decodeStoredPromptPayload($trimmed);
        if (!is_array($decoded)) {
            return cmsSanitizePromptTemplateForTarget($value, $isLayoutTarget);
        }

        $extracted = self::extractStoredPromptTemplateFromPayload($decoded, $isLayoutTarget, $depth + 1);
        if ($extracted === null) {
            return '';
        }

        return cmsSanitizePromptTemplateForTarget($extracted, $isLayoutTarget);
    }

    private static function extractStoredPromptTemplateFromPayload(array $decoded, bool $isLayoutTarget, int $depth): ?string
    {
        $template = null;
        if (isset($decoded['choices'][0]['message']['content'])) {
            $content = $decoded['choices'][0]['message']['content'];
            if (is_string($content)) {
                $template = trim($content);
            } elseif (is_array($content)) {
                $parts = [];
                foreach ($content as $part) {
                    if (is_string($part) && trim($part) !== '') {
                        $parts[] = trim($part);
                        continue;
                    }
                    if (is_array($part) && isset($part['text']) && is_scalar($part['text'])) {
                        $parts[] = trim((string) $part['text']);
                    }
                }
                $template = trim(implode("\n", array_filter($parts)));
            }
        } elseif (isset($decoded['choices'][0]['text']) && is_scalar($decoded['choices'][0]['text'])) {
            $template = trim((string) $decoded['choices'][0]['text']);
        } elseif (isset($decoded['message']['content']) && is_scalar($decoded['message']['content'])) {
            $template = trim((string) $decoded['message']['content']);
        } elseif (isset($decoded['response']) && is_scalar($decoded['response'])) {
            $template = trim((string) $decoded['response']);
        } elseif (isset($decoded['template'])) {
            $template = trim((string) $decoded['template']);
        } elseif (isset($decoded['content'])) {
            $template = trim((string) $decoded['content']);
        }

        if ($template === null) {
            return null;
        }

        if ($template === '') {
            return '';
        }

        return self::sanitizeStoredPromptTemplate($template, $isLayoutTarget, $depth);
    }

    private static function storedPromptResponseCandidates(string $raw): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];

        if (preg_match_all('/```(?:[a-z0-9_-]+)?\s*([\s\S]*?)```/i', $trimmed, $matches)) {
            foreach ($matches[1] as $match) {
                $candidate = trim((string) $match);
                if ($candidate !== '') {
                    $candidates[] = $candidate;
                }
            }
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = trim(substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1));
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        $normalized = [];
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $normalized, true)) {
                continue;
            }
            $normalized[] = $candidate;
        }

        return $normalized;
    }

    private static function decodeStoredPromptLooseString(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $decoded = json_decode($trimmed, true);
        if (is_string($decoded)) {
            return $decoded;
        }

        if ($trimmed[0] === '"') {
            $trimmed = substr($trimmed, 1);
        }
        if ($trimmed !== '' && substr($trimmed, -1) === '"') {
            $trimmed = substr($trimmed, 0, -1);
        }

        return stripcslashes($trimmed);
    }

    private static function extractStoredPromptLooseScalarField(string $payload, string $key): ?string
    {
        $knownKeys = [
            'template',
            'css',
            'style',
            'js',
            'script',
            'work',
            'title',
            'description',
            'model',
            'content',
            'response',
        ];
        $otherKeys = array_values(array_filter($knownKeys, static fn (string $candidate): bool => $candidate !== $key));
        $otherKeysPattern = implode('|', array_map(static fn (string $candidate): string => preg_quote($candidate, '/'), $otherKeys));
        $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*([\s\S]*?)(?=\s*(?:,\s*"(?:' . $otherKeysPattern . ')"\s*:|\}\s*$|$))/i';
        if (preg_match($pattern, $payload, $matches) !== 1) {
            return null;
        }

        return self::decodeStoredPromptLooseString((string) $matches[1]);
    }

    private static function decodeStoredPromptPayload(string $raw): ?array
    {
        $candidates = self::storedPromptResponseCandidates($raw);
        foreach ($candidates as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        foreach ($candidates as $candidate) {
            if (!preg_match('/"(template|css|style|js|script|title|description|model|content|response)"\s*:/i', $candidate)) {
                continue;
            }

            $result = [];
            foreach (['template', 'css', 'style', 'js', 'script', 'title', 'description', 'model', 'content', 'response'] as $key) {
                $value = self::extractStoredPromptLooseScalarField($candidate, $key);
                if ($value !== null) {
                    $result[$key] = $value;
                }
            }

            if ($result !== []) {
                return $result;
            }
        }

        return null;
    }

    private static function findInheritedLayoutDir(string $dir, string $localLayoutDir): ?array
    {
        $cwd = realpath(getcwd() ?: '.');
        $current = realpath($dir);
        if ($current === false) {
            return null;
        }

        $localLayoutRealpath = realpath($localLayoutDir) ?: $localLayoutDir;

        while ($current !== false) {
            $candidate = $current . DIRECTORY_SEPARATOR . self::DEFAULT_LAYOUT_FOLDER;
            if ($candidate !== $localLayoutRealpath && is_dir($candidate)) {
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

        foreach (['preset', 'source', 'sharedName', 'model', 'stylePrompt'] as $key) {
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
