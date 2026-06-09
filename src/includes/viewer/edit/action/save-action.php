<?php

function cmsHandleEditSaveAction(array $ctx): void
{
    cmsEditRequirePost('Save');

    $config = $ctx['config'];
    $data = $ctx['data'];
    cmsEditSaveApplyBasicFields($config, $data, $ctx['subjectType'], $ctx['targetFile']);
    cmsEditSaveApplyWorkFields($config, $data, $ctx['targetDir']);
    cmsEditSaveApplyLayoutFields($config, $data, $ctx['subjectType'], $ctx['targetDir'], $ctx['targetFile']);
    cmsEditSaveFinalize($config, $ctx['targetDir'], $ctx['subjectType'], $ctx['targetFile']);
    cmsSyncParentTreeItemMeta($ctx['rootDir'], $ctx['subjectRelativePath'], $ctx['subjectType'], $config);
    if ($ctx['subjectType'] === 'file' && (array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data))) {
        cmsApplyParentTreeVisible($ctx['rootDir'], $ctx['subjectRelativePath'], $ctx['subjectType'], $ctx['targetDir'], $data['treeVisible'] ?? $data['tree_visible'] ?? null);
    }

    $responseConfig = PoffConfig::hydrateConfigLayout(
        $config,
        $ctx['targetDir'],
        $ctx['subjectType'] === 'file' ? (string) $ctx['targetFile'] : null
    );
    $responseConfig = cmsAnnotateConfigWorktypeCatalog($responseConfig, $ctx['subjectType'], $ctx['targetDir'], $ctx['targetFile'], $ctx['subjectType'] === 'folder' ? ($ctx['folderViewData'] ?? []) : []);

    cmsJsonResponse([
        'allowed' => true,
        'target' => $ctx['isLayoutTarget'] ? 'layout' : $ctx['subjectType'],
        'subjectTarget' => $ctx['subjectType'],
        'routePath' => $ctx['subjectRelativePath'],
        'routeSlug' => (string) ($responseConfig['slug'] ?? $config['slug'] ?? ''),
        'saved' => true,
        'config' => $responseConfig,
    ]);
}
