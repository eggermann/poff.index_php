<?php

trait PoffConfigCoreHelpers
{
    private static function generateId(): string
    {
        try {
            return 'poff_' . bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return 'poff_' . uniqid();
        }
    }

    private static function normalizeTemplateMap(mixed $value): array
    {
        return class_exists('Worktype') ? Worktype::normalizeTemplateMap($value) : [];
    }

    public static function trimTemplateMapOverrides(array $templateMap, array $inheritedMap): array
    {
        $normalizedTemplateMap = self::normalizeTemplateMap($templateMap);
        $normalizedInheritedMap = self::normalizeTemplateMap($inheritedMap);

        if ($normalizedTemplateMap === []) {
            return [];
        }

        foreach ($normalizedTemplateMap as $mime => $template) {
            if (isset($normalizedInheritedMap[$mime]) && $normalizedInheritedMap[$mime] === $template) {
                unset($normalizedTemplateMap[$mime]);
            }
        }

        return self::normalizeTemplateMap($normalizedTemplateMap);
    }

    private static function resolveConfigTemplateMap(array $config): array
    {
        $work = isset($config['work']) && is_array($config['work']) ? $config['work'] : [];
        return self::normalizeTemplateMap($work['templateMap'] ?? null);
    }

    public static function resolveInheritedTemplateMap(string $dir): array
    {
        $normalizedDir = realpath($dir);
        if ($normalizedDir === false) {
            $normalizedDir = rtrim($dir, DIRECTORY_SEPARATOR);
        }

        $directories = [];
        $cursor = $normalizedDir;
        while ($cursor !== '' && $cursor !== DIRECTORY_SEPARATOR) {
            $directories[] = $cursor;
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $cursor = $parent;
        }

        $map = [];
        foreach (array_reverse($directories) as $directory) {
            $configPath = self::configPath($directory);
            if (!is_file($configPath)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($configPath), true);
            if (!is_array($decoded)) {
                continue;
            }

            $map = array_replace($map, self::resolveConfigTemplateMap($decoded));
        }

        return self::normalizeTemplateMap($map);
    }

    public static function resolveEffectiveTemplateMap(string $dir, mixed $localTemplateMap = null): array
    {
        $map = self::resolveInheritedTemplateMap($dir);
        $local = self::normalizeTemplateMap($localTemplateMap);
        if ($local !== []) {
            $map = array_replace($map, $local);
        }

        return self::normalizeTemplateMap($map);
    }

    public static function resolveWorkTemplateState(string $dir, array $work, string $kind, ?string $mime = null, ?string $fileName = null): array
    {
        $normalizedKind = strtolower(trim($kind));
        if ($normalizedKind === '') {
            $normalizedKind = 'other';
        }

        $explicitTemplate = '';
        if (array_key_exists('template', $work) && is_string($work['template'])) {
            $explicitTemplate = Worktype::normalizeTemplateKey($work['template']);
        }

        $effectiveTemplateMap = self::resolveEffectiveTemplateMap($dir, $work['templateMap'] ?? null);
        $suggested = Worktype::resolveTemplateSelection($normalizedKind, $mime, $fileName, $effectiveTemplateMap);

        if ($explicitTemplate !== '') {
            $suggested['template'] = $explicitTemplate;
            $suggested['source'] = 'explicit';
        }

        if ($suggested['template'] === 'video' && Worktype::shouldAutoplayByDefault('video', $normalizedKind, $mime, $fileName)) {
            $suggested['autoplay'] = true;
        }

        $resolvedWork = $work;
        $resolvedWork['templateMap'] = $effectiveTemplateMap;
        $resolvedWork['template'] = $suggested['template'];
        if (!array_key_exists('type', $resolvedWork) || trim((string) $resolvedWork['type']) === '') {
            $resolvedWork['type'] = $normalizedKind;
        }
        if ($suggested['autoplay'] === true) {
            $resolvedWork['autoplay'] = true;
        }

        return [
            'work' => $resolvedWork,
            'template' => $suggested['template'],
            'source' => $suggested['source'],
            'templateMap' => $effectiveTemplateMap,
            'autoplay' => $suggested['autoplay'] ?? false,
        ];
    }

    private static function defaultWork(string $kind, ?string $mime = null, ?string $fileName = null): array
    {
        $selection = Worktype::resolveTemplateSelection($kind, $mime, $fileName);
        $templateKey = (string) ($selection['template'] ?? Worktype::suggestedWorktypeKey($kind, $mime, $fileName));
        $work = Worktype::definition($templateKey, $mime);
        $section = $kind === 'folder' ? 'works' : 'work';
        $work['layout'] = Worktype::normalizeLayout($work['layout'] ?? null, $section);
        if (!isset($work['template']) || trim((string) $work['template']) === '') {
            $work['template'] = $templateKey;
        }
        if (($selection['autoplay'] ?? false) === true) {
            $work['autoplay'] = true;
        }
        $work['type'] = $work['type'] ?? $kind;

        return $work;
    }

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
                self::isEditOnlyTreeEntry($entry) ||
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
        $base['work'] = self::defaultWork($kind, $mime, $fileName);

        return $base;
    }

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

    public static function ensure(string $dir): array
    {
        $configPath = self::configPath($dir);
        $defaults = self::defaultConfig($dir);
        $existing = null;
        $forceWrite = false;

        if (file_exists($configPath)) {
            $existing = json_decode((string) file_get_contents($configPath), true);
        }

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
                    foreach ($existingItem as $k => $v) {
                        if (in_array($k, ['name', 'type', 'path', 'modifiedAt'], true)) {
                            continue;
                        }
                        if (!array_key_exists($k, $item)) {
                            $item[$k] = $v;
                        } elseif ($k === 'slug' && is_string($v) && trim($v) !== '') {
                            $item[$k] = trim($v);
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
                if (self::isEditOnlyTreeEntry($name)) {
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

        $shouldWrite = true;
        if (is_array($existing) && ($existing['treeHash'] ?? '') === $data['treeHash']) {
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

    private static function isEditOnlyTreeEntry(string $entry): bool
    {
        return in_array(trim($entry), self::EDIT_ONLY_TREE_ENTRIES, true);
    }

    public static function ensureFileConfig(string $dir, string $fileName): array
    {
        $configPath = self::fileConfigPath($dir, $fileName);
        $defaults = self::defaultFileConfig($dir, $fileName);
        $existing = null;

        if (file_exists($configPath)) {
            $existing = json_decode((string) file_get_contents($configPath), true);
        }

        $data = $defaults;
        $workDefault = self::defaultWork($data['kind'] ?? 'other', $data['mimeType'] ?? null, $fileName);
        $existingWork = [];
        $forceWrite = false;
        if (is_array($existing)) {
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
}
