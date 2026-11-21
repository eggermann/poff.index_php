<?php
declare(strict_types=1);

/**
 * Normalizes exported remote Works into local virtual-reference entries.
 * This class owns data rules only; the Node API adapter owns transport and persistence.
 */
final class PoffRemoteFeedImport
{
    public static function sourceId(string $url, array $payload): string
    {
        $nodeId = PoffNodeIdentity::normalizeId($payload['source']['nodeId'] ?? '');
        if ($nodeId !== '') {
            return $nodeId;
        }
        $host = trim((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host !== '') {
            return preg_replace('/[^a-z0-9._-]+/i', '-', strtolower($host)) ?: 'remote';
        }
        return 'remote';
    }

    public static function flattenCopyItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $isFolder = ($item['isFolder'] ?? false) === true || (string) ($item['type'] ?? '') === 'folder';
            $srcUrl = trim((string) ($item['srcUrl'] ?? ''));
            if (!$isFolder && $srcUrl !== '') {
                $result[] = $item;
            }
            if (isset($item['children']) && is_array($item['children'])) {
                foreach (self::flattenCopyItems($item['children']) as $child) {
                    $result[] = $child;
                }
            }
        }

        return $result;
    }

    public static function safeCopyName(array $item, int $index): string
    {
        $preferred = trim((string) ($item['basename'] ?? $item['name'] ?? ''));
        if ($preferred === '') {
            $path = (string) (parse_url((string) ($item['srcUrl'] ?? ''), PHP_URL_PATH) ?? '');
            $preferred = basename($path);
        }
        $preferred = basename(str_replace('\\', '/', $preferred));
        $preferred = preg_replace('/[^a-z0-9._ -]+/i', '-', $preferred) ?: '';
        $preferred = trim($preferred, ". \t\n\r\0\x0B-");
        if ($preferred === '') {
            $extension = trim((string) ($item['extension'] ?? ''), ". \t\n\r\0\x0B");
            $preferred = 'remote-file-' . ($index + 1) . ($extension !== '' ? '.' . $extension : '');
        }

        return substr($preferred, 0, 180);
    }

    public static function uniqueCopyName(string $name, array &$usedNames): string
    {
        $key = strtolower($name);
        if (!isset($usedNames[$key])) {
            $usedNames[$key] = true;
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $extension !== '' ? substr($name, 0, -(strlen($extension) + 1)) : $name;
        $suffix = 2;
        do {
            $candidate = $stem . '-' . $suffix . ($extension !== '' ? '.' . $extension : '');
            $key = strtolower($candidate);
            $suffix++;
        } while (isset($usedNames[$key]));

        $usedNames[$key] = true;
        return $candidate;
    }

    public static function normalizeTreeEntries(array $payload, string $sourceUrl, string $sourceId, array $existingTree): array
    {
        $rawItems = $payload['items'] ?? $payload['tree'] ?? [];
        if (!is_array($rawItems)) {
            return [];
        }

        $usedNames = [];
        foreach ($existingTree as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name !== '') {
                $usedNames[$name] = true;
            }
        }

        $entries = [];
        foreach ($rawItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $pageLink = trim((string) ($item['pageLink'] ?? ''));
            if ($pageLink === '') {
                continue;
            }

            $preferredName = trim((string) ($item['title'] ?? $item['name'] ?? ''));
            $uniqueName = PoffConfig::uniqueImportedName($preferredName, $sourceId, $usedNames);
            $type = trim((string) ($item['type'] ?? 'file'));
            $isFolder = ($item['isFolder'] ?? false) === true || $type === 'folder';
            $routePath = trim((string) ($item['routePath'] ?? ($item['relativePath'] ?? ($item['path'] ?? ''))));
            $routeSlug = trim((string) ($item['routeSlug'] ?? ''));
            if ($routeSlug === '') {
                $routeBase = $routePath !== '' ? basename(str_replace('\\', '/', $routePath)) : $uniqueName;
                $routeSlug = trim(PoffConfig::slugify($routeBase), '-');
            }

            $entry = [
                'name' => $uniqueName,
                'title' => trim((string) ($item['title'] ?? $item['name'] ?? $uniqueName)),
                'slug' => $routeSlug !== '' ? $routeSlug : trim(PoffConfig::slugify($uniqueName), '-'),
                'routeSlug' => $routeSlug,
                'routePath' => $routePath,
                'type' => $isFolder
                    ? 'folder'
                    : (trim((string) ($item['kind'] ?? $type)) === 'link' ? 'link' : 'file'),
                'path' => trim((string) ($item['relativePath'] ?? $item['path'] ?? $uniqueName)),
                'pageLink' => $pageLink,
                'linkUrl' => trim((string) ($item['linkUrl'] ?? '')),
                'srcUrl' => trim((string) ($item['srcUrl'] ?? '')),
                'visible' => array_key_exists('visible', $item) ? (bool) $item['visible'] : true,
                'remoteSource' => $sourceId,
                'remoteFeedUrl' => $sourceUrl,
                'remotePath' => trim((string) ($item['path'] ?? '')),
                'remoteKind' => trim((string) ($item['kind'] ?? $type)),
                'externalWork' => true,
                'importedAt' => date('c'),
            ];

            $reference = is_array($item['reference'] ?? null) ? $item['reference'] : [];
            $remoteNodeId = PoffNodeIdentity::normalizeId($reference['nodeId'] ?? ($payload['source']['nodeId'] ?? ''));
            $remoteWorkId = trim((string) ($reference['workId'] ?? ($item['workId'] ?? '')));
            $remoteFeedNodeId = PoffNodeIdentity::normalizeId($payload['source']['nodeId'] ?? '');
            if ($remoteNodeId !== '') {
                $entry['remoteNodeId'] = $remoteNodeId;
            }
            if ($remoteWorkId !== '') {
                $entry['remoteWorkId'] = $remoteWorkId;
            }
            if ($remoteFeedNodeId !== '') {
                $entry['remoteFeedNodeId'] = $remoteFeedNodeId;
            }
            $entries[] = $entry;
        }

        return $entries;
    }
}
