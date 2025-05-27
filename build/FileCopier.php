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
}