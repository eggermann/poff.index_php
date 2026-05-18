<?php

declare(strict_types=1);

$payloadRaw = $argv[1] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

if (isset($payload['query']) && is_array($payload['query'])) {
    foreach ($payload['query'] as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $_GET[$key] = (string) $value;
        }
    }
}

ob_start();
require __DIR__ . '/../src/includes/layout.php';
echo ob_get_clean();
