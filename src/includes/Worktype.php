<?php
/**
 * Worktype helper for media layout defaults and overrides.
 */

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
        return $base;
    }

    /**
     * Render body content for a given kind using templates.
     */
    public static function render(string $kind, array $ctx): string
    {
        $tpl = self::loadTemplate($kind);
        if (!$tpl) {
            $tpl = self::loadTemplate('other');
        }

        if ($tpl) {
            return self::applyTemplate($tpl, $ctx);
        }

        // Fallback minimal rendering
        return '<iframe src="' . htmlspecialchars($ctx['safePath'] ?? '', ENT_QUOTES, 'UTF-8') . '" title="' . htmlspecialchars($ctx['safeName'] ?? '', ENT_QUOTES, 'UTF-8') . '"></iframe>';
    }

    private static function loadTemplate(string $kind): ?string
    {
        self::loadBundle();
        self::loadFilePair($kind);

        if (isset(self::$fileTemplates[$kind])) {
            return self::$fileTemplates[$kind];
        }
        if (isset(self::$bundleTemplates[$kind])) {
            return self::$bundleTemplates[$kind];
        }
        $path = __DIR__ . '/worktypes/templates/' . $kind . '.tpl';
        if (file_exists($path)) {
            return (string) file_get_contents($path);
        }
        if (isset(self::$embeddedTemplates[$kind])) {
            return self::$embeddedTemplates[$kind];
        }
        return null;
    }

    private static function applyTemplate(string $tpl, array $ctx): string
    {
        $safePath = htmlspecialchars($ctx['safePath'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($ctx['safeName'] ?? '', ENT_QUOTES, 'UTF-8');
        $safeLinkUrl = htmlspecialchars($ctx['safeLinkUrl'] ?? '', ENT_QUOTES, 'UTF-8');
        $work = $ctx['work'] ?? [];

        $replacements = [
            '{{path}}' => $safePath,
            '{{name}}' => $safeName,
            '{{linkUrl}}' => $safeLinkUrl,
            '{{target}}' => htmlspecialchars($work['target'] ?? '_blank', ENT_QUOTES, 'UTF-8'),
            '{{fit}}' => htmlspecialchars($work['fit'] ?? 'contain', ENT_QUOTES, 'UTF-8'),
            '{{background}}' => htmlspecialchars($work['background'] ?? '#000', ENT_QUOTES, 'UTF-8'),
            '{{poster}}' => htmlspecialchars($work['poster'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{autoplayAttr}}' => !empty($work['autoplay']) ? 'autoplay' : '',
            '{{loopAttr}}' => !empty($work['loop']) ? 'loop' : '',
            '{{mutedAttr}}' => !empty($work['muted']) ? 'muted' : '',
            '{{viewer}}' => htmlspecialchars($work['viewer'] ?? 'embed', ENT_QUOTES, 'UTF-8'),
        ];

        return strtr($tpl, $replacements);
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
