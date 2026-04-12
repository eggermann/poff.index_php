<?php
return [
    'model' => [
        'type' => 'folder',
        'layout' => [
            'mode' => 'poff-layout',
            'name' => 'poff-layout',
            'engine' => 'lightncandy',
            'section' => 'works',
        ],
    ],
    'template' => (string) file_get_contents(__DIR__ . '/templates/folder.hbs'),
];
