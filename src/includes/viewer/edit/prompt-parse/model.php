<?php

require_once __DIR__ . '/../../../prompt-template-sanitize.php';
require_once __DIR__ . '/loose.php';

function cmsParsePromptModelResult(string $raw, bool $isLayoutTarget = false): array
{
    $trimmed = trim($raw);
    if ($trimmed === '') {
        return ['template' => ''];
    }

    $decoded = cmsPromptDecodeLoosePayload($trimmed);
    if (!is_array($decoded)) {
        return ['template' => cmsSanitizePromptTemplateForTarget($trimmed, $isLayoutTarget)];
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

    $template = cmsSanitizePromptTemplateForTarget($template, $isLayoutTarget);
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
    if (isset($decoded['treeVisible']) && is_array($decoded['treeVisible'])) {
        $result['treeVisible'] = array_values(array_filter($decoded['treeVisible'], static fn (mixed $value): bool => is_scalar($value)));
    }

    return $result;
}
