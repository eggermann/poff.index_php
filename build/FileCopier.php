<?php
class FileCopier {
    public static function copyFileToAllDirectories($sourceFile, $targetDir) {
        $projectRoot = $targetDir;
        
        if (!file_exists($sourceFile)) {
            throw new Exception("Source file not found: $sourceFile");
        }

        // Get all directories recursively
        $dirs = self::findAllDirectories($projectRoot);
        foreach ($dirs as $dir) {
            $destination = $dir . DIRECTORY_SEPARATOR . 'index.php';
            if (copy($sourceFile, $destination)) {
                echo "Copied to: $destination\n";
            } else {
                echo "Failed to copy to: $destination\n";
            }
        }
    }

    public static function mirrorDirectory(string $sourceDir, string $targetDir): void {
        if (!is_dir($sourceDir)) {
            throw new Exception("Source directory not found: $sourceDir");
        }

        self::removeDirectory($targetDir);
        self::copyDirectory($sourceDir, $targetDir);
    }

    private static function findAllDirectories($root) {
        $dirs = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                // Skip special directories
                $pathname = $item->getPathname();
                $dirname = basename($pathname);
                
                // Skip build, vendor, node_modules, and hidden directories
                if (in_array($dirname, ['build', 'vendor', 'node_modules']) || 
                    strpos($dirname, '.') === 0 ||
                    strpos($pathname, DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR) !== false ||
                    strpos($pathname, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false ||
                    strpos($pathname, DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR) !== false) {
                    continue;
                }
                
                $dirs[] = $pathname;
                echo "Found directory: $pathname\n";
            }
        }
        
        return $dirs;
    }

    private static function copyDirectory(string $sourceDir, string $targetDir): void {
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new Exception("Failed to create target directory: $targetDir");
        }

        $iterator = new DirectoryIterator($sourceDir);
        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            $sourcePath = $item->getPathname();
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $item->getFilename();

            if ($item->isDir()) {
                self::copyDirectory($sourcePath, $targetPath);
                continue;
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new Exception("Failed to copy asset: $sourcePath");
            }
        }
    }

    private static function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                rmdir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
