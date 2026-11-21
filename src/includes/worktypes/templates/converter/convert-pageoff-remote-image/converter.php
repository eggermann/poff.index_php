<?php
declare(strict_types=1);

function pageoffRemoteImageConverterFetchUrlBody(string $url): ?string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return null;
    }

    $scheme = strtolower((string) (parse_url($trimmed, PHP_URL_SCHEME) ?? ''));
    if ($scheme === '' && !is_file($trimmed)) {
        return null;
    }

    $body = @file_get_contents($trimmed);
    if (is_string($body) && $body !== '') {
        return $body;
    }

    if (!in_array($scheme, ['http', 'https'], true) || !function_exists('curl_init')) {
        return null;
    }

    $curl = curl_init($trimmed);
    if ($curl === false) {
        return null;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
    ]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300 || $response === '') {
        return null;
    }

    return is_string($response) ? $response : null;
}

function pageoffRemoteImageConverterHandle(array $payload): array
{
    $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];
    $converter = is_array($payload['converter'] ?? null) ? $payload['converter'] : [];
    $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];

    $converterId = strtolower(trim((string) ($converter['id'] ?? '')));
    if ($converterId !== 'converter/convert-pageoff-remote-image') {
        return [
            'ok' => false,
            'error' => 'Unsupported converter for pageoff remote image endpoint.',
        ];
    }

    $inputBytes = null;
    $sourceUrl = trim((string) ($source['srcUrl'] ?? ($source['url'] ?? ($source['downloadUrl'] ?? ''))));
    if ($sourceUrl !== '') {
        $inputBytes = pageoffRemoteImageConverterFetchUrlBody($sourceUrl);
    }

    if (!is_string($inputBytes) || $inputBytes === '') {
        $bodyBase64 = (string) ($source['bodyBase64'] ?? '');
        if ($bodyBase64 === '') {
            return [
                'ok' => false,
                'error' => 'Missing source image payload.',
            ];
        }

        $inputBytes = base64_decode($bodyBase64, true);
        if ($inputBytes === false) {
            return [
                'ok' => false,
                'error' => 'Invalid source image payload.',
            ];
        }
    }

    $format = strtolower(trim((string) ($converter['format'] ?? 'png')));
    if (!in_array($format, ['png', 'jpeg', 'webp'], true)) {
        $format = 'png';
    }
    $outputMime = match ($format) {
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        default => 'image/png',
    };

    $input = tempnam(sys_get_temp_dir(), 'poff-remote-image-in-');
    if (!is_string($input) || $input === '') {
        return [
            'ok' => false,
            'error' => 'Failed to create temp input file.',
        ];
    }
    $output = $input . '.' . $format;
    file_put_contents($input, $inputBytes);

    $binary = trim((string) @shell_exec('command -v magick 2>/dev/null || command -v convert 2>/dev/null'));
    if ($binary === '') {
        @unlink($input);
        return [
            'ok' => false,
            'error' => 'ImageMagick CLI is unavailable.',
        ];
    }

    $quality = strtolower(trim((string) ($converter['quality'] ?? 'default')));
    $qualityMap = [
        'preview' => '60',
        'default' => '82',
        'archival' => '95',
        'small-web' => '72',
    ];
    $qualityValue = $qualityMap[$quality] ?? '82';
    $command = sprintf(
        '%s %s[0] -auto-orient -background white -alpha remove -alpha off -strip -quality %s %s 2>/dev/null',
        escapeshellarg($binary),
        escapeshellarg($input),
        escapeshellarg($qualityValue),
        escapeshellarg($output)
    );
    @exec($command, $unused, $exitCode);

    $outputBytes = $exitCode === 0 && is_file($output) ? (string) file_get_contents($output) : '';
    @unlink($input);
    @unlink($output);

    if ($outputBytes === '') {
        return [
            'ok' => false,
            'error' => 'Remote image conversion failed.',
        ];
    }

    $sourcePath = str_replace('\\', '/', trim((string) ($source['path'] ?? ''), '/'));
    $sourceName = (string) ($source['name'] ?? ($sourcePath !== '' ? basename($sourcePath) : 'image-source.tiff'));
    $outputName = trim((string) ($target['saveAs'] ?? ''));
    if ($outputName === '') {
        $outputName = pathinfo($sourceName, PATHINFO_FILENAME) . '.' . $format;
    }

    return [
        'ok' => true,
        'type' => 'converted-work',
        'source' => [
            'path' => $sourcePath,
            'mimeType' => (string) ($source['mimeType'] ?? ''),
            'srcUrl' => $sourceUrl,
        ],
        'output' => [
            'name' => $outputName,
            'mimeType' => $outputMime,
            'kind' => 'image',
            'size' => strlen($outputBytes),
            'bodyBase64' => base64_encode($outputBytes),
        ],
        'generatedBy' => [
            'type' => 'converter',
            'name' => 'convert-pageoff-remote-image',
            'id' => 'converter/convert-pageoff-remote-image',
            'node' => 'pageoff-local-php',
            'engine' => 'remote-node',
            'format' => $format,
            'quality' => $quality,
            'usedMagick' => true,
            'convertedAt' => date('c'),
        ],
    ];
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $raw = file_get_contents('php://input');
    $payload = $raw !== false && $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($payload)) {
        $payload = [];
    }

    header('Content-Type: application/json');
    echo json_encode(pageoffRemoteImageConverterHandle($payload), JSON_UNESCAPED_SLASHES);
    exit;
}
