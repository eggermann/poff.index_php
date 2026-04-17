<?php

function renderViewerShell(array $payload): void
{
    $rawType = (string) ($payload['type'] ?? 'file');
    $rawName = (string) ($payload['name'] ?? '');
    $bodyContent = (string) ($payload['bodyContent'] ?? '');
    $layout = isset($payload['layout']) && is_array($payload['layout']) ? $payload['layout'] : [];

    $safeName = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
    $safeType = htmlspecialchars($rawType, ENT_QUOTES, 'UTF-8');
    $layoutCssHref = trim((string) ($layout['cssHref'] ?? ''));
    $layoutJsHref = trim((string) ($layout['jsHref'] ?? ''));
    $layoutCssInline = $layoutCssHref === '' ? trim((string) ($layout['css'] ?? '')) : '';
    $layoutJsInline = $layoutJsHref === '' ? trim((string) ($layout['js'] ?? '')) : '';

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer - <?= $safeName ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100%;
            background: #0b1021;
            color: #e5e7eb;
            font-family: Arial, sans-serif;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .viewer {
            --poff-viewport-height: 100dvh;
            --poff-work-max-height: 100dvh;
            min-height: var(--poff-viewport-height);
            width: 100%;
            display: block;
            background: #0b1021;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .viewer img, .viewer video, .viewer iframe, .viewer canvas, .viewer svg {
            max-width: 100%;
            max-height: var(--poff-work-max-height);
            width: auto;
            height: auto;
            box-shadow: 0 12px 30px rgba(0,0,0,0.45);
            border-radius: 6px;
            background: #111827;
        }
        .viewer iframe {
            width: 100%;
            min-height: var(--poff-work-max-height);
            background: #c3cddbff;
        }
        .viewer .viewer-template,
        .viewer .poff-default-layout {
            width: 100%;
            min-height: var(--poff-viewport-height);
            box-sizing: border-box;
        }
        .viewer .viewer-template--folder {
            padding: 24px;
        }
        .folder-view {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .folder-view-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            color: #9ca3af;
            font-size: 13px;
        }
        .folder-view-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }
        .folder-view-card {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            background: rgba(17, 24, 39, 0.72);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.28);
        }
        .folder-view-card-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(59, 130, 246, 0.16);
            color: #93c5fd;
            font-size: 11px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .folder-view-card-link {
            color: inherit;
            text-decoration: none;
        }
        .folder-view-card-link:hover,
        .folder-view-card-link:focus-visible {
            text-decoration: underline;
        }
        .folder-view-card--folder .folder-view-card-label {
            background: rgba(34, 197, 94, 0.16);
            color: #86efac;
        }
        .folder-view-card-name {
            display: block;
            margin-top: 14px;
            font-size: 16px;
            font-weight: 600;
            color: #f9fafb;
            word-break: break-word;
        }
        .folder-view-card-path {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            color: #94a3b8;
            word-break: break-word;
        }
        .work-description {
            position: absolute;
            bottom: 16px;
            left: 16px;
            right: 16px;
            padding: 12px 14px;
            background: rgba(17, 24, 39, 0.6);
            color: #e5e7eb;
            border-radius: 16px;
            backdrop-filter: blur(8px);
            margin: 0;
            max-width: 70%;
            line-height: 1.4;
            box-shadow: 0 20px 40px rgba(0,0,0,0.45);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .message { padding: 24px; text-align: center; color: #d1d5db; }
    </style>
<?php if ($layoutCssHref !== ''): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($layoutCssHref, ENT_QUOTES, 'UTF-8') ?>">
<?php elseif ($layoutCssInline !== ''): ?>
    <style data-layout-style><?= $layoutCssInline ?></style>
<?php endif; ?>
</head>
<body>
    <div class="viewer">
        <?= $bodyContent ?>
    </div>
<?php if ($layoutJsHref !== ''): ?>
    <script src="<?= htmlspecialchars($layoutJsHref, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php elseif ($layoutJsInline !== ''): ?>
    <script><?= $layoutJsInline ?></script>
<?php endif; ?>
</body>
</html>
<?php
}
