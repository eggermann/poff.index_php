<?php
/**
 * Media type helper: shared MIME/classification detection.
 */

class MediaType
{
    private const IMAGE_EXTS = ['png','jpg','jpeg','gif','webp','bmp','svg','tif','tiff','heic'];
    private const VIDEO_EXTS = ['mp4','mov','webm','avi','mkv','m4v','mts'];
    private const AUDIO_EXTS = ['mp3','wav','ogg','m4a','flac','aac'];
    private const LINK_EXTS  = ['webloc','url','desktop'];
    private const TEXT_EXTS  = ['txt','md','csv','json','log','ini','yml','yaml','xml','html','htm','css','js','rtf','hbs','tpl','mustache'];
    private const INLINE_TEXT_PREVIEW_EXTS = ['rtf','hbs','tpl','mustache'];

    private const MIME_BY_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'bmp' => 'image/bmp',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'heic' => 'image/heic',
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'avi' => 'video/x-msvideo',
        'mkv' => 'video/x-matroska',
        'm4v' => 'video/x-m4v',
        'mts' => 'video/MP2T',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'md' => 'text/markdown',
        'csv' => 'text/csv',
        'json' => 'application/json',
        'log' => 'text/plain',
        'ini' => 'text/plain',
        'yml' => 'text/yaml',
        'yaml' => 'text/yaml',
        'xml' => 'application/xml',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'rtf' => 'application/rtf',
    ];

    public static function classifyExtension(string $fileName): string
    {
        if (basename($fileName) === '.htaccess') {
            return 'htaccess';
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, self::IMAGE_EXTS, true)) {
            return 'image';
        }
        if (in_array($ext, self::VIDEO_EXTS, true)) {
            return 'video';
        }
        if (in_array($ext, self::AUDIO_EXTS, true)) {
            return 'audio';
        }
        if (in_array($ext, self::LINK_EXTS, true)) {
            return 'link';
        }
        if (in_array($ext, self::TEXT_EXTS, true)) {
            return 'text';
        }
        if ($ext === 'pdf') {
            return 'pdf';
        }
        return 'other';
    }

    public static function detectMimeType(string $fullPath, string $fileName): ?string
    {
        static $cache = [];

        $resolvedPath = realpath($fullPath);
        $normalizedPath = is_string($resolvedPath) && $resolvedPath !== '' ? $resolvedPath : $fullPath;
        $cacheKey = $normalizedPath
            . '|'
            . (string) (@filemtime($normalizedPath) ?: 0)
            . '|'
            . (string) (@filesize($normalizedPath) ?: 0)
            . '|'
            . strtolower($fileName);
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        if (basename($fileName) === '.htaccess') {
            return $cache[$cacheKey] = 'text/plain';
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (isset(self::MIME_BY_EXT[$ext])) {
            return $cache[$cacheKey] = self::MIME_BY_EXT[$ext];
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $fullPath);
                finfo_close($finfo);
                if ($mime !== false) {
                    return $cache[$cacheKey] = $mime;
                }
            }
        }

        $cache[$cacheKey] = null;

        return null;
    }

    public static function shouldUseInlineTextPreview(string $fileName, ?string $mimeType = null): bool
    {
        if (basename($fileName) === '.htaccess') {
            return true;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, self::INLINE_TEXT_PREVIEW_EXTS, true)) {
            return true;
        }

        $mime = strtolower(trim((string) $mimeType));
        return $mime === 'application/rtf' || $mime === 'text/rtf';
    }
}
