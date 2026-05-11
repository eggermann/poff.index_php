<?php

function cmsPromptResponseCandidates(string $raw): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return [];
    }

    $candidates = [$trimmed];

    if (preg_match_all('/```(?:[a-z0-9_-]+)?\s*([\s\S]*?)```/i', $trimmed, $matches)) {
        foreach ($matches[1] as $match) {
            $candidate = trim((string) $match);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }
    }

    $firstBrace = strpos($trimmed, '{');
    $lastBrace = strrpos($trimmed, '}');
    if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
        $candidate = trim(substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1));
        if ($candidate !== '') {
            $candidates[] = $candidate;
        }
    }

    return array_values(array_unique($candidates));
}

function cmsPromptDecodeLooseString(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    $decoded = json_decode($trimmed, true);
    if (is_string($decoded)) {
        return $decoded;
    }

    if ($trimmed[0] === '"') {
        $trimmed = substr($trimmed, 1);
    }
    if ($trimmed !== '' && substr($trimmed, -1) === '"') {
        $trimmed = substr($trimmed, 0, -1);
    }

    return stripcslashes($trimmed);
}

function cmsPromptExtractLooseScalarField(string $payload, string $key): ?string
{
    $knownKeys = ['template', 'css', 'style', 'js', 'script', 'work', 'title', 'description', 'model', 'content', 'response'];
    $otherKeys = array_values(array_filter($knownKeys, static fn (string $candidate): bool => $candidate !== $key));
    $otherKeysPattern = implode('|', array_map(static fn (string $candidate): string => preg_quote($candidate, '/'), $otherKeys));
    $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*([\s\S]*?)(?=\s*(?:,\s*"(?:' . $otherKeysPattern . ')"\s*:|\}\s*$|$))/i';
    if (preg_match($pattern, $payload, $matches) !== 1) {
        return null;
    }

    return cmsPromptDecodeLooseString((string) $matches[1]);
}

function cmsPromptDecodeLoosePayload(string $raw): ?array
{
    $candidates = cmsPromptResponseCandidates($raw);
    foreach ($candidates as $candidate) {
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    foreach ($candidates as $candidate) {
        if (!preg_match('/"(template|css|style|js|script|title|description|model|content|response)"\s*:/i', $candidate)) {
            continue;
        }
        $result = [];
        foreach (['template', 'css', 'style', 'js', 'script', 'title', 'description', 'model', 'content', 'response'] as $key) {
            $value = cmsPromptExtractLooseScalarField($candidate, $key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        if ($result !== []) {
            return $result;
        }
    }

    return null;
}

function cmsPromptImagePayload(array $data): ?array
{
    $image = $data['image'] ?? null;
    if (!is_array($image)) {
        return null;
    }
    $dataUrl = isset($image['dataUrl']) ? trim((string) $image['dataUrl']) : '';
    if ($dataUrl === '' || !preg_match('#^data:(image/[a-z0-9.+-]+);base64,(.+)$#i', $dataUrl, $matches)) {
        return null;
    }

    return [
        'name' => trim((string) ($image['name'] ?? 'clipboard-image.png')),
        'mimeType' => strtolower($matches[1]),
        'dataUrl' => $dataUrl,
        'base64' => $matches[2],
    ];
}
