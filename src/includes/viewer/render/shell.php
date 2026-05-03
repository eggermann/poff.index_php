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
    $projectRootDir = function_exists('cmsProjectRootDir') ? cmsProjectRootDir() : dirname(__DIR__, 4);
    $viewerShellCssPath = rtrim($projectRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.css';
    $viewerShellCss = is_file($viewerShellCssPath) ? trim((string) file_get_contents($viewerShellCssPath)) : '';

    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewer - <?= $safeName ?></title>
<?php if ($viewerShellCss !== ''): ?>
    <style data-app-style><?= $viewerShellCss ?></style>
<?php endif; ?>
<?php if ($layoutCssHref !== ''): ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($layoutCssHref, ENT_QUOTES, 'UTF-8') ?>">
<?php elseif ($layoutCssInline !== ''): ?>
    <style data-layout-style><?= $layoutCssInline ?></style>
<?php endif; ?>
</head>
<body class="m-0 min-h-full overflow-x-hidden overflow-y-auto bg-slate-950 p-0 text-slate-200">
    <div class="viewer block min-h-screen w-full overflow-x-hidden overflow-y-auto bg-slate-950">
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
