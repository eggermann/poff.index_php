<?php
/**
 * Upload helpers for active work folders.
 */

function cmsUploadActiveWorkFolderName(string $targetDir, string $activeWorkName): string
{
    $baseName = cmsUploadSafeName($activeWorkName);
    if ($baseName === '') {
        $baseName = 'work';
    }

    $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $baseName;
    if (!file_exists($targetPath)) {
        return $baseName;
    }

    $suffix = 2;
    do {
        $name = $baseName . '-' . $suffix;
        $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        $suffix++;
    } while (file_exists($targetPath));

    return $name;
}

function cmsUploadSplitFolderLayoutTemplate(): string
{
    return <<<'HBS'
<section class="poff-split-work-folder">
    <header class="poff-split-work-folder__header">
        <p class="poff-split-work-folder__eyebrow">Work folder</p>
        <h1 class="poff-split-work-folder__title">{{title}}</h1>
    </header>
    <main class="poff-default-layout__main poff-split-work-folder__main">
        {{#if isFolder}}{{> works}}{{else}}{{> work}}{{/if}}
    </main>
</section>
HBS;
}

function cmsUploadSplitFolderWorksTemplate(): string
{
    return <<<'HBS'
<section class="poff-split-folder-view">
    {{#each items}}
        {{#if fullview}}
            <article class="poff-split-folder-view__original">
                <div class="poff-split-folder-view__original-label">Original work</div>
                <iframe class="poff-split-folder-view__original-frame" src="{{pageLink}}" title="{{title}}"></iframe>
            </article>
        {{/if}}
    {{/each}}
    {{#if hasItems}}
        <div class="poff-split-folder-view__related">
            {{#each items}}
                {{#unless fullview}}
                    <article class="poff-split-folder-view__card poff-split-folder-view__card--{{type}}">
                        <span class="poff-split-folder-view__kind">{{#if isFolder}}Folder{{else}}{{kind}}{{/if}}</span>
                        <a class="poff-split-folder-view__link" href="{{pageLink}}">{{title}}</a>
                        <span class="poff-split-folder-view__path">{{path}}</span>
                    </article>
                {{/unless}}
            {{/each}}
        </div>
    {{else}}
        <div class="message">This folder is empty.</div>
    {{/if}}
</section>
HBS;
}

function cmsUploadSplitFolderLayoutCss(): string
{
    return <<<'CSS'
.poff-split-work-folder {
    min-height: 100dvh;
    background: #101724;
    color: #edf4ff;
}

.poff-split-work-folder__header {
    padding: 1.25rem 1.5rem 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.poff-split-work-folder__eyebrow {
    margin: 0 0 0.35rem;
    color: #91b7ff;
    font-size: 0.72rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}

.poff-split-work-folder__title {
    margin: 0;
    font-size: clamp(1.6rem, 3vw, 3rem);
    line-height: 1;
}

.poff-split-work-folder__main {
    padding: 1rem;
}

.poff-split-folder-view {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(16rem, 0.8fr);
    gap: 1rem;
    min-height: calc(100dvh - 7rem);
}

.poff-split-folder-view__original,
.poff-split-folder-view__related {
    min-width: 0;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 1.25rem;
    background: rgba(6, 10, 18, 0.72);
    overflow: hidden;
}

.poff-split-folder-view__original {
    display: grid;
    grid-template-rows: auto 1fr;
}

.poff-split-folder-view__original-label,
.poff-split-folder-view__kind {
    color: #9fb6d8;
    font-size: 0.72rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
}

.poff-split-folder-view__original-label {
    padding: 0.8rem 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.poff-split-folder-view__original-frame {
    width: 100%;
    min-height: 32rem;
    height: 100%;
    border: 0;
    background: #030712;
}

.poff-split-folder-view__related {
    display: grid;
    align-content: start;
    gap: 0.75rem;
    padding: 0.85rem;
}

.poff-split-folder-view__card {
    display: grid;
    gap: 0.35rem;
    padding: 0.85rem;
    border-radius: 0.9rem;
    background: rgba(255, 255, 255, 0.06);
}

.poff-split-folder-view__link {
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
}

.poff-split-folder-view__link:hover {
    text-decoration: underline;
}

.poff-split-folder-view__path {
    color: rgba(237, 244, 255, 0.58);
    font-size: 0.8rem;
    word-break: break-word;
}

@media (max-width: 780px) {
    .poff-split-folder-view {
        grid-template-columns: 1fr;
    }

    .poff-split-folder-view__original-frame {
        min-height: 24rem;
    }
}
CSS;
}

function cmsConfigureActiveWorkFolder(string $folderPath, string $folderName, string $activeWorkName, string $activeWorkPath, bool $activeWorkIsFile): void
{
    $config = PoffConfig::ensure($folderPath);
    $title = trim($activeWorkName) !== '' ? trim($activeWorkName) : $folderName;
    $config['folderName'] = $folderName;
    $config['title'] = $title;
    $config['slug'] = PoffConfig::slugify($title);
    $config['type'] = 'folder';

    $tree = is_array($config['tree'] ?? null) ? $config['tree'] : [];
    $activeWorkPath = trim($activeWorkPath, "/\\");
    if ($activeWorkPath !== '') {
        $originalTarget = $activeWorkIsFile
            ? '?view=1&file=' . rawurlencode($activeWorkPath)
            : '?view=1&path=' . rawurlencode($activeWorkPath);
        $hasOriginal = false;
        foreach ($tree as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (!empty($item['fullview']) || (string) ($item['path'] ?? '') === $originalTarget) {
                $hasOriginal = true;
                break;
            }
        }
        if (!$hasOriginal) {
            array_unshift($tree, [
                'name' => 'original-work',
                'title' => $title,
                'slug' => 'original-work',
                'type' => 'file',
                'kind' => 'link',
                'path' => $originalTarget,
                'visible' => true,
                'fullview' => true,
                'sourcePath' => $activeWorkPath,
            ]);
        }
    }

    $config['tree'] = $tree;
    $config['treeHash'] = hash('sha256', json_encode($tree));
    $config['updatedAt'] = date('c');
    $work = is_array($config['work'] ?? null) ? $config['work'] : [];
    $work['type'] = 'folder';
    $work['template'] = 'folder';
    $work['layout'] = [
        'name' => 'splitted',
        'mode' => 'custom-layout',
        'preset' => 'custom',
        'template' => cmsUploadSplitFolderLayoutTemplate(),
        'sectionTemplate' => cmsUploadSplitFolderWorksTemplate(),
        'css' => cmsUploadSplitFolderLayoutCss(),
        'js' => '',
    ];
    $config['work'] = $work;

    file_put_contents(PoffConfig::configPath($folderPath), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function cmsResolveUploadTargetDir(string $targetDir, array $data, string $source): array
{
    $saveTarget = trim((string) ($data['saveTarget'] ?? 'current'));
    if ($saveTarget !== 'work-folder' || $source === 'folder') {
        return [
            'dir' => $targetDir,
            'folderName' => '',
            'createdWorkFolder' => false,
        ];
    }

    $folderName = cmsUploadActiveWorkFolderName($targetDir, (string) ($data['activeWorkName'] ?? 'work'));
    $folderPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folderName;
    $dirError = cmsEnsureUploadDirectory($folderPath, 'Failed to create active work folder.');
    if ($dirError !== null) {
        return [
            'dir' => $targetDir,
            'folderName' => '',
            'createdWorkFolder' => false,
            'error' => $dirError,
        ];
    }

    cmsConfigureActiveWorkFolder(
        $folderPath,
        $folderName,
        (string) ($data['activeWorkName'] ?? $folderName),
        (string) ($data['activeWorkPath'] ?? ''),
        !empty($data['activeWorkIsFile'])
    );

    return [
        'dir' => $folderPath,
        'folderName' => $folderName,
        'createdWorkFolder' => true,
    ];
}
