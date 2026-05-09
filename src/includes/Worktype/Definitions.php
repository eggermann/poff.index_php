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

    public static function isKnownWorktypeKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, self::availableKinds(), true);
    }

    public static function normalizeTemplateKey(?string $value): string
    {
        $candidate = strtolower(trim((string) $value));
        return self::isKnownWorktypeKey($candidate) ? $candidate : '';
    }

    public static function normalizeTemplateMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $mime => $template) {
            if (!is_scalar($mime)) {
                continue;
            }

            $normalizedMime = strtolower(trim((string) $mime));
            if ($normalizedMime === '') {
                continue;
            }

            if (is_array($template)) {
                $template = $template['template'] ?? $template['value'] ?? $template['kind'] ?? '';
            }

            $normalizedTemplate = self::normalizeTemplateKey(is_scalar($template) ? (string) $template : '');
            if ($normalizedTemplate === '') {
                continue;
            }

            $map[$normalizedMime] = $normalizedTemplate;
        }

        if ($map !== []) {
            ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $map;
    }

    private static function templateMapCandidates(string $kind, ?string $mime = null, ?string $fileName = null): array
    {
        $candidates = [];
        $normalizedMime = strtolower(trim((string) $mime));
        if ($normalizedMime !== '') {
            $candidates[] = $normalizedMime;
            $mimeMajor = strtok($normalizedMime, '/');
            if (is_string($mimeMajor) && trim($mimeMajor) !== '') {
                $candidates[] = strtolower(trim($mimeMajor)) . '/*';
            }
        }

        $normalizedKind = strtolower(trim($kind));
        if ($normalizedKind !== '') {
            $candidates[] = $normalizedKind;
        }

        if ($fileName !== null && trim($fileName) !== '') {
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($extension !== '') {
                $candidates[] = $extension;
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn(string $candidate): bool => $candidate !== '')));
    }

    private static function lookupTemplateMap(array $templateMap, string $kind, ?string $mime = null, ?string $fileName = null): string
    {
        foreach (self::templateMapCandidates($kind, $mime, $fileName) as $candidate) {
            if (!array_key_exists($candidate, $templateMap)) {
                continue;
            }

            $normalizedTemplate = self::normalizeTemplateKey((string) $templateMap[$candidate]);
            if ($normalizedTemplate !== '') {
                return $normalizedTemplate;
            }
        }

        return '';
    }

    public static function shouldAutoplayByDefault(string $template, string $kind, ?string $mime = null, ?string $fileName = null): bool
    {
        if ($template !== 'video') {
            return false;
        }

        $normalizedMime = strtolower(trim((string) $mime));
        if ($normalizedMime === 'video/quicktime') {
            return true;
        }

        if ($fileName !== null && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'mov') {
            return true;
        }

        return false;
    }

    public static function resolveTemplateSelection(string $kind, ?string $mime = null, ?string $fileName = null, mixed $templateMap = null): array
    {
        $normalizedKind = strtolower(trim($kind));
        if ($normalizedKind === '') {
            $normalizedKind = 'other';
        }

        $normalizedMap = self::normalizeTemplateMap($templateMap);
        $selected = self::lookupTemplateMap($normalizedMap, $normalizedKind, $mime, $fileName);
        $source = $selected !== '' ? 'templateMap' : 'suggested';
        if ($selected === '') {
            $selected = self::suggestedWorktypeKey($normalizedKind, $mime, $fileName);
        }

        if (!self::isKnownWorktypeKey($selected)) {
            $selected = self::isKnownWorktypeKey($normalizedKind) ? $normalizedKind : 'other';
        }

        return [
            'kind' => $normalizedKind,
            'template' => $selected,
            'source' => $source,
            'mime' => is_string($mime) ? trim($mime) : null,
            'fileName' => is_string($fileName) ? trim($fileName) : null,
            'templateMap' => $normalizedMap,
            'autoplay' => self::shouldAutoplayByDefault($selected, $normalizedKind, $mime, $fileName),
        ];
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

    public static function availableKinds(): array
    {
        self::loadBundle();
        $keys = array_keys(self::$bundleDefinitions + self::$fileDefinitions + self::$embedded);
        $keys = array_values(array_unique(array_filter(array_map(static fn (mixed $value): string => strtolower(trim((string) $value)), $keys), static fn (string $value): bool => $value !== '')));
        sort($keys, SORT_NATURAL | SORT_FLAG_CASE);

        return $keys;
    }

    public static function suggestedWorktypeKey(string $kind, ?string $mime = null, ?string $fileName = null): string
    {
        $subjectType = strtolower(trim($kind)) === 'folder' ? 'folder' : 'file';
        $catalog = self::worktypeCatalog($mime, $fileName, null, $subjectType);
        return (string) ($catalog['selected'] ?? $kind);
    }

    /**
     * Build a selector catalog for work templates.
     *
     * @return array<string,mixed>
     */
    public static function worktypeCatalog(?string $mime = null, ?string $fileName = null, ?string $selected = null, ?string $subjectType = null): array
    {
        self::loadBundle();

        $selected = strtolower(trim((string) $selected));
        $subjectType = strtolower(trim((string) $subjectType));
        $detectedKind = $fileName !== null && $fileName !== ''
            ? strtolower((string) (class_exists('MediaType') ? MediaType::classifyExtension($fileName) : 'other'))
            : '';
        $mime = strtolower(trim((string) $mime));
        $extension = $fileName !== null ? strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) : '';
        $choices = [];

        foreach (self::availableKinds() as $key) {
            $definition = self::definition($key, $mime !== '' ? $mime : null);
            $choiceKind = strtolower(trim((string) ($definition['type'] ?? $key)));
            $choiceLabel = self::worktypeLabel($key, $definition);
            $choiceMimes = self::normalizeChoiceList($definition['mimes'] ?? $definition['mimeTypes'] ?? null);
            $choiceExtensions = self::normalizeChoiceList($definition['extensions'] ?? $definition['suffixes'] ?? null);

            if ($subjectType === 'folder' && $choiceKind !== 'folder') {
                continue;
            }
            if ($subjectType === 'file' && $choiceKind === 'folder') {
                continue;
            }

            $score = 0;

            if ($selected !== '' && $selected === $key) {
                $score += 1000;
            }
            if ($choiceKind !== '' && $detectedKind !== '' && $choiceKind === $detectedKind) {
                $score += 300;
            }
            if ($mime !== '' && in_array($mime, $choiceMimes, true)) {
                $score += 600;
            }
            if ($extension !== '' && in_array($extension, $choiceExtensions, true)) {
                $score += 500;
            }
            if (($definition['preferred'] ?? false) === true) {
                $score += 50;
            }
            if ($key === $choiceKind) {
                $score += 25;
            }

            $choices[] = [
                'value' => $key,
                'label' => $choiceLabel,
                'kind' => $choiceKind ?: $key,
                'description' => trim((string) ($definition['description'] ?? '')),
                'mimes' => $choiceMimes,
                'extensions' => $choiceExtensions,
                'score' => $score,
            ];
        }

        usort($choices, static function (array $left, array $right): int {
            $scoreCompare = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $kindCompare = strcasecmp((string) ($left['kind'] ?? ''), (string) ($right['kind'] ?? ''));
            if ($kindCompare !== 0) {
                return $kindCompare;
            }

            return strcasecmp((string) ($left['label'] ?? $left['value'] ?? ''), (string) ($right['label'] ?? $right['value'] ?? ''));
        });

        if ($subjectType === 'file' && $detectedKind !== '') {
            $focusedChoices = array_values(array_filter($choices, static function (array $choice) use ($detectedKind): bool {
                return (string) ($choice['kind'] ?? '') === $detectedKind;
            }));
            if ($focusedChoices !== []) {
                $choices = $focusedChoices;
            }
        }

        $selectedValue = '';
        if ($selected !== '') {
            foreach ($choices as $choice) {
                if ((string) ($choice['value'] ?? '') === $selected) {
                    $selectedValue = $selected;
                    break;
                }
            }
        }
        if ($selectedValue === '' && isset($choices[0]['value'])) {
            $selectedValue = (string) $choices[0]['value'];
        }

        foreach ($choices as &$choice) {
            $choice['selected'] = (string) ($choice['value'] ?? '') === $selectedValue;
            unset($choice['score']);
        }
        unset($choice);

        return [
            'detectedKind' => $detectedKind,
            'detectedMime' => $mime,
            'detectedExtension' => $extension,
            'selected' => $selectedValue,
            'choices' => $choices,
        ];
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

    private static function normalizeChoiceList(mixed $value): array
    {
        if (!is_array($value)) {
            if (is_string($value) && trim($value) !== '') {
                $value = preg_split('/\r?\n|,/', $value) ?: [];
            } else {
                $value = [];
            }
        }

        $choices = [];
        foreach ($value as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $normalized = strtolower(trim((string) $candidate));
            if ($normalized === '' || in_array($normalized, $choices, true)) {
                continue;
            }
            $choices[] = $normalized;
        }

        return $choices;
    }

    private static function worktypeLabel(string $key, array $definition): string
    {
        $label = trim((string) ($definition['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        return ucwords(str_replace(['-', '_'], ' ', $key));
    }
}
