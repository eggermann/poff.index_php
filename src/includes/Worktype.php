<?php
/**
 * Worktype helper for media layout defaults and overrides.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class Worktype
{
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
        $template = self::layoutTemplate($kind, $resolvedWork);
        $partials = self::templates();

        if ($template) {
            if (!self::rendererAvailable()) {
                return self::fallbackRender($ctx);
            }
            try {
                $compiled = \LightnCandy\LightnCandy::compile($template, [
                    'partials' => $partials,
                    'flags' => \LightnCandy\LightnCandy::FLAG_HANDLEBARSJS
                        | \LightnCandy\LightnCandy::FLAG_RUNTIMEPARTIAL
                        | \LightnCandy\LightnCandy::FLAG_ERROR_LOG,
                ]);
                $renderer = \LightnCandy\LightnCandy::prepare($compiled);

                return $renderer(self::buildRenderContext($kind, $ctx, $resolvedWork, $layout));
            } catch (\Throwable $error) {
                return self::fallbackRender($ctx);
            }
        }

        return self::fallbackRender($ctx);
    }

    public static function normalizeLayout(mixed $value, string $section = 'work'): array
    {
        $layout = [
            'mode' => 'default',
            'name' => 'default-layout',
            'engine' => 'lightncandy',
            'section' => $section,
        ];

        if (is_string($value) && trim($value) !== '') {
            $name = trim($value);
            $layout['mode'] = $name;
            $layout['name'] = $name === 'default' ? 'default-layout' : $name;
            return $layout;
        }

        if (!is_array($value)) {
            return $layout;
        }

        $candidate = trim((string) ($value['name'] ?? $value['mode'] ?? $value['value'] ?? ''));
        if ($candidate !== '') {
            $layout['mode'] = $candidate;
            $layout['name'] = $candidate === 'default' ? 'default-layout' : $candidate;
        }

        if (isset($value['engine']) && is_string($value['engine']) && trim($value['engine']) !== '') {
            $layout['engine'] = trim($value['engine']);
        }

        if (isset($value['section']) && is_string($value['section']) && trim($value['section']) !== '') {
            $layout['section'] = trim($value['section']);
        }

        foreach (['template', 'model', 'stylePrompt'] as $key) {
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

        foreach ((glob(__DIR__ . '/worktypes/templates/*.hbs') ?: []) as $path) {
            $templates[pathinfo($path, PATHINFO_FILENAME)] = (string) file_get_contents($path);
        }

        foreach ((glob(__DIR__ . '/worktypes/templates/*.tpl') ?: []) as $path) {
            $key = pathinfo($path, PATHINFO_FILENAME);
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
            $path = __DIR__ . '/worktypes/templates/' . $name . $extension;
            if (file_exists($path)) {
                return (string) file_get_contents($path);
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
        if (!empty($layout['template']) && is_string($layout['template'])) {
            return $layout['template'];
        }

        $namedTemplate = self::template($layout['name']);
        if (is_string($namedTemplate) && $namedTemplate !== '') {
            return $namedTemplate;
        }

        $defaultTemplate = self::template('default-layout');
        if (is_string($defaultTemplate) && $defaultTemplate !== '') {
            return $defaultTemplate;
        }

        return '';
    }

    private static function rendererAvailable(): bool
    {
        return class_exists('\LightnCandy\LightnCandy');
    }

    private static function buildRenderContext(string $kind, array $ctx, array $work, array $layout): array
    {
        $context = [
            'kind' => $kind,
            'path' => (string) ($ctx['path'] ?? ''),
            'mimeType' => (string) ($ctx['mimeType'] ?? ''),
            'name' => (string) ($ctx['name'] ?? ''),
            'title' => (string) ($ctx['title'] ?? ($ctx['name'] ?? '')),
            'description' => (string) ($ctx['description'] ?? ''),
            'descriptionHtml' => (string) ($ctx['descriptionHtml'] ?? ''),
            'linkUrl' => (string) ($ctx['linkUrl'] ?? ''),
            'slug' => (string) ($ctx['slug'] ?? ''),
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

        return $context;
    }

    private static function fallbackRender(array $ctx): string
    {
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
