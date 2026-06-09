<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';

$checks = [
    ['video/mp4', MediaType::detectMimeType('/tmp/generated-video-1.mp4', 'generated-video-1.mp4')],
    ['video/webm', MediaType::detectMimeType('/tmp/generated-video-2.webm', 'generated-video-2.webm')],
    ['image/jpeg', MediaType::detectMimeType('/tmp/generated-image.jpg', 'generated-image.jpg')],
    ['application/json', MediaType::detectMimeType('/tmp/generated-data.json', 'generated-data.json')],
    ['application/json', MediaType::mimeFromExtension('json')],
    ['application/rtf', MediaType::mimeFromExtension('rtf')],
    ['video/quicktime', PoffConfig::detectMimeType('/tmp/generated-video-3.mov', 'generated-video-3.mov')],
    ['image/png', PoffConfig::detectMimeType('/tmp/generated-image-2.png', 'generated-image-2.png')],
];

foreach ($checks as [$expected, $actual]) {
    if ($actual !== $expected) {
        fwrite(STDERR, "Expected {$expected}, got " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

if (MediaType::shouldUseInlineTextPreview('generated-video-1.mp4', 'video/mp4')) {
    fwrite(STDERR, "Video files should not use inline text preview." . PHP_EOL);
    exit(1);
}

echo "ok" . PHP_EOL;
