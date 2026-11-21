<?php
/**
 * Work move helpers for edit actions.
 */

require_once __DIR__ . '/../utils.php';
require_once __DIR__ . '/../../PoffConfig.php';

function cmsMoveRelativePath(string $rootDir, string $absolutePath): string
{
    $root = rtrim(str_replace('\\', '/', $rootDir), '/');
    $path = str_replace('\\', '/', $absolutePath);
    return ltrim(substr($path, strlen($root)), '/');
}

function cmsMovePathIsInsideRoot(string $rootDir, string $path): bool
{
    $root = rtrim(str_replace('\\', '/', $rootDir), '/');
    $path = rtrim(str_replace('\\', '/', $path), '/');
    return $path === $root || str_starts_with($path, $root . '/');
}

function cmsMovePathHasSymlink(string $rootDir, string $relativePath): bool
{
    $cursor = rtrim($rootDir, DIRECTORY_SEPARATOR);
    foreach (array_filter(explode('/', str_replace('\\', '/', trim($relativePath, "/\\")))) as $part) {
        $cursor .= DIRECTORY_SEPARATOR . $part;
        if (is_link($cursor)) {
            return true;
        }
    }
    return false;
}

function cmsMoveWriteConfig(string $path, array $config): bool
{
    // Test seam for exercising write failures; normal requests do not set this guard.
    $writeGuard = $GLOBALS['__poff_move_write_guard'] ?? null;
    if (is_callable($writeGuard) && !$writeGuard($path, $config)) {
        return false;
    }
    $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }
    try {
        $temporaryPath = $path . '.move-' . bin2hex(random_bytes(6)) . '.tmp';
    } catch (Throwable) {
        return false;
    }
    if (file_put_contents($temporaryPath, $encoded, LOCK_EX) === false) {
        @unlink($temporaryPath);
        return false;
    }
    if (@rename($temporaryPath, $path)) {
        return true;
    }
    @unlink($temporaryPath);
    return false;
}

function cmsMoveRename(string $from, string $to, bool $rollback = false): bool
{
    $guard = $GLOBALS['__poff_move_rename_guard'] ?? null;
    if (is_callable($guard) && !$guard($from, $to, $rollback)) {
        return false;
    }
    if ($rollback && (file_exists($to) || is_link($to))) {
        return false;
    }
    return @rename($from, $to);
}

function cmsMoveApplyTreeMetadata(string $destinationParent, string $newName, string $sourceName, ?array $sourceEntry): bool
{
    if (!is_array($sourceEntry) || cmsConfiguredTreeLinkTarget($sourceEntry) !== '') {
        return true;
    }

    PoffConfig::ensure($destinationParent);
    $destinationConfig = json_decode((string) @file_get_contents(PoffConfig::configPath($destinationParent)), true);
    if (!is_array($destinationConfig)) {
        return false;
    }
    $tree = is_array($destinationConfig['tree'] ?? null) ? $destinationConfig['tree'] : [];
    $matchIndex = null;
    foreach ($tree as $index => $item) {
        if (is_array($item) && (string) ($item['name'] ?? '') === $newName) {
            $matchIndex = $index;
            break;
        }
    }
    if ($matchIndex === null) {
        return false;
    }

    $physicalFields = ['name', 'type', 'path', 'modifiedAt'];
    foreach ($sourceEntry as $key => $value) {
        if (!in_array($key, $physicalFields, true)) {
            $tree[$matchIndex][$key] = $value;
        }
    }
    if ((string) ($sourceEntry['slug'] ?? '') === PoffConfig::slugify($sourceName)) {
        $tree[$matchIndex]['slug'] = PoffConfig::slugify($newName);
    }

    $destinationConfig['tree'] = $tree;
    $destinationConfig['treeHash'] = hash('sha256', json_encode($tree));
    $destinationConfig['updatedAt'] = date('c');
    return cmsMoveWriteConfig(PoffConfig::configPath($destinationParent), $destinationConfig);
}

