<?php

function cmsEditSavePersistOriginalLayoutFiles(
    array $config,
    string $originalLayoutTarget,
    bool $originalLayoutTemplateProvided,
    ?string $originalLayoutTemplate,
    bool $originalLayoutCssProvided,
    ?string $originalLayoutCss,
    bool $originalLayoutJsProvided,
    ?string $originalLayoutJs
): void {
    if (!is_array($config['work']['layout'] ?? null) || $originalLayoutTarget === '') {
        return;
    }
    if (!$originalLayoutTemplateProvided && !$originalLayoutCssProvided && !$originalLayoutJsProvided) {
        return;
    }

    PoffConfig::persistOriginalLayoutFiles($originalLayoutTarget, [
        'template' => $originalLayoutTemplateProvided ? $originalLayoutTemplate : null,
        'css' => $originalLayoutCssProvided ? $originalLayoutCss : null,
        'js' => $originalLayoutJsProvided ? $originalLayoutJs : null,
    ]);
}
