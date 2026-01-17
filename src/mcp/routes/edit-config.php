<?php
declare(strict_types=1);

function mcpResolveEditPath(string $rootDir, string $relativePath): ?string
{
    $trimmed = trim($relativePath, "/\\");
    $base = rtrim($rootDir, DIRECTORY_SEPARATOR);
    if ($trimmed === '') {
        return realpath($base) ?: null;
    }
    $candidate = realpath($base . DIRECTORY_SEPARATOR . $trimmed);
    if ($candidate === false) {
        return null;
    }
    if (strpos($candidate, $base) !== 0) {
        return null;
    }
    if (!is_dir($candidate)) {
        return null;
    }
    return $candidate;
}

function mcpReadJsonBody(): array
{
    $raw = (string) file_get_contents('php://input');
    if ($raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function handleEditConfig(array $opts): array
{
    $rootDir = $opts['rootDir'];
    $path = $opts['path'] ?? '';
    $allowFile = $opts['allowFile'] ?? ($rootDir . DIRECTORY_SEPARATOR . '.edit.allow');
    $allowed = is_file($allowFile);

    if (!$allowed) {
        return [
            'route' => 'edit-config',
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ];
    }

    if (!class_exists('PoffConfig')) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ];
    }

    $targetDir = mcpResolveEditPath($rootDir, (string) $path);
    if ($targetDir === null) {
        return [
            'route' => 'edit-config',
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ];
    }

    $config = PoffConfig::ensure($targetDir);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'POST') {
        $data = mcpReadJsonBody();
        if ($data === []) {
            $data = $_POST;
        }

        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $link = trim((string) ($data['link'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));
        $workType = trim((string) ($data['work']['type'] ?? $data['work_type'] ?? ''));
        $workLayoutRaw = $data['work']['layout'] ?? $data['work_layout'] ?? '';
        $workLayout = trim((string) $workLayoutRaw);
        $layoutPayload = $data['layout'] ?? null;
        $layoutPayloadMode = is_array($layoutPayload) ? ($layoutPayload['mode'] ?? '') : '';
        $layoutMode = trim((string) ($data['layout_mode'] ?? $layoutPayloadMode));
        $layoutModel = is_array($layoutPayload) ? ($layoutPayload['model'] ?? null) : null;
        if ($layoutModel === null && array_key_exists('layout_model', $data)) {
            $layoutModel = $data['layout_model'];
        }
        $layoutTemplateProvided = false;
        $layoutTemplate = null;
        if (is_array($layoutPayload) && array_key_exists('template', $layoutPayload)) {
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $layoutPayload['template'];
        }
        if (array_key_exists('layout_template', $data)) {
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layout_template'];
        }
        if (array_key_exists('layoutTemplate', $data)) {
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layoutTemplate'];
        }
        $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
        $visibleKeys = [];
        $hasTreeUpdate = array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data);
        if (is_array($treeVisible)) {
            foreach ($treeVisible as $key) {
                if (is_scalar($key)) {
                    $visibleKeys[(string) $key] = true;
                }
            }
        }

        $config['title'] = $title;
        $config['description'] = $description;
        if ($link !== '') {
            $config['link'] = $link;
        } else {
            unset($config['link']);
        }
        if ($url !== '') {
            $config['url'] = $url;
        } else {
            unset($config['url']);
        }

        $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
        if ($workType !== '') {
            $work['type'] = $workType;
        }
        $layoutValue = $work['layout'] ?? null;
        $layout = is_array($layoutValue) ? $layoutValue : [];
        if (is_string($layoutValue) && $layoutValue !== '') {
            $layout['mode'] = $layoutValue;
        }
        if ($layoutMode !== '') {
            $layout['mode'] = $layoutMode;
        }
        if ($layoutTemplateProvided) {
            $layout['template'] = $layoutTemplate;
        }
        if (is_string($layoutModel) && $layoutModel !== '') {
            $layout['model'] = $layoutModel;
        }
        $hasLayoutUpdate = is_array($layoutPayload)
            || $layoutTemplateProvided
            || array_key_exists('layout_mode', $data)
            || array_key_exists('layout_model', $data);
        if ($hasLayoutUpdate) {
            $work['layout'] = $layout;
        } elseif ($workLayout !== '') {
            $work['layout'] = $workLayout;
        }
        $config['work'] = $work;

        if ($hasTreeUpdate && isset($config['tree']) && is_array($config['tree'])) {
            foreach ($config['tree'] as &$item) {
                $key = $item['path'] ?? $item['name'] ?? null;
                if ($key === null) {
                    continue;
                }
                $item['visible'] = isset($visibleKeys[$key]);
            }
            unset($item);
        }

        $config['updatedAt'] = date('c');
        $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
        $configPath = PoffConfig::configPath($targetDir);
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return [
                'route' => 'edit-config',
                'allowed' => true,
                'error' => 'Failed to encode config JSON.',
            ];
        }
        file_put_contents($configPath, $encoded);

        return [
            'route' => 'edit-config',
            'allowed' => true,
            'saved' => true,
            'config' => $config,
        ];
    }

    return [
        'route' => 'edit-config',
        'allowed' => true,
        'config' => $config,
    ];
}
