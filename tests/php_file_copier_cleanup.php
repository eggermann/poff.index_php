<?php
declare(strict_types=1);

require_once __DIR__ . '/../build/FileCopier.php';

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Missing root directory\n");
    exit(1);
}

$generatedDir = $root . DIRECTORY_SEPARATOR . 'generated-child';
$customDir = $root . DIRECTORY_SEPARATOR . 'custom-child';
mkdir($generatedDir, 0775, true);
mkdir($customDir, 0775, true);

file_put_contents($root . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "root";');
file_put_contents(
    $generatedDir . DIRECTORY_SEPARATOR . 'index.php',
    '<?php class PoffConfig {} function renderViewerShell(array $payload): void {} ?><script>window.POFF_CONTEXT = {};</script>'
);
file_put_contents($customDir . DIRECTORY_SEPARATOR . 'index.php', '<?php echo "custom";');

FileCopier::removeGeneratedEntrypointsFromSubdirectories($root);

if (!is_file($root . DIRECTORY_SEPARATOR . 'index.php')) {
    fwrite(STDERR, "Root entrypoint was removed\n");
    exit(1);
}
if (is_file($generatedDir . DIRECTORY_SEPARATOR . 'index.php')) {
    fwrite(STDERR, "Generated child entrypoint was not removed\n");
    exit(1);
}
if (is_file($customDir . DIRECTORY_SEPARATOR . 'index.php')) {
    fwrite(STDERR, "Custom child entrypoint was not removed\n");
    exit(1);
}

echo "ok\n";
