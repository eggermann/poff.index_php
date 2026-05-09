<?php

trait PoffConfigPromptHelpers
{
    private static function sanitizeStoredPromptTemplate(string $value, bool $isLayoutTarget = true, int $depth = 0): string
    {
        if ($depth > 3) {
            return cmsSanitizePromptTemplateForTarget((string) $value, $isLayoutTarget);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $decoded = self::decodeStoredPromptPayload($trimmed);
        if (!is_array($decoded)) {
            return cmsSanitizePromptTemplateForTarget($value, $isLayoutTarget);
        }

        $extracted = self::extractStoredPromptTemplateFromPayload($decoded, $isLayoutTarget, $depth + 1);
        if ($extracted === null) {
            return '';
        }

        return cmsSanitizePromptTemplateForTarget($extracted, $isLayoutTarget);
    }

    private static function extractStoredPromptTemplateFromPayload(array $decoded, bool $isLayoutTarget, int $depth): ?string
    {
        $template = null;
        if (isset($decoded['choices'][0]['message']['content'])) {
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
            $template = trim((string) $decoded['choices'][0]['text']);
        } elseif (isset($decoded['message']['content']) && is_scalar($decoded['message']['content'])) {
            $template = trim((string) $decoded['message']['content']);
        } elseif (isset($decoded['response']) && is_scalar($decoded['response'])) {
            $template = trim((string) $decoded['response']);
        } elseif (isset($decoded['template'])) {
            $template = trim((string) $decoded['template']);
        } elseif (isset($decoded['content'])) {
            $template = trim((string) $decoded['content']);
        }

        if ($template === null) {
            return null;
        }

        if ($template === '') {
            return '';
        }

        return self::sanitizeStoredPromptTemplate($template, $isLayoutTarget, $depth);
    }

    private static function storedPromptResponseCandidates(string $raw): array
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

    private static function decodeStoredPromptLooseString(string $value): string
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

    private static function extractStoredPromptLooseScalarField(string $payload, string $key): ?string
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
        $otherKeys = array_values(array_filter($knownKeys, static fn(string $candidate): bool => $candidate !== $key));
        $otherKeysPattern = implode('|', array_map(static fn(string $candidate): string => preg_quote($candidate, '/'), $otherKeys));
        $pattern = '/"' . preg_quote($key, '/') . '"\s*:\s*([\s\S]*?)(?=\s*(?:,\s*"(?:' . $otherKeysPattern . ')"\s*:|\}\s*$|$))/i';
        if (preg_match($pattern, $payload, $matches) !== 1) {
            return null;
        }

        return self::decodeStoredPromptLooseString((string) $matches[1]);
    }

    private static function decodeStoredPromptPayload(string $raw): ?array
    {
        $candidates = self::storedPromptResponseCandidates($raw);
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
                $value = self::extractStoredPromptLooseScalarField($candidate, $key);
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
}
