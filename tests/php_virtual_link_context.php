<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/render.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-refs.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-context.php';
require_once __DIR__ . '/../src/includes/viewer/edit/prompt-compact.php';

$root = realpath(__DIR__ . '/poff-tests');
if ($root === false) {
    fwrite(STDERR, "Invalid test root\n");
    exit(1);
}

$folderRelativePath = 'virtual-links';
$folderDir = $root . DIRECTORY_SEPARATOR . $folderRelativePath;
$configPath = $folderDir . DIRECTORY_SEPARATOR . 'poff.config.json';
$config = json_decode((string) file_get_contents($configPath), true);
if (!is_array($config)) {
    fwrite(STDERR, "Invalid virtual link config\n");
    exit(1);
}

$viewerData = buildFolderViewerData($folderRelativePath, $folderDir, $config, [
    'name' => $config['folderName'] ?? 'virtual-links',
    'title' => $config['title'] ?? 'Virtual Links',
    'slug' => $config['slug'] ?? 'virtual-links',
]);

$promptContext = cmsPromptCompactContext(cmsBuildPromptContext($folderRelativePath, 'folder', $config));

echo json_encode([
    'tree' => $viewerData['tree'],
    'allLinks' => $viewerData['allLinks'],
    'prompt' => $promptContext,
], JSON_UNESCAPED_SLASHES);
