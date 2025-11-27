<?php
/**
 * Single-file bundle for worktype definitions and templates.
 * Edit this if you prefer one-file maintenance over per-type files.
 */

$types = ['image','video','audio','pdf','text','link','folder','other'];
$bundle = [];

foreach ($types as $type) {
    $entry = include __DIR__ . '/' . $type . '.worktype.php';
    if (!is_array($entry)) {
        continue;
    }
    if (isset($entry['definition']) && !isset($entry['model'])) {
        $entry['model'] = $entry['definition'];
    }
    // If template missing, try matching template file
    if (!isset($entry['template'])) {
        $tplPath = __DIR__ . '/templates/' . $type . '.tpl';
        if (file_exists($tplPath)) {
            $entry['template'] = (string) file_get_contents($tplPath);
        }
    }
    $bundle[$type] = $entry;
}

return $bundle;