function cmsMoveTarget(string $rootDir, string $relativePath, string $destinationDirectory, string $newName = ''): array
{
    $root = realpath($rootDir);
    if (!is_string($root) || $root === '') {
        return ['errors' => ['Workspace root unavailable.']];
    }

    $sourceRelative = trim($relativePath, "/\\");
    if ($sourceRelative === '' || cmsIsVirtualLayoutPath($sourceRelative)) {
        return ['errors' => ['Move requires a physical Work target.']];
    }
    if (cmsMovePathHasSymlink($root, $sourceRelative) || cmsMovePathHasSymlink($root, $destinationDirectory)) {
        return ['errors' => ['Move does not follow symbolic links.']];
    }

    $source = cmsResolvePhysicalTarget($root, $sourceRelative);
    $destination = cmsResolvePhysicalTarget($root, trim($destinationDirectory, "/\\"));
    if (!is_array($source) || !is_array($destination) || ($destination['type'] ?? '') !== 'folder') {
        return ['errors' => ['Move source or destination folder is invalid.']];
    }

    $type = (string) ($source['type'] ?? '');
    if (!in_array($type, ['file', 'folder'], true)) {
        return ['errors' => ['Unsupported move target.']];
    }

    $sourcePath = $type === 'folder' ? (string) ($source['dir'] ?? '') : (string) ($source['path'] ?? '');
    $sourceParent = $type === 'folder' ? dirname($sourcePath) : (string) ($source['dir'] ?? '');
    $sourcePathReal = realpath($sourcePath);
    $destinationParentReal = realpath((string) ($destination['dir'] ?? ''));
    if (!is_string($sourcePathReal) || !is_string($destinationParentReal) || !cmsMovePathIsInsideRoot($root, $sourcePathReal) || !cmsMovePathIsInsideRoot($root, $destinationParentReal)) {
        return ['errors' => ['Move source or destination leaves the workspace.']];
    }
    $sourceName = basename($sourcePath);
    PoffConfig::ensure($sourceParent);
    $sourceParentConfigBefore = json_decode((string) @file_get_contents(PoffConfig::configPath($sourceParent)), true);
    $sourceTreeEntry = null;
    foreach (is_array($sourceParentConfigBefore['tree'] ?? null) ? $sourceParentConfigBefore['tree'] : [] as $treeEntry) {
        if (is_array($treeEntry) && (string) ($treeEntry['name'] ?? '') === $sourceName) {
            $sourceTreeEntry = $treeEntry;
            break;
        }
    }
    $newName = trim($newName) === '' ? $sourceName : trim($newName);
    if ($newName === '' || $newName === '.' || $newName === '..' || str_contains($newName, '/') || str_contains($newName, '\\') || str_contains($newName, "\0")) {
        return ['errors' => ['Move name must be a single path segment.']];
    }

    $destinationPath = rtrim((string) ($destination['dir'] ?? ''), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;
    if ($type === 'folder') {
        if ($sourcePathReal === $root || str_starts_with($destinationParentReal . DIRECTORY_SEPARATOR, rtrim($sourcePathReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            return ['errors' => ['Cannot move the workspace root or move a folder inside itself.']];
        }
    }

    $sourceConfig = $type === 'folder'
        ? PoffConfig::ensure($sourcePath)
        : PoffConfig::ensureFileConfig($sourceParent, $sourceName);
    $workId = (string) ($sourceConfig['id'] ?? '');
    if ($workId === '') {
        return ['errors' => ['Source Work identity is unavailable.']];
    }

    $moves = [[$sourcePath, $destinationPath]];
    if ($type === 'file') {
        $sourceConfigPath = PoffConfig::fileConfigPath($sourceParent, $sourceName);
        $destinationConfigPath = PoffConfig::fileConfigPath((string) $destination['dir'], $newName);
        $sourceLayoutPath = PoffConfig::fileLayoutDir($sourceParent, $sourceName);
        $destinationLayoutPath = PoffConfig::fileLayoutDir((string) $destination['dir'], $newName);

        if (is_link(dirname($sourceConfigPath)) || is_link(dirname($destinationConfigPath)) || is_link($sourceConfigPath) || is_link($sourceLayoutPath)) {
            return ['errors' => ['Move does not follow symbolic links in Work metadata.']];
        }

        if (is_file($sourceConfigPath)) {
            $moves[] = [$sourceConfigPath, $destinationConfigPath];
        }
        if (is_dir($sourceLayoutPath)) {
            $moves[] = [$sourceLayoutPath, $destinationLayoutPath];
        }
    }

    foreach ($moves as [, $moveDestination]) {
        if (file_exists($moveDestination) || is_link($moveDestination)) {
            return ['errors' => ['Move destination already exists.']];
        }
    }
    if ($type === 'file' && count($moves) > 1 && !is_dir(dirname($destinationConfigPath)) && !@mkdir(dirname($destinationConfigPath), 0755, true) && !is_dir(dirname($destinationConfigPath))) {
        return ['errors' => ['Failed to prepare destination Work metadata.']];
    }

    $sourceMetadataPath = $type === 'folder'
        ? PoffConfig::configPath($sourcePath)
        : PoffConfig::fileConfigPath($sourceParent, $sourceName);
    $sourceMetadataSnapshot = json_decode((string) @file_get_contents($sourceMetadataPath), true);
    if (!is_array($sourceMetadataSnapshot)) {
        return ['errors' => ['Source Work metadata is unavailable.']];
    }

    $moved = [];
    foreach ($moves as [$moveSource, $moveDestination]) {
        if (!cmsMoveRename($moveSource, $moveDestination)) {
            $rollbackFailed = false;
            foreach (array_reverse($moved) as [$rollbackSource, $rollbackDestination]) {
                if (!cmsMoveRename($rollbackDestination, $rollbackSource, true)) {
                    $rollbackFailed = true;
                }
            }
            return ['errors' => [$rollbackFailed ? 'Move failed and rollback was incomplete.' : 'Move failed; previous paths were restored.']];
        }
        $moved[] = [$moveSource, $moveDestination];
    }

    if ($type === 'folder') {
        $movedConfigPath = PoffConfig::configPath($destinationPath);
        $movedConfig = json_decode((string) @file_get_contents($movedConfigPath), true);
        if (!is_array($movedConfig)) {
            $movedConfig = $sourceConfig;
        }
        $movedConfig['folderName'] = $newName;
        if ((string) ($movedConfig['slug'] ?? '') === PoffConfig::slugify($sourceName)) {
            $movedConfig['slug'] = PoffConfig::slugify($newName);
        }
        $movedConfig['updatedAt'] = date('c');
        $metadataUpdated = cmsMoveWriteConfig($movedConfigPath, $movedConfig);
    } else {
        $movedConfigPath = PoffConfig::fileConfigPath((string) $destination['dir'], $newName);
        $movedConfig = json_decode((string) @file_get_contents($movedConfigPath), true);
        $movedConfig = is_array($movedConfig) ? $movedConfig : $sourceConfig;
        $newDefaults = PoffConfig::defaultFileConfig((string) $destination['dir'], $newName);
        $movedConfig['name'] = $newName;
        if ((string) ($movedConfig['slug'] ?? '') === PoffConfig::slugify($sourceName)) {
            $movedConfig['slug'] = PoffConfig::slugify($newName);
        }
        $movedConfig['path'] = $newName;
        foreach (['kind', 'mimeType', 'size', 'modifiedAt', 'hash'] as $derivedField) {
            if (array_key_exists($derivedField, $newDefaults)) {
                $movedConfig[$derivedField] = $newDefaults[$derivedField];
            }
        }
        $movedConfig['id'] = $workId;
        $movedConfig['updatedAt'] = date('c');
        $metadataUpdated = cmsMoveWriteConfig($movedConfigPath, $movedConfig);
    }

    if (!$metadataUpdated) {
        $rollbackFailed = false;
        foreach (array_reverse($moved) as [$rollbackSource, $rollbackDestination]) {
            if (!cmsMoveRename($rollbackDestination, $rollbackSource, true)) {
                $rollbackFailed = true;
            }
        }
        return ['errors' => [$rollbackFailed ? 'Move failed and rollback was incomplete.' : 'Move metadata update failed; previous paths were restored.']];
    }

    $sourceParentRelative = cmsMoveRelativePath($root, $sourceParent);
    $destinationParentRelative = cmsMoveRelativePath($root, (string) $destination['dir']);
    $sourceParentConfig = PoffConfig::ensure($sourceParent);
    $destinationParentConfig = $sourceParent === (string) $destination['dir']
        ? $sourceParentConfig
        : PoffConfig::ensure((string) $destination['dir']);
    if (!cmsMoveApplyTreeMetadata((string) $destination['dir'], $newName, $sourceName, $sourceTreeEntry)) {
        $rollbackFailed = false;
        foreach (array_reverse($moved) as [$rollbackSource, $rollbackDestination]) {
            if (!cmsMoveRename($rollbackDestination, $rollbackSource, true)) {
                $rollbackFailed = true;
            }
        }
        if (!$rollbackFailed && !cmsMoveWriteConfig($sourceMetadataPath, $sourceMetadataSnapshot)) {
            $rollbackFailed = true;
        }
        if (!$rollbackFailed) {
            PoffConfig::ensure($sourceParent);
            if ($sourceParent !== (string) $destination['dir']) {
                PoffConfig::ensure((string) $destination['dir']);
            }
            if (!cmsMoveApplyTreeMetadata($sourceParent, $sourceName, $sourceName, $sourceTreeEntry)) {
                $rollbackFailed = true;
            }
        }
        return ['errors' => [$rollbackFailed ? 'Move failed and rollback was incomplete.' : 'Move tree update failed; previous paths were restored.']];
    }
    $sourceParentConfig = PoffConfig::ensure($sourceParent);
    $destinationParentConfig = $sourceParent === (string) $destination['dir']
        ? $sourceParentConfig
        : PoffConfig::ensure((string) $destination['dir']);

    return [
        'errors' => [],
        'moved' => [[
            'id' => $workId,
            'type' => $type,
            'from' => $sourceRelative,
            'to' => trim(($destinationParentRelative !== '' ? $destinationParentRelative . '/' : '') . $newName, '/'),
        ]],
        'refreshDir' => (string) $destination['dir'],
        'sourceParentConfig' => $sourceParentConfig,
        'destinationParentConfig' => $destinationParentConfig,
        'sourceParentPath' => $sourceParentRelative,
    ];
}
