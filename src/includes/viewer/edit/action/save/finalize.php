<?php

function cmsEditSaveFinalize(array &$config, string $targetDir, string $subjectType, ?string $targetFile): void
{
    $configPath = $subjectType === 'file'
        ? PoffConfig::fileConfigPath($targetDir, (string) $targetFile)
        : PoffConfig::configPath($targetDir);
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Failed to encode config JSON.'], 500);
    }
    file_put_contents($configPath, $encoded);
}
