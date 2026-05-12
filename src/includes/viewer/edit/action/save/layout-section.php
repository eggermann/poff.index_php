<?php

function cmsEditSaveApplySectionOnlyLayoutTemplate(
    array &$config,
    bool $sectionOnlyLayoutUpdate,
    string $targetDir,
    ?string $targetFile,
    ?string $layoutSectionTemplate,
    string $layoutSection,
    string $subjectType
): void {
    if (!$sectionOnlyLayoutUpdate) {
        return;
    }

    $sanitizedSectionTemplate = PoffConfig::persistSectionTemplate(
        $targetDir,
        $subjectType === 'file' ? (string) $targetFile : null,
        (string) $layoutSectionTemplate,
        $layoutSection
    );
    if (!isset($config['work']['layout']) || !is_array($config['work']['layout'])) {
        $config['work']['layout'] = [];
    }
    $config['work']['layout']['sectionTemplate'] = $sanitizedSectionTemplate;
    $config['work']['layout'] = Worktype::normalizeLayout($config['work']['layout'], $layoutSection);
}
