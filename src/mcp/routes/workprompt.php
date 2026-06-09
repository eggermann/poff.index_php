<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers.php';

function handleWorkPrompt(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $targetFile = $opts['file'] ?? '';
    $stylePrompt = $opts['style'] ?? '';
    $access = mcpEditorAccessState($rootDir, (string) $targetFile);
    if (!$access['allowed']) {
        return array_merge(['route' => 'workprompt', 'file' => $targetFile], $access);
    }

    if ($targetFile === '') {
        mcpJsonError('Missing file parameter (?file=relative/path)', ['route' => 'workprompt']);
    }
    $absPath = mcpResolveFileInsideRoot($rootDir, $targetFile);
    if ($absPath === null) {
        mcpJsonError('File not found or outside workspace', ['route' => 'workprompt', 'file' => $targetFile]);
    }

    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($absPath), basename($absPath));
    }
    $kind = class_exists('MediaType') ? MediaType::classifyExtension($absPath) : 'other';
    $mime = class_exists('MediaType') ? MediaType::detectMimeType($absPath, basename($absPath)) : null;
    $model = class_exists('Worktype') ? Worktype::definition($kind, $mime) : ['type' => $kind];

    $template = class_exists('Worktype') ? Worktype::layoutTemplate($kind, $model) : '';
    $partials = class_exists('Worktype') ? Worktype::templates() : [];

    $configPath = class_exists('PoffConfig')
        ? PoffConfig::fileConfigPath(dirname($absPath), basename($absPath))
        : null;
    if ($configPath) {
        $currentConfig = $fileConfig ?? [];
        $existingWork = isset($currentConfig['work']) && is_array($currentConfig['work']) ? $currentConfig['work'] : [];
        $mergedWork = array_merge($model, $existingWork);
        $mergedWork['layout'] = PoffConfig::persistLayoutFiles(
            dirname($absPath),
            basename($absPath),
            [
                'name' => 'filesystem-layout',
                'engine' => 'lightncandy',
                'section' => 'work',
                'model' => $model,
                'template' => $template,
                'stylePrompt' => $stylePrompt,
            ],
            'work'
        );
        $currentConfig['work'] = $mergedWork;
        $currentConfig['updatedAt'] = date('c');
        $writeError = mcpWriteJsonFile($configPath, $currentConfig);
        if ($writeError !== null) {
            mcpJsonError($writeError, ['route' => 'workprompt', 'file' => $targetFile], 500);
        }
        $fileConfig = PoffConfig::hydrateConfigLayout($currentConfig, dirname($absPath), basename($absPath));
    }

    return [
        'route' => 'workprompt',
        'file' => $targetFile,
        'kind' => $kind,
        'stylePrompt' => $stylePrompt,
        'model' => $model,
        'template' => $template,
        'partials' => $partials,
        'config' => $fileConfig,
        'configPath' => $configPath,
        'instruction' => 'Use the filesystem-layout HBS template and partials as a base. The section includes works for folders and work for files. The bundled poff-layout remains available as a fallback. Save updates into the item layout filesystem as source templates in .layout and .works. Use semantic HTML and stable readable class names. Put styling in scoped style.css under a unique root class, without global selectors, inline style attributes, or Tailwind utility classes. script.js is for behavior only; guard DOM readiness, avoid network calls, and degrade gracefully.',
    ];
}
