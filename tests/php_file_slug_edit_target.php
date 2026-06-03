<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';

$rootDir = sys_get_temp_dir() . '/poff-file-slug-edit-' . bin2hex(random_bytes(4));
mkdir($rootDir, 0777, true);
file_put_contents($rootDir . '/bak.htaccess', "Options +Indexes\n");
file_put_contents($rootDir . '/poff.config.json', json_encode([
    'folderName' => 'poff-file-slug-edit',
    'slug' => 'poff-file-slug-edit',
    'title' => 'Poff File Slug Edit',
    'type' => 'folder',
    'tree' => [
        [
            'name' => 'bak.htaccess',
            'title' => 'bak.htaccess',
            'slug' => 'bak-htaccess',
            'type' => 'file',
            'path' => 'bak.htaccess',
            'visible' => true,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$resolved = cmsResolveTarget($rootDir, 'bak-htaccess');
$config = null;
if (is_array($resolved)) {
    $config = PoffConfig::ensureFileConfig((string) ($resolved['dir'] ?? ''), (string) ($resolved['file'] ?? ''));
}

echo json_encode([
    'resolved' => $resolved,
    'config' => $config,
], JSON_UNESCAPED_SLASHES);
