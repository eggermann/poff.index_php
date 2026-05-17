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
$payloadRaw = $editMode ? ($argv[4] ?? '') : ($argv[3] ?? '');

if ($payloadRaw !== '') {
    $payload = json_decode($payloadRaw, true);
    if (is_array($payload) && isset($payload['mockResponse']) && is_array($payload['mockResponse'])) {
        $mockResponse = $payload['mockResponse'];
        $GLOBALS['__poff_prompt_http_get'] = static function (string $url, array $headers = []) use ($mockResponse): array {
            return [
                'ok' => (bool) ($mockResponse['ok'] ?? true),
                'status' => (int) ($mockResponse['status'] ?? 200),
                'statusLine' => (string) ($mockResponse['statusLine'] ?? 'HTTP/1.1 200 OK'),
                'body' => (string) ($mockResponse['body'] ?? ''),
            ];
        };
    }
}

if ($editMode) {
    $_GET['edit'] = 'true';
}

ob_start();
renderViewer($baseDir, $relativePath);
echo ob_get_clean();
