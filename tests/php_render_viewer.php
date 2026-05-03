<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/render.php';

$baseDir = $argv[1] ?? '';
$relativePath = $argv[2] ?? '';
$editMode = ($argv[3] ?? '') === 'edit';

if ($editMode) {
    $_GET['edit'] = 'true';
}

ob_start();
renderViewer($baseDir, $relativePath);
echo ob_get_clean();
