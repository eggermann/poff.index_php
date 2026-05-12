<?php

function cmsEditSaveApplyWorkFields(array &$config, array $data, string $targetDir): void
{
    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    $templateMapUpdate = null;
    if (isset($data['work']) && is_array($data['work'])) {
        foreach ($data['work'] as $key => $value) {
            if ($key === 'type') {
                continue;
            }
            if ($key === 'templateMap') {
                $templateMapUpdate = $value;
                continue;
            }
            $work[$key] = $value;
        }
    }
    $workType = '';
    $hasWorkType = false;
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

    $inheritedTemplateMap = PoffConfig::resolveInheritedTemplateMap($targetDir);
    if ($templateMapUpdate !== null) {
        $nextTemplateMap = is_array($work['templateMap'] ?? null) ? $work['templateMap'] : [];
        if (is_array($templateMapUpdate)) {
            foreach ($templateMapUpdate as $mime => $template) {
                if (!is_scalar($mime)) {
                    continue;
                }
                $normalizedMime = strtolower(trim((string) $mime));
                if ($normalizedMime === '') {
                    continue;
                }
                if (!is_scalar($template) || trim((string) $template) === '') {
                    unset($nextTemplateMap[$normalizedMime]);
                    continue;
                }
                $normalizedTemplate = Worktype::normalizeTemplateKey((string) $template);
                if ($normalizedTemplate === '') {
                    unset($nextTemplateMap[$normalizedMime]);
                    continue;
                }
                $nextTemplateMap[$normalizedMime] = $normalizedTemplate;
            }
        }
        $nextTemplateMap = PoffConfig::trimTemplateMapOverrides($nextTemplateMap, $inheritedTemplateMap);
        if ($nextTemplateMap !== []) {
            $work['templateMap'] = $nextTemplateMap;
        } else {
            unset($work['templateMap']);
        }
    }
    if (array_key_exists('templateMap', $work) && is_array($work['templateMap'])) {
        $currentTemplateMap = PoffConfig::trimTemplateMapOverrides($work['templateMap'], $inheritedTemplateMap);
        if ($currentTemplateMap !== []) {
            $work['templateMap'] = $currentTemplateMap;
        } else {
            unset($work['templateMap']);
        }
    }
    $config['work'] = $work;
}
