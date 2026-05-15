<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';

$tempDir = sys_get_temp_dir() . '/poff-link-test-' . bin2hex(random_bytes(4));
mkdir($tempDir, 0777, true);

file_put_contents($tempDir . '/sample.url', "[InternetShortcut]\nURL=https://example.com/\n");
file_put_contents($tempDir . '/sample.webloc', '<?xml version="1.0"?><plist><dict><key>URL</key><string>https://example.org/</string></dict></plist>');
file_put_contents($tempDir . '/sample.mov', str_repeat('x', 1024));

$url = extractLinkFileUrl($tempDir . '/sample.url');
$webloc = extractLinkFileUrl($tempDir . '/sample.webloc');
$mov = extractLinkFileUrl($tempDir . '/sample.mov');

if ($url !== 'https://example.com/') {
    fwrite(STDERR, "Expected URL link extraction to succeed." . PHP_EOL);
    exit(1);
}

if ($webloc !== 'https://example.org/') {
    fwrite(STDERR, "Expected webloc link extraction to succeed." . PHP_EOL);
    exit(1);
}

if ($mov !== null) {
    fwrite(STDERR, "Expected mov files to be ignored by link extraction." . PHP_EOL);
    exit(1);
}

echo "ok" . PHP_EOL;
