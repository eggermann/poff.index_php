<?php

function cmsEditModeDeniedMarkers(): array
{
    return ['.edit.not-allow', 'edit.not-allow'];
}

function cmsEditModeAllowedForDirectory(string $directory, ?string $scopeRoot = null): bool
{
    $current = realpath($directory);
    if ($current === false || !is_dir($current)) {
        return false;
    }

    $root = $scopeRoot !== null ? realpath($scopeRoot) : null;
    if ($root === false) {
        $root = null;
    }

    while ($current !== false) {
        foreach (cmsEditModeDeniedMarkers() as $marker) {
            if (is_file($current . DIRECTORY_SEPARATOR . $marker)) {
                return false;
            }
        }

        if ($root !== null && $current === $root) {
            break;
        }

        $parent = dirname($current);
        if ($parent === $current) {
            break;
        }
        $current = realpath($parent);
    }

    return true;
}
