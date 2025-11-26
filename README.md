# PHP File Browser Build Instructions

## Prerequisites
- PHP 7.4+ installed
- Git (for version control)
- No external dependencies required

## Build Steps

1. **Clone or copy the repository to your server or local machine.**

2. **Edit your source files as needed:**
   - Main PHP: `src/index.php`
   - Components: `src/includes/header.php`, `src/includes/layout.php`, `src/includes/scripts.php`
   - Styles: `src/frontend/css/styles.css`

3. **Build process (manual or script):**
   - Inline CSS for production:
     - Copy the contents of `src/frontend/css/styles.css`.
     - Paste into a `<style>` tag at the top of your production `index.php` (or use the provided PHP snippet to automate).
   - Example PHP snippet for inlining CSS:
     ```php
     echo '<style>';
     echo file_get_contents(__DIR__ . "/src/frontend/css/styles.css");
     echo '</style>';
     ```
   - Remove or comment out any `<link rel="stylesheet" ...>` tags in production output.

4. **Deploy:**
   - Upload all PHP files and assets to your web server.
   - Ensure file permissions allow PHP execution and file reading.

## Usage

- Open `index.php` in your browser.
- Navigate folders and files using the sidebar.
- Visual loading indicators are shown for folder navigation.

## Notes

- For development, use external CSS for easier editing.
- For production, inline CSS for faster load and single-file deployment.
- No build tools required; all steps can be performed manually or scripted in PHP.

## Auto-build, symlink, and browser refresh

- Run `npm run watch` to monitor `src` and rebuild via `build/build.php` whenever files change.
- After each successful build it ensures a symlink from the configured `outputDir` (in `build/BuildConfig.php`) into `/Applications/MAMP/htdocs/{outputDirBasename}` so MAMP serves the fresh build.
- The watcher prints the URL it expects under MAMP (defaults to `http://localhost:8888/{outputDirBasename}/`), and asks the front-most Chrome or Safari tab to reload after builds; if neither is open it simply skips the refresh.
