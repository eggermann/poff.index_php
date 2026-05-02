<?php
/**
 * Worktype helper for media layout defaults and overrides.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class Worktype
{
    private const DEFAULT_LAYOUT_NAME = 'poff-layout';
    private const FILESYSTEM_LAYOUT_NAME = 'filesystem-layout';

    private static array $embedded = [];
    private static array $embeddedTemplates = [];
    private static array $bundleDefinitions = [];
    private static array $bundleTemplates = [];
    private static bool $bundleLoaded = false;
    private static array $fileDefinitions = [];
    private static array $fileTemplates = [];

    /**
     * Load a worktype definition for a given kind, preferring /includes/worktypes overrides.
     */
    public static function definition(string $kind, ?string $mime = null): array
    {
        $kind = strtolower($kind);
        self::loadBundle();
        self::loadFilePair($kind);

        if (isset(self::$fileDefinitions[$kind])) {
            $base = self::$fileDefinitions[$kind];
        } elseif (isset(self::$bundleDefinitions[$kind])) {
            $base = self::$bundleDefinitions[$kind];
        } else {
            $base = null;
        }

        if (!$base && isset(self::$embedded[$kind])) {
            $base = self::$embedded[$kind];
        }
        if (!$base && isset(self::$bundleDefinitions['other'])) {
            $base = self::$bundleDefinitions['other'];
        }
        if (!$base && isset(self::$embedded['other'])) {
            $base = self::$embedded['other'];
        }
        if (!$base && isset(self::$fileDefinitions['other'])) {
            $base = self::$fileDefinitions['other'];
        }
        if (!$base) {
            $base = ['type' => $kind];
        }

        if ($mime && ($base['type'] ?? $kind) === 'text' && empty($base['syntax'])) {
            $base['syntax'] = $mime;
        }

        $base['type'] = $base['type'] ?? $kind;
        $base['layout'] = self::normalizeLayout($base['layout'] ?? null, $base['type'] === 'folder' ? 'works' : 'work');

        return $base;
    }

    /**
     * Render body content for a given kind using templates.
     */
    public static function render(string $kind, array $ctx): string
    {
        $work = $ctx['work'] ?? [];
        $layout = self::normalizeLayout(is_array($work) ? ($work['layout'] ?? null) : null, $kind === 'folder' ? 'works' : 'work');
        $resolvedWork = is_array($work) ? $work : [];
        $resolvedWork['layout'] = $layout;
        $rendered = self::renderTemplate($kind, $ctx, $resolvedWork, $layout);
        if ($rendered !== null) {
            return $rendered;
        }

        $fallbackLayout = self::normalizeLayout([
            'mode' => self::DEFAULT_LAYOUT_NAME,
            'name' => self::DEFAULT_LAYOUT_NAME,
            'engine' => 'lightncandy',
            'section' => $kind === 'folder' ? 'works' : 'work',
        ], $kind === 'folder' ? 'works' : 'work');
        $fallbackWork = $resolvedWork;
        $fallbackWork['layout'] = $fallbackLayout;

        $fallbackRendered = self::renderTemplate($kind, $ctx, $fallbackWork, $fallbackLayout);
        if ($fallbackRendered !== null) {
            return $fallbackRendered;
        }

        return self::fallbackRender($kind, $ctx);
    }

    public static function normalizeLayout(mixed $value, string $section = 'work'): array
    {
        $layout = [
            'mode' => self::DEFAULT_LAYOUT_NAME,
            'name' => self::DEFAULT_LAYOUT_NAME,
            'engine' => 'lightncandy',
            'section' => $section,
        ];

        if (is_string($value) && trim($value) !== '') {
            $name = trim($value);
            $layout['mode'] = self::normalizeLayoutMode($name);
            $layout['name'] = self::canonicalLayoutName($name);
            return $layout;
        }

        if (!is_array($value)) {
            return $layout;
        }

        $candidate = trim((string) ($value['name'] ?? $value['mode'] ?? $value['value'] ?? ''));
        if ($candidate !== '') {
            $layout['mode'] = self::normalizeLayoutMode($candidate);
            $layout['name'] = self::canonicalLayoutName($candidate);
        }

        if (isset($value['engine']) && is_string($value['engine']) && trim($value['engine']) !== '') {
            $layout['engine'] = trim($value['engine']);
        }

        if (isset($value['section']) && is_string($value['section']) && trim($value['section']) !== '') {
            $layout['section'] = trim($value['section']);
        }

        if (array_key_exists('css', $value) || array_key_exists('style', $value)) {
            $layout['css'] = (string) ($value['css'] ?? $value['style'] ?? '');
        }

        if (array_key_exists('js', $value) || array_key_exists('script', $value)) {
            $layout['js'] = (string) ($value['js'] ?? $value['script'] ?? '');
        }

        foreach ([
            'preset',
            'source',
            'sharedName',
            'template',
            'model',
            'stylePrompt',
            'storage',
            'directory',
            'inheritedDirectory',
            'baseHref',
            'sectionTemplate',
            'workTemplate',
            'worksTemplate',
            'sectionDirectory',
            'sectionBaseHref',
            'cssHref',
            'jsHref',
            'assets',
            'files',
            'assetCount',
        ] as $key) {
            if (array_key_exists($key, $value)) {
                $layout[$key] = $value[$key];
            }
        }

        return $layout;
    }

    public static function templates(): array
    {
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

        return $templates;
    }

    public static function template(string $name): ?string
    {
        self::loadBundle();
        self::loadFilePair($name);

        if (isset(self::$fileTemplates[$name])) {
            return self::$fileTemplates[$name];
        }
        if (isset(self::$bundleTemplates[$name])) {
            return self::$bundleTemplates[$name];
        }
        foreach (['.hbs', '.tpl'] as $extension) {
            $entryMap = self::templateEntries(ltrim($extension, '.'), $name);
            if (isset($entryMap[$name]) && file_exists($entryMap[$name])) {
                return (string) file_get_contents($entryMap[$name]);
            }
        }
        if (isset(self::$embeddedTemplates[$name])) {
            return self::$embeddedTemplates[$name];
        }

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

        $defaultTemplate = self::template(self::DEFAULT_LAYOUT_NAME);
        if (is_string($defaultTemplate) && $defaultTemplate !== '') {
            return $defaultTemplate;
        }

        return '';
    }

    public static function defaultLayoutName(): string
    {
        return self::DEFAULT_LAYOUT_NAME;
    }

    public static function filesystemLayoutName(): string
    {
        return self::FILESYSTEM_LAYOUT_NAME;
    }

    public static function sharedLayoutChoices(string $section): array
    {
        $choices = [];
        foreach ([self::DEFAULT_LAYOUT_NAME, self::FILESYSTEM_LAYOUT_NAME] as $name) {
            $package = self::sharedLayoutPackage($section, $name);
            if (is_array($package)) {
                $package['source'] = 'bundled';
                $choices[] = $package;
            }
        }

        $sharedBase = __DIR__ . '/worktypes/templates/layout/shared/' . $section;
        foreach ((glob($sharedBase . '/*/template.hbs') ?: []) as $path) {
            $name = basename(dirname($path));
            if ($name === '' || in_array($name, [self::DEFAULT_LAYOUT_NAME, self::FILESYSTEM_LAYOUT_NAME], true)) {
                continue;
            }
            $package = self::sharedLayoutPackage($section, $name);
            if (is_array($package)) {
                $package['source'] = 'shared';
                $choices[] = $package;
            }
        }

        usort($choices, static fn(array $left, array $right): int => strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? '')));

        return $choices;
    }

    public static function sharedLayoutPackage(string $section, string $name): ?array
    {
        $directory = self::sharedLayoutDirectory($section, $name);
        if ($directory === null) {
            return null;
        }

        $template = self::readLayoutPackageFile($directory, 'template.hbs');
        if (!is_string($template) || trim($template) === '') {
            return null;
        }

        return [
            'name' => $name,
            'label' => self::sharedLayoutLabel($name),
            'section' => $section,
            'directory' => $directory,
            'template' => $template,
            'css' => self::readLayoutPackageFile($directory, 'style.css') ?? '',
            'js' => self::readLayoutPackageFile($directory, 'script.js') ?? '',
            'sectionTemplate' => self::readLayoutPackageFile($directory, $section === 'works' ? 'works.hbs' : 'work.hbs') ?? '',
        ];
    }

    private static function sharedLayoutLabel(string $name): string
    {
        return ucwords(str_replace(['-', '_'], ' ', trim($name)));
    }

    private static function sharedLayoutDirectory(string $section, string $name): ?string
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        if (in_array($name, [self::DEFAULT_LAYOUT_NAME, self::FILESYSTEM_LAYOUT_NAME], true)) {
            return self::layoutBundleDirectory($name);
        }

        $candidate = __DIR__ . '/worktypes/templates/layout/shared/' . $section . '/' . $name;
        return is_dir($candidate) ? $candidate : null;
    }

    private static function readLayoutPackageFile(string $directory, string $file): ?string
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, '/');
        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    public static function layoutBundleAsset(string $name, string $file): ?string
    {
        $path = self::layoutBundleAssetPath($name, $file);
        if ($path === null || !is_file($path)) {
            return null;
        }

        return (string) file_get_contents($path);
    }

    public static function canonicalLayoutName(?string $name): string
    {
        $candidate = trim((string) $name);
        if ($candidate === '') {
            return self::DEFAULT_LAYOUT_NAME;
        }

        return match ($candidate) {
            'poff', self::DEFAULT_LAYOUT_NAME => self::DEFAULT_LAYOUT_NAME,
            'filesystem', self::FILESYSTEM_LAYOUT_NAME => self::FILESYSTEM_LAYOUT_NAME,
            default => $candidate,
        };
    }

    private static function normalizeLayoutMode(string $mode): string
    {
        $candidate = trim($mode);
        if ($candidate === '') {
            return self::DEFAULT_LAYOUT_NAME;
        }

        return match ($candidate) {
            'poff' => self::DEFAULT_LAYOUT_NAME,
            'filesystem' => self::FILESYSTEM_LAYOUT_NAME,
            default => $candidate,
        };
    }

    private static function rendererAvailable(): bool
    {
        return class_exists('\LightnCandy\LightnCandy');
    }

    private static function templateEntries(string $extension, ?string $name = null): array
    {
        $base = __DIR__ . '/worktypes/templates';
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
        $base = __DIR__ . '/worktypes/templates/layout';

        return [
            self::DEFAULT_LAYOUT_NAME => $base . '/default/template.' . $extension,
            self::FILESYSTEM_LAYOUT_NAME => $base . '/file-system/template.' . $extension,
        ];
    }

    private static function layoutBundleDirectory(string $name): ?string
    {
        return match (self::canonicalLayoutName($name)) {
            self::DEFAULT_LAYOUT_NAME => __DIR__ . '/worktypes/templates/layout/default',
            self::FILESYSTEM_LAYOUT_NAME => __DIR__ . '/worktypes/templates/layout/file-system',
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

    private static function helpers(): array
    {
        return [
            'eq' => static function ($left = null, $right = null, $options = null): bool {
                return $left == $right;
            },
            'ne' => static function ($left = null, $right = null, $options = null): bool {
                return $left != $right;
            },
            'contains' => static function ($haystack = null, $needle = null, $options = null): bool {
                if ($needle === null) {
                    return false;
                }
                if (is_array($haystack)) {
                    return in_array($needle, $haystack, false);
                }

                return str_contains((string) $haystack, (string) $needle);
            },
            'startsWith' => static function ($value = null, $prefix = null, $options = null): bool {
                if ($prefix === null) {
                    return false;
                }

                return str_starts_with((string) $value, (string) $prefix);
            },
            'endsWith' => static function ($value = null, $suffix = null, $options = null): bool {
                if ($suffix === null) {
                    return false;
                }

                return str_ends_with((string) $value, (string) $suffix);
            },
            'not' => static function ($value = null, $options = null): bool {
                return !$value;
            },
            'and' => static function ($left = null, $right = null, $options = null): bool {
                return (bool) $left && (bool) $right;
            },
            'or' => static function ($left = null, $right = null, $options = null): bool {
                return (bool) $left || (bool) $right;
            },
        ];
    }

    private static function buildRenderContext(string $kind, array $ctx, array $work, array $layout): array
    {
        $path = (string) ($ctx['path'] ?? '');
        $viewerHref = (string) ($ctx['viewerHref'] ?? self::defaultViewerHref($kind, $path));
        $rawHref = (string) ($ctx['rawHref'] ?? self::defaultAssetHref($kind, $path));
        $directoryPath = $kind === 'folder'
            ? trim($path, '/')
            : trim((string) preg_replace('~/[^/]+$~', '', $path), '/');
        $parentPath = '';
        if ($directoryPath !== '') {
            $parentPath = trim(dirname($directoryPath), '/.');
        }
        $directoryPageLink = self::defaultViewerHref('folder', $directoryPath);
        $parentPageLink = $directoryPath !== '' ? self::defaultViewerHref('folder', $parentPath) : '';

        $context = [
            'kind' => $kind,
            'path' => $path,
            'directoryPath' => $directoryPath,
            'directoryPageLink' => $directoryPageLink,
            'showDirectoryPageLink' => $directoryPath !== '',
            'parentPath' => $parentPath,
            'parentPageLink' => $parentPageLink,
            'mimeType' => (string) ($ctx['mimeType'] ?? ''),
            'name' => (string) ($ctx['name'] ?? ''),
            'title' => (string) ($ctx['title'] ?? ($ctx['name'] ?? '')),
            'description' => (string) ($ctx['description'] ?? ''),
            'descriptionHtml' => (string) ($ctx['descriptionHtml'] ?? ''),
            'linkUrl' => (string) ($ctx['linkUrl'] ?? ''),
            'slug' => (string) ($ctx['slug'] ?? ''),
            'viewerHref' => $viewerHref,
            'viewUrl' => (string) ($ctx['viewUrl'] ?? $viewerHref),
            'workUrl' => (string) ($ctx['workUrl'] ?? $viewerHref),
            'pageLink' => (string) ($ctx['pageLink'] ?? ($ctx['workUrl'] ?? $viewerHref)),
            'pageUrl' => (string) ($ctx['pageUrl'] ?? ($ctx['viewUrl'] ?? $viewerHref)),
            'rawHref' => $rawHref,
            'assetUrl' => (string) ($ctx['assetUrl'] ?? $rawHref),
            'assetLink' => (string) ($ctx['assetLink'] ?? ($ctx['assetUrl'] ?? $rawHref)),
            'srcUrl' => (string) ($ctx['srcUrl'] ?? ($ctx['assetUrl'] ?? $rawHref)),
            'sourceUrl' => (string) ($ctx['sourceUrl'] ?? ($ctx['srcUrl'] ?? ($ctx['assetUrl'] ?? $rawHref))),
            'layout' => $layout,
            'work' => $work,
            'isFolder' => $kind === 'folder',
            'isImage' => $kind === 'image',
            'isVideo' => $kind === 'video',
            'isAudio' => $kind === 'audio',
            'isPdf' => $kind === 'pdf',
            'isText' => $kind === 'text',
            'isLink' => $kind === 'link',
            'isOther' => $kind === 'other',
        ];

        foreach ($ctx as $key => $value) {
            if ($key === 'work') {
                continue;
            }
            $context[$key] = $value;
        }

        foreach ($work as $key => $value) {
            if ($key === 'fields' && is_array($value)) {
                $context['fields'] = $value;
                $context['work']['fields'] = $value;
                continue;
            }
            if (is_bool($value)) {
                $context[$key] = $value;
                $context[$key . 'Attr'] = $value ? $key : '';
                $context['work'][$key . 'Attr'] = $value ? $key : '';
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $context[$key] = $value;
                $context['work'][$key] = $value;
            }
        }

        return self::hydrateRenderCollections($context);
    }

    private static function defaultViewerHref(string $kind, string $path): string
    {
        if ($kind === 'folder') {
            return '?view=1&path=' . rawurlencode($path);
        }

        if ($path === '') {
            return '';
        }

        return '?view=1&file=' . rawurlencode($path);
    }

    private static function defaultAssetHref(string $kind, string $path): string
    {
        if ($kind === 'folder') {
            return '?path=' . rawurlencode($path);
        }

        if ($path === '') {
            return '';
        }

        $parts = explode('/', $path);
        $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

        return implode('/', $encoded);
    }

    private static function hydrateRenderCollections(array $context): array
    {
        foreach ([
            'tree',
            'items',
            'allItems',
            'allFiles',
            'allFolders',
            'allImages',
            'allVideos',
            'allAudio',
            'allPdfs',
            'allTexts',
            'allLinks',
            'allOther',
        ] as $key) {
            if (isset($context[$key]) && is_array($context[$key])) {
                $context[$key] = self::hydrateRenderItems($context[$key]);
            }
        }

        if (isset($context['workTree']) && is_array($context['workTree'])) {
            $context['workTree'] = self::hydrateRenderItem($context['workTree']);
        }

        return $context;
    }

    private static function hydrateRenderItems(array $items): array
    {
        $hydrated = [];
        foreach ($items as $index => $item) {
            $hydrated[$index] = is_array($item) ? self::hydrateRenderItem($item) : $item;
        }

        return $hydrated;
    }

    private static function hydrateRenderItem(array $item): array
    {
        $path = (string) ($item['path'] ?? $item['relativePath'] ?? '');
        $isFolder = array_key_exists('isFolder', $item)
            ? (bool) $item['isFolder']
            : (($item['type'] ?? $item['kind'] ?? '') === 'folder');
        $kind = $isFolder ? 'folder' : 'file';
        $viewerHref = (string) ($item['viewerHref'] ?? self::defaultViewerHref($kind, $path));
        $rawHref = (string) ($item['rawHref'] ?? self::defaultAssetHref($kind, $path));

        $item['viewerHref'] = $viewerHref;
        $item['viewUrl'] = (string) ($item['viewUrl'] ?? $viewerHref);
        $item['workUrl'] = (string) ($item['workUrl'] ?? $viewerHref);
        $item['pageLink'] = (string) ($item['pageLink'] ?? ($item['workUrl'] ?? $viewerHref));
        $item['pageUrl'] = (string) ($item['pageUrl'] ?? ($item['viewUrl'] ?? $viewerHref));
        $item['rawHref'] = $rawHref;
        $item['assetUrl'] = (string) ($item['assetUrl'] ?? $rawHref);
        $item['assetLink'] = (string) ($item['assetLink'] ?? ($item['assetUrl'] ?? $rawHref));
        $item['srcUrl'] = (string) ($item['srcUrl'] ?? ($item['assetUrl'] ?? $rawHref));
        $item['sourceUrl'] = (string) ($item['sourceUrl'] ?? ($item['srcUrl'] ?? ($item['assetUrl'] ?? $rawHref)));

        if (isset($item['children']) && is_array($item['children'])) {
            $item['children'] = self::hydrateRenderItems($item['children']);
        }

        return $item;
    }

    private static function renderTemplate(string $kind, array $ctx, array $work, array $layout): ?string
    {
        $template = self::layoutTemplate($kind, $work);
        if ($template === '' || !self::rendererAvailable()) {
            return null;
        }

        $partials = self::sanitizePartials(self::templates());
        $section = (string) ($layout['section'] ?? ($kind === 'folder' ? 'works' : 'work'));
        if (!empty($layout['sectionTemplate']) && is_string($layout['sectionTemplate'])) {
            $partials[$section] = $layout['sectionTemplate'];
            $template = self::ensureTemplateIncludesSectionPartial($template, $section, $layout);
        }

        $handlerInstalled = false;
        try {
            set_error_handler(
                static function (int $severity, string $message, string $file = '', int $line = 0): void {
                    throw new \ErrorException($message, 0, $severity, $file, $line);
                }
            );
            $handlerInstalled = true;
            $compiled = \LightnCandy\LightnCandy::compile($template, [
                'partials' => $partials,
                'helpers' => self::helpers(),
                'flags' => \LightnCandy\LightnCandy::FLAG_HANDLEBARSJS
                    | \LightnCandy\LightnCandy::FLAG_RUNTIMEPARTIAL
                    | \LightnCandy\LightnCandy::FLAG_ERROR_LOG,
            ]);
            if (!is_string($compiled) || $compiled === '') {
                return null;
            }
            $renderer = \LightnCandy\LightnCandy::prepare($compiled);
            if (!is_callable($renderer)) {
                return null;
            }

            return $renderer(self::buildRenderContext($kind, $ctx, $work, $layout));
        } catch (\Throwable $error) {
            return null;
        } finally {
            if ($handlerInstalled) {
                restore_error_handler();
            }
        }
    }

    private static function sanitizePartials(array $partials): array
    {
        $sanitized = [];
        foreach ($partials as $name => $template) {
            $partialName = trim((string) $name);
            if ($partialName === '' || $template === null) {
                continue;
            }

            if (!is_string($template)) {
                if (!is_scalar($template)) {
                    continue;
                }
                $template = (string) $template;
            }

            $sanitized[$partialName] = $template;
        }

        return $sanitized;
    }

    private static function ensureTemplateIncludesSectionPartial(string $template, string $section, array $layout): string
    {
        if (strpos($template, '{{#if isFolder}}') !== false && strpos($template, '{{else}}') !== false) {
            return $template;
        }

        $hasWorks = preg_match('/\{\{\s*>\s*works\b/', $template) === 1;
        $hasWork = preg_match('/\{\{\s*>\s*work\b/', $template) === 1;
        $sharedBlock = '{{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}';

        if ($hasWorks && !$hasWork) {
            $updated = preg_replace('/\{\{\s*>\s*works\b[^}]*\}\}/', $sharedBlock, $template, 1);
            return is_string($updated) ? $updated : $template;
        }

        if ($hasWork && !$hasWorks) {
            $updated = preg_replace('/\{\{\s*>\s*work\b[^}]*\}\}/', $sharedBlock, $template, 1);
            return is_string($updated) ? $updated : $template;
        }

        $partialPattern = '/\{\{\s*>\s*' . preg_quote($section, '/') . '\b/';
        if (preg_match($partialPattern, $template) === 1) {
            return $template;
        }

        $partialMarkup = '{{> ' . $section . '}}';
        foreach (['</main>', '</section>', '</article>', '</div>'] as $closingTag) {
            $position = strripos($template, $closingTag);
            if ($position !== false) {
                return substr($template, 0, $position) . $partialMarkup . substr($template, $position);
            }
        }

        return $template . $partialMarkup;
    }

    private static function fallbackRender(string $kind, array $ctx): string
    {
        if ($kind === 'folder') {
            return '';
        }

        $path = htmlspecialchars((string) ($ctx['path'] ?? ''), ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string) ($ctx['name'] ?? ''), ENT_QUOTES, 'UTF-8');

        return '<iframe src="' . $path . '" title="' . $name . '"></iframe>';
    }

    /**
     * Set embedded worktypes (used in built single-file output).
     */
    public static function setEmbedded(array $map): void
    {
        self::$embedded = $map;
    }

    /**
     * Set embedded templates (used in built single-file output).
     */
    public static function setEmbeddedTemplates(array $map): void
    {
        self::$embeddedTemplates = $map;
    }

    /**
     * Load per-kind definition/template from file once.
     */
    private static function loadFilePair(string $kind): void
    {
        if (isset(self::$fileDefinitions[$kind]) || isset(self::$fileTemplates[$kind])) {
            return;
        }
        $path = __DIR__ . '/worktypes/' . $kind . '.worktype.php';
        if (file_exists($path)) {
            $data = include $path;
            if (is_array($data)) {
                if (isset($data['model'])) {
                    self::$fileDefinitions[$kind] = $data['model'];
                } elseif (isset($data['definition'])) {
                    self::$fileDefinitions[$kind] = $data['definition'];
                } else {
                    self::$fileDefinitions[$kind] = $data;
                }
                if (isset($data['template'])) {
                    self::$fileTemplates[$kind] = $data['template'];
                }
            }
        }
    }

    private static function loadBundle(): void
    {
        if (self::$bundleLoaded) {
            return;
        }
        $bundlePath = __DIR__ . '/worktypes/worktypes.php';
        if (file_exists($bundlePath)) {
            $data = include $bundlePath;
            if (is_array($data)) {
                $hasLegacyKeys = isset($data['definitions']) || isset($data['templates']);
                if ($hasLegacyKeys) {
                    if (isset($data['definitions']) && is_array($data['definitions'])) {
                        self::$bundleDefinitions = $data['definitions'];
                    }
                    if (isset($data['templates']) && is_array($data['templates'])) {
                        self::$bundleTemplates = $data['templates'];
                    }
                } else {
                    foreach ($data as $key => $value) {
                        if (!is_array($value)) {
                            continue;
                        }
                        if (isset($value['model'])) {
                            self::$bundleDefinitions[$key] = $value['model'];
                        } elseif (isset($value['definition'])) {
                            self::$bundleDefinitions[$key] = $value['definition'];
                        }
                        if (isset($value['template'])) {
                            self::$bundleTemplates[$key] = $value['template'];
                        }
                    }
                }
            }
        }
        self::$bundleLoaded = true;
    }
}
