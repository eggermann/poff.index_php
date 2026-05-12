<?php
/**
 * Single-file bundle for worktype definitions and templates.
 * Edit this if you prefer one-file maintenance over per-type files.
 */

 $definitions = [];
 $templates = [];

 foreach ((glob(__DIR__ . '/*.worktype.php') ?: []) as $worktypePath) {
     $type = pathinfo($worktypePath, PATHINFO_FILENAME);
     $type = preg_replace('/\.worktype$/', '', $type) ?? $type;
     if ($type === 'worktypes') {
         continue;
     }
     $entry = include $worktypePath;
     if (!is_array($entry)) {
         continue;
     }

     if (isset($entry['model']) && is_array($entry['model'])) {
         $definitions[$type] = $entry['model'];
     } elseif (isset($entry['definition']) && is_array($entry['definition'])) {
         $definitions[$type] = $entry['definition'];
     } else {
         $definitions[$type] = $entry;
     }
 }

foreach ((glob(__DIR__ . '/templates/*.hbs') ?: []) as $tplPath) {
    $templates[pathinfo($tplPath, PATHINFO_FILENAME)] = (string) file_get_contents($tplPath);
}

foreach ([
    'poff-layout' => __DIR__ . '/templates/layout/default/template.hbs',
    'filesystem-layout' => __DIR__ . '/templates/layout/file-system/template.hbs',
] as $name => $tplPath) {
    if (is_file($tplPath)) {
        $templates[$name] = (string) file_get_contents($tplPath);
    }
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
