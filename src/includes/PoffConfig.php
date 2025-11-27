<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

class PoffConfig
{
    public static function configPath(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff.config.json';
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
            if ($entry === '.' || $entry === '..' || $entry === 'poff.config.json') {
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
            'tree' => $tree,
            'treeHash' => $treeHash,
            'updatedAt' => $now,
        ];
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

        // Start with defaults but preserve user-provided title/description/link/url if present
        $data = $defaults;
        if (is_array($existing)) {
            $data['title'] = $existing['title'] ?? $data['title'];
            $data['description'] = $existing['description'] ?? $data['description'];
            if (isset($existing['link'])) {
                $data['link'] = $existing['link'];
            }
            if (isset($existing['url'])) {
                $data['url'] = $existing['url'];
            }
            // If tree hash matches, keep prior updatedAt to avoid noisy rewrites
            if (($existing['treeHash'] ?? '') === $defaults['treeHash']) {
                $data['updatedAt'] = $existing['updatedAt'] ?? $data['updatedAt'];
            }
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
            file_put_contents($configPath, json_encode($data, JSON_PRETTY_PRINT));
        }

        return $data;
    }
}
