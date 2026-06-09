<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/auth.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';

$rootDir = sys_get_temp_dir() . '/poff-hidden-route-' . bin2hex(random_bytes(4));
mkdir($rootDir, 0777, true);
file_put_contents($rootDir . '/poff.config.json', json_encode([
    'folderName' => 'poff-hidden-route',
    'slug' => 'poff-hidden-route',
    'title' => 'Poff Hidden Route',
    'type' => 'folder',
    'tree' => [
        [
            'name' => 'XXXXXX',
            'title' => 'XXXXXX',
            'slug' => 'xxxxxx',
            'type' => 'link',
            'kind' => 'link',
            'path' => 'XXXXXX',
            'linkUrl' => 'https://dominikeggermann.com/#/ai-movs/joined-test-1761605035676.mp4',
            'visible' => false,
            'externalSubmission' => true,
            'approvalStatus' => 'pending',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$public = cmsResolveSlugRouteInTree($rootDir, '', 'xxxxxx');
$editor = cmsResolveSlugRouteInTree($rootDir, '', 'xxxxxx', 0, true);

echo json_encode([
    'public' => $public,
    'editor' => $editor,
], JSON_UNESCAPED_SLASHES);
