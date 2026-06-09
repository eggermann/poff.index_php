<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/functions.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/viewer/utils.php';
require_once __DIR__ . '/../src/includes/viewer/edit.php';

$GLOBALS['__poff_prompt_http_get'] = static function (string $url, array $headers = []): array {
    return [
        'ok' => true,
        'status' => 200,
        'statusLine' => 'HTTP/1.1 200 OK',
        'body' => '<html><body><article class="snapshot">Remote Snapshot</article></body></html>',
    ];
};

$targetDir = sys_get_temp_dir() . '/poff-link-test-' . bin2hex(random_bytes(4));
mkdir($targetDir, 0777, true);

$label = 'Remote Index';
$linkUrl = 'https://remote.example/index.php?view=1&path=portfolio';
$result = cmsCreateLinkEntry($targetDir, $label, $linkUrl);
$config = PoffConfig::ensure($targetDir);

$linkItems = array_values(array_filter(
    is_array($config['tree'] ?? null) ? $config['tree'] : [],
    static fn(array $item): bool => ($item['type'] ?? '') === 'link'
));

if (($result['stored'][0]['linkUrl'] ?? '') !== $linkUrl) {
    fwrite(STDERR, "Expected link helper to store the remote URL.\n");
    exit(1);
}

if (($result['stored'][0]['baseUrl'] ?? '') !== 'https://remote.example/index.php') {
    fwrite(STDERR, "Expected link helper to store the normalized base URL.\n");
    exit(1);
}

if (($linkItems[0]['linkUrl'] ?? '') !== $linkUrl) {
    fwrite(STDERR, "Expected config tree to contain the remote URL.\n");
    exit(1);
}

if (($linkItems[0]['baseUrl'] ?? '') !== 'https://remote.example/index.php') {
    fwrite(STDERR, "Expected config tree to contain the normalized base URL.\n");
    exit(1);
}

if (array_key_exists('template', $linkItems[0])) {
    fwrite(STDERR, "Expected the remote link entry to stay on the default link template.\n");
    exit(1);
}

if (($linkItems[0]['type'] ?? '') !== 'link') {
    fwrite(STDERR, "Expected a link-type tree entry.\n");
    exit(1);
}

if (($linkItems[0]['path'] ?? '') === '' || ($linkItems[0]['name'] ?? '') === '') {
    fwrite(STDERR, "Expected a named tree entry for the poff link.\n");
    exit(1);
}

echo "ok" . PHP_EOL;
