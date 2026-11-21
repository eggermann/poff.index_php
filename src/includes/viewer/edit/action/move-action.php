<?php

function cmsHandleEditMoveAction(array $ctx): void
{
    cmsEditRequirePost('Move');

    $result = cmsMoveTarget(
        (string) $ctx['rootDir'],
        (string) $ctx['path'],
        (string) ($ctx['data']['destination'] ?? ''),
        (string) ($ctx['data']['name'] ?? '')
    );
    if (($result['errors'] ?? []) !== []) {
        cmsJsonResponse(['allowed' => true, 'error' => $result['errors'][0] ?? 'Move failed.'], 400);
    }

    $returnPath = trim((string) ($ctx['data']['return'] ?? $_GET['return'] ?? ''), "/\\");
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json') || str_contains($accept, 'text/json')) {
        cmsJsonResponse([
            'allowed' => true,
            'moved' => $result['moved'],
            'config' => $result['destinationParentConfig'],
            'sourceParentConfig' => $result['sourceParentConfig'],
            'sourceParentPath' => $result['sourceParentPath'],
        ]);
    }

    $toPath = (string) ($result['moved'][0]['to'] ?? '');
    header('Location: ?path=' . urlencode($returnPath !== '' ? $returnPath : $toPath) . '&edit=true', true, 303);
    exit;
}
