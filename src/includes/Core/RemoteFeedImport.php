<?php
declare(strict_types=1);

/**
 * Normalizes exported remote Works into local virtual-reference entries.
 * This class owns data rules only; the Node API adapter owns transport and persistence.
 */
final class PoffRemoteFeedImport
{
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
            $remoteNodeId = self::validNodeId($reference['nodeId'] ?? ($payload['source']['nodeId'] ?? ''));
            $remoteWorkId = trim((string) ($reference['workId'] ?? ($item['workId'] ?? '')));
            $remoteFeedNodeId = self::validNodeId($payload['source']['nodeId'] ?? '');
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

    private static function validNodeId(mixed $value): string
    {
        $nodeId = trim(is_string($value) ? $value : '');
        return preg_match('/^poff_node_[a-f0-9]{32}$/', $nodeId) === 1 ? $nodeId : '';
    }
}
