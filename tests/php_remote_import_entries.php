<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/includes/MediaType.php';
require_once __DIR__ . '/../src/includes/Worktype.php';
require_once __DIR__ . '/../src/includes/PoffConfig.php';
require_once __DIR__ . '/../src/includes/NodeIdentity.php';
require_once __DIR__ . '/../src/includes/Core/RemoteFeedImport.php';

$input = json_decode((string) ($argv[1] ?? ''), true);
if (!is_array($input)) {
    fwrite(STDERR, "Expected JSON payload.\n");
    exit(1);
}

if (($input['operation'] ?? '') === 'copyItems') {
    $usedNames = [];
    $names = [];
    foreach (($input['names'] ?? []) as $index => $item) {
        if (!is_array($item)) {
            continue;
        }
        $names[] = PoffRemoteFeedImport::uniqueCopyName(
            PoffRemoteFeedImport::safeCopyName($item, (int) $index),
            $usedNames
        );
    }
    echo json_encode([
        'items' => PoffRemoteFeedImport::flattenCopyItems(is_array($input['items'] ?? null) ? $input['items'] : []),
        'names' => $names,
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

$entries = PoffRemoteFeedImport::normalizeTreeEntries(
    is_array($input['payload'] ?? null) ? $input['payload'] : [],
    (string) ($input['sourceUrl'] ?? ''),
    (string) ($input['sourceId'] ?? ''),
    is_array($input['existingTree'] ?? null) ? $input['existingTree'] : []
);

echo json_encode($entries, JSON_UNESCAPED_SLASHES);
