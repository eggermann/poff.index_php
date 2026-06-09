<?php

trait WorktypeLayoutTrait
{
    use WorktypeDefinitionsTrait;

    use WorktypeStateTrait;

    public static function templates(): array
    {
        static $cache = null;

        if (is_array($cache)) {
            return $cache;
        }

        self::loadBundle();

        $templates = self::$embeddedTemplates;
        foreach (self::$bundleTemplates as $key => $template) {
            $templates[$key] = $template;
        }

        foreach (self::templateEntries('hbs') as $key => $path) {
            $templates[$key] = (string) file_get_contents($path);
        }

        foreach (self::templateEntries('tpl') as $key => $path) {
            if (!isset($templates[$key])) {
                $templates[$key] = (string) file_get_contents($path);
            }
        }

        foreach (array_keys(self::$bundleDefinitions + self::$embedded) as $kind) {
            self::loadFilePair((string) $kind);
        }
        foreach (self::$fileTemplates as $key => $template) {
            $templates[$key] = $template;
        }

        $cache = $templates;

        return $templates;
    }

    public static function template(string $name): ?string
    {
        static $cache = [];

        if (array_key_exists($name, $cache)) {
            return $cache[$name];
        }

        self::loadBundle();
        self::loadFilePair($name);

        if (isset(self::$fileTemplates[$name])) {
            return $cache[$name] = self::$fileTemplates[$name];
        }
        if (isset(self::$bundleTemplates[$name])) {
            return $cache[$name] = self::$bundleTemplates[$name];
        }
        foreach (['.hbs', '.tpl'] as $extension) {
            $entryMap = self::templateEntries(ltrim($extension, '.'), $name);
            if (isset($entryMap[$name]) && file_exists($entryMap[$name])) {
                return $cache[$name] = (string) file_get_contents($entryMap[$name]);
            }
        }
        if (isset(self::$embeddedTemplates[$name])) {
            return $cache[$name] = self::$embeddedTemplates[$name];
        }

        $cache[$name] = null;

        return null;
    }

    public static function layoutTemplate(string $kind, array $work = []): string
    {
        $layout = self::normalizeLayout($work['layout'] ?? null, $kind === 'folder' ? 'works' : 'work');
        if (($layout['name'] ?? '') === 'none' || ($layout['mode'] ?? '') === 'none') {
            return $kind === 'folder' ? '{{> works}}' : '{{> work}}';
        }
        if (!empty($layout['template']) && is_string($layout['template'])) {
            return $layout['template'];
        }

        $sharedName = trim((string) ($layout['sharedName'] ?? ''));
        $isSharedLayout = in_array((string) ($layout['preset'] ?? ''), ['shared'], true)
            || in_array((string) ($layout['source'] ?? ''), ['shared'], true)
            || in_array((string) ($layout['storage'] ?? ''), ['shared'], true);
        if ($isSharedLayout) {
            if ($sharedName === '') {
                $sharedName = trim((string) ($layout['name'] ?? ''));
            }
        }
        if ($sharedName !== '') {
            $sharedLayout = self::sharedLayoutPackage($kind === 'folder' ? 'works' : 'work', $sharedName);
            if (is_array($sharedLayout) && is_string($sharedLayout['template'] ?? null) && trim((string) $sharedLayout['template']) !== '') {
                return (string) $sharedLayout['template'];
            }
        }

        $namedTemplate = self::template($layout['name']);
        if (is_string($namedTemplate) && $namedTemplate !== '') {
            return $namedTemplate;
        }

        $defaultTemplate = self::template(self::defaultLayoutName());
        if (is_string($defaultTemplate) && $defaultTemplate !== '') {
            return $defaultTemplate;
        }

        return '';
    }

    public static function sharedLayoutChoices(string $section): array
    {
        $choices = [];
        $layoutBase = __DIR__ . '/../worktypes/templates/layout';
        foreach ((glob($layoutBase . '/*/template.hbs') ?: []) as $path) {
            $name = basename(dirname($path));
            if ($name === '' || $name === 'shared') {
                continue;
            }
            $package = self::sharedLayoutPackage($section, $name);
            if (is_array($package)) {
                $package['source'] = in_array($name, [self::defaultLayoutName(), self::filesystemLayoutName()], true)
                    ? 'bundled'
                    : 'shared';
                $choices[] = $package;
            }
        }

        usort($choices, static fn(array $left, array $right): int => strcasecmp((string) ($left['folderName'] ?? $left['label'] ?? ''), (string) ($right['folderName'] ?? $right['label'] ?? '')));

        return $choices;
    }

