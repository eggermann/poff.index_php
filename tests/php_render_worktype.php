<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';

$action = $argv[1] ?? 'definition';
$kind = $argv[2] ?? 'image';
$payload = isset($argv[3]) ? json_decode((string) $argv[3], true) : [];

if ($action === 'definition') {
    echo json_encode(Worktype::definition($kind), JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'catalog') {
    $mime = isset($payload['mime']) ? (string) $payload['mime'] : null;
    $fileName = isset($payload['fileName']) ? (string) $payload['fileName'] : null;
    $selected = isset($payload['selected']) ? (string) $payload['selected'] : null;
    $subjectType = isset($payload['subjectType']) ? (string) $payload['subjectType'] : null;
    echo json_encode(Worktype::worktypeCatalog($mime, $fileName, $selected, $subjectType), JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'select') {
    echo json_encode(Worktype::resolveTemplateSelection(
        $kind,
        isset($payload['mime']) ? (string) $payload['mime'] : null,
        isset($payload['fileName']) ? (string) $payload['fileName'] : null,
        $payload['templateMap'] ?? null
    ), JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'detect') {
    $fileName = (string) ($payload['fileName'] ?? '');
    echo json_encode([
        'kind' => MediaType::classifyExtension($fileName),
        'mime' => MediaType::detectMimeType((string) ($payload['fullPath'] ?? $fileName), $fileName),
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'bundle-metadata') {
    $bundle = include __DIR__ . '/../src/includes/worktypes/worktypes.php';
    echo json_encode($bundle['metadata'] ?? [], JSON_UNESCAPED_SLASHES);
    exit(0);
}

if ($action === 'render') {
    $context = is_array($payload['ctx'] ?? null) ? $payload['ctx'] : [];
    echo Worktype::render($kind, $context);
    exit(0);
}

fwrite(STDERR, "Unknown action: {$action}\n");
exit(1);
