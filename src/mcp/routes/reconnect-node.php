<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Core/RemoteNodeAddresses.php';

function handleReconnectNode(string $rootDir, array $input, string $method, ?callable $fetchIdentity = null): array
{
    if ($method !== 'POST') {
        return ['ok' => false, 'error' => 'Use POST to update a node address.'];
    }

    $access = mcpEditorAccessState($rootDir);
    if (!$access['allowed']) {
        return ['ok' => false] + $access;
    }

    $nodeId = PoffNodeIdentity::normalizeId($input['nodeId'] ?? null);
    $url = PoffRemoteNodeAddresses::normalizeUrl($input['url'] ?? null);
    if ($nodeId === '' || $url === '') {
        return ['ok' => false, 'error' => 'Supply the node ID and its HTTPS entrypoint URL.'];
    }

    try {
        if (PoffNodeIdentity::read($rootDir) === $nodeId) {
            return ['ok' => false, 'error' => 'This is the local node ID.'];
        }
        $fetchIdentity ??= 'mcpFetchRemoteNodeIdentity';
        $reportedId = $fetchIdentity($url . '?ajax=node-identity');
        if (PoffNodeIdentity::normalizeId($reportedId) !== $nodeId) {
            return ['ok' => false, 'error' => 'The address did not confirm the expected node ID.'];
        }
        PoffRemoteNodeAddresses::remember($rootDir, $nodeId, $url);
    } catch (RuntimeException $exception) {
        return ['ok' => false, 'error' => $exception->getMessage()];
    }

    return ['ok' => true, 'nodeId' => $nodeId, 'url' => $url];
}

function mcpFetchRemoteNodeIdentity(string $url): ?string
{
    if (!function_exists('curl_init') || !defined('FILTER_FLAG_GLOBAL_RANGE')) {
        throw new RuntimeException('Node address verification requires PHP 8.2+ with cURL.');
    }

    $host = (string) parse_url($url, PHP_URL_HOST);
    $port = (int) (parse_url($url, PHP_URL_PORT) ?? 443);
    $literal = trim($host, '[]');
    $isLiteral = filter_var($literal, FILTER_VALIDATE_IP) !== false;
    $addresses = [$literal];
    if (!$isLiteral) {
        // Require an ordinary DNS hostname before giving it to the HTTP client.
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\\.)+[a-z][a-z0-9-]*$/iD', $host)) {
            return null;
        }
        $addresses = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (is_string($ip)) {
                $addresses[] = $ip;
            }
        }
    }
    if ($addresses === []) {
        return null;
    }
    foreach ($addresses as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)
            || (str_contains($ip, ':') && !preg_match('/^[23][0-9a-f]{3}:/i', $ip))) {
            return null;
        }
    }

    $curl = curl_init($url);
    $body = '';
    curl_setopt_array($curl, [
        CURLOPT_PROXY => '',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 4096) {
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    if (!$isLiteral) {
        $pinned = array_map(static fn(string $ip): string => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, $addresses);
        curl_setopt($curl, CURLOPT_RESOLVE, [$host . ':' . $port . ':' . implode(',', $pinned)]);
    }

    if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
        return null;
    }
    $payload = json_decode($body, true);
    return is_string($payload['nodeId'] ?? null) ? $payload['nodeId'] : null;
}
