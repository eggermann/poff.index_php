<?php

require_once __DIR__ . '/../../utils.php';
require_once __DIR__ . '/wrapper.php';

function cmsPromptParentWork(array $parentConfig, string $parentRelativePath): array
{
    $items = is_array($parentConfig['tree'] ?? null) ? $parentConfig['tree'] : [];
    $files = [];
    $folders = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (($item['type'] ?? 'file') === 'folder') {
            $folders[] = $item;
        } else {
            $files[] = $item;
        }
    }

    return [
        'relativePath' => $parentRelativePath,
        'files' => $files,
        'folders' => $folders,
        'layout' => is_array($parentConfig['work']['layout'] ?? null) ? $parentConfig['work']['layout'] : [],
    ];
}

function cmsApplyParentPromptContext(array &$context, array $parentPrompt, string $currentPath): void
{
    if ($parentPrompt === []) {
        return;
    }

    $context['parent'] = $parentPrompt;
    if (is_array($parentPrompt['config'] ?? null)) {
        $parentConfig = $parentPrompt['config'];
        $parentName = (string) ($parentConfig['folderName'] ?? $parentConfig['title'] ?? basename((string) ($parentPrompt['relativePath'] ?? '')));
        $parentSlug = (string) ($parentConfig['slug'] ?? ($parentName !== '' ? PoffConfig::slugify($parentName) : ''));
        $parentWork = is_array($parentConfig['work'] ?? null) ? $parentConfig['work'] : [];
        $context['current']['parentWork'] = [
            'name' => (string) ($parentWork['name'] ?? $parentName),
            'title' => (string) ($parentWork['title'] ?? $parentName),
            'path' => (string) ($parentPrompt['relativePath'] ?? ''),
            'slug' => (string) ($parentWork['slug'] ?? $parentSlug),
            'description' => (string) ($parentWork['description'] ?? ($parentConfig['description'] ?? '')),
            'type' => (string) ($parentWork['type'] ?? 'folder'),
            'kind' => (string) ($parentWork['kind'] ?? 'folder'),
        ];
        if (is_array($parentConfig['tree'] ?? null)) {
            $builtRefs = [];
            $currentName = basename((string) $currentPath);
            foreach ($parentConfig['tree'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $ref = cmsBuildPromptRef((string) ($parentPrompt['relativePath'] ?? ''), $item);
                if (!is_array($ref)) {
                    continue;
                }
                if (($ref['name'] ?? '') === $currentName || (string) ($ref['path'] ?? '') === $currentPath) {
                    continue;
                }
                $builtRefs[] = $ref;
            }
            if ($builtRefs !== []) {
                $context['siblingWorks'] = $builtRefs;
                $context['siblingFolders'] = array_values(array_filter($builtRefs, static fn (array $ref): bool => (bool) ($ref['isFolder'] ?? false)));
            }
        }
    }
    if (isset($parentPrompt['layout']) && is_array($parentPrompt['layout'])) {
        $context['current']['parentLayout'] = $parentPrompt['layout'];
    }
    $context['current']['currentPath'] = $currentPath;
}
