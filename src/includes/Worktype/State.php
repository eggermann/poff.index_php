<?php

trait WorktypeStateTrait
{
    private static function defaultLayoutNameValue(): string
    {
        return 'poff-layout';
    }

    private static function filesystemLayoutNameValue(): string
    {
        return 'filesystem-layout';
    }

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
