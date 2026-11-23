<?php
declare(strict_types=1);

// CLI-Werkzeug: rotiert die Node-ID im angegebenen Node-Verzeichnis.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$nodeRoot = trim((string) ($argv[1] ?? ''));
if ($nodeRoot === '') {
    fwrite(STDERR, "Usage: php scripts/runtime/rotate-node-identity.php <node-root>\n");
    exit(2);
}

require_once __DIR__ . '/../../src/includes/NodeIdentity.php';

try {
    $rotated = PoffNodeIdentity::rotate($nodeRoot);
    fwrite(STDOUT, "Node identity rotated.\nPrevious: {$rotated['previousId']}\nCurrent: {$rotated['id']}\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
