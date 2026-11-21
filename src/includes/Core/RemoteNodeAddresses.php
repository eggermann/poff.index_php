<?php
declare(strict_types=1);

require_once __DIR__ . '/../NodeIdentity.php';

final class PoffRemoteNodeAddresses
{
    private const FILE_NAME = '.poff-node-addresses.json';
    private const LOCK_NAME = '.poff-node-addresses.lock';

    public static function normalizeUrl(mixed $value): string
    {
        $url = is_string($value) ? trim($value) : '';
        if ($url === '' || preg_match('/[[:space:][:cntrl:]\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        return $url;
    }

    public static function load(string $rootDir): array
    {
        try {
            return self::read($rootDir);
        } catch (RuntimeException) {
            return [];
        }
    }

    public static function workUrl(array $addresses, array $reference): string
    {
        $nodeId = PoffNodeIdentity::normalizeId($reference['remoteNodeId'] ?? null);
        $workId = $reference['remoteWorkId'] ?? null;
        if ($nodeId === '' || !is_string($workId) || !preg_match('/^poff_[a-z0-9._-]{1,120}$/iD', $workId)) {
            return '';
        }
        $address = self::normalizeUrl($addresses[$nodeId] ?? '');
        return $address === '' ? '' : $address . '?' . http_build_query([
            'ajax' => 'resolve',
            'nodeId' => $nodeId,
            'workId' => $workId,
            'open' => '1',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function remember(string $rootDir, string $nodeId, string $url): void
    {
        $nodeId = PoffNodeIdentity::normalizeId($nodeId);
        $url = self::normalizeUrl($url);
        $resolvedRoot = realpath($rootDir);
        if ($nodeId === '' || $url === '' || $resolvedRoot === false || !is_dir($resolvedRoot)) {
            throw new RuntimeException('Invalid node address.');
        }

        $lockPath = self::path($resolvedRoot, self::LOCK_NAME);
        $lock = is_link($lockPath) ? false : @fopen($lockPath, 'c');
        if (!is_resource($lock)) {
            throw new RuntimeException('Unable to lock node addresses.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Unable to lock node addresses.');
            }
            $addresses = self::read($resolvedRoot);
            if (($addresses[$nodeId] ?? null) === $url) {
                return;
            }
            $addresses[$nodeId] = $url;
            $temporary = @tempnam($resolvedRoot, '.poff-node-address-');
            if ($temporary === false) {
                throw new RuntimeException('Unable to create node address file.');
            }
            try {
                $json = json_encode($addresses, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                if (@file_put_contents($temporary, $json) !== strlen($json)
                    || !@rename($temporary, self::path($resolvedRoot, self::FILE_NAME))) {
                    throw new RuntimeException('Unable to save node addresses.');
                }
            } finally {
                if (is_file($temporary)) {
                    @unlink($temporary);
                }
            }
        } finally {
            fclose($lock);
        }
    }

    private static function path(string $rootDir, string $name): string
    {
        return rtrim($rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
    }

    private static function read(string $rootDir): array
    {
        $path = self::path($rootDir, self::FILE_NAME);
        if (is_link($path)) {
            throw new RuntimeException('Invalid node address file.');
        }
        if (!file_exists($path)) {
            return [];
        }
        $value = is_file($path) ? @file_get_contents($path) : false;
        $addresses = $value === false ? null : json_decode($value, true);
        if (!is_array($addresses)) {
            throw new RuntimeException('Invalid node address file.');
        }
        return $addresses;
    }
}
