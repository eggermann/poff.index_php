<?php
/**
 * Config and catalog helpers for edit actions.
 */

require_once __DIR__ . '/../../utils.php';
require_once __DIR__ . '/../../render/data.php';
require_once __DIR__ . '/../prompt-parse.php';
require_once __DIR__ . '/../prompt-refs.php';
require_once __DIR__ . '/../prompt-context.php';
require_once __DIR__ . '/../prompt-compact.php';
@require_once __DIR__ . '/../../../Converter.php';

function cmsIniBytes(string $value): int
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return 0;
    }

    $unit = strtolower(substr($trimmed, -1));
    $number = (float) $trimmed;

    return match ($unit) {
        'g' => (int) round($number * 1024 * 1024 * 1024),
        'm' => (int) round($number * 1024 * 1024),
        'k' => (int) round($number * 1024),
        default => (int) round((float) $trimmed),
    };
}

function cmsUploadLimits(): array
{
    $postMax = (string) ini_get('post_max_size');
    $uploadMax = (string) ini_get('upload_max_filesize');

    return [
        'postMax' => $postMax,
        'postMaxBytes' => cmsIniBytes($postMax),
        'uploadMax' => $uploadMax,
        'uploadMaxBytes' => cmsIniBytes($uploadMax),
        'maxFileUploads' => (int) ini_get('max_file_uploads'),
    ];
}

function cmsWorktypeCatalogForConfig(array $config, string $subjectType, string $targetDir, ?string $targetFile = null): array
{
    if (!class_exists('Worktype')) {
        return [];
    }

    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    $mime = $subjectType === 'file' ? trim((string) ($config['mimeType'] ?? '')) : '';
    $fileName = $subjectType === 'file' ? trim((string) ($targetFile ?? '')) : null;
    $resolved = class_exists('PoffConfig')
        ? PoffConfig::resolveWorkTemplateState($targetDir, $work, $subjectType === 'folder' ? 'folder' : (string) ($config['kind'] ?? $subjectType), $mime !== '' ? $mime : null, $fileName)
        : [];
    $selected = trim((string) ($resolved['template'] ?? $work['template'] ?? ''));

    return Worktype::worktypeCatalog($mime !== '' ? $mime : null, $fileName, $selected !== '' ? $selected : null, $subjectType);
}

function cmsWorktypeMapCatalogForConfig(array $config, string $subjectType, string $targetDir, ?string $targetFile = null, array $folderViewData = []): array
{
    if ($subjectType !== 'folder') {
        return [];
    }

    $items = is_array($folderViewData['allFiles'] ?? null) ? $folderViewData['allFiles'] : [];
    $groups = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $mime = strtolower(trim((string) ($item['mimeType'] ?? '')));
        if ($mime === '') {
            continue;
        }
        $kind = strtolower(trim((string) ($item['kind'] ?? 'other')));
        if (!isset($groups[$mime])) {
            $groups[$mime] = [
                'mime' => $mime,
                'kind' => $kind === '' ? 'other' : $kind,
                'count' => 0,
                'sampleName' => '',
            ];
        }
        $groups[$mime]['count']++;
        if ($groups[$mime]['sampleName'] === '' && is_string($item['name'] ?? null)) {
            $groups[$mime]['sampleName'] = (string) $item['name'];
        }
    }

    if ($groups === []) {
        return ['rows' => [], 'count' => 0];
    }

    $work = (isset($config['work']) && is_array($config['work'])) ? $config['work'] : [];
    $effectiveTemplateMap = class_exists('PoffConfig')
        ? PoffConfig::resolveEffectiveTemplateMap($targetDir, $work['templateMap'] ?? null)
        : Worktype::normalizeTemplateMap($work['templateMap'] ?? null);

    $rows = [];
    foreach ($groups as $group) {
        $mime = (string) ($group['mime'] ?? '');
        $kind = (string) ($group['kind'] ?? 'other');
        $sampleName = (string) ($group['sampleName'] ?? '');
        $selectedState = Worktype::resolveTemplateSelection($kind, $mime, $sampleName !== '' ? $sampleName : null, $effectiveTemplateMap);
        $catalog = Worktype::worktypeCatalog($mime, $sampleName !== '' ? $sampleName : null, (string) ($selectedState['template'] ?? ''), 'file');
        $rows[] = [
            'mime' => $mime,
            'kind' => $kind,
            'label' => ucfirst($kind) . ' · ' . $mime,
            'count' => (int) ($group['count'] ?? 0),
            'sampleName' => $sampleName,
            'selected' => (string) ($selectedState['template'] ?? ''),
            'catalog' => $catalog,
        ];
    }

    usort($rows, static function (array $left, array $right): int {
        $kindCompare = strcasecmp((string) ($left['kind'] ?? ''), (string) ($right['kind'] ?? ''));
        return $kindCompare !== 0 ? $kindCompare : strcasecmp((string) ($left['mime'] ?? ''), (string) ($right['mime'] ?? ''));
    });

    return ['rows' => $rows, 'count' => count($rows)];
}

function cmsAnnotateConfigWorktypeCatalog(array $config, string $subjectType, string $targetDir, ?string $targetFile = null, array $folderViewData = []): array
{
    $config['workTemplateCatalog'] = cmsWorktypeCatalogForConfig($config, $subjectType, $targetDir, $targetFile);
    $config['workTemplateMapCatalog'] = cmsWorktypeMapCatalogForConfig($config, $subjectType, $targetDir, $targetFile, $folderViewData);
    if ($subjectType === 'file' && class_exists('Converter')) {
        $mime = strtolower(trim((string) ($config['mimeType'] ?? '')));
        $kind = strtolower(trim((string) ($config['kind'] ?? '')));
        $extension = strtolower(pathinfo((string) ($targetFile ?? $config['name'] ?? ''), PATHINFO_EXTENSION));
        $config['converterCatalog'] = [
            'webReadable' => Converter::isWebReadableMime($mime),
            'available' => Converter::availableFor($mime, $kind, $extension),
        ];
        $folderConfig = class_exists('PoffConfig') ? PoffConfig::ensure($targetDir) : [];
        $tree = is_array($folderConfig['tree'] ?? null) ? $folderConfig['tree'] : [];
        $config['generatedWorks'] = array_values(array_filter($tree, static function ($item) use ($targetFile): bool {
            return is_array($item)
                && ($item['generated'] ?? false) === true
                && (string) ($item['sourceWork'] ?? '') === (string) $targetFile;
        }));
    }
    return $config;
}
