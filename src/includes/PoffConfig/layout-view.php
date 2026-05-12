<?php

trait PoffConfigLayoutViewHelpers
{
    public static function hydrateConfigLayout(array $config, string $dir, ?string $fileName = null): array
    {
        $section = $fileName === null ? 'works' : 'work';
        $work = isset($config['work']) && is_array($config['work']) ? $config['work'] : [];
        $work['layout'] = self::hydrateLayoutFilesystem($work['layout'] ?? null, $dir, $fileName, $section);
        $workType = trim((string) ($work['type'] ?? ($fileName === null ? 'folder' : 'other')));
        $defaultSectionTemplate = Worktype::template($workType);
        if (is_string($defaultSectionTemplate) && $defaultSectionTemplate !== '') {
            $work['layout']['defaultSectionTemplate'] = $defaultSectionTemplate;
        }
        $config['work'] = $work;

        return $config;
    }

    public static function prepareLayoutForView(mixed $layout, string $itemPath, bool $isFile, string $section = 'work'): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        $resolvedTemplate = '';
        $defaultLayoutTemplate = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_TEMPLATE_FILE);
        $filesystemLayoutTemplate = Worktype::layoutBundleAsset(Worktype::filesystemLayoutName(), self::LAYOUT_TEMPLATE_FILE);
        $defaultTemplateMarkup = is_string($defaultLayoutTemplate) ? trim($defaultLayoutTemplate) : '';
        $filesystemTemplateMarkup = is_string($filesystemLayoutTemplate) ? trim($filesystemLayoutTemplate) : '';

        if (array_key_exists('phpTemplate', $resolved) && is_string($resolved['phpTemplate'])) {
            $resolvedTemplate = trim($resolved['phpTemplate']);
        }

        $usesDefaultBundleTemplate = $resolvedTemplate !== '' && (
            ($defaultTemplateMarkup !== '' && $resolvedTemplate === $defaultTemplateMarkup)
            || ($filesystemTemplateMarkup !== '' && $resolvedTemplate === $filesystemTemplateMarkup)
        );
        $layoutTemplateSource = trim((string) ($resolved['template'] ?? ''));
        $usesDefaultBundlePartial = $layoutTemplateSource !== '' && preg_match('/\{\{>\s*(poff-layout|filesystem-layout)\s*\}\}/', $layoutTemplateSource) === 1;
        $loadDefaultBundleAssets = $usesDefaultBundleTemplate || $usesDefaultBundlePartial;

        if ($loadDefaultBundleAssets) {
            if (!array_key_exists('css', $resolved) || trim((string) ($resolved['css'] ?? '')) === '') {
                $bundleCss = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_STYLE_FILE);
                if (is_string($bundleCss) && $bundleCss !== '') {
                    $resolved['css'] = $bundleCss;
                    $resolved['cssInlineOnly'] = true;
                }
            }
            if (!array_key_exists('js', $resolved) || trim((string) ($resolved['js'] ?? '')) === '') {
                $bundleJs = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_SCRIPT_FILE);
                if (is_string($bundleJs) && $bundleJs !== '') {
                    $resolved['js'] = $bundleJs;
                    $resolved['jsInlineOnly'] = true;
                }
            }
        }

        if (($resolved['storage'] ?? '') !== 'filesystem') {
            if (!array_key_exists('css', $resolved) || trim((string) ($resolved['css'] ?? '')) === '') {
                $bundleCss = Worktype::layoutBundleAsset((string) ($resolved['name'] ?? ''), self::LAYOUT_STYLE_FILE);
                if (is_string($bundleCss) && $bundleCss !== '') {
                    $resolved['css'] = $bundleCss;
                }
                if ((!array_key_exists('css', $resolved) || trim((string) ($resolved['css'] ?? '')) === '') && $usesDefaultBundleTemplate) {
                    if ($defaultTemplateMarkup !== '') {
                        $bundleCss = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_STYLE_FILE);
                        if (is_string($bundleCss) && $bundleCss !== '') {
                            $resolved['css'] = $bundleCss;
                        }
                    }
                }
            }
            if (!array_key_exists('js', $resolved) || trim((string) ($resolved['js'] ?? '')) === '') {
                $bundleJs = Worktype::layoutBundleAsset((string) ($resolved['name'] ?? ''), self::LAYOUT_SCRIPT_FILE);
                if (is_string($bundleJs) && $bundleJs !== '') {
                    $resolved['js'] = $bundleJs;
                }
                if ((!array_key_exists('js', $resolved) || trim((string) ($resolved['js'] ?? '')) === '') && $usesDefaultBundleTemplate) {
                    if ($defaultTemplateMarkup !== '') {
                        $bundleJs = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_SCRIPT_FILE);
                        if (is_string($bundleJs) && $bundleJs !== '') {
                            $resolved['js'] = $bundleJs;
                        }
                    }
                }
            }
        }
        $layoutName = Worktype::canonicalLayoutName((string) ($resolved['name'] ?? ''));
        $publicBundleBasePath = self::publicFolderLayoutPath($itemPath, $isFile);
        $inheritedDirectory = isset($resolved['inheritedDirectory']) && is_string($resolved['inheritedDirectory'])
            ? trim($resolved['inheritedDirectory'], "/\\")
            : '';
        $resolvedDirectory = isset($resolved['directory']) && is_string($resolved['directory']) ? trim($resolved['directory'], "/\\") : '';
        $hasFilesystemLayout = ($resolved['storage'] ?? '') === 'filesystem';
        $usePublicBundleBasePath = in_array($layoutName, [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)
            && !$hasFilesystemLayout;
        $basePath = $usePublicBundleBasePath
            ? ($inheritedDirectory !== '' ? str_replace('\\', '/', $inheritedDirectory) : $publicBundleBasePath)
            : ($resolvedDirectory !== '' ? str_replace('\\', '/', $resolvedDirectory) : ($publicBundleBasePath !== '' ? $publicBundleBasePath : self::relativeLayoutPath($itemPath, $isFile)));
        $resolved['baseHref'] = self::encodeRelativePath($basePath);
        $sectionBasePath = isset($resolved['sectionDirectory']) && is_string($resolved['sectionDirectory']) && trim($resolved['sectionDirectory']) !== ''
            ? str_replace('\\', '/', trim($resolved['sectionDirectory'], "/\\"))
            : $basePath;
        $resolved['sectionBaseHref'] = self::encodeRelativePath($sectionBasePath);
        $assets = [];
        $files = [];
        foreach (($resolved['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }

            $assetPath = str_replace('\\', '/', (string) ($asset['path'] ?? ''));
            if ($assetPath === '') {
                continue;
            }

            $asset['href'] = self::encodeRelativePath($basePath . '/' . $assetPath);
            $assets[] = $asset;
            $files[$assetPath] = $asset['href'];
        }

        $resolved['assets'] = $assets;
        $resolved['files'] = $files;
        $resolved['assetCount'] = count($assets);

        if (($resolved['storage'] ?? '') === 'filesystem') {
            if (array_key_exists('js', $resolved) && trim((string) $resolved['js']) !== '') {
                $bundleJs = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_SCRIPT_FILE);
                if (is_string($bundleJs) && trim($bundleJs) !== '') {
                    $resolved['jsInlineAfterHref'] = $bundleJs;
                }
            }
            if (array_key_exists('css', $resolved) && trim((string) $resolved['css']) !== '' && empty($resolved['cssInlineOnly'])) {
                $resolved['cssHref'] = self::encodeRelativePath($basePath . '/' . self::LAYOUT_STYLE_FILE);
            }
            if (array_key_exists('js', $resolved) && trim((string) $resolved['js']) !== '' && empty($resolved['jsInlineOnly'])) {
                $resolved['jsHref'] = self::encodeRelativePath($basePath . '/' . self::LAYOUT_SCRIPT_FILE);
            }
        }

        return $resolved;
    }

    private static function hydrateLayoutFilesystem(mixed $layout, string $dir, ?string $fileName, string $section): array
    {
        $resolved = Worktype::normalizeLayout($layout, $section);
        $mode = trim((string) ($resolved['mode'] ?? ''));
        $name = trim((string) ($resolved['name'] ?? ''));
        $preset = trim((string) ($resolved['preset'] ?? ''));
        $isNoneLayout = $mode === 'none' || $name === 'none' || $preset === 'none';

        if ($isNoneLayout) {
            $resolved['mode'] = 'none';
            $resolved['name'] = 'none';
            $resolved['storage'] = 'none';
            $resolved['directory'] = '';
            $resolved['inheritedDirectory'] = '';
            $resolved['sectionDirectory'] = '';
            $resolved['template'] = '';
            $resolved['css'] = '';
            $resolved['js'] = '';
            $resolved['sectionTemplate'] = '';
            $resolved['assets'] = [];
            $resolved['files'] = [];
            $resolved['assetCount'] = 0;
            unset($resolved['cssHref'], $resolved['jsHref'], $resolved['phpTemplate']);

            return $resolved;
        }

        if (array_key_exists('template', $resolved)) {
            $resolved['template'] = self::sanitizeStoredPromptTemplate((string) $resolved['template'], true);
        }
        if (array_key_exists('sectionTemplate', $resolved)) {
            $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) $resolved['sectionTemplate'], false);
        }
        $resolved['phpTemplate'] = Worktype::template((string) ($resolved['name'] ?? Worktype::defaultLayoutName())) ?? '';
        $resolved['sharedLayouts'] = self::layoutCollectionChoices($dir, $section);
        $sharedName = trim((string) ($resolved['sharedName'] ?? ''));
        $sharedPreset = $preset === 'shared'
            || $mode === 'shared'
            || (
                $preset === ''
                && (
                    (($resolved['source'] ?? '') === 'shared')
                    || $sharedName !== ''
                )
            );
        if ($sharedPreset) {
            if ($sharedName === '' && isset($resolved['sharedLayouts'][0]['name'])) {
                $sharedName = trim((string) $resolved['sharedLayouts'][0]['name']);
            }
            $sharedPackage = $sharedName !== '' ? self::layoutCollectionPackage($dir, $section, $sharedName) : null;
            if (is_array($sharedPackage)) {
                $resolved['storage'] = 'shared';
                $resolved['source'] = 'shared';
                $resolved['sharedName'] = $sharedName;
                $resolved['directory'] = (string) ($sharedPackage['directory'] ?? ('shared/' . $section . '/' . $sharedName));
                $resolved['sectionDirectory'] = $resolved['directory'];
                if (!array_key_exists('template', $resolved) || trim((string) $resolved['template']) === '') {
                    $resolved['template'] = self::sanitizeStoredPromptTemplate((string) ($sharedPackage['template'] ?? ''), true);
                }
                if (!array_key_exists('css', $resolved) || trim((string) $resolved['css'] ?? '') === '') {
                    $resolved['css'] = (string) ($sharedPackage['css'] ?? '');
                }
                if (!array_key_exists('js', $resolved) || trim((string) $resolved['js'] ?? '') === '') {
                    $resolved['js'] = (string) ($sharedPackage['js'] ?? '');
                }
                if (!array_key_exists('sectionTemplate', $resolved) || trim((string) $resolved['sectionTemplate'] ?? '') === '') {
                    $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) ($sharedPackage['sectionTemplate'] ?? ''), false);
                }
                $resolved['phpTemplate'] = (string) ($sharedPackage['template'] ?? $resolved['phpTemplate'] ?? '');
                $resolved['assets'] = is_array($sharedPackage['assets'] ?? null) ? $sharedPackage['assets'] : [];
                $resolved['files'] = is_array($sharedPackage['files'] ?? null) ? $sharedPackage['files'] : [];
                $resolved['assetCount'] = count($resolved['assets']);

                return $resolved;
            }
        }
        if ($preset !== 'shared') {
            unset($resolved['source'], $resolved['sharedName']);
        }
        $localLayoutDir = $fileName === null
            ? self::folderLayoutDir($dir)
            : self::fileLayoutDir($dir, $fileName);
        $localRelativeDir = $fileName === null ? '.layout' : '.works/' . $fileName . '.layout';
        $layoutDir = null;
        $resolved['directory'] = $localRelativeDir;
        $resolved['localDirectory'] = $localRelativeDir;
        $inheritedLayout = self::findInheritedLayoutDir($dir, $localLayoutDir);
        if (is_array($inheritedLayout)) {
            $resolved['inheritedDirectory'] = $inheritedLayout['relative'];
        }

        $assets = [];
        $files = [];
        $sectionTemplateFile = self::sectionTemplateFile($section);
        $sectionTemplatePath = null;
        $resolved['sectionDirectory'] = '';

        $hasLocalWrapperFiles = self::hasWrapperFiles($localLayoutDir);
        if ($hasLocalWrapperFiles) {
            $layoutDir = $localLayoutDir;
        } elseif (is_array($inheritedLayout) && self::hasWrapperFiles($inheritedLayout['absolute'])) {
            $layoutDir = $inheritedLayout['absolute'];
            $resolved['directory'] = $inheritedLayout['relative'];
        }

        if ($layoutDir !== null && is_dir($layoutDir)) {
            $resolved['storage'] = 'filesystem';
            if (in_array($resolved['name'] ?? '', [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)) {
                $resolved['mode'] = Worktype::filesystemLayoutName();
                $resolved['name'] = Worktype::filesystemLayoutName();
            }

            $templatePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_TEMPLATE_FILE;
            if (is_file($templatePath)) {
                $resolved['template'] = self::sanitizeStoredPromptTemplate((string) file_get_contents($templatePath), true);
            }

            $stylePath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_STYLE_FILE;
            if (is_file($stylePath)) {
                $resolved['css'] = (string) file_get_contents($stylePath);
            }

            $scriptPath = $layoutDir . DIRECTORY_SEPARATOR . self::LAYOUT_SCRIPT_FILE;
            if (is_file($scriptPath)) {
                $resolved['js'] = (string) file_get_contents($scriptPath);
            }
            if (array_key_exists('js', $resolved) && trim((string) $resolved['js']) !== '') {
                $bundleJs = Worktype::layoutBundleAsset(Worktype::defaultLayoutName(), self::LAYOUT_SCRIPT_FILE);
                if (is_string($bundleJs) && trim($bundleJs) !== '') {
                    $resolved['jsInlineAfterHref'] = $bundleJs;
                }
            }

            [$assets, $files] = self::scanLayoutAssets($layoutDir);
        } elseif (
            (!empty($resolved['template']) && is_string($resolved['template']))
            || (!empty($resolved['css']) && is_string($resolved['css']))
            || (!empty($resolved['js']) && is_string($resolved['js']))
        ) {
            $resolved['storage'] = 'inline';
        } else {
            $resolved['storage'] = 'default';
            if (in_array($resolved['name'] ?? '', [Worktype::defaultLayoutName(), Worktype::filesystemLayoutName()], true)) {
                $resolved['mode'] = Worktype::defaultLayoutName();
                $resolved['name'] = Worktype::defaultLayoutName();
            }
        }

        $localSectionPath = $localLayoutDir . DIRECTORY_SEPARATOR . $sectionTemplateFile;
        $canUseLocalSectionTemplate = $fileName === null || $hasLocalWrapperFiles;
        if ($canUseLocalSectionTemplate && is_file($localSectionPath)) {
            $sectionTemplatePath = $localSectionPath;
            $resolved['sectionDirectory'] = $localRelativeDir;
        } elseif ($layoutDir !== null) {
            $layoutSectionPath = $layoutDir . DIRECTORY_SEPARATOR . $sectionTemplateFile;
            if (is_file($layoutSectionPath)) {
                $sectionTemplatePath = $layoutSectionPath;
                $resolved['sectionDirectory'] = $resolved['directory'];
            }
        }

        if (is_string($sectionTemplatePath) && $sectionTemplatePath !== '') {
            $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) file_get_contents($sectionTemplatePath), false);
        } elseif ($section === 'work') {
            $sharedLayout = Worktype::sharedLayoutPackage($section, (string) ($resolved['name'] ?? Worktype::defaultLayoutName()));
            if (is_array($sharedLayout) && isset($sharedLayout['sectionTemplate']) && is_string($sharedLayout['sectionTemplate']) && trim($sharedLayout['sectionTemplate']) !== '') {
                $resolved['sectionTemplate'] = self::sanitizeStoredPromptTemplate((string) $sharedLayout['sectionTemplate'], false);
            }
        }

        $resolved['assets'] = $assets;
        $resolved['files'] = $files;
        $resolved['assetCount'] = count($assets);

        return $resolved;
    }
}
