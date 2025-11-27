<?php
declare(strict_types=1);

function handleWorkPrompt(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $targetFile = $opts['file'] ?? '';
    $stylePrompt = $opts['style'] ?? '';

    if ($targetFile === '') {
        mcpJsonError('Missing file parameter (?file=relative/path)', ['route' => 'workprompt']);
    }
    $absPath = realpath($rootDir . DIRECTORY_SEPARATOR . ltrim($targetFile, '/\\'));
    if ($absPath === false || strpos($absPath, $rootDir) !== 0 || !is_file($absPath)) {
        mcpJsonError('File not found or outside workspace', ['route' => 'workprompt', 'file' => $targetFile]);
    }

    $fileConfig = null;
    if (class_exists('PoffConfig')) {
        $fileConfig = PoffConfig::ensureFileConfig(dirname($absPath), basename($absPath));
    }
    $kind = class_exists('MediaType') ? MediaType::classifyExtension($absPath) : 'other';
    $mime = class_exists('MediaType') ? MediaType::detectMimeType($absPath, basename($absPath)) : null;
    $model = class_exists('Worktype') ? Worktype::definition($kind, $mime) : ['type' => $kind];

    $template = '';
    $tplPath = __DIR__ . '/../../includes/worktypes/templates/' . $kind . '.tpl';
    if (!file_exists($tplPath)) {
        $tplPath = __DIR__ . '/../../includes/worktypes/templates/other.tpl';
    }
    if (file_exists($tplPath)) {
        $template = (string) file_get_contents($tplPath);
    }

    $configPath = class_exists('PoffConfig')
        ? PoffConfig::fileConfigPath(dirname($absPath), basename($absPath))
        : null;
    if ($configPath) {
        $currentConfig = $fileConfig ?? [];
        $existingWork = isset($currentConfig['work']) && is_array($currentConfig['work']) ? $currentConfig['work'] : [];
        $mergedWork = array_merge($model, $existingWork);
        $mergedWork['layout'] = [
            'model' => $model,
            'template' => $template,
            'stylePrompt' => $stylePrompt,
        ];
        $currentConfig['work'] = $mergedWork;
        $currentConfig['updatedAt'] = date('c');
        $dirPath = dirname($configPath);
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
        file_put_contents($configPath, json_encode($currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $fileConfig = $currentConfig;
    }

    return [
        'route' => 'workprompt',
        'file' => $targetFile,
        'kind' => $kind,
        'stylePrompt' => $stylePrompt,
        'model' => $model,
        'template' => $template,
        'config' => $fileConfig,
        'configPath' => $configPath,
        'instruction' => 'Use model+template as a base. Apply style prompt to produce a new card template and updated work model. Save updates into work.layout.model/template.',
    ];
}
