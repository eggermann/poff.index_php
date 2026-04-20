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

    $normalized = [];
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $normalized, true)) {
            continue;
        }
        $normalized[] = $candidate;
    }

    return $normalized;
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
    $knownKeys = [
        'template',
        'css',
        'style',
        'js',
        'script',
        'work',
        'title',
        'description',
        'model',
        'content',
        'response',
    ];
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

function cmsDefaultLayoutMainBlock(): string
{
    return <<<HBS
<main class="poff-default-layout__main">
    {{#if isFolder}}
        {{> works}}
    {{else}}
        {{> work}}
    {{/if}}
</main>
HBS;
}

function cmsNormalizeLayoutPromptTemplate(string $template): string
{
    $trimmed = trim($template);
    if ($trimmed === '') {
        return '';
    }

    $requiredPartials = str_contains($trimmed, '{{> works}}') && str_contains($trimmed, '{{> work}}');
    $mainPattern = '/<main\b[^>]*class\s*=\s*["\"][^"\']*\bpoff-default-layout__main\b[^"\']*["\'][^>]*>.*?<\/main>/is';
    $mainBlock = cmsDefaultLayoutMainBlock();

    if (preg_match($mainPattern, $trimmed) === 1) {
        if ($requiredPartials) {
            return $trimmed;
        }

        return preg_replace($mainPattern, $mainBlock, $trimmed, 1) ?? $trimmed;
    }

    if ($requiredPartials) {
        return $trimmed;
    }

    foreach (['</footer>', '</div>'] as $closingTag) {
        $position = strripos($trimmed, $closingTag);
        if ($position !== false) {
            return substr($trimmed, 0, $position) . $mainBlock . "\n\n" . substr($trimmed, $position);
        }
    }

    return $trimmed . "\n\n" . $mainBlock;
}

function cmsParsePromptModelResult(string $raw, bool $isLayoutTarget = false): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return ['template' => ''];
    }

    $decoded = cmsPromptDecodeLoosePayload($trimmed);
    if (!is_array($decoded)) {
        return ['template' => $trimmed];
    }

    $template = '';
    $extractedFromEnvelope = false;
    if (isset($decoded['choices'][0]['message']['content'])) {
        $extractedFromEnvelope = true;
        $content = $decoded['choices'][0]['message']['content'];
        if (is_string($content)) {
            $template = trim($content);
        } elseif (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_string($part) && trim($part) !== '') {
                    $parts[] = trim($part);
                    continue;
                }
                if (is_array($part) && isset($part['text']) && is_scalar($part['text'])) {
                    $parts[] = trim((string) $part['text']);
                }
            }
            $template = trim(implode("\n", array_filter($parts)));
        }
    } elseif (isset($decoded['choices'][0]['text']) && is_scalar($decoded['choices'][0]['text'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['choices'][0]['text']);
    } elseif (isset($decoded['message']['content']) && is_scalar($decoded['message']['content'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['message']['content']);
    } elseif (isset($decoded['response']) && is_scalar($decoded['response'])) {
        $extractedFromEnvelope = true;
        $template = trim((string) $decoded['response']);
    } elseif (isset($decoded['template'])) {
        $template = trim((string) $decoded['template']);
    } elseif (isset($decoded['content'])) {
        $template = trim((string) $decoded['content']);
    }

    if ($extractedFromEnvelope && $template !== '' && $template !== $trimmed) {
        return cmsParsePromptModelResult($template, $isLayoutTarget);
    }

    if ($extractedFromEnvelope && $template === '') {
        return ['template' => ''];
    }

    if ($template === '') {
        return ['template' => ''];
    }

    if ($isLayoutTarget) {
        $template = cmsNormalizeLayoutPromptTemplate($template);
    }

    $result = ['template' => $template];
    foreach (['title', 'description', 'model'] as $key) {
        if (isset($decoded[$key]) && is_scalar($decoded[$key])) {
            $result[$key] = trim((string) $decoded[$key]);
        }
    }
    if (array_key_exists('css', $decoded) || array_key_exists('style', $decoded)) {
        $result['css'] = (string) ($decoded['css'] ?? $decoded['style'] ?? '');
    }
    if (array_key_exists('js', $decoded) || array_key_exists('script', $decoded)) {
        $result['js'] = (string) ($decoded['js'] ?? $decoded['script'] ?? '');
    }
    if (isset($decoded['work']) && is_array($decoded['work'])) {
        $result['work'] = $decoded['work'];
    }

    return $result;
}
