<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

class PoffConfig
{
    private static function generateId(): string
    {
        try {
            return 'poff_' . bin2hex(random_bytes(8));
        } catch (Exception $e) {
            return 'poff_' . uniqid();
        }
    }

    public static function configPath(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff.config.json';
    }

    public static function fileConfigPath(string $dir, string $fileName): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.config.json';
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
            if ($entry === '.' || $entry === '..' || $entry === 'poff.config.json' || $entry === '.works') {
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
            'folderName' => $folderName,
            'slug' => self::slugify($folderName),
            'title' => $folderName,
            'description' => '',
            'type' => 'folder',
            'id' => self::generateId(),
            'tree' => $tree,
            'treeHash' => $treeHash,
            'updatedAt' => $now,
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
        $mime = self::detectMimeType($fullPath, $fileName);
        $base = [
            'name' => $fileName,
            'slug' => self::slugify($fileName),
            'type' => 'file',
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
        if (is_array($existing)) {
            $data['title'] = $existing['title'] ?? $data['title'];
            $data['description'] = $existing['description'] ?? $data['description'];
            if (isset($existing['link'])) {
                $data['link'] = $existing['link'];
            }
            if (isset($existing['url'])) {
                $data['url'] = $existing['url'];
            }
            if (isset($existing['id'])) {
                $data['id'] = $existing['id'];
            }
        }
        if (empty($data['id'])) {
            $data['id'] = self::generateId();
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

        return $data;
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
        if (is_array($existing)) {
            // Preserve user-editable metadata and visibility
            $data['visible'] = $existing['visible'] ?? $data['visible'];
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
            // Carry any extra custom fields
            foreach ($existing as $k => $v) {
                if (array_key_exists($k, $data)) {
                    continue;
                }
                $data[$k] = $v;
            }
        }
        if (empty($data['id'])) {
            $data['id'] = self::generateId();
        }

        // Only write when changed
        $serializedExisting = is_array($existing) ? json_encode($existing) : '';
        $serializedData = json_encode($data);
        if ($serializedExisting !== $serializedData) {
            $dirPath = dirname($configPath);
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            $data['updatedAt'] = date('c');
            file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $data;
    }
}
