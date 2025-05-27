<?php
/**
 * Component Reader for the build process.
 */

class ComponentReader
{
    public static function readComponentFile($path)
    {
        if (!file_exists($path)) {
            throw new Exception("File not found: $path");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new Exception("Failed to read file: $path");
        }

        // Remove PHP tags
        $content = preg_replace('/^<\?php\s*/', '', $content);
        $content = preg_replace('/\?>\s*$/', '', $content);

        // Remove PHP doc comments that shouldn't be in the output
        $content = preg_replace('/\/\*\*\s*\n\s*\*[^*]*\*\/\s*\n/', '', $content);

        return $content;
    }
}