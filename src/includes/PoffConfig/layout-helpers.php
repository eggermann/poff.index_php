<?php

trait PoffConfigLayoutHelpers
{
    public static function configPath(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff.config.json';
    }

    public static function fileConfigPath(string $dir, string $fileName): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.config.json';
    }

    public static function folderLayoutDir(string $dir): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.layout';
    }

    public static function fileLayoutDir(string $dir, string $fileName): string
    {
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.works' . DIRECTORY_SEPARATOR . $fileName . '.layout';
    }

    public static function relativeLayoutPath(string $itemPath, bool $isFile): string
    {
        $normalized = str_replace('\\', '/', trim($itemPath, "/\\"));
        if (!$isFile) {
            return $normalized === '' ? '.layout' : $normalized . '/.layout';
        }

        if ($normalized === '') {
            return '.works/unknown.layout';
        }

        $fileName = basename($normalized);
        $dirName = dirname($normalized);
        if ($dirName === '.' || $dirName === DIRECTORY_SEPARATOR) {
            $dirName = '';
        }

        return ($dirName !== '' ? $dirName . '/' : '') . '.works/' . $fileName . '.layout';
    }

    public static function publicFolderLayoutPath(string $itemPath, bool $isFile): string
    {
        $normalized = str_replace('\\', '/', trim($itemPath, "/\\"));
        if ($normalized === '') {
            return '.layout';
        }

        if (!$isFile) {
            return $normalized . '/.layout';
        }

        $dirName = dirname($normalized);
        if ($dirName === '.' || $dirName === DIRECTORY_SEPARATOR) {
            return '.layout';
        }

        return trim($dirName, "/\\") . '/.layout';
    }

    public static function slugify(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($name));
        return trim($slug, '-') ?: 'untitled';
    }

    public static function defaultLayoutFiles(string $section): array
    {
        return [
            'template.hbs' => null,
            'style.css' => null,
            'script.js' => null,
            self::sectionTemplateFile($section) => null,
        ];
    }

    public static function serializeLayout(mixed $layout, string $section): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        $serialized = [
            'mode' => $resolved['mode'],
            'name' => $resolved['name'],
            'engine' => $resolved['engine'],
            'section' => $resolved['section'],
        ];

        foreach (['preset', 'source', 'sharedName', 'model', 'stylePrompt'] as $key) {
            if (array_key_exists($key, $resolved)) {
                $serialized[$key] = $resolved[$key];
            }
        }

        return $serialized;
    }

    public static function sectionTemplateFile(string $section): string
    {
        return $section === 'works'
            ? 'works.hbs'
            : 'work.hbs';
    }

    public static function writeManagedLayoutFiles(string $layoutDir, array $managedFiles): void
    {
        foreach ($managedFiles as $name => $contents) {
            if ($contents === null) {
                continue;
            }

            $targetPath = $layoutDir . DIRECTORY_SEPARATOR . $name;
            if (trim($contents) === '') {
                if (is_file($targetPath)) {
                    unlink($targetPath);
                }
                continue;
            }

            if (!is_dir($layoutDir)) {
                mkdir($layoutDir, 0755, true);
            }

            file_put_contents($targetPath, $contents);
        }

        if (self::isDirectoryEmpty($layoutDir)) {
            @rmdir($layoutDir);
        }
    }

    public static function resolveRelativeDirectory(string $relativeDir): ?string
    {
        $base = realpath(cmsProjectRootDir());
        if ($base === false) {
            return null;
        }

        $normalized = str_replace('\\', '/', trim($relativeDir, "/\\"));
        if ($normalized === '') {
            return null;
        }

        $parts = array_filter(explode('/', $normalized), static fn(string $part): bool => $part !== '');
        foreach ($parts as $part) {
            if ($part === '.' || $part === '..') {
                return null;
            }
        }

        return $base . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }

    public static function encodeRelativePath(string $path): string
    {
        $parts = explode('/', str_replace('\\', '/', $path));
        $encoded = array_map(static fn(string $part): string => rawurlencode($part), $parts);

        return implode('/', $encoded);
    }

    public static function relativePathFromBase(string $path, string $base): string
    {
        $normalizedPath = str_replace('\\', '/', rtrim($path, DIRECTORY_SEPARATOR));
        $normalizedBase = str_replace('\\', '/', rtrim($base, DIRECTORY_SEPARATOR));
        if ($normalizedPath === $normalizedBase) {
            return '.';
        }
        if (str_starts_with($normalizedPath, $normalizedBase . '/')) {
            return substr($normalizedPath, strlen($normalizedBase) + 1);
        }

        return ltrim($normalizedPath, '/');
    }

    public static function scanLayoutAssets(string $layoutDir): array
    {
        $assets = [];
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($layoutDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $pathName = $fileInfo->getPathname();
            $relativePath = substr($pathName, strlen($layoutDir) + 1);
            if ($relativePath === false || $relativePath === '') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $relativePath);
            if (in_array($relativePath, ['template.hbs', 'style.css', 'script.js'], true)) {
                continue;
            }

            $asset = [
                'name' => basename($relativePath),
                'path' => $relativePath,
                'size' => $fileInfo->getSize(),
                'updatedAt' => date('c', $fileInfo->getMTime()),
            ];
            $assets[] = $asset;
            $files[$relativePath] = $relativePath;
        }

        usort($assets, static fn(array $left, array $right): int => strcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? '')));

        return [$assets, $files];
    }

    public static function isDirectoryEmpty(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                return false;
            }
        }

        return true;
    }

    public static function hasWrapperFiles(string $layoutDir): bool
    {
        foreach (['template.hbs', 'style.css', 'script.js'] as $fileName) {
            if (is_file($layoutDir . DIRECTORY_SEPARATOR . $fileName)) {
                return true;
            }
        }

        return false;
    }
}
