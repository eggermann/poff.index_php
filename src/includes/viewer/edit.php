<?php
/**
 * Edit actions: config, save, and prompt endpoints.
 */

require_once __DIR__ . '/utils.php';

function cmsHandleEditAction(): void
{
    $action = $_GET['edit'] ?? '';
    if (!in_array($action, ['config', 'save', 'prompt'], true)) {
        return;
    }

    $rootDir = getcwd();
    $allowFile = $rootDir . DIRECTORY_SEPARATOR . '.edit.allow';
    if (!is_file($allowFile)) {
        cmsJsonResponse([
            'allowed' => false,
            'error' => 'Edit mode not enabled.',
        ]);
    }

    if (!class_exists('PoffConfig')) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'PoffConfig unavailable.',
        ]);
    }

    $data = ($action === 'save' || $action === 'prompt') ? cmsReadJsonBody() : [];
    if ($data === []) {
        $data = $_POST;
    }
    $path = isset($_GET['path']) ? (string) $_GET['path'] : '';
    if ($path === '' && isset($data['path'])) {
        $path = (string) $data['path'];
    }
    $target = cmsResolveTarget($rootDir, $path);
    if ($target === null) {
        cmsJsonResponse([
            'allowed' => true,
            'error' => 'Invalid folder path.',
        ]);
    }
    $targetType = $target['type'];
    $targetDir = $target['dir'];
    $targetFile = $target['file'] ?? null;

    if ($targetType === 'file') {
        $config = PoffConfig::ensureFileConfig($targetDir, (string) $targetFile);
    } else {
        $config = PoffConfig::ensure($targetDir);
    }

    if ($action === 'config') {
        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'config' => $config,
        ]);
    }

    if ($action === 'save') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Save requires POST.',
            ], 405);
        }

        if (array_key_exists('title', $data)) {
            $config['title'] = trim((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $config['description'] = trim((string) $data['description']);
        }
        if (array_key_exists('link', $data)) {
            $link = trim((string) $data['link']);
            if ($link !== '') {
                $config['link'] = $link;
            } else {
                unset($config['link']);
            }
        }
        if (array_key_exists('url', $data)) {
            $url = trim((string) $data['url']);
            if ($url !== '') {
                $config['url'] = $url;
            } else {
                unset($config['url']);
            }
        }

        $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
        $hasWorkType = false;
        $workType = '';
        if (isset($data['work']) && is_array($data['work']) && array_key_exists('type', $data['work'])) {
            $workType = trim((string) $data['work']['type']);
            $hasWorkType = true;
        } elseif (array_key_exists('work_type', $data)) {
            $workType = trim((string) $data['work_type']);
            $hasWorkType = true;
        }
        if ($hasWorkType && $workType !== '') {
            $work['type'] = $workType;
        }

        $layoutPayload = isset($data['layout']) && is_array($data['layout']) ? $data['layout'] : null;
        $layoutMode = '';
        $layoutModel = null;
        $layoutTemplateProvided = false;
        $layoutTemplate = null;
        $hasLayoutUpdate = false;

        if (is_array($layoutPayload)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) ($layoutPayload['mode'] ?? $layoutPayload['name'] ?? ''));
            $layoutModel = $layoutPayload['model'] ?? null;
            if (array_key_exists('template', $layoutPayload)) {
                $layoutTemplateProvided = true;
                $layoutTemplate = (string) $layoutPayload['template'];
            }
        }

        if (array_key_exists('layout_mode', $data)) {
            $hasLayoutUpdate = true;
            $layoutMode = trim((string) $data['layout_mode']);
        }
        if (array_key_exists('layout_model', $data)) {
            $hasLayoutUpdate = true;
            $layoutModel = $data['layout_model'];
        }
        if (array_key_exists('layout_template', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layout_template'];
        }
        if (array_key_exists('layoutTemplate', $data)) {
            $hasLayoutUpdate = true;
            $layoutTemplateProvided = true;
            $layoutTemplate = (string) $data['layoutTemplate'];
        }

        $workLayout = '';
        if (array_key_exists('work_layout', $data)) {
            $workLayout = trim((string) $data['work_layout']);
        }

        $layoutSection = $targetType === 'folder' ? 'works' : 'work';
        if ($hasLayoutUpdate) {
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
            $work['layout'] = Worktype::normalizeLayout($layout, $layoutSection);
        } elseif ($workLayout !== '') {
            $work['layout'] = Worktype::normalizeLayout($workLayout, $layoutSection);
        }

        $config['work'] = $work;

        if ($targetType === 'folder') {
            $treeVisible = $data['treeVisible'] ?? $data['tree_visible'] ?? null;
            $hasTreeUpdate = array_key_exists('treeVisible', $data) || array_key_exists('tree_visible', $data);
            if ($hasTreeUpdate && is_array($config['tree'] ?? null)) {
                $visibleKeys = [];
                if (is_array($treeVisible)) {
                    foreach ($treeVisible as $key) {
                        if (is_scalar($key)) {
                            $visibleKeys[(string) $key] = true;
                        }
                    }
                }
                foreach ($config['tree'] as &$item) {
                    $key = $item['path'] ?? $item['name'] ?? null;
                    if ($key === null) {
                        continue;
                    }
                    $item['visible'] = isset($visibleKeys[$key]);
                }
                unset($item);
            }
        }

        $config['updatedAt'] = date('c');
        if ($targetType === 'folder') {
            $config['treeHash'] = hash('sha256', json_encode($config['tree'] ?? []));
        }
        $configPath = $targetType === 'file'
            ? PoffConfig::fileConfigPath($targetDir, (string) $targetFile)
            : PoffConfig::configPath($targetDir);
        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Failed to encode config JSON.',
            ], 500);
        }
        file_put_contents($configPath, $encoded);

        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'saved' => true,
            'config' => $config,
        ]);
    }

    if ($action === 'prompt') {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Prompt requires POST.',
            ], 405);
        }

        $provider = strtolower((string) ($data['provider'] ?? 'local'));
        $prompt = trim((string) ($data['prompt'] ?? ''));
        $model = trim((string) ($data['model'] ?? ''));
        $endpoint = trim((string) ($data['endpoint'] ?? ''));
        $apiKey = trim((string) ($data['apiKey'] ?? ''));
        $history = is_array($data['history'] ?? null) ? $data['history'] : [];
        $systemPromptValue = trim((string) ($data['systemPrompt'] ?? ''));

        if ($prompt === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Missing prompt.',
            ]);
        }

        $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $defaultSystemPrompt = implode("\n", [
            'You are a Handlebars (HBS) template generator for this single-page CMS.',
            'Return one HBS template string rendered through the LightnCandy renderer and saved to work.layout.template.',
            'Return only the template (no Markdown, no fences).',
            'Default layout technique: use {{> default-layout}}. Inside that layout, the section includes {{> works}} for folders and {{> work}} for files.',
            'Inputs available: {{path}}, {{name}}, {{title}}, {{linkUrl}}, {{slug}}, layout.*, and work.* values from config/work.',
            'Use config/title/description, layout name/template, and tree data when relevant; prefer existing worktypes: image, video, audio, pdf, text, link, folder, other.',
            'You may embed scoped <style> and <script>; keep everything self-contained, avoid external URLs, and namespace ids/classes to prevent collisions.',
            'If you add JS, guard for DOM readiness and avoid network calls; degrade gracefully if JS is disabled.',
        ]);
        $systemPrompt = $systemPromptValue !== '' ? $systemPromptValue : $defaultSystemPrompt;
        $historyText = '';
        foreach ($history as $msg) {
            if (!is_array($msg) || !isset($msg['role']) || !isset($msg['content'])) {
                continue;
            }
            $role = strtolower((string) $msg['role']);
            $content = trim((string) $msg['content']);
            if ($content === '') {
                continue;
            }
            $historyText .= strtoupper($role) . ": " . $content . "\n";
        }
        $userPrompt = "Config JSON:\n" . $configJson . "\n\n" . $historyText . "USER: " . $prompt;

        $env = cmsLoadEnv($rootDir);
        $template = '';
        $usedModel = $model;

        if ($provider === 'openai') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'OPENAI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'OpenAI API key not set.',
                ]);
            }
            if ($usedModel === '') {
                $usedModel = 'gpt-4o-mini';
            }
            $payload = [
                'model' => $usedModel,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.4,
            ];
            $response = cmsHttpPost('https://api.openai.com/v1/chat/completions', [
                'Authorization: Bearer ' . $key,
            ], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'OpenAI request failed.',
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['choices'][0]['message']['content'] ?? '');
        } elseif ($provider === 'gemini') {
            $key = $apiKey !== '' ? $apiKey : (cmsEnvValue($env, 'GEMINI_API_KEY') ?? '');
            if ($key === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Gemini API key not set.',
                ]);
            }
            if ($usedModel === '') {
                $usedModel = 'gemini-1.5-flash';
            }
            $promptText = $systemPrompt . "\n\n" . $userPrompt;
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $promptText],
                        ],
                    ],
                ],
            ];
            $url = sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s', rawurlencode($usedModel), $key);
            $response = cmsHttpPost($url, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Gemini request failed.',
                ]);
            }
            $decoded = json_decode($response['body'], true);
            $template = (string) ($decoded['candidates'][0]['content']['parts'][0]['text'] ?? '');
        } else {
            if ($endpoint === '') {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Local endpoint URL missing.',
                ]);
            }
            $payload = [
                'prompt' => $prompt,
                'history' => $history,
                'config' => $config,
                'instruction' => $systemPrompt,
            ];
            $response = cmsHttpPost($endpoint, [], $payload);
            if (!$response['ok']) {
                cmsJsonResponse([
                    'allowed' => true,
                    'error' => 'Local endpoint request failed.',
                ]);
            }
            $decoded = json_decode($response['body'], true);
            if (is_array($decoded)) {
                if (isset($decoded['template'])) {
                    $template = (string) $decoded['template'];
                } elseif (isset($decoded['content'])) {
                    $template = (string) $decoded['content'];
                }
            }
            if ($template === '') {
                $template = trim((string) $response['body']);
            }
        }

        $template = trim($template);
        if ($template === '') {
            cmsJsonResponse([
                'allowed' => true,
                'error' => 'Template was empty.',
            ]);
        }

        cmsJsonResponse([
            'allowed' => true,
            'target' => $targetType,
            'provider' => $provider,
            'model' => $usedModel,
            'template' => $template,
            'systemPrompt' => $systemPrompt,
        ]);
    }
}
