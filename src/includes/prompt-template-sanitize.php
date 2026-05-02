<?php

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
    $trimmed = cmsNormalizePromptViewerLinkConcatenation($trimmed);

    $requiredPartials = str_contains($trimmed, '{{> works}}') && str_contains($trimmed, '{{> work}}');
    $mainPattern = '/<main\b[^>]*class\s*=\s*["\'][^"\']*\bpoff-default-layout__main\b[^"\']*["\'][^>]*>.*?<\/main>/is';
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

function cmsNormalizePromptViewerLinkConcatenation(string $template): string
{
    $viewerFields = 'pageLink|pageUrl|workUrl|viewUrl|viewerHref';

    return preg_replace(
        '/{{\s*(?:' . $viewerFields . ')\s*}}\s*(\?view=1(?:&|&amp;)(?:path|file)=[^"\'<>\s]+)/i',
        '$1',
        $template
    ) ?? $template;
}

function cmsStripPromptClassBlock(string $template, string $tag, string $className): string
{
    $pattern = '/<' . preg_quote($tag, '/') . '\b[^>]*class\s*=\s*["\'][^"\']*(?<![A-Za-z0-9_-])'
        . preg_quote($className, '/')
        . '(?![A-Za-z0-9_-])[^"\']*["\'][^>]*>.*?<\/'
        . preg_quote($tag, '/')
        . '>/is';

    return preg_replace($pattern, '', $template) ?? $template;
}

function cmsTrimPromptOuterLayoutContainers(string $template): string
{
    $trimmed = trim($template);
    if ($trimmed === '') {
        return '';
    }

    $wrapperClasses = [
        'poff-default-layout',
        'poff-default-layout__stage',
    ];

    for ($i = 0; $i < 4; $i++) {
        $matched = false;
        foreach ($wrapperClasses as $className) {
            $pattern = '/^\s*<div\b[^>]*class\s*=\s*["\'][^"\']*(?<![A-Za-z0-9_-])'
                . preg_quote($className, '/')
                . '(?![A-Za-z0-9_-])[^"\']*["\'][^>]*>\s*/is';
            if (preg_match($pattern, $trimmed, $matches) !== 1) {
                continue;
            }

            $openingLength = strlen((string) $matches[0]);
            $closingPosition = strripos($trimmed, '</div>');
            if ($closingPosition === false || $closingPosition < $openingLength) {
                continue;
            }

            $trimmed = trim(substr($trimmed, $openingLength, $closingPosition - $openingLength));
            $matched = true;
            break;
        }

        if (!$matched) {
            break;
        }
    }

    return $trimmed;
}

function cmsStripPromptEdgeTagBlocks(string $template, array $tags): string
{
    $trimmed = trim($template);
    if ($trimmed === '') {
        return '';
    }

    $tagPattern = implode('|', array_map(static fn (string $tag): string => preg_quote($tag, '/'), $tags));
    if ($tagPattern === '') {
        return $trimmed;
    }

    for ($i = 0; $i < 4; $i++) {
        $next = preg_replace('/^\s*<(?:' . $tagPattern . ')\b[^>]*>.*?<\/(?:' . $tagPattern . ')>\s*/is', '', $trimmed, 1);
        if (!is_string($next) || $next === $trimmed) {
            break;
        }
        $trimmed = trim($next);
    }

    for ($i = 0; $i < 4; $i++) {
        $next = preg_replace('/\s*<(?:' . $tagPattern . ')\b[^>]*>.*?<\/(?:' . $tagPattern . ')>\s*$/is', '', $trimmed, 1);
        if (!is_string($next) || $next === $trimmed) {
            break;
        }
        $trimmed = trim($next);
    }

    return $trimmed;
}

function cmsSanitizeInnerPromptTemplate(string $template): string
{
    $trimmed = trim($template);
    if ($trimmed === '') {
        return '';
    }

    $mainPattern = '/<main\b[^>]*class\s*=\s*["\'][^"\']*\bpoff-default-layout__main\b[^"\']*["\'][^>]*>([\s\S]*?)<\/main>/i';
    if (preg_match($mainPattern, $trimmed, $matches) === 1) {
        $trimmed = trim((string) ($matches[1] ?? ''));
    } elseif (
        (
            str_contains($trimmed, '<header')
            || str_contains($trimmed, '<footer')
            || str_contains($trimmed, '<nav')
            || str_contains($trimmed, '<aside')
            || str_contains($trimmed, '{{> work}}')
            || str_contains($trimmed, '{{> works}}')
            || str_contains($trimmed, 'poff-default-layout')
        )
        && preg_match('/<main\b[^>]*>([\s\S]*?)<\/main>/i', $trimmed, $matches) === 1
    ) {
        $trimmed = trim((string) ($matches[1] ?? ''));
    }

    foreach ([
        ['header', 'poff-default-layout__header'],
        ['footer', 'poff-default-layout__footer'],
        ['aside', 'poff-default-layout__sidebar'],
        ['nav', 'poff-default-layout__nav'],
        ['div', 'poff-default-layout__header-copy'],
    ] as [$tag, $className]) {
        $trimmed = cmsStripPromptClassBlock($trimmed, $tag, $className);
    }

    $trimmed = preg_replace('/<\/?main\b[^>]*class\s*=\s*["\'][^"\']*\bpoff-default-layout__main\b[^"\']*["\'][^>]*>/i', '', $trimmed) ?? $trimmed;
    $trimmed = preg_replace('/{{#if\s+isFolder}}\s*{{>\s*works\s*}}\s*{{else}}\s*{{>\s*work\s*}}\s*{{\/if}}/i', '', $trimmed) ?? $trimmed;
    $trimmed = preg_replace('/{{>\s*(?:work|works|poff-layout|filesystem-layout)\s*}}/i', '', $trimmed) ?? $trimmed;
    $trimmed = cmsTrimPromptOuterLayoutContainers($trimmed);
    $trimmed = cmsStripPromptEdgeTagBlocks($trimmed, ['header', 'footer', 'nav', 'aside']);

    return trim($trimmed);
}

function cmsSanitizePromptTemplateForTarget(string $template, bool $isLayoutTarget): string
{
    return $isLayoutTarget
        ? cmsNormalizeLayoutPromptTemplate($template)
        : cmsSanitizeInnerPromptTemplate($template);
}
