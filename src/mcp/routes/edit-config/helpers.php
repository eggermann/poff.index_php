<?php
declare(strict_types=1);

function mcpResolveEditPath(string $rootDir, string $relativePath): ?string
{
    $trimmed = trim($relativePath, "/\\");
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        return realpath($base) ?: null;
    }
    $candidate = realpath($base . DIRECTORY_SEPARATOR . $trimmed);
    if ($candidate === false) {
        return null;
    }
    if (strpos($candidate, $base) !== 0) {
        return null;
    }
    if (!is_dir($candidate)) {
        return null;
    }
    return $candidate;
}

function mcpReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function mcpReadEditConfigRequest(): array
{
    $data = mcpReadJsonBody();
    if ($data === []) {
        $data = $_POST;
    }
    return is_array($data) ? $data : [];
}
