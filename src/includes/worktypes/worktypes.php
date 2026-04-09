<?php
/**
 * Single-file bundle for worktype definitions and templates.
 * Edit this if you prefer one-file maintenance over per-type files.
 */

$types = ['image','video','audio','pdf','text','link','folder','other'];
$definitions = [];
$templates = [];

foreach ($types as $type) {
    $entry = include __DIR__ . '/' . $type . '.worktype.php';
    if (!is_array($entry)) {
        continue;
    }

    if (isset($entry['model']) && is_array($entry['model'])) {
        $definitions[$type] = $entry['model'];
    } elseif (isset($entry['definition']) && is_array($entry['definition'])) {
        $definitions[$type] = $entry['definition'];
    }
}

foreach ((glob(__DIR__ . '/templates/*.hbs') ?: []) as $tplPath) {
    $templates[pathinfo($tplPath, PATHINFO_FILENAME)] = (string) file_get_contents($tplPath);
}

if ($templates === []) {
    foreach ((glob(__DIR__ . '/templates/*.tpl') ?: []) as $tplPath) {
        $templates[pathinfo($tplPath, PATHINFO_FILENAME)] = (string) file_get_contents($tplPath);
    }
}

return [
    'definitions' => $definitions,
    'templates' => $templates,
];
