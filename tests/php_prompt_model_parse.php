<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/includes/viewer/edit.php';

$mode = $argv[1] ?? 'work';
$raw = $argv[2] ?? '';

$result = cmsParsePromptModelResult($raw, $mode === 'layout');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
