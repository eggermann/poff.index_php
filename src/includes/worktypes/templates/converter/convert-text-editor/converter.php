<?php
declare(strict_types=1);

function textEditorConverterResolveFormat(string $format, string $sourcePath, string $sourceMime): string
{
    $normalized = strtolower(trim($format));
    if ($normalized !== 'source') {
        return $normalized === 'markdown' ? 'md' : $normalized;
    }

    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    if (in_array($extension, ['json', 'html', 'txt', 'md'], true)) {
        return $extension;
    }

    return match (strtolower(trim($sourceMime))) {
        'application/json' => 'json',
        'text/html' => 'html',
        'text/markdown' => 'md',
        default => 'txt',
    };
}

function textEditorConverterMimeFromFormat(string $format, string $sourcePath, string $sourceMime): string
{
    return match (textEditorConverterResolveFormat($format, $sourcePath, $sourceMime)) {
        'json' => 'application/json',
        'md' => 'text/markdown',
        'html' => 'text/html',
        default => 'text/plain',
    };
}

function textEditorConverterRenderHtml(string $content, string $sourceName, string $converterLabel): string
{
    $safeTitle = htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8');
    $safeConverter = htmlspecialchars($converterLabel, ENT_QUOTES, 'UTF-8');
    $safeBody = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$safeTitle}</title>
    <style>
        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background: #0b1020;
            color: #e5eefb;
        }
        .poff-text-converted {
            max-width: 72rem;
            margin: 0 auto;
            padding: 2rem;
        }
        .poff-text-converted__meta {
            display: grid;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            color: #8da7cb;
        }
        .poff-text-converted__surface {
            border: 1px solid rgba(124, 162, 255, 0.28);
            border-radius: 1rem;
            background: rgba(14, 22, 42, 0.88);
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            overflow: hidden;
        }
        .poff-text-converted__body {
            margin: 0;
            padding: 1.5rem;
            font-family: "IBM Plex Mono", "SFMono-Regular", monospace;
            font-size: 14px;
            line-height: 1.65;
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>
    <main class="poff-text-converted">
        <div class="poff-text-converted__meta">
            <strong>{$safeConverter}</strong>
            <span>Source: {$safeTitle}</span>
            <span>PHP used: yes</span>
        </div>
        <section class="poff-text-converted__surface">
            <pre class="poff-text-converted__body">{$safeBody}</pre>
        </section>
    </main>
</body>
</html>
HTML;
}

function textEditorConverterHandle(array $payload): array
{
    $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
    $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
    $editor = is_array($payload['editor'] ?? null) ? $payload['editor'] : [];
    $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];

    $converterId = strtolower(trim((string) ($converter['id'] ?? '')));
    if ($converterId !== 'converter/convert-text-editor') {
        return [
            'ok' => false,
            'error' => 'Unsupported converter for text editor local PHP endpoint.',
        ];
    }

    $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
    $sourceName = (string) ($source['name'] ?? ($sourcePath !== '' ? basename($sourcePath) : 'text-source'));
    $requestedFormat = strtolower(trim((string) ($converter['format'] ?? 'source')));
    $actualFormat = textEditorConverterResolveFormat($requestedFormat, $sourcePath, (string) ($source['mimeType'] ?? ''));
    $outputMime = textEditorConverterMimeFromFormat($requestedFormat, $sourcePath, (string) ($source['mimeType'] ?? ''));
    $outputName = trim((string) ($target['saveAs'] ?? ''));
    if ($outputName === '') {
        $baseName = pathinfo($sourceName, PATHINFO_FILENAME);
        $outputName = $requestedFormat === 'source'
            ? $sourceName
            : $baseName . '.' . $actualFormat;
    }

    $body = array_key_exists('content', $editor) ? (string) ($editor['content'] ?? '') : '';
    if ($actualFormat === 'html') {
        $body = textEditorConverterRenderHtml(
            $body,
            $sourceName,
            (string) ($converter['label'] ?? 'Convert Text Editor')
        );
    }

    return [
        'ok' => true,
        'type' => 'converted-work',
        'source' => [
            'path' => $sourcePath,
            'mimeType' => (string) ($source['mimeType'] ?? ''),
        ],
        'output' => [
            'name' => $outputName,
            'mimeType' => $outputMime,
            'kind' => 'text',
            'size' => strlen($body),
            'bodyBase64' => base64_encode($body),
        ],
        'generatedBy' => [
            'type' => 'converter',
            'name' => 'convert-text-editor',
            'id' => 'converter/convert-text-editor',
            'node' => 'text-editor-local-php',
            'engine' => 'remote-node',
            'quality' => strtolower(trim((string) ($converter['quality'] ?? 'default'))),
            'format' => $requestedFormat,
            'outputFormat' => $actualFormat,
            'usedPhp' => true,
            'convertedAt' => date('c'),
        ],
    ];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $raw = file_get_contents('php://input');
    $payload = $raw !== false && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($payload)) {
        $payload = [];
    }

    header('Content-Type: application/json');
    echo json_encode(textEditorConverterHandle($payload), JSON_UNESCAPED_SLASHES);
    exit;
}
