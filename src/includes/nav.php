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

$isNavigationFetch = (string) ($_GET['ajax'] ?? '') === 'nav';
$isEditorNavigation = isset($_GET['edit']) && $_GET['edit'] === 'true';

// Public visitors start with the work surface, not a duplicate server-rendered tree.
// The navigation is fetched the first time they open Explore work.
if ($isNavigationFetch || $isEditorNavigation) {
    echo cmsRenderNavListMarkup($navContext);
}
