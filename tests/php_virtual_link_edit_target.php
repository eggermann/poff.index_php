<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';

$rootDir = sys_get_temp_dir() . '/poff-virtual-link-edit-' . bin2hex(random_bytes(4));
mkdir($rootDir, 0777, true);
file_put_contents($rootDir . '/poff.config.json', json_encode([
    'folderName' => 'poff-virtual-link-edit',
    'slug' => 'poff-virtual-link-edit',
    'title' => 'Poff Virtual Link Edit',
    'type' => 'folder',
    'tree' => [
        [
            'name' => 'dominikeggermann.com',
            'title' => 'dominikeggermann.com',
            'slug' => 'dominikeggermann-com',
            'type' => 'link',
            'kind' => 'link',
            'path' => 'dominikeggermann.com',
            'linkUrl' => 'https://dominikeggermann.com/',
            'visible' => true,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$resolved = cmsResolveTarget($rootDir, 'dominikeggermann.com');
$config = null;
if (is_array($resolved)) {
    set_error_handler(static function (): bool {
        return true;
    });
    $config = PoffConfig::ensureFileConfig((string) ($resolved['dir'] ?? ''), (string) ($resolved['file'] ?? ''));
    restore_error_handler();
}

echo json_encode([
    'resolved' => $resolved,
    'config' => $config,
], JSON_UNESCAPED_SLASHES);
