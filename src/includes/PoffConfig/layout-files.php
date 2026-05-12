<?php

trait PoffConfigLayoutFileHelpers
{
    public static function persistLayoutFiles(string $dir, ?string $fileName, mixed $layout, string $section = 'work'): array
    {
        $normalized = Worktype::normalizeLayout($layout, $section);
        $layoutMode = trim((string) ($normalized['mode'] ?? ''));
        $layoutName = trim((string) ($normalized['name'] ?? ''));
        $layoutPreset = trim((string) ($normalized['preset'] ?? ''));
        if ($layoutPreset !== 'shared') {
            unset($normalized['source'], $normalized['sharedName']);
        }
        $isSharedPreset = $layoutPreset === 'shared'
            || $layoutMode === 'shared'
            || (($normalized['source'] ?? '') === 'shared')
            || (($normalized['sharedName'] ?? '') !== '');
        $inactivePreset = $layoutMode === 'none'
            || $layoutName === 'none'
            || $layoutPreset === 'none'
            || (
                $layoutPreset === 'actual'
                && in_array($layoutName, [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)
            );
        $isCustomPreset = $layoutMode === 'custom-layout'
            || $layoutName === 'custom-layout'
            || $layoutPreset === 'custom';
        if (array_key_exists('template', $normalized)) {
            $normalized['template'] = self::sanitizeStoredPromptTemplate((string) $normalized['template'], true);
        }
        foreach (['sectionTemplate', 'workTemplate', 'worksTemplate'] as $sectionKey) {
            if (array_key_exists($sectionKey, $normalized)) {
                $normalized[$sectionKey] = self::sanitizeStoredPromptTemplate((string) $normalized[$sectionKey], false);
            }
        }
        if ($inactivePreset) {
            foreach (['template', 'css', 'js', 'sectionTemplate', 'workTemplate', 'worksTemplate'] as $key) {
                unset($normalized[$key]);
            }
        }
        if ($isSharedPreset) {
            $normalized['storage'] = 'shared';
            return self::serializeLayout($normalized, $section);
        }
        $layoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $managedLayoutKeys = ['template', 'css', 'js', 'sectionTemplate', 'workTemplate', 'worksTemplate'];
        $providedManagedKeys = array_values(array_filter(
            $managedLayoutKeys,
            static fn(string $key): bool => array_key_exists($key, $normalized)
        ));
        $allProvidedManagedValuesEmpty = $providedManagedKeys !== [];
        foreach ($providedManagedKeys as $key) {
            if (trim((string) $normalized[$key]) !== '') {
                $allProvidedManagedValuesEmpty = false;
                break;
            }
        }
        $hasExistingManagedLayoutFile = self::hasWrapperFiles($layoutDir)
            || is_file($layoutDir . DIRECTORY_SEPARATOR . self::sectionTemplateFile($section));
        if ($isCustomPreset && $allProvidedManagedValuesEmpty && $hasExistingManagedLayoutFile) {
            foreach ($providedManagedKeys as $key) {
                unset($normalized[$key]);
            }
        }
        self::writeManagedLayoutFiles($layoutDir, self::defaultLayoutFiles($section));
        $sectionFiles = [];
        if (array_key_exists('sectionTemplate', $normalized)) {
            $sectionFiles[self::sectionTemplateFile($section)] = (string) $normalized['sectionTemplate'];
        }
        if (array_key_exists('workTemplate', $normalized)) {
            $sectionFiles[self::WORK_SECTION_TEMPLATE_FILE] = (string) $normalized['workTemplate'];
        }
        if (array_key_exists('worksTemplate', $normalized)) {
            $sectionFiles[self::WORKS_SECTION_TEMPLATE_FILE] = (string) $normalized['worksTemplate'];
        }

        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $normalized) ? (string) $normalized['template'] : null,
            self::LAYOUT_STYLE_FILE => array_key_exists('css', $normalized) ? (string) $normalized['css'] : null,
            self::LAYOUT_SCRIPT_FILE => array_key_exists('js', $normalized) ? (string) $normalized['js'] : null,
        ] + $sectionFiles);

        return self::serializeLayout($normalized, $section);
    }

    public static function persistSectionTemplate(string $dir, ?string $fileName, string $sectionTemplate, string $section = 'work'): string
    {
        $layoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $sanitized = self::sanitizeStoredPromptTemplate($sectionTemplate, false);
        self::writeManagedLayoutFiles($layoutDir, [
            self::sectionTemplateFile($section) => $sanitized,
        ]);

        return $sanitized;
    }

    public static function persistOriginalLayoutFiles(string $relativeDir, array $payload): string
    {
        $layoutDir = self::resolveRelativeDirectory($relativeDir);
        if ($layoutDir === null) {
            throw new InvalidArgumentException('Invalid layout source path.');
        }

        self::writeManagedLayoutFiles($layoutDir, [
            self::LAYOUT_TEMPLATE_FILE => array_key_exists('template', $payload) ? self::sanitizeStoredPromptTemplate((string) $payload['template'], true) : null,
            self::LAYOUT_STYLE_FILE => array_key_exists('css', $payload) ? (string) $payload['css'] : null,
            self::LAYOUT_SCRIPT_FILE => array_key_exists('js', $payload) ? (string) $payload['js'] : null,
        ]);

        return str_replace('\\', '/', trim($relativeDir, "/\\"));
    }
}
