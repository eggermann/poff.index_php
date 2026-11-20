<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/includes/PoffConfig.php';

$usedNames = [];
$names = [
    PoffConfig::uniqueImportedName('Photo', 'origin', $usedNames),
    PoffConfig::uniqueImportedName('Photo', 'origin', $usedNames),
    PoffConfig::uniqueImportedName('Photo', 'origin', $usedNames),
    PoffConfig::uniqueImportedName('', 'remote-node', $usedNames),
];

echo json_encode($names, JSON_UNESCAPED_SLASHES);