    public static function sharedLayoutPackage(string $section, string $name): ?array
    {
        static $cache = [];

        $cacheKey = $section . '|' . $name;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $directory = self::sharedLayoutDirectory($section, $name);
        if ($directory === null) {
            $cache[$cacheKey] = null;
            return null;
        }

        $template = self::readLayoutPackageFile($directory, 'template.hbs');
        if (!is_string($template) || trim($template) === '') {
            $cache[$cacheKey] = null;
            return null;
        }

        $cache[$cacheKey] = [
            'name' => $name,
            'folderName' => self::sharedLayoutFolderName($directory),
            'label' => self::sharedLayoutFolderName($directory),
            'section' => $section,
            'directory' => $directory,
            'template' => $template,
            'css' => self::readLayoutPackageFile($directory, 'style.css') ?? '',
            'js' => self::readLayoutPackageFile($directory, 'script.js') ?? '',
            'sectionTemplate' => self::readLayoutPackageFile($directory, $section === 'works' ? 'works.hbs' : 'work.hbs') ?? '',
        ];

        return $cache[$cacheKey];
    }

    private static function sharedLayoutFolderName(string $directory): string
    {
        $folderName = basename(rtrim($directory, DIRECTORY_SEPARATOR));
        return trim($folderName) !== '' ? $folderName : 'shared';
    }

    private static function sharedLayoutDirectory(string $section, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        if (in_array($name, [self::defaultLayoutName(), self::filesystemLayoutName()], true)) {
            return self::layoutBundleDirectory($name);
        }

        $candidate = __DIR__ . '/../worktypes/templates/layout/' . $name;
        if (is_dir($candidate) && is_file($candidate . DIRECTORY_SEPARATOR . 'template.hbs')) {
            return $candidate;
        }

        $candidate = __DIR__ . '/../worktypes/templates/layout/shared/' . $section . '/' . $name;
        return is_dir($candidate) ? $candidate : null;
    }

    private static function readLayoutPackageFile(string $directory, string $file): ?string
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, '/');
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public static function layoutBundleAsset(string $name, string $file): ?string
    {
        static $cache = [];

        $cacheKey = self::canonicalLayoutName($name) . '|' . $file;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $path = self::layoutBundleAssetPath($name, $file);
        if ($path === null || !is_file($path)) {
            $layoutName = self::canonicalLayoutName($name);
            if (isset(self::$embeddedLayoutAssets[$layoutName][$file]) && is_string(self::$embeddedLayoutAssets[$layoutName][$file])) {
                $embedded = trim(self::$embeddedLayoutAssets[$layoutName][$file]);
                $cache[$cacheKey] = $embedded !== '' ? self::$embeddedLayoutAssets[$layoutName][$file] : null;
                return $cache[$cacheKey];
            }

            $cache[$cacheKey] = null;
            return null;
        }

        $cache[$cacheKey] = (string) file_get_contents($path);

        return $cache[$cacheKey];
    }

    private static function templateEntries(string $extension, ?string $name = null): array
    {
        $base = __DIR__ . '/../worktypes/templates';
        $normalizedExtension = ltrim($extension, '.');
        $entries = [];

        foreach ((glob($base . '/*.' . $normalizedExtension) ?: []) as $path) {
            $entries[pathinfo($path, PATHINFO_FILENAME)] = $path;
        }

        foreach (self::layoutBundleTemplateMap($normalizedExtension) as $key => $path) {
            if (file_exists($path)) {
                $entries[$key] = $path;
            }
        }

        if ($name !== null) {
            return isset($entries[$name]) ? [$name => $entries[$name]] : [];
        }

        return $entries;
    }

    private static function layoutBundleTemplateMap(string $extension): array
    {
        $base = __DIR__ . '/../worktypes/templates/layout';

        return [
            self::defaultLayoutName() => $base . '/default/template.' . $extension,
            self::filesystemLayoutName() => $base . '/file-system/template.' . $extension,
        ];
    }

    private static function layoutBundleDirectory(string $name): ?string
    {
        return match (self::canonicalLayoutName($name)) {
            self::defaultLayoutName() => __DIR__ . '/../worktypes/templates/layout/default',
            self::filesystemLayoutName() => __DIR__ . '/../worktypes/templates/layout/file-system',
            default => null,
        };
    }

    private static function layoutBundleAssetPath(string $name, string $file): ?string
    {
        $directory = self::layoutBundleDirectory($name);
        if ($directory === null) {
            return null;
        }

        return $directory . '/' . ltrim($file, '/');
    }
}
