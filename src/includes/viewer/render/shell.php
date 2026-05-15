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
    $layoutJsInlineAfterHref = trim((string) ($layout['jsInlineAfterHref'] ?? ''));
    $isPreviewFetch = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch-preview';
    $viewerShellCss = $isPreviewFetch ? '' : trim((string) ($GLOBALS['__embeddedViewerShellCss'] ?? ''));
    if ($viewerShellCss === '' && !$isPreviewFetch) {
        $projectRootDir = function_exists('cmsProjectRootDir') ? cmsProjectRootDir() : dirname(__DIR__, 4);
        $viewerShellCssPath = rtrim($projectRootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'app.css';
        $viewerShellCss = is_file($viewerShellCssPath) ? trim((string) file_get_contents($viewerShellCssPath)) : '';
    }

    $html = "<!DOCTYPE html>\n";
    $html .= "<html lang=\"en\">\n";
    $html .= "<head>\n";
    $html .= "    <meta charset=\"UTF-8\">\n";
    $html .= "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
    $html .= "    <title>Viewer - {$safeName}</title>\n";
    if ($viewerShellCss !== '') {
        $html .= "    <style data-app-style>{$viewerShellCss}</style>\n";
    }
    if ($layoutCssHref !== '') {
        $safeCssHref = htmlspecialchars($layoutCssHref, ENT_QUOTES, 'UTF-8');
        $html .= "    <link rel=\"stylesheet\" href=\"{$safeCssHref}\">\n";
    } elseif ($layoutCssInline !== '') {
        $html .= "    <style data-layout-style>{$layoutCssInline}</style>\n";
    }
    $html .= "</head>\n";
    $html .= "<body class=\"m-0 min-h-full overflow-x-hidden overflow-y-auto bg-slate-950 p-0 text-slate-200\">\n";
    $html .= "    <div class=\"viewer block min-h-screen w-full overflow-x-hidden overflow-y-auto bg-slate-950\" data-viewer-type=\"{$safeType}\">\n";
    $html .= $bodyContent . "\n";
    $html .= "    </div>\n";
    if ($layoutJsHref !== '') {
        $safeJsHref = htmlspecialchars($layoutJsHref, ENT_QUOTES, 'UTF-8');
        $html .= "    <script src=\"{$safeJsHref}\" defer></script>\n";
    }
    if ($layoutJsHref === '' && $layoutJsInline !== '') {
        $html .= "    <script>{$layoutJsInline}</script>\n";
    }
    if ($layoutJsInlineAfterHref !== '') {
        $html .= "    <script>{$layoutJsInlineAfterHref}</script>\n";
    }
    $html .= "</body>\n";
    $html .= "</html>\n";

    echo $html;
}
