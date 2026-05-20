<?php

function cmsRenderNavListMarkup(array $context): string
{
    $baseDir = (string) ($context['baseDir'] ?? '');
    $currentRelativePath = (string) ($context['currentRelativePath'] ?? '');
    $currentAbsolutePath = (string) ($context['currentAbsolutePath'] ?? '');
    $currentScript = (string) ($context['currentScript'] ?? '');
    $folderPoffConfig = $context['folderPoffConfig'] ?? null;

    ob_start();
    include __DIR__ . '/nav.php';

    return (string) ob_get_clean();
}
