<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit/action/save/meta.php';

$config = [
    'title' => 'Old Title',
    'slug' => 'stable-slug',
];

cmsEditSaveApplyBasicFields($config, [
    'title' => 'New Title',
]);

if (($config['title'] ?? '') !== 'New Title') {
    fwrite(STDERR, "Expected the title to update.\n");
    exit(1);
}

if (($config['slug'] ?? '') !== 'stable-slug') {
    fwrite(STDERR, "Expected an existing slug to stay stable when the title changes.\n");
    exit(1);
}

$config = [
    'title' => '',
];

cmsEditSaveApplyBasicFields($config, [
    'title' => 'Generated Title',
]);

if (($config['slug'] ?? '') !== 'generated-title') {
    fwrite(STDERR, "Expected a missing slug to be synthesized from the title.\n");
    exit(1);
}

echo "ok" . PHP_EOL;
