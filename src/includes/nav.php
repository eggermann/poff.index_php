<?php
// Navigation wrapper used by the build inject step and source runtime.

$navContext = [
    'baseDir' => $baseDir ?? '',
    'currentRelativePath' => $currentRelativePath ?? '',
    'currentAbsolutePath' => $currentAbsolutePath ?? '',
    'currentScript' => $currentScript ?? '',
    'folderPoffConfig' => $folderPoffConfig ?? null,
];

echo cmsRenderNavListMarkup($navContext);
