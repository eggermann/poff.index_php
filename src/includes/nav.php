<?php
// Navigation wrapper used by the build inject step and source runtime.

require_once __DIR__ . '/nav-render.php';

$folderPoffConfig = $folderPoffConfig ?? null;

$navContext = [
    'baseDir' => $baseDir ?? '',
    'currentRelativePath' => $currentRelativePath ?? '',
    'currentAbsolutePath' => $currentAbsolutePath ?? '',
    'currentScript' => $currentScript ?? '',
    'folderPoffConfig' => $folderPoffConfig ?? null,
];

echo cmsRenderNavListMarkup($navContext);
