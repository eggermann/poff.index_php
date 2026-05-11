<?php

function cmsEditSaveApplyLayoutTreeVisibility(array &$config, array $data, string $subjectType): void
{
    if ($subjectType !== 'folder') {
        return;
    }

    if (!array_key_exists('treeVisible', $data) && !array_key_exists('tree_visible', $data)) {
        return;
    }

    $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
    $visibleKeys = [];
    if (is_array($treeVisible)) {
        foreach ($treeVisible as $key) {
            if (is_scalar($key)) {
                $visibleKeys[(string) $key] = true;
            }
        }
    }

    foreach ($config['tree'] as &$item) {
        $key = $item['path'] ?? $item['name'] ?? null;
        if ($key === null) {
            continue;
        }
        $item['visible'] = isset($visibleKeys[$key]);
    }
    unset($item);
}
