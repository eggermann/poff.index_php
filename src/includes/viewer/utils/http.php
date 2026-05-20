<?php

function cmsHttpOverrideResponse(string $globalKey, mixed ...$args): ?array
{
    $override = $GLOBALS[$globalKey] ?? null;
    if (!is_callable($override)) return null;
    $response = $override(...$args);
    return is_array($response) ? $response : null;
}

function cmsHttpPrepareTimeout(): string|false
{
    if (function_exists('set_time_limit')) @set_time_limit(CMS_HTTP_TIMEOUT_SECONDS + 30);
    $previous = ini_get('default_socket_timeout');
    if (function_exists('ini_set')) @ini_set('default_socket_timeout', (string) CMS_HTTP_TIMEOUT_SECONDS);
    return $previous;
}

function cmsHttpRestoreTimeout(string|false $previous): void
{
    if ($previous !== false && function_exists('ini_set')) @ini_set('default_socket_timeout', (string) $previous);
}

function cmsHttpStatusFromResponseHeaders(?array $headers): array
{
    $statusLine = (string) ($headers[0] ?? '');
    $status = preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1 ? (int) $matches[1] : 0;
    return [$status, $statusLine];
}

function cmsHttpPost(string $url, array $headers, array $payload): array
{
    $override = cmsHttpOverrideResponse('__poff_prompt_http_post', $url, $headers, $payload);
    if ($override !== null) return $override;
    $previousSocketTimeout = cmsHttpPrepareTimeout();

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headerLines),
                'content' => json_encode($payload),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
    } finally {
        cmsHttpRestoreTimeout($previousSocketTimeout);
    }

    [$status, $statusLine] = cmsHttpStatusFromResponseHeaders($http_response_header ?? null);

    return [
        'ok' => $response !== false && $status >= 200 && $status < 400,
        'status' => $status,
        'statusLine' => $statusLine,
        'body' => $response !== false ? $response : '',
    ];
}

function cmsHttpGet(string $url, array $headers = []): array
{
    $override = cmsHttpOverrideResponse('__poff_prompt_http_get', $url, $headers);
    if ($override !== null) return $override;
    $previousSocketTimeout = cmsHttpPrepareTimeout();

    try {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) return ['ok' => false, 'status' => 0, 'statusLine' => '', 'body' => ''];

            $responseBody = '';
            $status = 0;
            $statusLine = '';
            curl_setopt_array($curl, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                    $trimmed = trim($headerLine);
                    if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                        $status = (int) $matches[1];
                        $statusLine = $trimmed;
                    }
                    return strlen($headerLine);
                },
            ]);

            $result = curl_exec($curl);
            if (is_string($result)) {
                $responseBody = $result;
            }
            if ($status === 0) {
                $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            }
            if ($statusLine === '') {
                $statusLine = 'HTTP ' . $status;
            }
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => $result !== false && $status >= 200 && $status < 400,
                'status' => $status,
                'statusLine' => $statusLine,
                'body' => $responseBody,
                'error' => $error,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => CMS_HTTP_TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        [$status, $statusLine] = cmsHttpStatusFromResponseHeaders($http_response_header ?? null);

        return [
            'ok' => $response !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $response !== false ? $response : '',
        ];
    } finally {
        cmsHttpRestoreTimeout($previousSocketTimeout);
    }
}

function cmsHttpPostStream(string $url, array $headers, array $payload, ?callable $onChunk = null): array
{
    $override = cmsHttpOverrideResponse('__poff_prompt_http_post_stream', $url, $headers, $payload, $onChunk);
    if ($override !== null) return $override;
    $previousSocketTimeout = cmsHttpPrepareTimeout();

    $headerLines = array_merge(['Content-Type: application/json'], $headers);
    $responseBody = '';
    $status = 0;
    $statusLine = '';
    try {
        if (!function_exists('curl_init')) {
            $response = cmsHttpPost($url, $headers, $payload);
            $responseBody = (string) ($response['body'] ?? '');
            if (is_callable($onChunk) && $responseBody !== '') $onChunk($responseBody);
            return $response;
        }

        $curl = curl_init($url);
        if ($curl === false) return ['ok' => false, 'status' => 0, 'statusLine' => '', 'body' => ''];

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => CMS_HTTP_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$responseBody, $onChunk) {
                $responseBody .= $chunk;
                if (is_callable($onChunk)) {
                    $onChunk($chunk);
                }
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($curlHandle, $headerLine) use (&$status, &$statusLine) {
                $trimmed = trim($headerLine);
                if ($trimmed !== '' && preg_match('/^HTTP\/\S+\s+(\d{3})\s*(.*)$/i', $trimmed, $matches)) {
                    $status = (int) $matches[1];
                    $statusLine = $trimmed;
                }
                return strlen($headerLine);
            },
        ]);

        $ok = curl_exec($curl);
        if ($status === 0) {
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        }
        if ($statusLine === '') {
            $statusLine = 'HTTP ' . $status;
        }
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'ok' => $ok !== false && $status >= 200 && $status < 400,
            'status' => $status,
            'statusLine' => $statusLine,
            'body' => $responseBody,
            'error' => $error,
        ];
    } finally {
        cmsHttpRestoreTimeout($previousSocketTimeout);
    }
}

function cmsPromptDebugCapture(string $rootDir, array $entry): string
{
    $baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'poff-prompt-debug';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }

    $workspace = basename(rtrim($rootDir, DIRECTORY_SEPARATOR));
    $workspace = preg_replace('/[^a-z0-9._-]+/i', '-', (string) $workspace) ?: 'workspace';
    $targetPath = $baseDir . DIRECTORY_SEPARATOR . $workspace . '-last-local-prompt.json';
    $entry['capturedAt'] = date('c');
    @file_put_contents($targetPath, json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $targetPath;
}

function cmsPromptNormalizeErrorText(string $text): string
{
    $normalized = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/sk-[A-Za-z0-9_-]{12,}/', 'sk-***', $normalized) ?? $normalized;
    $normalized = preg_replace('/AIza[0-9A-Za-z_-]{12,}/', 'AIza***', $normalized) ?? $normalized;
    $normalized = trim($normalized, " \t\n\r\0\x0B.:");
    if ($normalized === '') return '';
    if (strlen($normalized) > 220) {
        $normalized = substr($normalized, 0, 217) . '...';
    }

    return $normalized;
}

function cmsPromptExtractErrorDetail(mixed $value): string
{
    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') return '';
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $detail = cmsPromptExtractErrorDetail($decoded);
            if ($detail !== '') return $detail;
        }
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $trimmed, $matches)) {
            return cmsPromptNormalizeErrorText((string) $matches[1]);
        }

        return cmsPromptNormalizeErrorText($trimmed);
    }

    if (!is_array($value)) return '';

    foreach (['message', 'detail', 'error', 'details'] as $key) {
        if (array_key_exists($key, $value)) {
            $detail = cmsPromptExtractErrorDetail($value[$key]);
            if ($detail !== '') return $detail;
        }
    }

    foreach ($value as $item) {
        $detail = cmsPromptExtractErrorDetail($item);
        if ($detail !== '') return $detail;
    }

    return '';
}

function cmsFormatPromptHttpError(string $label, array $response): string
{
    $status = (int) ($response['status'] ?? 0);
    $detail = cmsPromptExtractErrorDetail($response['body'] ?? '');
    $message = $label . ' request failed';
    if ($status > 0) {
        $message .= ' (HTTP ' . $status . ')';
    }
    if ($detail !== '') {
        $message .= ': ' . $detail;
    }

    return $message . '.';
}
