<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/includes/worktypes/templates/converter/convert-text-editor/converter.php';

$payloadRaw = $argv[1] ?? '';
$payload = $payloadRaw !== '' ? json_decode($payloadRaw, true) : [];
if (!is_array($payload)) {
    $payload = [];
}

echo json_encode(textEditorConverterHandle($payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
