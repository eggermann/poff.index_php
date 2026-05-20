<?php

function cmsHandleEditDeleteAction(array $ctx): void
{
    cmsEditRequirePost('Delete');

    $deleteResult = cmsDeleteTarget($ctx['rootDir'], $ctx['path']);
    if (($deleteResult['errors'] ?? []) !== []) {
        cmsJsonResponse(['allowed' => true, 'error' => $deleteResult['errors'][0] ?? 'Delete failed.'], 400);
    }

    $refreshDir = (string) ($deleteResult['refreshDir'] ?? $ctx['rootDir']);
    $updatedConfig = PoffConfig::ensure($refreshDir);
    $returnPath = trim((string) ($ctx['data']['return'] ?? $_GET['return'] ?? ''), "/\\");

    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    if (str_contains($accept, 'application/json') || str_contains($accept, 'text/json')) {
        cmsJsonResponse(['allowed' => true, 'deleted' => $deleteResult['deleted'], 'config' => $updatedConfig]);
    }

    header('Location: ' . ($returnPath !== '' ? '?path=' . urlencode($returnPath) . '&edit=true' : '?edit=true'), true, 303);
    exit;
}
