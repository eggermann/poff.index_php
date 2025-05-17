<?php
/**
 * Common functions used throughout the application
 */

/**
 * Extracts a URL from "link"‑type files so we can treat them like normal hyperlinks
 * in the browser sidebar. Supports macOS *.webloc*, Windows *.url*, and Linux *.desktop* files.
 *
 * @param string $filePath  Absolute path to the link file.
 * @return string|null      The extracted URL or null if none was found.
 */
function extractLinkFileUrl(string $filePath): ?string {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $content = @file_get_contents($filePath);
    if (!$content) {
        return null;
    }

    switch ($ext) {
        case 'webloc':   // macOS .webloc (plist xml)
            if (preg_match('/<key>URL<\/key>\s*<string>([^<]+)<\/string>/i', $content, $m)) {
                return trim($m[1]);
            }
            break;

        case 'url':      // Windows Internet Shortcut (.url)
        case 'desktop':  // Linux .desktop
            if (preg_match('/^URL=(.+)$/mi', $content, $m)) {
                return trim($m[1]);
            }
            break;
    }
    return null;
}