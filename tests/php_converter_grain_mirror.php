<?php
declare(strict_types=1);

require_once __DIR__ . '/../build/FileCopier.php';

$targetDir = $argv[1] ?? '';
if ($targetDir === '') {
    fwrite(STDERR, "Missing converter grain target directory\n");
    exit(1);
}

$sourceDir = __DIR__ . '/../src/includes/worktypes/templates/converter';
FileCopier::mirrorDirectory($sourceDir, $targetDir);
echo "ok\n";
