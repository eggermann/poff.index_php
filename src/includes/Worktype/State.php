<?php

trait WorktypeStateTrait
{
    private const DEFAULT_LAYOUT_NAME = 'poff-layout';
    private const FILESYSTEM_LAYOUT_NAME = 'filesystem-layout';

    private static array $embedded = [];
    private static array $embeddedTemplates = [];
    private static array $embeddedLayoutAssets = [];
    private static array $bundleDefinitions = [];
    private static array $bundleTemplates = [];
    private static bool $bundleLoaded = false;
    private static array $fileDefinitions = [];
    private static array $fileTemplates = [];
    private static array $worktypeCatalog = [];
}
