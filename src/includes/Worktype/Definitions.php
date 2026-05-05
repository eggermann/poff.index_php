<?php

trait WorktypeDefinitionsTrait
{
    use WorktypeStateTrait;

    private static function defaultCategoriesForKind(string $kind): array
    {
        return match (strtolower(trim($kind))) {
            'image' => ['image', 'media', 'visual'],
            'video' => ['video', 'media', 'motion'],
            'audio' => ['audio', 'media', 'sound'],
            'pdf' => ['pdf', 'document'],
            'text' => ['text', 'document'],
            'link' => ['link', 'reference'],
            'folder' => ['folder', 'collection'],
            default => ['other'],
        };
    }

    private static function normalizeCategories(mixed $value, string $kind): array
    {
        $categories = [];
        $append = static function (string $candidate) use (&$categories): void {
            $normalized = strtolower(trim($candidate));
            if ($normalized === '' || in_array($normalized, $categories, true)) {
                return;
            }
            $categories[] = $normalized;
        };

        foreach (self::defaultCategoriesForKind($kind) as $defaultCategory) {
            $append((string) $defaultCategory);
        }

        $sourceValues = [];
        if (is_array($value)) {
            $sourceValues = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $sourceValues = preg_split('/\r?\n|,/', $value) ?: [];
        }

        foreach ($sourceValues as $candidate) {
            if (is_string($candidate) || is_scalar($candidate)) {
                $append((string) $candidate);
            }
        }

        return $categories;
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
        $base['categories'] = self::normalizeCategories($base['categories'] ?? null, (string) $base['type']);

        return $base;
    }

    public static function setEmbedded(array $map): void
    {
        self::$embedded = $map;
    }

    public static function setEmbeddedTemplates(array $map): void
    {
        self::$embeddedTemplates = $map;
    }

    public static function setEmbeddedLayoutAssets(array $map): void
    {
        self::$embeddedLayoutAssets = $map;
    }

    public static function defaultLayoutName(): string
    {
        return self::DEFAULT_LAYOUT_NAME;
    }

    public static function filesystemLayoutName(): string
    {
        return self::FILESYSTEM_LAYOUT_NAME;
    }

    private static function loadFilePair(string $kind): void
    {
        if (isset(self::$fileDefinitions[$kind]) || isset(self::$fileTemplates[$kind])) {
            return;
        }
        $path = __DIR__ . '/../worktypes/' . $kind . '.worktype.php';
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
        $bundlePath = __DIR__ . '/../worktypes/worktypes.php';
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
}
