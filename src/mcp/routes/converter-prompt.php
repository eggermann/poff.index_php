<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../includes/Converter.php';

function handleConverterPrompt(array $opts): array
{
    $rootDir = (string) ($opts['rootDir'] ?? '');
    $path = trim((string) ($opts['path'] ?? ''), "/\\");
    $access = mcpEditorAccessState($rootDir, $path);
    if (!$access['allowed']) {
        return array_merge(['route' => 'converter-prompt'], $access);
    }

    $definition = $path !== '' ? Converter::definitionFromFolder($rootDir, $path) : null;
    return [
        'route' => 'converter-prompt',
        'targetType' => 'converter',
        'path' => $path,
        'definition' => $definition,
        'grain' => Converter::defaultGrain(),
        'instruction' => implode("\n", [
            'You are editing a poff converter app.',
            'The converter receives an incoming conversion payload and renders converter UI, status, options, and preview information.',
            'Do not render the final generated work directly.',
            'The generated work will be saved as a poff work and displayed through external.hbs.',
            'Return template, css, and optional js.',
            'Use semantic HTML and stable readable class names.',
            'Scope all CSS under one unique root class.',
            'Do not use inline styles.',
            'Do not use Tailwind utility classes.',
            'Do not make network calls in script.js.',
        ]),
    ];
}
