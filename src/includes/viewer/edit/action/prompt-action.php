<?php

function cmsHandleEditPromptAction(array $ctx): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        cmsJsonResponse(['allowed' => true, 'error' => 'Prompt requires POST.'], 405);
    }

    $request = cmsBuildEditPromptRequest($ctx);
    if ($request['prompt'] === '' && !$request['image']) {
        cmsJsonResponse(['allowed' => true, 'error' => 'Missing prompt or image.']);
    }

    $request['rootDir'] = $ctx['rootDir'];
    $request['systemPrompt'] = $request['systemPrompt'] !== '' ? $request['systemPrompt'] : $request['defaultSystemPrompt'];
    $request['userPrompt'] = "Config JSON:\n" . $request['configJson'] . "\n\nPrompt context JSON:\n" . $request['promptContextJson'] . "\n\n" . $request['responseFormatInstruction'] . "\n\n" . cmsPromptHistoryText($request['history']) . "USER: " . $request['prompt'];
    if ($request['image']) {
        $request['userPrompt'] .= "\n\nAttached image: " . ($request['image']['name'] ?: 'clipboard-image.png');
    }
    $request['env'] = cmsLoadEnv($ctx['rootDir']);
    $request['streamBuffer'] = '';
    $request['streamTemplate'] = '';
    $request['streamChunkParser'] = function (string $chunk) use (&$request): void {
        if ($chunk === '') {
            return;
        }
        $chunk = str_replace("\r\n", "\n", $chunk);
        echo $chunk;
        if (function_exists('ob_flush')) {
            @ob_flush();
        }
        flush();
        $request['streamBuffer'] .= $chunk;
        while (strpos($request['streamBuffer'], "\n\n") !== false) {
            $splitAt = strpos($request['streamBuffer'], "\n\n");
            $block = trim(substr($request['streamBuffer'], 0, $splitAt));
            $request['streamBuffer'] = substr($request['streamBuffer'], $splitAt + 2);
            if ($block === '') {
                continue;
            }
            $dataLines = [];
            foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }
            $dataLine = implode("\n", $dataLines);
            if ($dataLine === '' || $dataLine === '[DONE]') {
                continue;
            }
            $decodedChunk = json_decode($dataLine, true);
            if (!is_array($decodedChunk)) {
                continue;
            }
            $delta = $decodedChunk['choices'][0]['delta']['content'] ?? null;
            if (is_string($delta) && $delta !== '') {
                $request['streamTemplate'] .= $delta;
            }
        }
    };

    $result = match ($request['provider']) {
        'openai' => cmsEditPromptRunOpenAi($request),
        'gemini' => cmsEditPromptRunGemini($request),
        default => cmsEditPromptRunLocal($request),
    };
    if (cmsPromptIsSseActive() && trim((string) ($result['template'] ?? '')) === '' && trim((string) ($request['streamTemplate'] ?? '')) !== '') {
        $result['template'] = (string) $request['streamTemplate'];
    }
    $parsedResult = cmsParsePromptModelResult((string) ($result['template'] ?? ''), $request['promptIsLayoutTarget']);
    $templateText = trim((string) ($parsedResult['template'] ?? ''));
    if ($templateText === '') {
        $errorPayload = [
            'allowed' => true,
            'error' => ($result['modelReturnedReasoningOnly'] ?? false)
                ? 'Model returned reasoning only and no template text. Disable reasoning/thinking in LM Studio or ask the model to return final template text.'
                : 'Template was empty.',
        ];
        if (cmsPromptIsSseActive()) {
            cmsPromptSendSseEvent('final', $errorPayload);
            exit;
        }
        cmsJsonResponse($errorPayload);
    }

    $responsePayload = [
        'allowed' => true,
        'target' => $request['promptIsLayoutTarget'] ? 'layout' : $ctx['subjectType'],
        'subjectTarget' => $ctx['subjectType'],
        'provider' => $request['provider'],
        'model' => (string) ($result['usedModel'] ?? ''),
        'template' => $templateText,
        'systemPrompt' => $request['systemPrompt'] !== '' ? $request['systemPrompt'] : $request['defaultSystemPrompt'],
    ];
    foreach (['title', 'description', 'work', 'css', 'js', 'treeVisible'] as $key) {
        if (array_key_exists($key, $parsedResult)) {
            $responsePayload[$key] = $parsedResult[$key];
        }
    }

    if (cmsPromptIsSseActive()) {
        cmsPromptSendSseEvent('final', $responsePayload);
        exit;
    }

    cmsJsonResponse($responsePayload);
}
