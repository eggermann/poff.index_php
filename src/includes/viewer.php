<?php
/**
 * Viewer partial: renders different templates for images, videos, links, and other files.
 */

function sanitizeRelativePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $path = trim($path, '/');
    return $path;
}

function detectFileType(string $path): string
{
    return MediaType::classifyExtension(basename($path));
}

function renderViewer(string $baseDir, string $requestedPath): void
{
    $relativePath = sanitizeRelativePath($requestedPath);

    if ($relativePath === '' || strpos($relativePath, '..') !== false) {
        http_response_code(400);
        echo 'Invalid file path.';
        return;
    }

    $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo 'File not found.';
        return;
    }

    // Ensure per-file config under .works and load it for work/model data
    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($fullPath), basename($fullPath));
    }

    $type = detectFileType($fullPath);
    $mimeType = MediaType::detectMimeType($fullPath, basename($fullPath));
    $workDefaults = Worktype::definition($type, $mimeType);
    $workData = (isset($fileConfig['work']) && is_array($fileConfig['work'])) ? $fileConfig['work'] : [];
    $work = array_merge($workDefaults, $workData);

    $linkUrl = null;
    if ($type === 'link') {
        $linkUrl = extractLinkFileUrl($fullPath);
    }

    $safePath = htmlspecialchars($relativePath, ENT_QUOTES, 'UTF-8');
    $safeName = htmlspecialchars(basename($relativePath), ENT_QUOTES, 'UTF-8');

    $safeLinkUrl = $linkUrl ? htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') : '';

    $bodyContent = Worktype::render($type, [
        'safePath' => $safePath,
        'safeName' => $safeName,
        'safeLinkUrl' => $safeLinkUrl,
        'work' => $work,
    ]);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer - {$safeName}</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #0b1021;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        header {
            padding: 12px 16px;
            background: #111827;
            border-bottom: 1px solid #1f2937;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        header .meta {
            display: flex;
            flex-direction: column;
        }
        header .meta .name {
            font-weight: 600;
        }
        header .meta .path {
            font-size: 12px;
            color: #9ca3af;
        }
        header a {
            color: #93c5fd;
            text-decoration: none;
            font-size: 14px;
        }
        header a:hover { text-decoration: underline; }
        .viewer {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0b1021;
            overflow: hidden;
        }
        .viewer img, .viewer video, .viewer iframe {
            max-width: 100%;
            max-height: 100%;
            width: auto;
            height: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,0.45);
            border-radius: 6px;
            background: #111827;
        }

        .viewer video, .viewer iframe {
            width: 100%;
            height: 100%;

        }

       .viewer iframe {
          background: #c3cddbff;

        }


        .message {
            padding: 24px;
            text-align: center;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <header>
        <div class="meta">
            <div class="name">{$safeName} <span style="opacity:0.7;font-size:12px;">({$type})</span></div>
            <div class="path">{$safePath}</div>
        </div>
        <div>
            <a href="{$safePath}" target="_blank" rel="noopener">Open Raw</a>
        </div>
    </header>
    <div class="viewer">
        {$bodyContent}
    </div>
</body>
</html>
HTML;

    echo $html;
}
