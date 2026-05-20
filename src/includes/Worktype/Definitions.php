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

        $sourceValues = is_array($value)
            ? $value
            : ((is_string($value) && trim($value) !== '') ? (preg_split('/\r?\n|,/', $value) ?: []) : []);
        foreach ($sourceValues as $candidate) {
            if (is_string($candidate) || is_scalar($candidate)) {
                $append((string) $candidate);
            }
        }

        return $categories;
    }

    private static function collectSharedCategories(): array
    {
        $rootDir = trim((string) (function_exists('cmsProjectRootDir') ? cmsProjectRootDir() : ''));
        if ($rootDir === '' || !is_dir($rootDir)) {
            return [];
        }
        $categories = [];
        $excluded = ['build', 'pages', 'scripts', 'src', 'tests', 'vendor', 'node_modules', '.git'];
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS),
            static fn($current): bool => $current instanceof SplFileInfo
                && ($current->isDir()
                    ? !in_array($current->getFilename(), $excluded, true)
                    : ($current->getFilename() === 'poff.config.json'
                        || (str_ends_with($current->getFilename(), '.config.json') && basename($current->getPath()) === '.works')))
        ));
        foreach ($iterator as $fileInfo) {
            $decoded = json_decode((string) file_get_contents($fileInfo->getPathname()), true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach (self::normalizeChoiceList($decoded['categories'] ?? null) as $category) {
                if (!in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }
            foreach (self::normalizeChoiceList($decoded['work']['categories'] ?? ($decoded['work']['category'] ?? null)) as $category) {
                if (!in_array($category, $categories, true)) {
                    $categories[] = $category;
                }
            }
        }

        return $categories;
    }

    public static function isKnownWorktypeKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        return $normalized !== '' && in_array($normalized, self::availableKinds(), true);
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

    private static function mimeMatchesPattern(string $mime, string $pattern): bool
    {
        $normalizedMime = strtolower(trim($mime));
        $normalizedPattern = strtolower(trim($pattern));
        if ($normalizedMime === '' || $normalizedPattern === '') {
            return false;
        }

        if ($normalizedMime === $normalizedPattern) {
            return true;
        }

        if (str_ends_with($normalizedPattern, '/*')) {
            $prefix = substr($normalizedPattern, 0, -2);
            return $prefix !== '' && str_starts_with($normalizedMime, $prefix . '/');
        }

        if (str_ends_with($normalizedPattern, '/.*')) {
            $prefix = substr($normalizedPattern, 0, -3);
            return $prefix !== '' && str_starts_with($normalizedMime, $prefix . '/');
        }

        return false;
    }

    private static function templateMapCandidates(string $kind, ?string $mime = null, ?string $fileName = null): array
    {
        $candidates = [];
        $normalizedMime = strtolower(trim((string) $mime));
        if ($normalizedMime !== '') {
            $candidates[] = $normalizedMime;
            $mimeMajor = strtok($normalizedMime, '/');
            if (is_string($mimeMajor) && trim($mimeMajor) !== '') {
                $major = strtolower(trim($mimeMajor));
                $candidates[] = $major . '/*';
                $candidates[] = $major . '/.*';
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

        $normalizedMime = strtolower(trim((string) $mime));
        if ($normalizedMime !== '') {
            foreach ($templateMap as $candidate => $template) {
                if (!is_string($candidate) || !self::mimeMatchesPattern($normalizedMime, (string) $candidate)) {
                    continue;
                }

                $normalizedTemplate = self::normalizeTemplateKey((string) $template);
                if ($normalizedTemplate !== '') {
                    return $normalizedTemplate;
                }
            }
        }

        return '';
    }

    public static function shouldAutoplayByDefault(string $template, string $kind, ?string $mime = null, ?string $fileName = null): bool
    {
        return $template === 'video'
            && (strtolower(trim((string) $mime)) === 'video/quicktime'
                || ($fileName !== null && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'mov'));
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
            'mode' => self::defaultLayoutName(),
            'name' => self::defaultLayoutName(),
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

    public static function availableCategories(): array
    {
        self::loadBundle();
        $categories = [];
        foreach (self::availableKinds() as $kind) {
            foreach (self::defaultCategoriesForKind($kind) as $category) {
                $normalized = strtolower(trim((string) $category));
                if ($normalized === '' || in_array($normalized, $categories, true)) {
                    continue;
                }
                $categories[] = $normalized;
            }
        }
        if ($categories === []) {
            $categories = ['other'];
        }
        foreach (self::collectSharedCategories() as $category) {
            if (!in_array($category, $categories, true)) {
                $categories[] = $category;
            }
        }
        sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return $categories;
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

        if ($mime !== '') {
            $mimeMatches = array_values(array_filter($choices, static function (array $choice) use ($mime): bool {
                $choiceMimes = is_array($choice['mimes'] ?? null) ? $choice['mimes'] : [];
                foreach ($choiceMimes as $choiceMime) {
                    if (is_string($choiceMime) && self::mimeMatchesPattern($mime, $choiceMime)) {
                        return true;
                    }
                }

                return false;
            }));

            if ($mimeMatches !== []) {
                if ($selected !== '') {
                    $selectedChoice = null;
                    foreach ($choices as $choice) {
                        if ((string) ($choice['value'] ?? '') === $selected) {
                            $selectedChoice = $choice;
                            break;
                        }
                    }
                    if ($selectedChoice !== null) {
                        $alreadyPresent = false;
                        foreach ($mimeMatches as $choice) {
                            if ((string) ($choice['value'] ?? '') === $selected) {
                                $alreadyPresent = true;
                                break;
                            }
                        }
                        if (!$alreadyPresent) {
                            array_unshift($mimeMatches, $selectedChoice);
                        }
                    }
                }

                $choices = $mimeMatches;
            }
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
            'categories' => self::availableCategories(),
        ];
    }

    public static function setEmbedded(array $map): void { self::$embedded = $map; }
    public static function setEmbeddedTemplates(array $map): void { self::$embeddedTemplates = $map; }
    public static function setEmbeddedLayoutAssets(array $map): void { self::$embeddedLayoutAssets = $map; }
    public static function defaultLayoutName(): string { return self::defaultLayoutNameValue(); }
    public static function filesystemLayoutName(): string { return self::filesystemLayoutNameValue(); }

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
            return self::defaultLayoutName();
        }

        return match ($candidate) {
            'poff' => self::defaultLayoutName(),
            'filesystem' => self::filesystemLayoutName(),
            default => $candidate,
        };
    }

    public static function canonicalLayoutName(?string $name): string
    {
        $candidate = trim((string) $name);
        if ($candidate === '') {
            return self::defaultLayoutName();
        }

        return match ($candidate) {
            'poff', self::defaultLayoutName() => self::defaultLayoutName(),
            'filesystem', self::filesystemLayoutName() => self::filesystemLayoutName(),
            default => $candidate,
        };
    }

    private static function normalizeChoiceList(mixed $value): array
    {
        if (!is_array($value)) {
            $value = is_string($value) && trim($value) !== '' ? (preg_split('/\r?\n|,/', $value) ?: []) : [];
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
