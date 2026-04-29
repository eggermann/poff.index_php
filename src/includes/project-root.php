<?php

function cmsProjectRootDir(?string $hint = null): string
{
    $candidates = [];
    foreach ([$hint, getcwd() ?: null, __DIR__] as $candidate) {
        if (!is_string($candidate) || trim($candidate) === '') {
            continue;
        }
        $resolved = realpath($candidate);
        if (is_string($resolved) && $resolved !== '') {
            $candidates[] = $resolved;
        }
    }

    $seen = [];
    foreach ($candidates as $candidate) {
        $current = $candidate;
        while ($current !== '' && $current !== DIRECTORY_SEPARATOR && !isset($seen[$current])) {
            $seen[$current] = true;
            if (
                is_file($current . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'BuildConfig.php')
                && is_dir($current . DIRECTORY_SEPARATOR . 'src')
            ) {
                return $current;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }
    }

    foreach ($candidates as $candidate) {
        if (is_dir($candidate)) {
            return $candidate;
        }
    }

    return '.';
}
