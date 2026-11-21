<?php
declare(strict_types=1);

final class PoffNodeIdentity
{
    private const FILE_NAME = 'poff.node.json';
    private const LOCK_FILE_NAME = '.poff-node-identity.lock';

    public static function path(string $rootDir): string
    {
        return rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }

    public static function read(string $rootDir): ?string
    {
        $resolvedRoot = realpath($rootDir);
        if (!is_string($resolvedRoot) || $resolvedRoot === '' || !is_dir($resolvedRoot)) {
            return null;
        }

        $identityPath = self::path($resolvedRoot);
        if (!file_exists($identityPath) && !is_link($identityPath)) {
            return null;
        }
        if (is_link($identityPath) || !is_file($identityPath)) {
            throw new RuntimeException('PageOff node identity file is invalid.');
        }

        return self::readId($identityPath);
    }

    public static function ensure(string $rootDir): string
    {
        $resolvedRoot = realpath($rootDir);
        if (!is_string($resolvedRoot) || $resolvedRoot === '' || !is_dir($resolvedRoot)) {
            throw new RuntimeException('PageOff node root is not an existing directory.');
        }

        $lockPath = $resolvedRoot . DIRECTORY_SEPARATOR . self::LOCK_FILE_NAME;
        $lockHandle = @fopen($lockPath, 'c');
        if (!is_resource($lockHandle)) {
            throw new RuntimeException('Could not lock PageOff node identity.');
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Could not lock PageOff node identity.');
            }

            $identityPath = self::path($resolvedRoot);
            if (is_file($identityPath)) {
                return self::readId($identityPath);
            }
            if (is_link($identityPath)) {
                throw new RuntimeException('PageOff node identity file is invalid.');
            }

            $identity = [
                'schemaVersion' => 1,
                'id' => self::newId(),
                'createdAt' => date('c'),
            ];
            return self::writeIdentity($resolvedRoot, $identity);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    public static function rotate(string $rootDir): array
    {
        $resolvedRoot = realpath($rootDir);
        if (!is_string($resolvedRoot) || $resolvedRoot === '' || !is_dir($resolvedRoot)) {
            throw new RuntimeException('PageOff node root is not an existing directory.');
        }

        $lockPath = $resolvedRoot . DIRECTORY_SEPARATOR . self::LOCK_FILE_NAME;
        $lockHandle = @fopen($lockPath, 'c');
        if (!is_resource($lockHandle)) {
            throw new RuntimeException('Could not lock PageOff node identity.');
        }

        try {
            if (!flock($lockHandle, LOCK_EX)) {
                throw new RuntimeException('Could not lock PageOff node identity.');
            }

            $identityPath = self::path($resolvedRoot);
            if (is_link($identityPath) || !is_file($identityPath)) {
                throw new RuntimeException('PageOff node identity file is missing or invalid.');
            }

            $current = json_decode((string) file_get_contents($identityPath), true);
            $previousId = self::readId($identityPath);
            $identity = [
                'schemaVersion' => 1,
                'id' => self::newId(),
                'createdAt' => is_array($current) && is_string($current['createdAt'] ?? null)
                    ? $current['createdAt']
                    : date('c'),
                'rotatedAt' => date('c'),
            ];
            $newId = self::writeIdentity($resolvedRoot, $identity);

            return [
                'previousId' => $previousId,
                'id' => $newId,
            ];
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private static function newId(): string
    {
        return 'poff_node_' . bin2hex(random_bytes(16));
    }

    private static function writeIdentity(string $rootDir, array $identity): string
    {
        $identityPath = self::path($rootDir);
        $temporaryPath = tempnam($rootDir, '.poff-node-identity-');
        if (!is_string($temporaryPath) || $temporaryPath === '') {
            throw new RuntimeException('Could not create PageOff node identity file.');
        }

        try {
            $json = json_encode($identity, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (!is_string($json) || file_put_contents($temporaryPath, $json . "\n") === false) {
                throw new RuntimeException('Could not write PageOff node identity file.');
            }
            if (!rename($temporaryPath, $identityPath)) {
                throw new RuntimeException('Could not store PageOff node identity file.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return (string) ($identity['id'] ?? '');
    }

    private static function readId(string $identityPath): string
    {
        $decoded = json_decode((string) file_get_contents($identityPath), true);
        $id = is_array($decoded) ? (string) ($decoded['id'] ?? '') : '';
        if (preg_match('/^poff_node_[a-f0-9]{32}$/', $id) !== 1) {
            throw new RuntimeException('PageOff node identity file is invalid.');
        }

        return $id;
    }
}
