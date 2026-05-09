<?php

trait PoffConfigLayoutCollectionHelpers
{
    private static function findInheritedLayoutDir(string $dir, string $localLayoutDir): ?array
    {
        $cwd = realpath(getcwd() ?: '.');
        $current = realpath($dir);
        if ($current === false) {
            return null;
        }

        $localLayoutRealpath = realpath($localLayoutDir) ?: $localLayoutDir;

        while ($current !== false) {
            $candidate = $current . DIRECTORY_SEPARATOR . self::DEFAULT_LAYOUT_FOLDER;
            if ($candidate !== $localLayoutRealpath && is_dir($candidate)) {
                return [
                    'absolute' => $candidate,
                    'relative' => self::relativePathFromBase($candidate, $cwd ?: $current),
                ];
            }

            if ($cwd && $current === $cwd) {
                break;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = realpath($parent);
        }

        return null;
    }

    private static function layoutCollectionChoices(string $dir, string $section): array
    {
        $choices = Worktype::sharedLayoutChoices($section);
        foreach (self::filesystemLayoutCollectionChoices($dir, $section) as $choice) {
            $choices[] = $choice;
        }

        usort($choices, static function (array $left, array $right): int {
            $sourceCompare = strcasecmp((string) ($left['source'] ?? ''), (string) ($right['source'] ?? ''));
            if ($sourceCompare !== 0) {
                return $sourceCompare;
            }

            return strcasecmp((string) ($left['label'] ?? $left['name'] ?? ''), (string) ($right['label'] ?? $right['name'] ?? ''));
        });

        return $choices;
    }

    private static function layoutCollectionPackage(string $dir, string $section, string $name): ?array
    {
        $filesystemPackage = self::filesystemLayoutCollectionPackage($dir, $section, $name);
        if (is_array($filesystemPackage)) {
            return $filesystemPackage;
        }

        return Worktype::sharedLayoutPackage($section, $name);
    }

    private static function filesystemLayoutCollectionChoices(string $dir, string $section): array
    {
        $root = self::layoutCollectionRoot($dir);
        if ($root === null) {
            return [];
        }

        $currentLayoutDir = realpath($section === 'works' ? self::folderLayoutDir($dir) : $dir);
        $choices = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isDir() || $fileInfo->getFilename() !== self::DEFAULT_LAYOUT_FOLDER) {
                continue;
            }

            $layoutDir = $fileInfo->getPathname();
            if ($currentLayoutDir !== false && realpath($layoutDir) === $currentLayoutDir) {
                continue;
            }

            $package = self::filesystemLayoutPackageFromDirectory($root, $layoutDir, $section);
            if (is_array($package)) {
                $choices[] = $package;
            }
        }

        return $choices;
    }

    private static function filesystemLayoutCollectionPackage(string $dir, string $section, string $name): ?array
    {
        $root = self::layoutCollectionRoot($dir);
        if ($root === null) {
            return null;
        }

        $relativeName = str_replace('\\', '/', trim($name, "/\\"));
        if ($relativeName === '' || !str_ends_with($relativeName, '/' . self::DEFAULT_LAYOUT_FOLDER) && $relativeName !== self::DEFAULT_LAYOUT_FOLDER) {
            return null;
        }

        $layoutDir = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeName);
        return self::filesystemLayoutPackageFromDirectory($root, $layoutDir, $section);
    }

    private static function filesystemLayoutPackageFromDirectory(string $root, string $layoutDir, string $section): ?array
    {
        if (!is_dir($layoutDir)) {
            return null;
        }

        $templatePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_TEMPLATE_FILE;
        if (!is_file($templatePath)) {
            return null;
        }

        $relativeDirectory = self::relativePathFromBase($layoutDir, $root);
        $parentDirectory = dirname($relativeDirectory);
        $folderName = $parentDirectory === '.' ? basename($root) : basename($parentDirectory);
        $sectionTemplate = self::readOptionalLayoutFile($layoutDir, self::sectionTemplateFile($section)) ?? '';
        [$assets, $files] = self::scanLayoutAssets($layoutDir);

        return [
            'name' => $relativeDirectory,
            'folderName' => $folderName,
            'label' => $folderName,
            'source' => 'collection',
            'section' => $section,
            'directory' => $relativeDirectory,
            'template' => (string) file_get_contents($templatePath),
            'css' => self::readOptionalLayoutFile($layoutDir, self::LAYOUT_STYLE_FILE) ?? '',
            'js' => self::readOptionalLayoutFile($layoutDir, self::LAYOUT_SCRIPT_FILE) ?? '',
            'sectionTemplate' => $sectionTemplate,
            'assets' => $assets,
            'files' => $files,
        ];
    }

    private static function layoutCollectionRoot(string $dir): ?string
    {
        $current = realpath($dir);
        $cwd = realpath(getcwd() ?: '.');
        if ($current === false) {
            return null;
        }

        if ($cwd !== false) {
            $pagesRoot = $cwd . DIRECTORY_SEPARATOR . 'pages';
            if (str_starts_with($current, $pagesRoot . DIRECTORY_SEPARATOR)) {
                $relative = substr($current, strlen($pagesRoot) + 1);
                $parts = explode(DIRECTORY_SEPARATOR, $relative);
                if (($parts[0] ?? '') !== '') {
                    return $pagesRoot . DIRECTORY_SEPARATOR . $parts[0];
                }
            }

            $testsRoot = $cwd . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'poff-tests';
            if ($current === $testsRoot || str_starts_with($current, $testsRoot . DIRECTORY_SEPARATOR)) {
                return $testsRoot;
            }
        }

        $cursor = $current;
        while ($cursor !== false) {
            if (is_file($cursor . DIRECTORY_SEPARATOR . '.edit.allow')) {
                return $cursor;
            }
            $parent = dirname($cursor);
            if ($parent === $cursor || ($cwd !== false && $cursor === $cwd)) {
                break;
            }
            $cursor = realpath($parent);
        }

        return $current;
    }

    private static function readOptionalLayoutFile(string $layoutDir, string $file): ?string
    {
        $path = $layoutDir . DIRECTORY_SEPARATOR . $file;
        return is_file($path) ? (string) file_get_contents($path) : null;
    }
}
