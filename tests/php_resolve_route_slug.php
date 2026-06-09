<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';

$rootDir = sys_get_temp_dir() . '/poff-route-slug-' . bin2hex(random_bytes(4));
$nestedDir = $rootDir . '/asasdasdadasd';
mkdir($nestedDir, 0777, true);
file_put_contents($nestedDir . '/1749825166559-flux.jpeg', 'fixture');

$rootConfig = [
    'tree' => [
        [
            'name' => 'asasdasdadasd',
            'slug' => 'asasdasdadasd',
            'type' => 'folder',
            'path' => 'asasdasdadasd',
            'title' => 'meine maus',
            'visible' => true,
        ],
    ],
];

$nestedConfig = [
    'tree' => [
        [
            'name' => '1749825166559-flux.jpeg',
            'slug' => 'medusa',
            'type' => 'file',
            'path' => '1749825166559-flux.jpeg',
            'title' => 'medusa',
            'visible' => true,
        ],
    ],
];

file_put_contents($rootDir . '/poff.config.json', json_encode($rootConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($nestedDir . '/poff.config.json', json_encode($nestedConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$resolved = cmsResolveSlugRouteInTree($rootDir, '', '1749825166559-flux-jpeg');

if (!is_array($resolved)) {
    fwrite(STDERR, "Expected the route slug to resolve.\n");
    exit(1);
}

if (($resolved['path'] ?? '') !== 'asasdasdadasd/1749825166559-flux.jpeg') {
    fwrite(STDERR, "Expected the resolved path to stay tied to the file name.\n");
    exit(1);
}

if (($resolved['routeSlug'] ?? '') !== '1749825166559-flux-jpeg') {
    fwrite(STDERR, "Expected the route slug to be derived from the file name.\n");
    exit(1);
}

if (($resolved['slug'] ?? '') !== 'medusa') {
    fwrite(STDERR, "Expected the stored item slug to remain the edited title slug.\n");
    exit(1);
}

echo "ok" . PHP_EOL;
